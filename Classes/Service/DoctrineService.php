<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Service;

use Doctrine\Migrations\DependencyFactory as DoctrineDependencyFactory;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use Doctrine\Migrations\Exception\NoMigrationsFoundWithCriteria;
use Doctrine\Migrations\Exception\NoMigrationsToExecute;
use Doctrine\Migrations\Exception\UnknownMigrationVersion;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsList;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Tools\Console\Exception\InvalidOptionUsage;
use Doctrine\Migrations\Tools\Console\Exception\VersionAlreadyExists;
use Doctrine\Migrations\Tools\Console\Exception\VersionDoesNotExist;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;

class DoctrineService
{
    private readonly DoctrineDependencyFactory $dependencyFactory;

    public function __construct(
        private readonly Connection $connection,
        DependencyFactory $dependencyFactory,
        private readonly BufferedOutput $logMessages = new BufferedOutput(null)
    ) {
        $this->logMessages->setDecorated(true);
        $this->dependencyFactory = $dependencyFactory->createFromConnection($this->connection, $this->logMessages);
    }

    public function getFormattedMigrationStatus($showMigrations = false): string
    {
        $infosHelper = $this->dependencyFactory->getMigrationStatusInfosHelper();
        $infosHelper->showMigrationsInfo($this->logMessages);

        if ($showMigrations) {
            $versions = $this->getSortedVersions(
                $this->dependencyFactory->getMigrationPlanCalculator()
                    ->getMigrations(), // available migrations
                $this->dependencyFactory->getMetadataStorage()
                    ->getExecutedMigrations() // executed migrations
            );

            $this->logMessages->writeln('');
            $this->dependencyFactory->getMigrationStatusInfosHelper()
                ->listVersions($versions, $this->logMessages);
        }

        return $this->logMessages->fetch();
    }

    public function getMigrationStatus(): array
    {
        $executedMigrations = $this->dependencyFactory->getMetadataStorage()
            ->getExecutedMigrations();
        $availableMigrations = $this->dependencyFactory->getMigrationPlanCalculator()
            ->getMigrations();
        $executedUnavailableMigrations = $this->dependencyFactory->getMigrationStatusCalculator(
        )->getExecutedUnavailableMigrations();
        $newMigrations = $this->dependencyFactory->getMigrationStatusCalculator()
            ->getNewMigrations();

        return [
            'executed' => count($executedMigrations),
            'unavailable' => count($executedUnavailableMigrations),
            'available' => count($availableMigrations),
            'new' => count($newMigrations),
        ];
    }

    public function executeMigrations(
        string $version = 'latest',
        string $outputPathAndFilename = null,
        mixed $dryRun = false,
        bool $quiet = false
    ) {
        $migrationRepository = $this->dependencyFactory->getMigrationRepository();
        if (count($migrationRepository->getMigrations()) === 0) {
            return sprintf('The version "%s" can\'t be reached, there are no registered migrations.', $version);
        }

        try {
            $resolvedVersion = $this->dependencyFactory->getVersionAliasResolver()
                ->resolveVersionAlias($version);
        } catch (UnknownMigrationVersion) {
            return sprintf('Unknown version: %s', OutputFormatter::escape($version));
        } catch (NoMigrationsToExecute|NoMigrationsFoundWithCriteria) {
            return $quiet === false ? $this->exitMessageForAlias($version) : '';
        }

        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanUntilVersion($resolvedVersion);
        if (count($plan) === 0) {
            return $quiet === false ? $this->exitMessageForAlias($version) : '';
        }

        if ($quiet === false) {
            $output = sprintf(
                'Migrating%s %s to %s',
                $dryRun ? ' (dry-run)' : '',
                $plan->getDirection(),
                (string) $resolvedVersion
            );
        } else {
            $output = '';
        }

        $migratorConfiguration = new MigratorConfiguration();
        $migratorConfiguration->setDryRun($dryRun || $outputPathAndFilename !== null);

        $migrator = $this->dependencyFactory->getMigrator();
        $sql = $migrator->migrate($plan, $migratorConfiguration);

        if ($quiet === false) {
            $output .= PHP_EOL;
            foreach ($sql as $item) {
                $output .= PHP_EOL;
                foreach ($item as $inner) {
                    $output .= '     -> ' . $inner->getStatement() . PHP_EOL;
                }
            }
            $output .= PHP_EOL;
            $output .= $this->logMessages->fetch();
        }

        if (is_string($outputPathAndFilename)) {
            $writer = $this->dependencyFactory->getQueryWriter();
            $writer->write($outputPathAndFilename, $plan->getDirection(), $sql);
            if ($quiet === false) {
                $output .= PHP_EOL . sprintf('SQL written to %s', $outputPathAndFilename);
            }
        }

        return $output;
    }

    public function executeMigration(
        string $version,
        string $direction = 'up',
        string $outputPathAndFilename = null,
        bool $dryRun = false
    ): string {
        $migrationRepository = $this->dependencyFactory->getMigrationRepository();
        if (!$migrationRepository->hasMigration($version)) {
            return sprintf('Version %s is not available', $version);
        }

        $migratorConfiguration = new MigratorConfiguration();
        $migratorConfiguration->setDryRun($dryRun || $outputPathAndFilename !== null);

        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanForVersions([new Version($version)], $direction);

        $output = sprintf('Migrating%s %s to %s', $dryRun ? ' (dry-run)' : '', $plan->getDirection(), $version);

        $migrator = $this->dependencyFactory->getMigrator();
        $sql = $migrator->migrate($plan, $migratorConfiguration);

        $output .= PHP_EOL;
        foreach ($sql as $item) {
            $output .= PHP_EOL;
            foreach ($item as $inner) {
                $output .= '     -> ' . $inner->getStatement() . PHP_EOL;
            }
        }
        $output .= PHP_EOL;
        $output .= $this->logMessages->fetch();

        if ($outputPathAndFilename !== null) {
            $writer = $this->dependencyFactory->getQueryWriter();
            $writer->write($outputPathAndFilename, $direction, $sql);
        }

        return $output;
    }

