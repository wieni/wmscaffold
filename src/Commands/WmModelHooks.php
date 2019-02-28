<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\wmscaffold\Service\Generator\ModelClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class WmModelHooks extends DrushCommands
{
    use RunCommandTrait;

    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var ModelClassGenerator */
    protected $modelClassGenerator;
    /** @var \PhpParser\PrettyPrinter\Standard */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityFieldManagerInterface $entityFieldManager,
        ModelClassGenerator $modelClassGenerator
    ) {
        $this->entityFieldManager = $entityFieldManager;
        $this->modelClassGenerator = $modelClassGenerator;
        $this->prettyPrinter = new Standard();
        $this->fileSystem = new Filesystem();
    }

    /**
     * @hook option field:create
     */
    public function hookOptionFieldCreate(Command $command)
    {
        $this->addModuleOption($command);
    }

    /**
     * @hook post-command field:create
     */
    public function hookPostFieldCreate($result, CommandData $commandData)
    {
        $entityType = $commandData->input()->getArgument('entityType');
        $bundle = $commandData->input()->getArgument('bundle');
        $module = $commandData->input()->getOption('wmmodel-output-module');
        $fieldName = $commandData->input()->getOption('field-name');

        $field = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle)[$fieldName] ?? null;

        if (!$field) {
            return;
        }

        if (!$statement = $this->modelClassGenerator->appendFieldGettersToExistingModel($entityType, $bundle, $module, [$field])) {
            return;
        }

        $this->logger()->notice('Adding new getter to model...');
        $output = $this->prettyPrinter->prettyPrintFile([$statement]);
        $destination = $this->modelClassGenerator->buildModelPath($entityType, $bundle, $module);

        if (!file_exists($destination)) {
            return;
        }

        $this->fileSystem->remove($destination);
        $this->fileSystem->appendToFile($destination, $output);

        $this->logger()->notice('Formatting model class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success('Successfully updated model.');
    }

    /**
     * @hook option nodetype:create
     */
    public function hookOptionNodeTypeCreate(Command $command)
    {
        $this->addModuleOption($command);
    }

    /**
     * @hook post-command nodetype:create
     */
    public function hookPostNodeTypeCreate($result, CommandData $commandData)
    {
        $entityType = 'node';
        $bundle = $commandData->input()->getOption('machine-name');
        $module = $commandData->input()->getOption('wmmodel-output-module');

        $this->drush(
            'wmmodel:generate',
            [
                'show-machine-names' => $commandData->input()->getOption('show-machine-names'),
                'output-module' => $module,
            ],
            compact('entityType', 'bundle')
        );
    }

    /**
     * @hook option vocabulary:create
     */
    public function hookOptionVocabularyCreate(Command $command)
    {
        $this->addModuleOption($command);
    }

    /**
     * @hook post-command vocabulary:create
     */
    public function hookPostVocabularyCreate($result, CommandData $commandData)
    {
        $entityType = 'taxonomy_term';
        $bundle = $commandData->input()->getOption('machine-name');
        $module = $commandData->input()->getOption('wmmodel-output-module');

        $this->drush(
            'wmmodel:generate',
            [
                'show-machine-names' => $commandData->input()->getOption('show-machine-names'),
                'output-module' => $module,
            ],
            compact('entityType', 'bundle')
        );
    }

    /**
     * @hook option eck:bundle:create
     */
    public function hookOptionEckBundleCreate(Command $command)
    {
        $this->addModuleOption($command);
    }

    /**
     * @hook post-command eck:bundle:create
     */
    public function hookPostEckBundleCreate($result, CommandData $commandData)
    {
        $entityType = $commandData->input()->getArgument('entityType');
        $bundle = $commandData->input()->getOption('machine-name');
        $module = $commandData->input()->getOption('wmmodel-output-module');

        $this->drush(
            'wmmodel:generate',
            [
                'show-machine-names' => $commandData->input()->getOption('show-machine-names'),
                'output-module' => $module,
            ],
            compact('entityType', 'bundle')
        );
    }

    protected function addModuleOption(Command $command)
    {
        if ($command->getDefinition()->hasOption('wmmodel-output-module')) {
            return;
        }

        $command->addOption(
            'wmmodel-output-module',
            '',
            InputOption::VALUE_OPTIONAL,
            'The name of the module containing the wmmodel class.'
        );
    }
}
