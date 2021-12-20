<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class WmSinglesHooks extends DrushCommands
{
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;

    public function __construct(
        ModuleHandlerInterface $moduleHandler
    ) {
        $this->moduleHandler = $moduleHandler;
    }

    /** @hook interact nodetype:create */
    public function hookInteract(): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $this->input->setOption(
            'is-single',
            $this->input->getOption('is-single') ?? $this->askIsSingle()
        );
    }

    /** @hook option nodetype:create */
    public function hookOption(Command $command): void
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
    public function hookCreate(array &$values): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $values['third_party_settings']['wmsingles']['isSingle'] = (int) $this->input()->getOption('is-single');
        $values['dependencies']['module'][] = 'wmsingles';
    }

    protected function askIsSingle(): bool
    {
        return $this->io()->confirm('Content type with a single entity?', false);
    }

    protected function isInstalled(): bool
    {
        return $this->moduleHandler->moduleExists('wmsingles');
    }
}
