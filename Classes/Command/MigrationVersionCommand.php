<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Command;

use Doctrine\Migrations\Exception\MigrationException;
use InvalidArgumentException;
use Netlogix\Migrations\Service\DoctrineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctrine:migrationversion', description: 'Migrate the database schema')]
class MigrationVersionCommand extends Command
{
    public function __construct(
        private readonly DoctrineService $doctrineService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('version', InputArgument::OPTIONAL, 'The version to migrate to', 'latest');
        $this
            ->addOption('add', null, InputOption::VALUE_NONE, 'The migration to mark as migrated');
        $this
            ->addOption('delete', null, InputOption::VALUE_NONE, 'The migration to mark as not migrated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('add') === false && $input->getOption('delete') === false) {
            throw new InvalidArgumentException(
                'You must specify whether you want to --add or --delete the specified version.'
            );
        }

        try {
            $this->doctrineService->markAsMigrated(
                $this->normalizeVersion($input->getArgument('version')),
                $input->getOption('add') ?: false
            );
        } catch (MigrationException $exception) {
            $output->writeln($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function normalizeVersion(string $version): string
    {
        if (!is_numeric($version)) {
            return $version;
        }

        return sprintf('Netlogix\Migrations\Persistence\Doctrine\Migrations\Version%s', $version);
    }
}
