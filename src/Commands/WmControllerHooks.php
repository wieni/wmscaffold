<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\wmscaffold\Service\Generator\ControllerClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class WmControllerHooks extends DrushCommands
{
    use RunCommandTrait;

    /** @var ControllerClassGenerator */
    protected $controllerClassGenerator;
    /** @var \PhpParser\PrettyPrinter\Standard */
    protected $prettyPrinter;

    public function __construct(
        ControllerClassGenerator $controllerClassGenerator
    ) {
        $this->controllerClassGenerator = $controllerClassGenerator;
        $this->prettyPrinter = new Standard();
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
        $module = $commandData->input()->getOption('wmcontroller-output-module');

        $this->drush(
            'wmcontroller:generate',
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
        $module = $commandData->input()->getOption('wmcontroller-output-module');

        $this->drush(
            'wmcontroller:generate',
            [
                'show-machine-names' => $commandData->input()->getOption('show-machine-names'),
                'output-module' => $module,
            ],
            compact('entityType', 'bundle')
        );
    }

    protected function addModuleOption(Command $command)
    {
        if ($command->getDefinition()->hasOption('wmcontroller-output-module')) {
            return;
        }

        $command->addOption(
            'wmcontroller-output-module',
            '',
            InputOption::VALUE_OPTIONAL,
            'The name of the module containing the wmcontroller class.'
        );
    }
}
