<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\wmscaffold\Service\Generator\ControllerClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class WmControllerHooks extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use RunCommandTrait;

    /** @var ConfigFactoryInterface */
    protected $configFactory;
    /** @var ControllerClassGenerator */
    protected $controllerClassGenerator;
    /** @var \PhpParser\PrettyPrinter\Standard */
    protected $prettyPrinter;

    public function __construct(
        ConfigFactoryInterface $configFactory,
        ControllerClassGenerator $controllerClassGenerator
    ) {
        $this->configFactory = $configFactory;
        $this->controllerClassGenerator = $controllerClassGenerator;
        $this->prettyPrinter = new Standard();
    }

    /**
     * @hook option nodetype:create
     */
    public function hookOptionNodeTypeCreate(Command $command)
    {
        $this->addOption($command);
    }

    /**
     * @hook init nodetype:create
     */
    public function hookInitNodeTypeCreate(InputInterface $input, AnnotationData $annotationData)
    {
        $this->setDefaultValue();
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
        $this->addOption($command);
    }

    /**
     * @hook init vocabulary:create
     */
    public function hookInitVocabularyTypeCreate(InputInterface $input, AnnotationData $annotationData)
    {
        $this->setDefaultValue();
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

    protected function addOption(Command $command)
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

    protected function setDefaultValue()
    {
        $module = $this->input->getOption('wmcontroller-output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.controller.outputModule');

            $this->input->setOption('wmcontroller-output-module', $default);
        }
    }
}
