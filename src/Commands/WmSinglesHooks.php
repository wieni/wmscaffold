<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class WmSinglesHooks extends DrushCommands
{
    /**
     * @hook interact nodetype:create
     */
    public function hookInteract(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $input->setOption(
            'is-single',
            $this->input->getOption('is-single') ?? $this->askIsSingle()
        );
    }

    /**
     * @hook option nodetype:create
     */
    public function hookOption(Command $command, AnnotationData $annotationData)
    {
        $command->addOption(
            'is-single',
            '',
            InputOption::VALUE_OPTIONAL,
            'Is a content type with a single entity.'
        );
    }

    /**
     * @hook on-event nodetype-create
     */
    public function hookCreate(&$values)
    {
        $values['third_party_settings']['wmsingles']['isSingle'] = (int) $this->input()->getOption('is-single');
    }

    protected function askIsSingle()
    {
        return $this->io()->askQuestion(
            new ConfirmationQuestion('Content type with a single entity?', false)
        );
    }
}
