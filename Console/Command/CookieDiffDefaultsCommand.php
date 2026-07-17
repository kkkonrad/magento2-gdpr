<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Console\Command;

use Kkkonrad\Gdpr\Application\Cookie\DefaultCookieCatalogManagement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CookieDiffDefaultsCommand extends Command
{
    private const OPTION_STORE = 'store';

    public function __construct(private readonly DefaultCookieCatalogManagement $catalogManagement)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('kkkonrad:gdpr:cookie:diff-defaults')
            ->setDescription('Compare the active cookie catalog with Kkkonrad GDPR defaults')
            ->addOption(self::OPTION_STORE, null, InputOption::VALUE_REQUIRED, 'Store view ID', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = max(0, (int)$input->getOption(self::OPTION_STORE));
        $rows = $this->catalogManagement->diff($storeId);
        $hasDifferences = false;
        $tableRows = [];
        foreach ($rows as $row) {
            $hasDifferences = $hasDifferences || $row['status'] !== 'ok';
            $tableRows[] = [$row['kind'], $row['key'], $row['status'], implode(', ', $row['differences'])];
        }
        (new Table($output))
            ->setHeaders(['Type', 'Key', 'Status', 'Different fields'])
            ->setRows($tableRows)
            ->render();

        if ($hasDifferences) {
            $output->writeln('<comment>Differences found. No data was changed.</comment>');
            return Command::FAILURE;
        }
        $output->writeln('<info>The default cookie catalog is current.</info>');
        return Command::SUCCESS;
    }
}
