<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Consolidation\SiteProcess\Util\ArgumentProcessor;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;

trait RunCommandTrait
{
    use ProcessManagerAwareTrait;
    use SiteAliasManagerAwareTrait;

    /**
     * Run another Drush command
     *
     * @return bool
     */
    protected function drush(string $command, array $options = [], array $arguments = [])
    {
        $alias = $this->siteAliasManager()->getSelf();
        $process = $this->processManager->drush($alias, $command, $arguments, $options + ['yes' => true]);

        try {
            $process->setTty(true);
        } catch (RuntimeException $e) {
            // At least we tried ¯\_(ツ)_/¯
        }

        $process->realtimeStdout()->writeln(
            $this->buildCommandString($alias, $command, $options, $arguments)
        );

        $process->mustRun($process->showRealtime());
    }

    protected function buildCommandString(SiteAlias $alias, string $command, array $options = [], array $arguments = []): string
    {
        $processor = new ArgumentProcessor();

        return sprintf(
            '<comment>> %s %s</comment>',
            $command,
            implode(' ', $processor->selectArgs($alias, $arguments, $options))
        );
    }

    /** Run a command in a Symphony Application */
    protected function runCommand(Application $application, string $commandName, array $arguments, array $options, array $extra = []): int
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
            if (empty($value) || (!$definition->hasOption($key) && $key != 'verbose')) {
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
