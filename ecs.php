<?php

declare(strict_types=1);

use Netlogix\CodingGuidelines\Php\DefaultPhp;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    (new DefaultPhp())->configure($ecsConfig);

    $ecsConfig->paths(
        [
            __DIR__ . '/Classes',
            __DIR__ . '/Configuration',
            __DIR__ . '/Tests'
        ]
    );

    $ecsConfig->skip([
        DeclareStrictTypesFixer::class => [
            __DIR__ . '/ext_tables.php',
            __DIR__ . '/Configuration/TCA/*',
        ]
    ]);
};