<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Console\Command;

use Kkkonrad\Gdpr\Application\DataRights\Anonymization\AnonymizationProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Erasure\ErasureProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Export\ExportGenerationProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Retention\OldOrdersProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Retention\RetentionCandidateReporter;
use Kkkonrad\Gdpr\Application\JobRunner;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class RunJobsCommand extends Command
{
    private const OPTION_LIMIT = 'limit';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_PROCESS = 'process';
    private const OPTION_STORE = 'store';
    private const OPTION_WEBSITE = 'website';

    private const PROCESSES = [
        'export' => ExportGenerationProcessor::TYPE,
        'anonymize' => AnonymizationProcessor::TYPE,
        'erase' => ErasureProcessor::TYPE,
        'retention' => OldOrdersProcessor::TYPE,
    ];

    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly JobQueue $jobQueue,
        private readonly StoreManagerInterface $storeManager,
        private readonly RetentionCandidateReporter $retentionCandidateReporter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('kkkonrad:gdpr:cron')
            ->setDescription('Run queued Kkkonrad GDPR jobs')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Maximum jobs to process', '100')
            ->addOption(
                self::OPTION_PROCESS,
                null,
                InputOption::VALUE_REQUIRED,
                'Process: all, export, anonymize, erase or retention',
                'all'
            )
            ->addOption(self::OPTION_STORE, null, InputOption::VALUE_REQUIRED, 'Limit to a store ID or code')
            ->addOption(self::OPTION_WEBSITE, null, InputOption::VALUE_REQUIRED, 'Limit to a website ID or code')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Report queues and retention candidates without writes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int)$input->getOption(self::OPTION_LIMIT));
        $process = strtolower(trim((string)$input->getOption(self::OPTION_PROCESS)));
        if ($process !== 'all' && !isset(self::PROCESSES[$process])) {
            throw new InvalidArgumentException(sprintf('Unknown GDPR process "%s".', $process));
        }
        $type = $process === 'all' ? null : self::PROCESSES[$process];
        $storeIds = $this->resolveStoreIds($input);
        if ((bool)$input->getOption(self::OPTION_DRY_RUN)) {
            $output->writeln(sprintf(
                '<info>Queued GDPR jobs: %d</info>',
                $this->jobQueue->countQueued($type, $storeIds)
            ));
            if (in_array($process, ['all', 'retention'], true)) {
                $rows = [];
                foreach ($this->retentionCandidateReporter->report($storeIds) as $report) {
                    $rows[] = [
                        $report['store_id'],
                        $report['old_orders'],
                        implode(',', $report['old_order_samples']),
                        $report['abandoned_accounts'],
                        implode(',', $report['account_samples']),
                    ];
                }
                (new Table($output))
                    ->setHeaders(['Store', 'Old orders', 'Sample order IDs', 'Abandoned accounts', 'Sample customer IDs'])
                    ->setRows($rows)
                    ->render();
                $output->writeln('<comment>Counts are pre-policy candidates; processing rechecks legal holds and protection rules.</comment>');
            }
            return Command::SUCCESS;
        }

        $result = $this->jobRunner->run($limit, $type, $storeIds);
        $output->writeln(sprintf(
            '<info>Processed: %d, retried: %d, failed: %d%s</info>',
            $result['processed'],
            $result['retried'],
            $result['failed'],
            $result['stopped_by_budget'] ? ', worker budget reached' : ''
        ));

        return $result['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return int[]|null */
    private function resolveStoreIds(InputInterface $input): ?array
    {
        $store = $input->getOption(self::OPTION_STORE);
        $website = $input->getOption(self::OPTION_WEBSITE);
        if ($store !== null && $website !== null) {
            throw new InvalidArgumentException('Use either --store or --website, not both.');
        }
        if ($store !== null) {
            return [(int)$this->storeManager->getStore((string)$store)->getId()];
        }
        if ($website !== null) {
            $websiteId = (int)$this->storeManager->getWebsite((string)$website)->getId();
            $ids = [];
            foreach ($this->storeManager->getStores(false, true) as $websiteStore) {
                if ((int)$websiteStore->getWebsiteId() === $websiteId) {
                    $ids[] = (int)$websiteStore->getId();
                }
            }
            return $ids;
        }
        return null;
    }
}
