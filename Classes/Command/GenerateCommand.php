<?php

declare(strict_types=1);

namespace Netlogix\Migrations\Command;

use Netlogix\Migrations\Service\DoctrineService;
use Netlogix\Migrations\Service\Files;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(name: 'doctrine:migrationgenerate', description: 'Creates a new migration file')]
class GenerateCommand extends Command
{
    public function __construct(
        private readonly DoctrineService $doctrineService,
        private readonly PackageManager $packageManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::OPTIONAL, 'Name of the package', '');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $packages = array_filter(
            $this->packageManager->getAvailablePackages(),
            fn (PackageInterface $package) => !$package->getPackageMetaData()
                ->isFrameworkType()
        );
        ksort($packages);

        $package = $input->getArgument('package');
        if ($package === '') {
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');

            $packageKeys = array_keys($packages);
            $packageValidator = static function (string $package) use ($packageKeys, $packages): string {
                if (array_key_exists($package, $packageKeys)) {
                    $package = $packageKeys[$package];
                }

                if (!array_key_exists($package, $packages)) {
                    throw new RuntimeException('Package does not exist ' . $package, 1_682_329_380);
                }

                return $package;
            };

            $question = new ChoiceQuestion('Please select a package', $packageKeys);
            $question->setValidator($packageValidator);
            $package = $questionHelper->ask($input, $output, $question);
        }

        if (!array_key_exists($package, $packages)) {
            $output->writeln(sprintf('Package <comment>%s</comment> not found!', $package));
            $output->writeln(
                sprintf('Available packages are: <comment>%s</comment>', implode(', ', array_keys($packages)))
            );

            return self::FAILURE;
        }
        $selectedPackage = $packages[$package];
        assert($selectedPackage instanceof PackageInterface);
        $migrationClassPathAndFilename = $this->doctrineService->generateMigration();
        $targetPathAndFilename = Files::concatenatePaths(
            $selectedPackage->getPackagePath(),
            'Migrations',
            ucfirst($this->doctrineService->getDatabasePlatformName()),
            basename($migrationClassPathAndFilename)
        );
        GeneralUtility::mkdir_deep(dirname((string) $targetPathAndFilename));
        rename($migrationClassPathAndFilename, $targetPathAndFilename);

        $output->writeln(
            sprintf('The migration was moved to: <comment>%s</comment>', substr(
                realpath($targetPathAndFilename),
                strlen((string) Environment::getProjectPath())
            ))
        );
        $output->writeln('- Review and adjust the generated migration.');
        $output->writeln(
            sprintf(
                '- (optional) execute the migration using <comment>%s doctrine:migrate</comment>',
                'vendor/bin/typo3'
            )
        );

        return self::SUCCESS;
    }
}
