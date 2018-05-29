<?php

namespace Drupal\wmscaffold\Commands;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;

trait RunCommandTrait
{
    /**
     * Run another Drush command
     *
     * @param string $command
     * @param array $options
     * @param array $arguments
     * @param bool $interactive
     * @return bool
     */
    protected function drush(string $command, array $options = [], array $arguments = [], bool $interactive = false)
    {
        $backend_options = ['interactive' => $interactive];
        return (bool) drush_invoke_process('@self', $command, $arguments, $options, $backend_options);
    }

    /**
     * Run a command in a Symphony Application
     *
     * @param Application $application
     * @param string $commandName
     * @param $arguments
     * @param $options
     * @param array $extra
     * @return int
     */
    protected function runCommand(Application $application, string $commandName, $arguments, $options, $extra = [])
    {
        $definition = $application->get($commandName)->getDefinition();
        $argv = [$commandName];

        foreach ($arguments as $key => $value) {
            if (empty($value) || !$definition->hasArgument($key)) {
                continue;
            }

            $argv[] = $value;
        }

        foreach ($options as $key => $value) {
            if (empty($value) || (!$definition->hasOption($key) && !in_array($key, ['verbose']))) {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $argv[] = sprintf('--%s', $key);
            } else {
                $argv[] = sprintf('--%s=%s', $key, $value);
            }
        }

        if (!empty($extra)) {
            $argv[] = '--';
            $argv = array_merge($argv, $extra);
        }

        return $application->run(new StringInput(implode(' ', $argv)));
    }
}
