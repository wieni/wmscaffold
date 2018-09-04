<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\wmmodel\Entity\EntityTypeBundleInfo;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\BuilderFactory;
use PhpParser\ParserFactory;

abstract class ClassGeneratorBase
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
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
        EntityTypeBundleInfo $entityTypeBundleInfo,
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->fileSystem = $fileSystem;

        $this->config = $configFactory->get('wmscaffold.settings');
        $this->builderFactory = new BuilderFactory();
        $this->parserFactory = new ParserFactory();
    }

    protected function parseExpression(string $expression)
    {
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $statements = $parser->parse('<?php ' . $expression . ';');
        return $statements[0];
    }

    protected function cleanUseStatements(Namespace_ $namespace)
    {
        $uses = [];

        // Deduplicate
        foreach ($namespace->stmts as $i => $statement) {
            if (!$statement instanceof Use_) {
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
                if ($a instanceof Class_ && $b instanceof Use_) {
                    return 1;
                }

                return -1;
            }
        );

        return $namespace;
    }

    protected function isReservedKeyword(string $input)
    {
        return in_array(strtolower($input), [
            '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
            'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
            'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends',
            'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
            'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private',
            'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try',
            'unset', 'use', 'var', 'while', 'xor'
        ]);
    }

    protected function toCamelCase(string $input)
    {
        return lcfirst($this->toPascalCase($input));
    }

    protected function toPascalCase(string $input)
    {
        $input = preg_replace('/\-|\s/', '_', $input);
        return str_replace('_', '', ucwords($input, '_'));
    }

    protected function toKebabCase(string $input)
    {
        $input = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $input);
        return ltrim(strtolower($input), '-');
    }

    protected function stripInvalidCharacters(string $string)
    {
        return preg_replace('/[^a-zA-Z_\x7f-\xff][^a-zA-Z0-9_\x7f-\xff]*/i', '', $string);
    }
}
