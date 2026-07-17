<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Retention;

use DateTimeInterface;
use DomainException;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Anonymization\AnonymizationProcessor;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Application\DataRights\Erasure\ErasureProcessor;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Kkkonrad\Gdpr\Domain\Shared\Job\PartialProcessingException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AlreadyExistsException;
use Psr\Log\LoggerInterface;
use Throwable;

class AbandonedAccountsProcessor implements JobProcessorInterface
{
    public const TYPE = 'retention.abandoned_accounts';
    private const PROCESSOR_CODE = 'magento_abandoned_accounts';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly RequestManagementInterface $requestManagement,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly RequestNotification $requestNotification,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function process(JobContext $context): void
    {
        $cutoff = (string)($context->payload['cutoff'] ?? '');
        $action = (string)($context->payload['action'] ?? 'anonymize');
        $reference = (string)($context->payload['reference'] ?? 'last_order_or_created');
        $batchSize = max(1, min(1000, (int)($context->payload['batch_size'] ?? 100)));
        $cursor = max(0, (int)($context->payload['cursor'] ?? 0));
        $warningDays = $action === 'erase'
            ? max(0, min(365, (int)($context->payload['warning_days'] ?? 0)))
            : 0;
        if ($cutoff === '' || !in_array($action, ['anonymize', 'erase'], true)) {
            throw new DomainException('Abandoned-account retention configuration is invalid.');
        }

        $customerIds = $this->candidateIds($context->storeId, $cutoff, $reference, $cursor, $batchSize);
        $processed = 0;
        $skipped = 0;
        $failedIds = [];
        $lastId = $cursor;
        $connection = $this->resourceConnection->getConnection();
        foreach ($customerIds as $customerId) {
            $lastId = $customerId;
            $requestId = null;
            $transactionStarted = false;
            try {
                $requestType = $action === 'erase' ? RequestType::ERASE : RequestType::ANONYMIZE;
                $eligibility = $this->eligibilityPolicy->evaluate($customerId, $requestType, $context->storeId);
                if (!$eligibility['eligible']) {
                    $skipped++;
                    continue;
                }
                $connection->beginTransaction();
                $transactionStarted = true;
                $requestId = $this->requestManagement->submit(
                    $customerId,
                    $requestType,
                    $context->storeId,
                    null,
                    'system'
                );
                $this->requestManagement->transition($requestId, RequestStatus::VALIDATION, 'system');
                $this->requestManagement->transition($requestId, RequestStatus::QUEUED, 'system');
                $availableAt = $warningDays > 0
                    ? $this->clock->now()->modify('+' . $warningDays . ' days')
                    : null;
                $this->scheduleCustomerJob(
                    $customerId,
                    $requestId,
                    $action,
                    $context->storeId,
                    $warningDays,
                    $availableAt,
                    $cutoff,
                    $reference
                );
                $connection->commit();
                $transactionStarted = false;
                if ($availableAt !== null) {
                    $this->queueWarning($requestId, $availableAt);
                }
                $processed++;
            } catch (AlreadyExistsException) {
                if ($transactionStarted) {
                    $connection->rollBack();
                }
                $skipped++;
            } catch (Throwable $exception) {
                if ($transactionStarted) {
                    $connection->rollBack();
                }
                $failedIds[] = $customerId;
                $this->logger->error('A GDPR abandoned-account candidate could not be queued.', [
                    'customer_id' => $customerId,
                    'request_id' => $requestId,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        $checkpoint = count($customerIds) < $batchSize ? 'exhausted' : 'cursor:' . $lastId;
        if ($failedIds !== []) {
            $checkpoint .= ';failed:' . implode(',', array_slice($failedIds, 0, 10));
        }
        $this->setCheckpoint($context->jobId, $checkpoint);
        $this->recordResult(
            $context->jobId,
            $failedIds === [] ? 'completed' : 'partially_completed',
            $processed,
            $skipped + count($failedIds),
            $failedIds === [] ? null : 'retention_candidate_failed'
        );
        if ($failedIds !== []) {
            throw new PartialProcessingException('One or more abandoned accounts require an audited retry.');
        }
    }

    /** @return int[] */
    private function candidateIds(
        int $storeId,
        string $cutoff,
        string $reference,
        int $cursor,
        int $batchSize
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $customerLogTable = $this->resourceConnection->getTableName('customer_log');
        $lastOrder = $connection->select()
            ->from($orderTable, ['customer_id', 'last_order_at' => 'MAX(created_at)'])
            ->where('customer_id IS NOT NULL')
            ->group('customer_id');
        $lastLogin = $connection->select()
            ->from($customerLogTable, ['customer_id', 'last_login_at' => 'MAX(last_login_at)'])
            ->group('customer_id');
        $referenceExpression = match ($reference) {
            'last_login_or_created' => 'COALESCE(login.last_login_at, customer.created_at)',
            'latest_activity' => 'GREATEST(customer.updated_at, '
                . 'COALESCE(orders.last_order_at, customer.created_at), '
                . 'COALESCE(login.last_login_at, customer.created_at))',
            default => 'COALESCE(orders.last_order_at, customer.created_at)',
        };
        return array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from(['customer' => $customerTable], ['entity_id'])
                ->joinLeft(['orders' => $lastOrder], 'orders.customer_id = customer.entity_id', [])
                ->joinLeft(['login' => $lastLogin], 'login.customer_id = customer.entity_id', [])
                ->where('customer.store_id = ?', $storeId)
                ->where($referenceExpression . ' < ?', $cutoff)
                ->where('customer.entity_id > ?', $cursor)
                ->where('customer.email NOT LIKE ?', 'anon-%@example.invalid')
                ->order('customer.entity_id ASC')
                ->limit($batchSize)
        ));
    }

    private function scheduleCustomerJob(
        int $customerId,
        int $requestId,
        string $action,
        int $storeId,
        int $warningDays,
        ?DateTimeInterface $availableAt,
        string $cutoff,
        string $reference
    ): void {
        $this->jobScheduler->schedule(
            $action === 'erase' ? ErasureProcessor::TYPE : AnonymizationProcessor::TYPE,
            FeatureCode::RETENTION_ABANDONED_ACCOUNTS,
            $storeId,
            [
                'customer_id' => $customerId,
                'retention_cutoff' => $cutoff,
                'retention_reference' => $reference,
            ],
            $requestId,
            'retention-account-' . $action . '-' . $customerId,
            ['warning_days' => $warningDays],
            $availableAt
        );
    }

    private function queueWarning(int $requestId, DateTimeInterface $availableAt): void
    {
        try {
            $this->requestNotification->prepare($requestId, 'retention_warning', false, [
                'scheduled_at' => $availableAt->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            $this->logger->warning('A GDPR abandoned-account warning remains queued for retry.', [
                'request_id' => $requestId,
                'exception_class' => $exception::class,
            ]);
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

    private function setCheckpoint(int $jobId, string $checkpoint): void
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $this->resourceConnection->getConnection()->update(
            $table,
            ['checkpoint' => $checkpoint],
            ['job_id = ?' => $jobId]
        );
    }
}
