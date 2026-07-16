<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Request;

use DomainException;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStateMachine;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Audit\SensitiveDataRedactor;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Ramsey\Uuid\Uuid;
use Throwable;

class RequestManagement implements RequestManagementInterface
{
    private const ACTOR_TYPES = ['customer', 'admin', 'system'];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly RequestStateMachine $stateMachine,
        private readonly EncryptorInterface $encryptor,
        private readonly SensitiveDataRedactor $redactor
    ) {
    }

    public function submit(int $customerId, string $type, int $storeId, ?string $subjectKey = null): int
    {
        if ($customerId <= 0) {
            throw new DomainException('A valid customer ID is required for a personal data request.');
        }
        RequestType::assertValid($type);

        $connection = $this->resourceConnection->getConnection();
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $eventTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request_event');

        $connection->beginTransaction();
        try {
            $existingRows = $connection->fetchAll(
                $connection->select()
                    ->from($requestTable, ['request_id'])
                    ->where('customer_id = ?', $customerId)
                    ->where('type = ?', $type)
                    ->where('status NOT IN (?)', RequestStatus::TERMINAL)
                    ->limit(1)
                    ->forUpdate(true)
            );
            if ($existingRows !== []) {
                throw new AlreadyExistsException(__('An active request of this type already exists.'));
            }

            $connection->insert($requestTable, [
                'public_id' => Uuid::uuid4()->toString(),
                'customer_id' => $customerId,
                'subject_key' => $subjectKey,
                'type' => $type,
                'status' => RequestStatus::SUBMITTED,
                'store_id' => $storeId,
            ]);
            $requestId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');

            $connection->insert($eventTable, [
                'request_id' => $requestId,
                'event_type' => 'request.submitted',
                'status_to' => RequestStatus::SUBMITTED,
                'actor_type' => 'customer',
                'actor_id' => $customerId,
            ]);
            $connection->commit();

            return $requestId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $metadata */
    public function transition(
        int $requestId,
        string $targetStatus,
        string $actorType,
        ?int $actorId = null,
        ?string $publicReason = null,
        ?string $adminReason = null,
        array $metadata = []
    ): void {
        if (!in_array($actorType, self::ACTOR_TYPES, true)) {
            throw new DomainException(sprintf('Unsupported GDPR actor type "%s".', $actorType));
        }

        $connection = $this->resourceConnection->getConnection();
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $eventTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request_event');
        $connection->beginTransaction();
        try {
            $request = $connection->fetchRow(
                $connection->select()
                    ->from($requestTable)
                    ->where('request_id = ?', $requestId)
                    ->forUpdate(true)
            );
            if ($request === false) {
                throw NoSuchEntityException::singleField('request_id', $requestId);
            }

            $currentStatus = (string)$request['status'];
            $this->stateMachine->assertCanTransition($currentStatus, $targetStatus);
            $update = [
                'status' => $targetStatus,
                'public_reason' => $publicReason,
                'admin_reason_encrypted' => $adminReason !== null ? $this->encryptor->encrypt($adminReason) : null,
            ];
            if (in_array($targetStatus, RequestStatus::TERMINAL, true)) {
                $update['completed_at'] = gmdate('Y-m-d H:i:s');
            }
            $connection->update($requestTable, $update, ['request_id = ?' => $requestId]);

            $connection->insert($eventTable, [
                'request_id' => $requestId,
                'event_type' => 'request.status_changed',
                'status_from' => $currentStatus,
                'status_to' => $targetStatus,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'metadata_json' => $metadata === []
                    ? null
                    : json_encode($this->redactor->redact($metadata), JSON_THROW_ON_ERROR),
            ]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