    public function markAsMigrated(string $version, bool $markAsMigrated): void
    {
        $executedMigrations = $this->dependencyFactory->getMetadataStorage()
            ->getExecutedMigrations();
        $availableVersions = $this->dependencyFactory->getMigrationPlanCalculator()
            ->getMigrations();
        if ($version === 'all') {
            if ($markAsMigrated === false) {
                foreach ($executedMigrations->getItems() as $availableMigration) {
                    $this->mark(
                        $this->logMessages,
                        $availableMigration->getVersion(),
                        false,
                        $executedMigrations,
                        !$markAsMigrated
                    );
                }
            }

            foreach ($availableVersions->getItems() as $availableMigration) {
                $this->mark(
                    $this->logMessages,
                    $availableMigration->getVersion(),
                    true,
                    $executedMigrations,
                    !$markAsMigrated
                );
            }
        } elseif ($version !== null) {
            $this->mark($this->logMessages, new Version($version), false, $executedMigrations, !$markAsMigrated);
        } else {
            throw InvalidOptionUsage::new('You must specify the version or use the --all argument.');
        }
    }

    public function generateMigration(): string
    {
        $fqcn = $this->dependencyFactory->getClassNameGenerator()
            ->generateClassName(DependencyFactory::DOCTRINE_MIGRATIONSNAMESPACE);
        $migrationGenerator = $this->dependencyFactory->getMigrationGenerator();

        return $migrationGenerator->generateMigration($fqcn);
    }

    public function getDatabasePlatformName(): string
    {
        return $this->dependencyFactory->getConnection()
            ->getDatabasePlatform()
            ->getName();
    }

    private function getSortedVersions(
        AvailableMigrationsList $availableMigrations,
        ExecutedMigrationsList $executedMigrations
    ): array {
        $availableVersions = array_map(
            static fn (AvailableMigration $availableMigration): Version => $availableMigration->getVersion(),
            $availableMigrations->getItems()
        );

        $executedVersions = array_map(
            static fn (ExecutedMigration $executedMigration): Version => $executedMigration->getVersion(),
            $executedMigrations->getItems()
        );

        $versions = array_unique(array_merge($availableVersions, $executedVersions));

        $comparator = $this->dependencyFactory->getVersionComparator();
        uasort($versions, static fn (Version $a, Version $b): int => $comparator->compare($a, $b));

        return $versions;
    }

    private function exitMessageForAlias(string $versionAlias): string
    {
        $version = $this->dependencyFactory->getVersionAliasResolver()
            ->resolveVersionAlias('current');

        // Allow meaningful message when latest version already reached.
        if (in_array($versionAlias, ['current', 'latest', 'first'], true)) {
            $message = sprintf('Already at the %s version ("%s")', $versionAlias, (string) $version);
        } elseif (in_array($versionAlias, ['next', 'prev'], true) || str_starts_with($versionAlias, 'current')) {
            $message = sprintf(
                'The version "%s" couldn\'t be reached, you are at version "%s"',
                $versionAlias,
                (string) $version
            );
        } else {
            $message = sprintf('You are already at version "%s"', (string) $version);
        }

        return $message;
    }

    private function mark(
        OutputInterface $output,
        Version $version,
        bool $all,
        ExecutedMigrationsList $executedMigrations,
        bool $delete
    ): void {
        try {
            $availableMigration = $this->dependencyFactory->getMigrationRepository()
                ->getMigration($version);
        } catch (MigrationClassNotFound) {
            $availableMigration = null;
        }

        $storage = $this->dependencyFactory->getMetadataStorage();
        if ($availableMigration === null) {
            if ($delete === false) {
                throw UnknownMigrationVersion::new((string) $version);
            }

            $migrationResult = new ExecutionResult($version, Direction::DOWN);
            $storage->complete($migrationResult);
            $output->writeln(sprintf("<info>%s</info> deleted from the version table.\n", (string) $version));

            return;
        }

        $marked = false;

        if ($delete === false && $executedMigrations->hasMigration($version)) {
            if (!$all) {
                throw VersionAlreadyExists::new($version);
            }

            $marked = true;
        }

        if ($delete && !$executedMigrations->hasMigration($version)) {
            if (!$all) {
                throw VersionDoesNotExist::new($version);
            }

            $marked = true;
        }

        if ($marked === true) {
            return;
        }

        if ($delete) {
            $migrationResult = new ExecutionResult($version, Direction::DOWN);
            $storage->complete($migrationResult);

            $output->writeln(sprintf("<info>%s</info> deleted from the version table.\n", (string) $version));
        } else {
            $migrationResult = new ExecutionResult($version, Direction::UP);
            $storage->complete($migrationResult);

            $output->writeln(sprintf("<info>%s</info> added to the version table.\n", (string) $version));
        }
    }
}
