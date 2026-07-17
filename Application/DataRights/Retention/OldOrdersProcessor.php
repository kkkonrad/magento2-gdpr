<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Retention;

use DomainException;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Domain\DataRights\Anonymization\PseudonymGenerator;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Kkkonrad\Gdpr\Domain\Shared\Job\PartialProcessingException;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class OldOrdersProcessor implements JobProcessorInterface
{
    public const TYPE = 'retention.old_orders';
    private const PROCESSOR_CODE = 'magento_old_orders';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly PseudonymGenerator $pseudonymGenerator,
        private readonly EligibilityPolicy $eligibilityPolicy
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function process(JobContext $context): void
    {
        $cutoff = (string)($context->payload['cutoff'] ?? '');
        $batchSize = max(1, min(1000, (int)($context->payload['batch_size'] ?? 100)));
        $cursor = max(0, (int)($context->payload['cursor'] ?? 0));
        $statuses = $context->payload['statuses'] ?? ['complete', 'closed', 'canceled'];
        if ($cutoff === '' || !is_array($statuses) || $statuses === []) {
            throw new DomainException('Old-order retention configuration is invalid.');
        }

        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($orderTable, ['entity_id', 'customer_id'])
                ->where('store_id = ?', $context->storeId)
                ->where('created_at < ?', $cutoff)
                ->where('status IN (?)', array_values(array_map('strval', $statuses)))
                ->where('customer_email NOT LIKE ?', 'anon-%@example.invalid')
                ->where('entity_id > ?', $cursor)
                ->order('entity_id ASC')
                ->limit($batchSize)
        );

        $processed = 0;
        $skipped = 0;
        $failedIds = [];
        $lastId = $cursor;
        foreach ($rows as $row) {
            $orderId = (int)$row['entity_id'];
            $lastId = $orderId;
            $customerId = $row['customer_id'] !== null ? (int)$row['customer_id'] : 0;
            if ($customerId > 0) {
                $eligibility = $this->eligibilityPolicy->evaluate(
                    $customerId,
                    RequestType::ANONYMIZE,
                    $context->storeId
                );
                if (!$eligibility['eligible']) {
                    $skipped++;
                    continue;
                }
            }

            $connection->beginTransaction();
            try {
                $this->anonymizeOrder($orderId, $context->publicId);
                $connection->commit();
                $processed++;
            } catch (Throwable) {
                $connection->rollBack();
                $failedIds[] = $orderId;
            }
        }

        $this->recordResult(
            $context->jobId,
            $failedIds === [] ? 'completed' : 'partially_completed',
            $processed,
            $skipped + count($failedIds),
            $failedIds === [] ? null : 'retention_record_failed'
        );
        $checkpoint = count($rows) < $batchSize ? 'exhausted' : 'cursor:' . $lastId;
        if ($failedIds !== []) {
            $checkpoint .= ';failed:' . implode(',', array_slice($failedIds, 0, 10));
        }
        $this->setCheckpoint($context->jobId, $checkpoint);
        if ($failedIds !== []) {
            throw new PartialProcessingException('One or more old orders require an audited retry.');
        }
    }

    private function anonymizeOrder(int $orderId, string $operationKey): void
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $addressTable = $this->resourceConnection->getTableName('sales_order_address');
        $token = $this->pseudonymGenerator->token($operationKey, 'retention_order', $orderId, 12);
        $connection->update($orderTable, [
            'customer_id' => null,
            'customer_email' => $this->pseudonymGenerator->email($operationKey, 'retention_order', $orderId),
            'customer_prefix' => null,
            'customer_firstname' => 'Anonymous',
            'customer_middlename' => null,
            'customer_lastname' => $token,
            'customer_suffix' => null,
            'customer_dob' => null,
            'customer_taxvat' => null,
            'customer_note' => null,
            'remote_ip' => null,
            'x_forwarded_for' => null,
        ], ['entity_id = ?' => $orderId]);

        $addressIds = array_map('intval', $connection->fetchCol(
            $connection->select()->from($addressTable, ['entity_id'])->where('parent_id = ?', $orderId)
        ));
        foreach ($addressIds as $addressId) {
            $data = $this->pseudonymGenerator->address($operationKey, 'retention_order_address', $addressId);
            $data['email'] = $this->pseudonymGenerator->email(
                $operationKey,
                'retention_order_address',
                $addressId
            );
            $data['customer_id'] = null;
            $data['customer_address_id'] = null;
            $connection->update($addressTable, $data, ['entity_id = ?' => $addressId]);
        }

        $parents = [
            'sales_invoice' => ['customer_note' => null, 'customer_note_notify' => 0],
            'sales_shipment' => [
                'customer_id' => null,
                'customer_note' => null,
                'customer_note_notify' => 0,
                'packages' => null,
                'shipping_label' => null,
            ],
            'sales_creditmemo' => ['customer_note' => null, 'customer_note_notify' => 0],
        ];
        foreach ($parents as $tableName => $data) {
            $connection->update(
                $this->resourceConnection->getTableName($tableName),
                $data,
                ['order_id = ?' => $orderId]
            );
        }
        foreach (['invoice', 'shipment', 'creditmemo'] as $documentType) {
            $parentTable = $this->resourceConnection->getTableName('sales_' . $documentType);
            $parentIds = array_map('intval', $connection->fetchCol(
                $connection->select()->from($parentTable, ['entity_id'])->where('order_id = ?', $orderId)
            ));
            if ($parentIds !== []) {
                $connection->update(
                    $this->resourceConnection->getTableName('sales_' . $documentType . '_comment'),
                    ['comment' => null],
                    ['parent_id IN (?)' => $parentIds, 'is_visible_on_front = ?' => 1]
                );
            }
        }
    }

    private function recordResult(
        int $jobId,
        string $status,
        int $processed,
        int $skipped,
        ?string $errorCode
    ): void {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_processor_result');
        $this->resourceConnection->getConnection()->insertOnDuplicate($table, [
            'job_id' => $jobId,
            'processor_code' => self::PROCESSOR_CODE,
            'status' => $status,
            'processed_count' => $processed,
            'skipped_count' => $skipped,
            'error_code' => $errorCode,
        ], ['status', 'processed_count', 'skipped_count', 'error_code']);
    }

    private function setCheckpoint(int $jobId, ?string $checkpoint): void
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $this->resourceConnection->getConnection()->update(
            $table,
            ['checkpoint' => $checkpoint],
            ['job_id = ?' => $jobId]
        );
    }
}
