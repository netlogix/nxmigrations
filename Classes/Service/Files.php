<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Files
{
    public static function concatenatePaths(string ...$paths): string
    {
        $resultingPath = '';
        foreach ($paths as $index => $path) {
            $path = GeneralUtility::fixWindowsFilePath($path);
            if ($index === 0) {
                $path = rtrim($path, '/');
            } else {
                $path = trim($path, '/');
            }
            if ($path !== '') {
                $resultingPath .= $path . '/';
            }
        }

        return rtrim($resultingPath, '/');
    }
}
