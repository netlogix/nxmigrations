<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Service;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory as DoctrineDependencyFactory;
use Doctrine\Migrations\Finder\MigrationFinder as MigrationFinderInterface;
use Doctrine\Migrations\Tools\Console\ConsoleLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DependencyFactory
{
    final public const DOCTRINE_MIGRATIONSTABLENAME = 'sys_doctrine_migrationstatus';

    final public const DOCTRINE_MIGRATIONSNAMESPACE = 'Netlogix\Migrations\Persistence\Doctrine\Migrations';

    public function createFromConnection(Connection $connection, BufferedOutput $logMessages): DoctrineDependencyFactory
    {
        $migrationsPath = Environment::getVarPath() . '/DoctrineMigrations';
        if (!is_dir($migrationsPath)) {
            GeneralUtility::mkdir_deep($migrationsPath);
        }
        $configurationLoader = new ConfigurationArray([
            'table_storage' => [
                'table_name' => self::DOCTRINE_MIGRATIONSTABLENAME,
                'version_column_length' => 255,
            ],
            'migrations_paths' => [
                self::DOCTRINE_MIGRATIONSNAMESPACE => $migrationsPath,
            ],
        ]);
        $connectionLoader = new ExistingConnection($connection);
        $logger = new ConsoleLogger($logMessages);

        $dependencyFactory = DoctrineDependencyFactory::fromConnection($configurationLoader, $connectionLoader);
        $dependencyFactory->setService(
            MigrationFinderInterface::class,
            new MigrationFinder(
                packageManager: GeneralUtility::makeInstance(PackageManager::class),
                databasePlatformName: match (true) {
                    $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform => 'Mysql',
                    $connection->getDatabasePlatform() instanceof PostgreSQLPlatform => 'Postgresql',
                    $connection->getDatabasePlatform() instanceof SQLitePlatform => 'Sqlite',
                }
            )
        );
        $dependencyFactory->setService(LoggerInterface::class, $logger);
        $dependencyFactory->getMetadataStorage()
            ->ensureInitialized();

        return $dependencyFactory;
    }
}
