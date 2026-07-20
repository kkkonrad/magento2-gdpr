<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Console\Command;

use Kkkonrad\Gdpr\Application\DataRights\Retention\AbandonedAccountsProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Retention\OldOrdersProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Retention\RetentionCandidateReporter;
use Kkkonrad\Gdpr\Application\JobRunner;
use Kkkonrad\Gdpr\Console\Command\RunJobsCommand;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunJobsCommandTest extends TestCase
{
    public function testRetentionProcessRunsBothRetentionJobTypesWithinSharedLimit(): void
    {
        $calls = [];
        $runner = $this->createMock(JobRunner::class);
        $runner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(static function (int $limit, ?string $type, ?array $storeIds) use (&$calls): array {
                $calls[] = [$limit, $type, $storeIds];

                return [
                    'processed' => 1,
                    'failed' => 0,
                    'retried' => 0,
                    'stopped_by_budget' => false,
                ];
            });
        $command = new RunJobsCommand(
            $runner,
            $this->createMock(JobQueue::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(RetentionCandidateReporter::class)
        );
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--process' => 'retention', '--limit' => '10']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame([
            [10, OldOrdersProcessor::TYPE, null],
            [9, AbandonedAccountsProcessor::TYPE, null],
        ], $calls);
        self::assertStringContainsString('Processed: 2, retried: 0, failed: 0', $tester->getDisplay());
    }
}
