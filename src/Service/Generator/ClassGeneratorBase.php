<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

abstract class ClassGeneratorBase
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var FileSystemInterface */
    protected $fileSystem;

    /** @var ImmutableConfig */
    protected $config;
    /** @var BuilderFactory */
    protected $builderFactory;
    /** @var ParserFactory */
    protected $parserFactory;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        $this->fileSystem = $fileSystem;

        $this->config = $configFactory->get('wmscaffold.settings');
        $this->builderFactory = new BuilderFactory();
        $this->parserFactory = new ParserFactory();
    }

    protected function parseExpression(string $expression): Stmt
    {
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $statements = $parser->parse('<?php ' . $expression . ';');

        return $statements[0];
    }

    protected function cleanUseStatements(Stmt\Namespace_ $namespace): Stmt\Namespace_
    {
        $uses = [];

        // Deduplicate
        foreach ($namespace->stmts as $i => $statement) {
            if (!$statement instanceof Stmt\Use_) {
                continue;
            }

            foreach ($statement->uses as $j => $use) {
                $name = (string) $use->name;
                if (in_array($name, $uses)) {
                    unset($statement->uses[$j]);
                    continue;
                }
                $uses[] = $name;
            }

            if (empty($statement->uses)) {
                unset($namespace->stmts[$i]);
            }
        }

        // Sort
        usort(
            $namespace->stmts,
            function (Stmt $a, Stmt $b) {
                if ($a instanceof Stmt\Class_ && $b instanceof Stmt\Use_) {
                    return 1;
                }

                return -1;
            }
        );

        return $namespace;
    }

    protected function isReservedKeyword(string $input): bool
    {
        return in_array(strtolower($input), [
            '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
            'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
            'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends',
            'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
            'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private',
            'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try',
            'unset', 'use', 'var', 'while', 'xor',
        ]);
    }

    protected function toCamelCase(string $input): string
    {
        return lcfirst($this->toPascalCase($input));
    }

    protected function toPascalCase(string $input): string
    {
        $input = preg_replace('/\-|\s/', '_', $input);

        return str_replace('_', '', ucwords($input, '_'));
    }

    protected function toKebabCase(string $input): string
    {
        $input = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $input);

        return strtolower(ltrim($input, '-'));
    }

    protected function stripInvalidCharacters(string $string)
    {
        // A valid function name starts with a letter or underscore.
        while (!preg_match('/^[a-zA-Z_]/', $string)) {
            $string = substr($string, 1);
        }

        // Strip invalid characters
        // @see https://www.php.net/manual/en/functions.user-defined.php
        $string = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]*/i', '', $string);

        return $string;
    }
}
