<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MenuUiHooks extends DrushCommands
{
    use QuestionTrait;

    /** @var ModuleHandlerInterface */
    protected $moduleHandler;
    /** @var MenuParentFormSelectorInterface */
    protected $menuParentFormSelector;

    public function __construct(
        ModuleHandlerInterface $moduleHandler,
        MenuParentFormSelectorInterface $menuParentFormSelector
    ) {
        $this->moduleHandler = $moduleHandler;
        $this->menuParentFormSelector = $menuParentFormSelector;
    }

    /** @hook interact nodetype:create */
    public function hookInteract(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        if (!$this->isInstalled()) {
            return;
        }

        $input->setOption(
            'menu-available',
            $this->input->getOption('menu-available') ?? $this->askMenus()
        );

        $menus = $this->input->getOption('menu-available');

        if (empty($menus)) {
            return;
        }

        $input->setOption(
            'menu-default-parent',
            $this->input->getOption('menu-default-parent') ?? $this->askDefaultParent($menus)
        );
    }

    /** @hook option nodetype:create */
    public function hookOption(Command $command, AnnotationData $annotationData)
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'menu-available',
            '',
            InputOption::VALUE_OPTIONAL,
            'The menus available to place links in for this content type.'
        );

        $command->addOption(
            'menu-default-parent',
            '',
            InputOption::VALUE_OPTIONAL,
            'The menu item to be the default parent for a new link in the content authoring form.'
        );
    }

    /** @hook on-event nodetype-create */
    public function hookCreate(&$values)
    {
        if (!$this->isInstalled()) {
            return;
        }

        $values['third_party_settings']['menu_ui']['available_menus'] = $this->input->getOption('menu-available');
        $values['third_party_settings']['menu_ui']['parent'] = $this->input->getOption('menu-default-parent') ?? '';
        $values['dependencies']['module'][] = 'menu_ui';
    }

    protected function askMenus()
    {
        $menus = menu_ui_get_menus();
        $choices = ['- None -'];

        foreach ($menus as $name => $label) {
            $label = $this->input->getOption('show-machine-names') ? $name : $label;
            $choices[$name] = $label;
        }

        return array_filter(
            $this->choice('Available menus', $choices, true, 0)
        );
    }

    protected function askDefaultParent(array $menus)
    {
        $menus = array_intersect_key(menu_ui_get_menus(), array_flip($menus));
        $options = $this->menuParentFormSelector->getParentSelectOptions('', $menus);

        return $this->choice('Default parent item', $options, false, 0);
    }

    protected function isInstalled()
    {
        return $this->moduleHandler->moduleExists('menu_ui');
    }
}
