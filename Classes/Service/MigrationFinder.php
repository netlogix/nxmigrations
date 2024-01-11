<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Service;

use Doctrine\Migrations\Finder\Finder;
use TYPO3\CMS\Core\Package\PackageManager;

class MigrationFinder extends Finder
{
    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly string $databasePlatformName
    ) {
    }

    public function findMigrations(string $directory, ?string $namespace = null): array
    {
        $files = [];

        foreach ($this->packageManager->getAvailablePackages() as $package) {
            $path = Files::concatenatePaths($package->getPackagePath(), 'Migrations', $this->databasePlatformName);
            if (is_dir($path)) {
                $files[] = glob($path . '/Version*.php');
            }
        }

        $files = array_merge([], ...$files); // the empty array covers cases when no loops were made

        return $this->loadMigrations($files, $namespace);
    }
}
