<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Command;

use Netlogix\Migrations\Service\DoctrineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctrine:migrate', description: 'Migrate the database schema')]
class MigrateCommand extends Command
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
            ->addOption('dryRun', null, InputOption::VALUE_NONE, 'Whether to do a dry run or not');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->doctrineService->executeMigrations(
            version: $this->normalizeVersion($input->getArgument('version')),
            dryRun: $input->getOption('dryRun'),
            quiet: $output->isQuiet()
        );

        if ($result !== '' && !$output->isQuiet()) {
            $output->writeln($result);
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
