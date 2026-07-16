<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Console\Command;

use Kkkonrad\Gdpr\Application\JobRunner;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunJobsCommand extends Command
{
    private const OPTION_LIMIT = 'limit';
    private const OPTION_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly JobQueue $jobQueue
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('kkkonrad:gdpr:cron')
            ->setDescription('Run queued Kkkonrad GDPR jobs')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Maximum jobs to process', '100')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Only report the queued job count');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int)$input->getOption(self::OPTION_LIMIT));
        if ((bool)$input->getOption(self::OPTION_DRY_RUN)) {
            $output->writeln(sprintf('<info>Queued GDPR jobs: %d</info>', $this->jobQueue->countQueued()));
            return Command::SUCCESS;
        }

        $result = $this->jobRunner->run($limit);
        $output->writeln(sprintf(
            '<info>Processed: %d, failed: %d</info>',
            $result['processed'],
            $result['failed']
        ));

        return $result['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
