<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Console\Command;

use Kkkonrad\Gdpr\Application\Cookie\DefaultCookieCatalogManagement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CookieRestoreDefaultsCommand extends Command
{
    private const OPTION_STORE = 'store';
    private const OPTION_FORCE = 'force';

    public function __construct(private readonly DefaultCookieCatalogManagement $catalogManagement)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('kkkonrad:gdpr:cookie:restore-defaults')
            ->setDescription('Restore the shipped cookie defaults without deleting custom definitions')
            ->addOption(self::OPTION_STORE, null, InputOption::VALUE_REQUIRED, 'Store view ID', '0')
            ->addOption(self::OPTION_FORCE, 'f', InputOption::VALUE_NONE, 'Restore without interactive confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = max(0, (int)$input->getOption(self::OPTION_STORE));
        if (!(bool)$input->getOption(self::OPTION_FORCE)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'Restore default groups and storage definitions for store %d? Custom definitions will remain [y/N] ',
                    $storeId
                ),
                false
            );
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Restore cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        $result = $this->catalogManagement->restore($storeId);
        $output->writeln(sprintf(
            '<info>Defaults restored: %d inserted, %d updated; policy version %d.</info>',
            $result['inserted'],
            $result['updated'],
            $result['policy_version']
        ));
        return Command::SUCCESS;
    }
}
