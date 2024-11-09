<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Command;

use Netlogix\Migrations\Service\DoctrineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctrine:status', description: 'Show the current migration status')]
class StatusCommand extends Command
{
    public function __construct(
        private readonly DoctrineService $doctrineService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'showMigrations',
                null,
                InputOption::VALUE_NONE,
                'Output a list of all migrations and their status'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->doctrineService->getFormattedMigrationStatus($input->getOption('showMigrations')));

        return self::SUCCESS;
    }
}
