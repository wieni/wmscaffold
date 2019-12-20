<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AllowedFormatsHooks extends DrushCommands
{
    use QuestionTrait;

    /** @var ModuleHandlerInterface */
    protected $moduleHandler;

    public function __construct(
        ModuleHandlerInterface $moduleHandler
    ) {
        $this->moduleHandler = $moduleHandler;
    }

    /** @hook interact field:create */
    public function hookInteract(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void
    {
        if (
            !$this->isInstalled()
            || !in_array($input->getOption('field-type'), _allowed_formats_field_types(), true)
        ) {
            return;
        }

        $input->setOption(
            'allowed-formats',
            $this->input->getOption('allowed-formats') ?? $this->askAllowedFormats()
        );
    }

    /** @hook option field:create */
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'allowed-formats',
            '',
            InputOption::VALUE_OPTIONAL,
            'Restrict which text formats are allowed, given the user has the required permissions.'
        );
    }

    /** @hook on-event field-create-field-config */
    public function hookFieldCreate(array &$values): void
    {
        if (
            !$this->isInstalled()
            || !in_array($values['field_type'], _allowed_formats_field_types(), true)
        ) {
            return;
        }

        $allFormats = array_keys(filter_formats());
        $allowedFormats = $this->input->getOption('allowed-formats') ?? [];
        $otherFormats = array_diff($allFormats, $allowedFormats);

        $values['dependencies']['module'][] = 'allowed_formats';
        $values['third_party_settings']['allowed_formats'] = array_merge(
            array_combine($allowedFormats, $allowedFormats),
            array_combine($otherFormats, array_fill(0, count($otherFormats), '0'))
        );
    }

    protected function askAllowedFormats(): array
    {
        $formats = filter_formats();
        $choices = ['- None -'];

        foreach ($formats as $format) {
            $label = $this->input->getOption('show-machine-names') ? $format->id() : $format->label();
            $choices[$format->id()] = $label;
        }

        return array_filter(
            $this->choice('Allowed formats', $choices, true, 0)
        );
    }

    protected function isInstalled(): bool
    {
        return $this->moduleHandler->moduleExists('allowed_formats');
    }
}
