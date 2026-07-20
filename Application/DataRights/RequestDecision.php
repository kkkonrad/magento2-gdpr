<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Anonymization\AnonymizationProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Erasure\ErasureProcessor;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobStatus;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Throwable;

class RequestDecision
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly RequestManagementInterface $requestManagement,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly FeatureManagerInterface $featureManager,
        private readonly RequestNotification $requestNotification,
        private readonly LoggerInterface $logger
    ) {
    }

    public function approve(
        int $requestId,
        int $adminId,
        string $publicReason,
        string $adminReason
    ): void {
        if (trim($publicReason) === '' || trim($adminReason) === '') {
            throw new DomainException('Both customer-safe and administrative reasons are required.');
        }
        $request = $this->getRequest($requestId);
        if ((string)$request['status'] !== RequestStatus::PENDING_APPROVAL) {
            throw new DomainException('Only a request pending approval can be approved.');
        }
        $customerId = (int)$request['customer_id'];
        $type = (string)$request['type'];
        $storeId = (int)$request['store_id'];
        $eligibility = $this->eligibilityPolicy->evaluate($customerId, $type, $storeId);
        if (!$eligibility['eligible']) {
            throw new DomainException($eligibility['message']);
        }
        [$jobType, $feature] = match ($type) {
            RequestType::ANONYMIZE => [AnonymizationProcessor::TYPE, FeatureCode::ANONYMIZATION_REQUEST],
            RequestType::ERASE => [ErasureProcessor::TYPE, FeatureCode::ERASURE_REQUEST],
            default => throw new DomainException('This request type does not require administrative approval.'),
        };
        if (!$this->featureManager->isEnabled($feature, $storeId)) {
            throw new DomainException('The corresponding GDPR feature is disabled for this store view.');
        }
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $this->requestManagement->transition(
                $requestId,
                RequestStatus::QUEUED,
                'admin',
                $adminId,
                trim($publicReason),
                trim($adminReason),
                ['decision' => 'approved']
            );
            $this->jobScheduler->schedule(
                $jobType,
                $feature,
                $storeId,
                ['customer_id' => $customerId],
                $requestId,
                $jobType . '-request-' . $requestId
            );
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $this->notify($requestId, 'approved', ['public_reason' => trim($publicReason)]);
    }

    public function reject(
        int $requestId,
        int $adminId,
        string $publicReason,
        string $adminReason
    ): void {
        if (trim($publicReason) === '' || trim($adminReason) === '') {
            throw new DomainException('Both customer-safe and administrative reasons are required.');
        }
        $request = $this->getRequest($requestId);
        if ((string)$request['status'] !== RequestStatus::PENDING_APPROVAL) {
            throw new DomainException('Only a request pending approval can be rejected.');
        }
        $this->requestManagement->transition(
            $requestId,
            RequestStatus::REJECTED,
            'admin',
            $adminId,
            trim($publicReason),
            trim($adminReason),
            ['decision' => 'rejected']
        );
        $this->notify($requestId, 'rejected', ['public_reason' => trim($publicReason)]);
    }

    public function retry(int $requestId, int $adminId, string $adminReason): void
    {
        if (trim($adminReason) === '') {
            throw new DomainException('An administrative retry reason is required.');
        }
        $connection = $this->resourceConnection->getConnection();
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $jobTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
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
            if (!in_array(
                (string)$request['status'],
                [RequestStatus::FAILED, RequestStatus::PARTIALLY_COMPLETED],
                true
            )) {
                throw new DomainException('Only a failed or partially completed request can be retried.');
            }

            $job = $connection->fetchRow(
                $connection->select()
                    ->from($jobTable, ['job_id', 'feature_code', 'status'])
                    ->where('request_id = ?', $requestId)
                    ->order('job_id DESC')
                    ->limit(1)
                    ->forUpdate(true)
            );
            if ($job === false || !in_array(
                (string)$job['status'],
                [JobStatus::FAILED, JobStatus::PARTIALLY_COMPLETED],
                true
            )) {
                throw new DomainException('The related GDPR job is not retryable.');
            }
            if (!$this->featureManager->isEnabled(
                (string)$job['feature_code'],
                (int)$request['store_id']
            )) {
                throw new DomainException('The corresponding GDPR feature is disabled for this store view.');
            }

            $this->requestManagement->transition(
                $requestId,
                RequestStatus::QUEUED,
                'admin',
                $adminId,
                (string)__('The request was queued for another processing attempt.'),
                trim($adminReason),
                ['decision' => 'retry']
            );
            $affected = $connection->update($jobTable, [
                'status' => JobStatus::QUEUED,
                'available_at' => gmdate('Y-m-d H:i:s'),
                'locked_at' => null,
                'locked_by' => null,
                'error_code' => null,
                'error_message' => null,
                'finished_at' => null,
            ], [
                'job_id = ?' => (int)$job['job_id'],
                'status IN (?)' => [JobStatus::FAILED, JobStatus::PARTIALLY_COMPLETED],
            ]);
            if ($affected !== 1) {
                throw new DomainException('The related GDPR job could not be queued for retry.');
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function getRequest(int $requestId): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $row = $this->resourceConnection->getConnection()->fetchRow(
            $this->resourceConnection->getConnection()->select()
                ->from($table)
                ->where('request_id = ?', $requestId)
        );
        if ($row === false) {
            throw NoSuchEntityException::singleField('request_id', $requestId);
        }

        return $row;
    }

    /** @param array<string, mixed> $variables */
    private function notify(int $requestId, string $event, array $variables = []): void
    {
        try {
            $this->requestNotification->prepare($requestId, $event, false, $variables);
        } catch (Throwable) {
            $this->logger->warning('A GDPR decision was saved but its notification could not be queued.', [
                'request_id' => $requestId,
                'notification_event' => $event,
                'error_code' => 'notification_prepare_failed',
            ]);
        }
    }
}
