<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application;

use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobLeaseLostException;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorPool;
use Kkkonrad\Gdpr\Domain\Shared\Job\PartialProcessingException;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Kkkonrad\Gdpr\Application\Notification\AdminNotification;
use Psr\Log\LoggerInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Throwable;

class JobRunner
{
    public function __construct(
        private readonly JobQueue $jobQueue,
        private readonly JobProcessorPool $processorPool,
        private readonly LoggerInterface $logger,
        private readonly ConfigProviderInterface $configProvider,
        private readonly LockManagerInterface $lockManager,
        private readonly AdminNotification $adminNotification
    ) {
    }

    /**
     * @param int[]|null $storeIds
     * @return array{processed:int, failed:int, retried:int, stopped_by_budget:bool}
     */
    public function run(int $limit = 100, ?string $type = null, ?array $storeIds = null): array
    {
        $processed = 0;
        $failed = 0;
        $retried = 0;
        $stoppedByBudget = false;
        $startedAt = microtime(true);
        $timeBudget = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/worker_time_budget_seconds', null, 50, 3600
        );
        $memoryBudgetBytes = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/worker_memory_limit_mb', null, 512, 4096
        ) * 1024 * 1024;
        $maxAttempts = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/job_max_attempts', null, 3, 10
        );
        $retryBaseDelay = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/job_retry_delay_seconds', null, 60, 3600
        );
        $workerId = sprintf('%s:%d', gethostname() ?: 'worker', getmypid() ?: 0);
        $this->jobQueue->releaseStaleClaims();

        while ($processed + $failed + $retried < max(1, $limit)) {
            if ((microtime(true) - $startedAt) >= $timeBudget || memory_get_usage(true) >= $memoryBudgetBytes) {
                $stoppedByBudget = true;
                break;
            }
            $row = $this->jobQueue->claimNext($workerId, $type, $storeIds);
            if ($row === null) {
                break;
            }
            $context = JobContext::fromRow($row);
            $customerId = (int)($context->payload['customer_id'] ?? 0);
            $subjectLock = $customerId > 0
                ? 'kkkonrad_gdpr_customer_' . hash('sha256', (string)$customerId)
                : null;
            if ($subjectLock !== null && !$this->lockManager->lock($subjectLock, 0)) {
                try {
                    $this->jobQueue->retryLater(
                        $context->jobId,
                        $workerId,
                        30,
                        'subject_lock_busy',
                        __('Another privacy operation for this account is still running.')->render()
                    );
                } catch (JobLeaseLostException) {
                    $this->logLeaseLoss($context, $workerId);
                }
                $retried++;
                continue;
            }
            try {
                $this->jobQueue->markProcessing($context->jobId, $workerId);
                $this->processorPool->get($context->type)->process($context);
                $this->jobQueue->complete($context->jobId, $workerId);
                $processed++;
            } catch (PartialProcessingException $exception) {
                try {
                    $this->jobQueue->partiallyComplete(
                        $context->jobId,
                        $workerId,
                        'processing_partially_completed',
                        __('Processing was partially completed and requires review.')->render()
                    );
                } catch (JobLeaseLostException) {
                    $this->logLeaseLoss($context, $workerId);
                    $retried++;
                    continue;
                }
                $this->logger->error('GDPR job was partially completed.', [
                    'job_id' => $context->jobId,
                    'job_type' => $context->type,
                    'exception' => $exception,
                ]);
                $this->notifyAutomationFailure(
                    $context->jobId,
                    $context->requestId,
                    $context->type,
                    $context->storeId,
                    'processing_partially_completed'
                );
                $failed++;
            } catch (JobLeaseLostException) {
                $this->logLeaseLoss($context, $workerId);
                $retried++;
            } catch (Throwable $exception) {
                $attempt = max(1, (int)($row['attempt_count'] ?? 1));
                // Request processors persist a terminal/partial request state themselves and therefore
                // require the audited manual retry path. Autonomous retention jobs can be retried safely.
                try {
                    if ($context->requestId === null && $attempt < $maxAttempts) {
                        $delay = min(3600, $retryBaseDelay * (2 ** ($attempt - 1)));
                        $this->jobQueue->retryLater(
                            $context->jobId,
                            $workerId,
                            $delay,
                            'processing_retry_scheduled',
                            __('Processing will be retried automatically.')->render()
                        );
                        $this->logger->warning('GDPR job retry was scheduled.', [
                            'job_id' => $context->jobId,
                            'job_type' => $context->type,
                            'attempt' => $attempt,
                            'next_delay_seconds' => $delay,
                            'exception_class' => $exception::class,
                        ]);
                        $retried++;
                    } else {
                        $this->jobQueue->fail(
                            $context->jobId,
                            $workerId,
                            'processing_failed',
                            __('Processing failed after the configured retry limit.')->render()
                        );
                        $this->logger->error('GDPR job processing failed permanently.', [
                            'job_id' => $context->jobId,
                            'job_type' => $context->type,
                            'attempt' => $attempt,
                            'exception_class' => $exception::class,
                        ]);
                        $this->notifyAutomationFailure(
                            $context->jobId,
                            $context->requestId,
                            $context->type,
                            $context->storeId,
                            'processing_failed'
                        );
                        $failed++;
                    }
                } catch (JobLeaseLostException) {
                    $this->logLeaseLoss($context, $workerId);
                    $retried++;
                }
            } finally {
                if ($subjectLock !== null) {
                    $this->lockManager->unlock($subjectLock);
                }
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'retried' => $retried,
            'stopped_by_budget' => $stoppedByBudget,
        ];
    }

    private function logLeaseLoss(JobContext $context, string $workerId): void
    {
        $this->logger->warning('A GDPR worker stopped because its job lease was lost.', [
            'job_id' => $context->jobId,
            'job_type' => $context->type,
            'worker_id' => $workerId,
        ]);
    }

    private function notifyAutomationFailure(
        int $jobId,
        ?int $requestId,
        string $jobType,
        int $storeId,
        string $errorCode
    ): void {
        try {
            $this->adminNotification->automationFailed($jobId, $requestId, $jobType, $storeId, $errorCode);
        } catch (Throwable $exception) {
            $this->logger->warning('A GDPR automation failure alert could not be queued.', [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'exception_class' => $exception::class,
            ]);
        }
    }
}
