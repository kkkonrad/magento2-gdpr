<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Anonymization;

use DomainException;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Kkkonrad\Gdpr\Domain\Shared\Job\PartialProcessingException;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Throwable;
use Kkkonrad\Gdpr\Application\DataRights\Retention\AbandonedAccountActivityPolicy;

class AnonymizationProcessor implements JobProcessorInterface
{
    public const TYPE = 'customer.anonymize';

    public function __construct(
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly AnonymizerPool $anonymizerPool,
        private readonly RequestManagementInterface $requestManagement,
        private readonly ResourceConnection $resourceConnection,
        private readonly RequestNotification $requestNotification,
        private readonly LoggerInterface $logger,
        private readonly AbandonedAccountActivityPolicy $abandonedAccountActivityPolicy
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function process(JobContext $context): void
    {
        $customerId = (int)($context->payload['customer_id'] ?? 0);
        if ($customerId <= 0 || $context->requestId === null) {
            throw new DomainException('Anonymization job has no customer or request reference.');
        }
        $requestStatus = $this->beginOrResumeRequest($context->requestId);
        if ($requestStatus === RequestStatus::COMPLETED) {
            $this->releaseNotification($context->requestId, 'completed');
            return;
        }
        $completedCodes = $this->getCompletedProcessorCodes($context->jobId);
        $hasCompleted = $completedCodes !== [];
        $currentCode = null;
        try {
            $this->assertRetentionInactivity($context, $customerId);
            $eligibility = $this->eligibilityPolicy->evaluate($customerId, RequestType::ANONYMIZE, $context->storeId);
            if (!$eligibility['eligible']) {
                throw new DomainException($eligibility['message']);
            }
            $this->requestNotification->prepare($context->requestId, 'completed', true);
            $this->requestNotification->prepare($context->requestId, 'failed', true);
            foreach ($this->anonymizerPool->all() as $anonymizer) {
                $currentCode = $anonymizer->getCode();
                if (in_array($currentCode, $completedCodes, true)) {
                    continue;
                }
                $this->setCheckpoint($context->jobId, $currentCode);
                $counts = $anonymizer->anonymize($customerId, $context->publicId);
                $this->recordResult($context->jobId, $currentCode, 'completed', $counts);
                $hasCompleted = true;
                $currentCode = null;
            }
            $this->setCheckpoint($context->jobId, null);
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::COMPLETED,
                'system',
                null,
                (string)__('Your personal data has been anonymized.'),
                null,
                ['processed_entities' => $this->getProcessedCount($context->jobId)]
            );
            $this->releaseNotification($context->requestId, 'completed');
        } catch (Throwable $exception) {
            if ($currentCode !== null) {
                $this->recordResult($context->jobId, $currentCode, 'failed', [], 'anonymizer_failed');
            }
            $this->requestManagement->transition(
                $context->requestId,
                $hasCompleted ? RequestStatus::PARTIALLY_COMPLETED : RequestStatus::FAILED,
                'system',
                null,
                $hasCompleted
                    ? (string)__('The anonymization was partially completed and requires review.')
                    : (string)__('The anonymization could not be completed and requires review.'),
                null,
                ['error_code' => $hasCompleted ? 'anonymization_partial' : 'anonymization_failed']
            );
            $this->releaseNotification($context->requestId, 'failed');
            if ($hasCompleted) {
                throw new PartialProcessingException('Anonymization was partially completed.', 0, $exception);
            }
            throw $exception;
        }
    }

    private function assertRetentionInactivity(JobContext $context, int $customerId): void
    {
        $cutoff = (string)($context->payload['retention_cutoff'] ?? '');
        if ($cutoff === '') {
            return;
        }
        $reference = (string)($context->payload['retention_reference'] ?? 'last_order_or_created');
        if (!$this->abandonedAccountActivityPolicy->isStillInactive(
            $customerId,
            $context->storeId,
            $cutoff,
            $reference
        )) {
            throw new DomainException('The account is no longer eligible for automated retention processing.');
        }
    }

    private function beginOrResumeRequest(int $requestId): string
    {
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $status = $this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($requestTable, ['status'])
                ->where('request_id = ?', $requestId)
        );
        if (!is_string($status)) {
            throw new DomainException('The anonymization request no longer exists.');
        }
        if ($status === RequestStatus::QUEUED) {
            $this->requestManagement->transition($requestId, RequestStatus::PROCESSING, 'system');
            return RequestStatus::PROCESSING;
        }
        if (!in_array($status, [RequestStatus::PROCESSING, RequestStatus::COMPLETED], true)) {
            throw new DomainException(sprintf(
                'The anonymization request cannot resume from status "%s".',
                $status
            ));
        }

        return $status;
    }

    private function releaseNotification(int $requestId, string $event): void
    {
        try {
            if (!$this->requestNotification->release($requestId, $event)) {
                $this->requestNotification->prepare($requestId, $event);
            }
        } catch (Throwable) {
            $this->logger->warning('A GDPR anonymization notification remains queued for retry.', [
                'request_id' => $requestId,
                'notification_event' => $event,
                'error_code' => 'notification_delivery_failed',
            ]);
        }
    }

    /** @param array<string, int> $counts */
    private function recordResult(
        int $jobId,
        string $processorCode,
        string $status,
        array $counts,
        ?string $errorCode = null
    ): void
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_processor_result');
        $this->resourceConnection->getConnection()->insertOnDuplicate($table, [
            'job_id' => $jobId,
            'processor_code' => $processorCode,
            'status' => $status,
            'processed_count' => array_sum($counts),
            'skipped_count' => 0,
            'error_code' => $errorCode,
        ], ['status', 'processed_count', 'skipped_count', 'error_code']);
    }

    /** @return string[] */
    private function getCompletedProcessorCodes(int $jobId): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_processor_result');
        return array_map('strval', $this->resourceConnection->getConnection()->fetchCol(
            $this->resourceConnection->getConnection()->select()
                ->from($table, ['processor_code'])
                ->where('job_id = ?', $jobId)
                ->where('status = ?', 'completed')
        ));
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

    private function getProcessedCount(int $jobId): int
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_processor_result');
        return (int)$this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($table, ['total' => 'SUM(processed_count)'])
                ->where('job_id = ?', $jobId)
                ->where('status = ?', 'completed')
        );
    }
}
