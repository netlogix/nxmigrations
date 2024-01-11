<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Rector\General\ConvertImplicitVariablesToExplicitGlobalsRector;
use Ssch\TYPO3Rector\Rector\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Rector\v11\v0\SubstituteConstantsModeAndRequestTypeRector;
use Ssch\TYPO3Rector\Set\Extension\NimutTestingFrameworkSetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return static function (RectorConfig $rectorConfig): void {

    // If you want to override the number of spaces for your typoscript files you can define it here, the default value is 4
    // $parameters = $rectorConfig->parameters();
    // $parameters->set(Typo3Option::TYPOSCRIPT_INDENT_SIZE, 2);

    $rectorConfig->sets([
        // PHP
        LevelSetList::UP_TO_PHP_81,

        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,

        // PhpUnit
        PHPUnitLevelSetList::UP_TO_PHPUNIT_100,

        // TYPO3
        // v7
        Typo3SetList::TCA_76,

        // v8
        Typo3SetList::TCA_87,

        // v9
        Typo3SetList::TCA_95,
        Typo3SetList::EXTBASE_COMMAND_CONTROLLERS_TO_SYMFONY_COMMANDS,

        // v10
        Typo3SetList::TCA_104,
        Typo3SetList::TYPOSCRIPT_CONDITIONS_95,
        Typo3SetList::TYPOSCRIPT_100,
        Typo3SetList::TYPOSCRIPT_CONDITIONS_104,
        Typo3SetList::DATABASE_TO_DBAL,

        // v11
        Typo3SetList::TCA_114,
        Typo3SetList::REGISTER_ICONS_TO_ICON,

        // v12
        Typo3SetList::TCA_120,
        Typo3SetList::TCA_123,
        Typo3SetList::TYPOSCRIPT_120,

        // Typo3LevelSetList::UP_TO_TYPO3_7,
        // Typo3LevelSetList::UP_TO_TYPO3_8,
        // Typo3LevelSetList::UP_TO_TYPO3_9,
        // Typo3LevelSetList::UP_TO_TYPO3_10,
        // Typo3LevelSetList::UP_TO_TYPO3_11,
        Typo3LevelSetList::UP_TO_TYPO3_12,

        NimutTestingFrameworkSetList::NIMUT_TESTING_FRAMEWORK_TO_TYPO3_TESTING_FRAMEWORK
    ]);

    $rectorConfig->rule(SubstituteConstantsModeAndRequestTypeRector::class);

    // Define your target version which you want to support
    $rectorConfig->phpVersion(PhpVersion::PHP_81);

    $parameters = $rectorConfig->parameters();
    $parameters->set(Option::IMPORT_SHORT_CLASSES, false);
    $parameters->set(Option::AUTO_IMPORT_DOC_BLOCK_NAMES, false);

    // If you only want to process one/some TYPO3 extension(s), you can specify its path(s) here.
    // If you use the option --config change __DIR__ to getcwd()
    $rectorConfig->paths([
        __DIR__,
    ]);

    // When you use rector there are rules that require some more actions like creating UpgradeWizards for outdated TCA types.
    // To fully support you we added some warnings. So watch out for them.

    // If you use importNames(), you should consider excluding some TYPO3 files.
    $rectorConfig->skip([
        // PHP
        AddLiteralSeparatorToNumberRector::class,

        // TYPO3
        // @see https://github.com/sabbelasichon/typo3-rector/issues/2536
        __DIR__ . '/**/Configuration/ExtensionBuilder/*',
        // We skip those directories on purpose as there might be node_modules or similar
        // that include typescript which would result in false positive processing
        __DIR__ . '/**/Resources/**/node_modules/*',
        __DIR__ . '/**/Resources/**/NodeModules/*',
        __DIR__ . '/**/Resources/**/BowerComponents/*',
        __DIR__ . '/**/Resources/**/bower_components/*',
        __DIR__ . '/**/Resources/**/build/*',
        __DIR__ . '/**/Resources/**/App/*',
        __DIR__ . '/**/Resources/Public/*',
        __DIR__ . '/vendor/*',
        __DIR__ . '/Build/*',
        __DIR__ . '/public/*',
        __DIR__ . '/.github/*',
        __DIR__ . '/.Build/*',
        NameImportingPostRector::class => [
            'ext_localconf.php',
            'ext_tables.php',
            'ClassAliasMap.php',
            __DIR__ . '/**/Configuration/*.php',
            __DIR__ . '/**/Configuration/**/*.php',
        ]
    ]);

    // If you have trouble that rector cannot run because some TYPO3 constants are not defined add an additional constants file
    // @see https://github.com/sabbelasichon/typo3-rector/blob/master/typo3.constants.php
    // @see https://github.com/rectorphp/rector/blob/main/docs/static_reflection_and_autoload.md#include-files
    // $parameters->set(Option::BOOTSTRAP_FILES, [
    //    __DIR__ . '/typo3.constants.php'
    // ]);

    // register a single rule
    // $rectorConfig->rule(\Ssch\TYPO3Rector\Rector\v9\v0\InjectAnnotationRector::class);

    /**
     * Useful rule from RectorPHP itself to transform i.e. GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')
     * to GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class) calls.
     * But be warned, sometimes it produces false positives (edge cases), so watch out
     */
    // $rectorConfig->rule(\Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class);

    // Optional non-php file functionalities:
    // @see https://github.com/sabbelasichon/typo3-rector/blob/main/docs/beyond_php_file_processors.md

    // Rewrite your extbase persistence class mapping from typoscript into php according to official docs.
    // This processor will create a summarized file with all the typoscript rewrites combined into a single file.
    /* $rectorConfig->ruleWithConfiguration(\Ssch\TYPO3Rector\FileProcessor\TypoScript\Rector\v10\v0\ExtbasePersistenceTypoScriptRector::class, [
        \Ssch\TYPO3Rector\FileProcessor\TypoScript\Rector\v10\v0\ExtbasePersistenceTypoScriptRector::FILENAME => __DIR__ . '/packages/acme_demo/Configuration/Extbase/Persistence/Classes.php',
    ]); */
    // Add some general TYPO3 rules
    $rectorConfig->rule(ConvertImplicitVariablesToExplicitGlobalsRector::class);
    $rectorConfig->ruleWithConfiguration(ExtEmConfRector::class, [
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => []
    ]);

    // Modernize your TypoScript include statements for files and move from <INCLUDE /> to @import use the FileIncludeToImportStatementVisitor (introduced with TYPO3 9.0)
    // $rectorConfig->rule(\Ssch\TYPO3Rector\FileProcessor\TypoScript\Rector\v9\v0\FileIncludeToImportStatementTypoScriptRector::class);
};
