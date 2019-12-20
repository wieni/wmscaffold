<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\wmscaffold\Service\Generator\ControllerClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
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
    /** @var PrettyPrinter */
    protected $prettyPrinter;

    public function __construct(
        ConfigFactoryInterface $configFactory,
        ControllerClassGenerator $controllerClassGenerator
    ) {
        $this->configFactory = $configFactory;
        $this->controllerClassGenerator = $controllerClassGenerator;
        $this->prettyPrinter = new PrettyPrinter();
    }

    /** @hook option nodetype:create */
    public function hookOptionNodeTypeCreate(Command $command): void
    {
        $this->addOption($command);
    }

    /** @hook init nodetype:create */
    public function hookInitNodeTypeCreate(InputInterface $input, AnnotationData $annotationData): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command nodetype:create */
    public function hookPostNodeTypeCreate($result, CommandData $commandData): void
    {
        $this->generateController($commandData, 'node');
    }

    /** @hook option vocabulary:create */
    public function hookOptionVocabularyCreate(Command $command): void
    {
        $this->addOption($command);
    }

    /** @hook init vocabulary:create */
    public function hookInitVocabularyTypeCreate(InputInterface $input, AnnotationData $annotationData): void
    {
        $this->setDefaultValue();
    }

    /** @hook post-command vocabulary:create */
    public function hookPostVocabularyCreate($result, CommandData $commandData): void
    {
        $this->generateController($commandData, 'taxonomy_term');
    }

    protected function addOption(Command $command): void
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

    protected function setDefaultValue(): void
    {
        $module = $this->input->getOption('wmcontroller-output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.controller.outputModule');

            $this->input->setOption('wmcontroller-output-module', $default);
        }
    }

    protected function generateController(CommandData $commandData, string $entityType): void
    {
        $bundle = $commandData->input()->getOption('machine-name');
        $module = $commandData->input()->getOption('wmcontroller-output-module');

        if (!$module) {
            return;
        }

        $this->drush(
            'wmcontroller:generate',
            [
                'show-machine-names' => $commandData->input()->getOption('show-machine-names'),
                'output-module' => $module,
            ],
            compact('entityType', 'bundle')
        );
    }
}
