<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Commands\DrushCommands;
use PhpCsFixer\Console\Application;
use PhpCsFixer\Console\Command\FixCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class PhpCsFixerCommands extends DrushCommands
{
    use RunCommandTrait;

    /** @var array<string, string> */
    protected $optionsMap = [
        'fixer-config' => 'config',
    ];

    /**
     * Fixes PHP coding standards using the friendsofphp/php-cs-fixer package.
     *
     * @command phpcs:fix
     * @aliases fix
     * @bootstrap none
     *
     * @param $path
     *      Path to a folder of file to fix.
     *
     * @return int
     *      The exit code
     */
    public function fix(?string $path = null, array $options = []): int
    {
        if (!class_exists(Application::class)) {
            throw new \InvalidArgumentException(
                t('The friendsofphp/php-cs-fixer package is required for this command to work.')
            );
        }

        $arguments = [];
        $extra = [];

        if ($path) {
            $arguments['path'] = $path;
        }

        if ($options['changed-only'] && $changedFiles = $this->getChangedFiles()) {
            $options['path-mode'] = 'intersection';
            $extra = $changedFiles;
        }

        foreach ($options as $name => $option) {
            if (isset($this->optionsMap[$name])) {
                $options[$this->optionsMap[$name]] = $option;
                unset($options[$name]);
            }
        }

        $application = new Application();
        $application->setAutoExit(false);

        return $this->runCommand($application, FixCommand::getDefaultName(), $arguments, $options, $extra);
    }

    /**
     * Provides the options for the phpcs:fix command.
     * @see FixCommand
     *
     * @hook option phpcs:fix
     */
    public function fixOptions(Command $command, AnnotationData $annotationData): void
    {
        if ($command->getDefinition()->hasOption('fixer-config')) {
            return;
        }

        // Standard
        $command->addOption('path-mode', '', InputOption::VALUE_REQUIRED, 'Specify path mode (can be override or intersection).', 'override');
        $command->addOption('allow-risky', '', InputOption::VALUE_REQUIRED, 'Are risky fixers allowed (can be yes or no).');
        $command->addOption('fixer-config', '', InputOption::VALUE_REQUIRED, 'The path to a .php_cs file.');
        $command->addOption('dry-run', '', InputOption::VALUE_OPTIONAL, 'Only shows which files would have been modified.');
        $command->addOption('rules', '', InputOption::VALUE_REQUIRED, 'The rules.');
        $command->addOption('using-cache', '', InputOption::VALUE_REQUIRED, 'Does cache should be used (can be yes or no).');
        $command->addOption('cache-file', '', InputOption::VALUE_REQUIRED, 'The path to the cache file.');
        $command->addOption('diff', '', InputOption::VALUE_OPTIONAL, 'Also produce diff for each file.');
        $command->addOption('diff-format', '', InputOption::VALUE_REQUIRED, 'Specify diff format.');
        $command->addOption('format', '', InputOption::VALUE_REQUIRED, 'To output results in other formats.');
        $command->addOption('stop-on-violation', '', InputOption::VALUE_OPTIONAL, 'Stop execution on first violation.');
        $command->addOption('show-progress', '', InputOption::VALUE_REQUIRED, 'Type of progress indicator (none, run-in, estimating, estimating-max or dots).');

        // Custom
        $command->addOption('changed-only', '', InputOption::VALUE_OPTIONAL, 'Only fix changed files.');
    }

    /** @return string[] */
    protected function getChangedFiles(): array
    {
        $process = $this->processManager()
            ->process(['git', 'diff', 'HEAD', '--name-only', '--diff-filter=ACMRTUXB']);

        $process->start();
        $process->wait();
        $output = $process->getOutput();

        if (strpos($output[0], 'fatal:') === 0) {
            return null;
        }

        return array_map(
            function ($path): string {
                return sprintf('../%s', $path);
            },
            array_filter(
                explode(PHP_EOL, $output)
            )
        );
    }
}
