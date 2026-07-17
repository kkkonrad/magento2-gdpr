<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application;

use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Application\JobRunner;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorPool;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Kkkonrad\Gdpr\Application\Notification\AdminNotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\Lock\LockManagerInterface;
use RuntimeException;

class JobRunnerTest extends TestCase
{
    public function testCompletesSuccessfulJob(): void
    {
        $queue = $this->createMock(JobQueue::class);
        $queue->expects(self::once())->method('releaseStaleClaims');
        $queue->expects(self::exactly(2))->method('claimNext')->willReturnOnConsecutiveCalls($this->row(), null);
        $queue->expects(self::once())->method('markProcessing')->with(7);
        $queue->expects(self::once())->method('complete')->with(7);
        $processor = $this->createMock(JobProcessorInterface::class);
        $processor->expects(self::once())->method('process')->with(self::isInstanceOf(JobContext::class));

        $result = $this->runner($queue, $processor)->run(5);

        self::assertSame(1, $result['processed']);
        self::assertSame(0, $result['failed']);
        self::assertSame(0, $result['retried']);
    }

    public function testSchedulesBackoffForAutonomousJob(): void
    {
        $queue = $this->createMock(JobQueue::class);
        $queue->method('claimNext')->willReturnOnConsecutiveCalls($this->row(null, 2), null);
        $queue->expects(self::once())->method('retryLater')->with(
            7,
            120,
            'processing_retry_scheduled',
            self::isType('string')
        );
        $queue->expects(self::never())->method('fail');
        $processor = $this->createMock(JobProcessorInterface::class);
        $processor->method('process')->willThrowException(new RuntimeException('transient'));

        $result = $this->runner($queue, $processor)->run(5);

        self::assertSame(1, $result['retried']);
        self::assertSame(0, $result['failed']);
    }

    public function testRequestLinkedFailureRequiresAuditedManualRetry(): void
    {
        $queue = $this->createMock(JobQueue::class);
        $queue->method('claimNext')->willReturnOnConsecutiveCalls($this->row(42, 1), null);
        $queue->expects(self::never())->method('retryLater');
        $queue->expects(self::once())->method('fail')->with(
            7,
            'processing_failed',
            self::isType('string')
        );
        $processor = $this->createMock(JobProcessorInterface::class);
        $processor->method('process')->willThrowException(new RuntimeException('fatal'));

        $result = $this->runner($queue, $processor)->run(5);

        self::assertSame(0, $result['retried']);
        self::assertSame(1, $result['failed']);
    }

    public function testBusyCustomerLockDefersJobWithoutCallingProcessor(): void
    {
        $row = $this->row(42, 1);
        $row['payload_json'] = '{"customer_id":19}';
        $queue = $this->createMock(JobQueue::class);
        $queue->method('claimNext')->willReturnOnConsecutiveCalls($row, null);
        $queue->expects(self::never())->method('markProcessing');
        $queue->expects(self::once())->method('retryLater')->with(
            7,
            30,
            'subject_lock_busy',
            self::isType('string')
        );
        $processor = $this->createMock(JobProcessorInterface::class);
        $processor->expects(self::never())->method('process');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(false);

        $result = $this->runner($queue, $processor, $lockManager)->run(5);

        self::assertSame(1, $result['retried']);
        self::assertSame(0, $result['failed']);
    }

    private function runner(
        JobQueue $queue,
        JobProcessorInterface $processor,
        ?LockManagerInterface $lockManager = null
    ): JobRunner
    {
        $pool = $this->createMock(JobProcessorPool::class);
        $pool->method('get')->with('retention.old_orders')->willReturn($processor);
        $config = $this->createMock(ConfigProviderInterface::class);
        $config->method('getPositiveInt')->willReturnCallback(
            static fn (string $path, int|string|null $scope, int $default): int => $default
        );

        if ($lockManager === null) {
            $lockManager = $this->createMock(LockManagerInterface::class);
            $lockManager->method('lock')->willReturn(true);
        }

        return new JobRunner(
            $queue,
            $pool,
            $this->createStub(LoggerInterface::class),
            $config,
            $lockManager,
            $this->createStub(AdminNotification::class)
        );
    }

    /** @return array<string, mixed> */
    private function row(?int $requestId = null, int $attempt = 1): array
    {
        return [
            'job_id' => 7,
            'public_id' => 'd43f1b57-d42c-4381-81c8-6d443a66a615',
            'type' => 'retention.old_orders',
            'feature_code' => 'retention_old_orders',
            'store_id' => 1,
            'request_id' => $requestId,
            'payload_json' => null,
            'config_snapshot_json' => null,
            'checkpoint' => null,
            'attempt_count' => $attempt,
        ];
    }
}
