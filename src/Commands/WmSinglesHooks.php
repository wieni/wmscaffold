<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WmSinglesHooks extends DrushCommands
{
    use QuestionTrait;

    /** @var ModuleHandlerInterface */
    protected $moduleHandler;

    public function __construct(
        ModuleHandlerInterface $moduleHandler
    ) {
        $this->moduleHandler = $moduleHandler;
    }

    /** @hook interact nodetype:create */
    public function hookInteract(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $input->setOption(
            'is-single',
            $this->input->getOption('is-single') ?? $this->askIsSingle()
        );
    }

    /** @hook option nodetype:create */
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'is-single',
            '',
            InputOption::VALUE_OPTIONAL,
            'Is a content type with a single entity.'
        );
    }

    /** @hook on-event nodetype-create */
    public function hookCreate(&$values): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $values['third_party_settings']['wmsingles']['isSingle'] = (int) $this->input()->getOption('is-single');
        $values['dependencies']['module'][] = 'wmsingles';
    }

    protected function askIsSingle(): bool
    {
        return $this->confirm('Content type with a single entity?', false);
    }

    protected function isInstalled(): bool
    {
        return $this->moduleHandler->moduleExists('wmsingles');
    }
}
