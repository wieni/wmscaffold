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
                if (in_array($name, $uses, true)) {
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
}
