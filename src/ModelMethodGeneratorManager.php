<?php

namespace Drupal\wmscaffold;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\wmscaffold\Annotation\ModelMethodGenerator;

class ModelMethodGeneratorManager extends DefaultPluginManager
{
    public function __construct(
        \Traversable $namespaces,
        CacheBackendInterface $cacheBackend,
        ModuleHandlerInterface $moduleHandler
    ) {
        parent::__construct(
            'Plugin/ModelMethodGenerator',
            $namespaces,
            $moduleHandler,
            ModelMethodGeneratorInterface::class,
            ModelMethodGenerator::class
        );
        $this->alterInfo('wmscaffold_info');
        $this->setCacheBackend($cacheBackend, 'wmscaffold_info_plugins');
    }
}
