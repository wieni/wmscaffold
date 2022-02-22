<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\wmscaffold\Service\Generator\EntityBundleClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class EntityBundleClassHooks extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use RunCommandTrait;

    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var ConfigFactoryInterface */
    protected $configFactory;
    /** @var EntityBundleClassGenerator */
    protected $entityBundleClassGenerator;
    /** @var PrettyPrinter */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityFieldManagerInterface $entityFieldManager,
        ConfigFactoryInterface $configFactory,
        EntityBundleClassGenerator $entityBundleClassGenerator
    ) {
        $this->entityFieldManager = $entityFieldManager;
        $this->configFactory = $configFactory;
        $this->entityBundleClassGenerator = $entityBundleClassGenerator;
        $this->prettyPrinter = new PrettyPrinter();
        $this->fileSystem = new Filesystem();
    }

    /** @hook option field:create */
    public function hookOptionFieldCreate(Command $command): void
    {
        $this->addModuleOption($command);
    }

    /** @hook init field:create */
    public function hookInitFieldCreate(): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command field:create */
    public function hookPostFieldCreate($result, CommandData $commandData): void
    {
        $entityType = $commandData->input()->getArgument('entityType');
        $bundle = $commandData->input()->getArgument('bundle');
        $module = $commandData->input()->getOption('bundle-class-output-module');
        $fieldName = $commandData->input()->getOption('field-name');

        $field = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle)[$fieldName] ?? null;

        if (!$field) {
            return;
        }

        if (!$statement = $this->entityBundleClassGenerator->appendFieldGettersToExistingClass($entityType, $bundle, [$field])) {
            return;
        }

        $this->logger()->notice('Adding new getter to entity bundle class...');
        $output = $this->prettyPrinter->prettyPrintFile([$statement]);
        $destination = $this->entityBundleClassGenerator->buildEntityBundleClassPath($entityType, $bundle, $module);

        if (!file_exists($destination)) {
            return;
        }

        $this->fileSystem->remove($destination);
        $this->fileSystem->appendToFile($destination, $output);

        $this->logger()->notice('Formatting entity bundle class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success('Successfully updated entity bundle class.');
    }

    /** @hook option nodetype:create */
    public function hookOptionNodeTypeCreate(Command $command): void
    {
        $this->addModuleOption($command);
    }

    /** @hook init nodetype:create */
    public function hookInitNodeTypeCreate(): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command nodetype:create */
    public function hookPostNodeTypeCreate($result, CommandData $commandData): void
    {
        $this->generateClass($commandData, 'node');
    }

    /** @hook option vocabulary:create */
    public function hookOptionVocabularyCreate(Command $command): void
    {
        $this->addModuleOption($command);
    }

    /** @hook init vocabulary:create */
    public function hookInitVocabularyCreate(): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command vocabulary:create */
    public function hookPostVocabularyCreate($result, CommandData $commandData): void
    {
        $this->generateClass($commandData, 'taxonomy_term');
    }

    /** @hook option eck:bundle:create */
    public function hookOptionEckBundleCreate(Command $command): void
    {
        $this->addModuleOption($command);
    }

    /** @hook init eck:bundle:create */
    public function hookInitEckBundleCreate(): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command eck:bundle:create */
    public function hookPostEckBundleCreate($result, CommandData $commandData): void
    {
        $this->generateClass($commandData, $commandData->input()->getArgument('entityType'));
    }

    /** @hook option paragraphs:type:create */
    public function hookOptionParagraphsTypeCreate(Command $command): void
    {
        $this->addModuleOption($command);
    }

    /** @hook init paragraphs:type:create */
    public function hookInitParagraphsTypeCreate(): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command paragraphs:type:create */
    public function hookPostParagraphsTypeCreate($result, CommandData $commandData): void
    {
        $this->generateClass($commandData, 'paragraph');
    }

    protected function addModuleOption(Command $command): void
    {
        if ($command->getDefinition()->hasOption('bundle-class-output-module')) {
            return;
        }

        $command->addOption(
            'bundle-class-output-module',
            '',
            InputOption::VALUE_OPTIONAL,
            'The name of the module containing the entity bundle class.'
        );
    }

    protected function setDefaultValue(): void
    {
        $module = $this->input->getOption('bundle-class-output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.bundle_class.output_module');

            $this->input->setOption('bundle-class-output-module', $default);
        }
    }

    protected function generateClass(CommandData $commandData, string $entityType): void
    {
        $bundle = $commandData->input()->getOption('machine-name');
        $module = $commandData->input()->getOption('bundle-class-output-module');

        if (!$module) {
            return;
        }

        $this->drush(
            'entity:bundle-class-generate',
            [
                'show-machine-names' => $commandData->input()->getOption('show-machine-names'),
                'output-module' => $module,
            ],
            ['entityType' => $entityType, 'bundle' => $bundle]
        );
    }
}
