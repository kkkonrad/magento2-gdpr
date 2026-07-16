<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application;

use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorPool;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Psr\Log\LoggerInterface;
use Throwable;

class JobRunner
{
    public function __construct(
        private readonly JobQueue $jobQueue,
        private readonly JobProcessorPool $processorPool,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @return array{processed:int, failed:int} */
    public function run(int $limit = 100): array
    {
        $processed = 0;
        $failed = 0;
        $workerId = sprintf('%s:%d', gethostname() ?: 'worker', getmypid() ?: 0);
        $this->jobQueue->releaseStaleClaims();

        while ($processed + $failed < max(1, $limit)) {
            $row = $this->jobQueue->claimNext($workerId);
            if ($row === null) {
                break;
            }
            $context = JobContext::fromRow($row);
            try {
                $this->jobQueue->markProcessing($context->jobId);
                $this->processorPool->get($context->type)->process($context);
                $this->jobQueue->complete($context->jobId);
                $processed++;
            } catch (Throwable $exception) {
                $this->jobQueue->fail($context->jobId, 'processing_failed', __('Processing failed.')->render());
                $this->logger->error('GDPR job processing failed.', [
                    'job_id' => $context->jobId,
                    'job_type' => $context->type,
                    'exception' => $exception,
                ]);
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}
