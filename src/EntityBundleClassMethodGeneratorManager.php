<?php

namespace Drupal\wmscaffold;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\wmscaffold\Annotation\EntityBundleClassMethodGenerator;

class EntityBundleClassMethodGeneratorManager extends DefaultPluginManager
{
    public function __construct(
        \Traversable $namespaces,
        CacheBackendInterface $cacheBackend,
        ModuleHandlerInterface $moduleHandler
    ) {
        parent::__construct(
            'Plugin/EntityBundleClassMethodGenerator',
            $namespaces,
            $moduleHandler,
            EntityBundleClassMethodGeneratorInterface::class,
            EntityBundleClassMethodGenerator::class
        );
        $this->alterInfo('wmscaffold_entity_bundle_class_method_generator');
        $this->setCacheBackend($cacheBackend, 'wmscaffold_entity_bundle_class_method_generator_plugins');
    }
}
