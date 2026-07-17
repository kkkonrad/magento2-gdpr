<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Console\Command;

use Kkkonrad\Gdpr\Application\JobRetry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetryJobCommand extends Command
{
    public function __construct(private readonly JobRetry $jobRetry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('kkkonrad:gdpr:job:retry')
            ->setDescription('Queue a failed GDPR job for checkpoint-aware retry')
            ->addArgument('job-id', InputArgument::REQUIRED, 'Internal job ID')
            ->addOption('reason-code', null, InputOption::VALUE_REQUIRED, 'Non-PII audit reason code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reasonCode = trim((string)$input->getOption('reason-code'));
        if ($reasonCode === '') {
            $output->writeln('<error>--reason-code is required.</error>');
            return Command::INVALID;
        }
        $jobId = (int)$input->getArgument('job-id');
        $this->jobRetry->retry($jobId, $reasonCode);
        $output->writeln(sprintf('<info>GDPR job %d was queued for retry.</info>', $jobId));
        return Command::SUCCESS;
    }
}
