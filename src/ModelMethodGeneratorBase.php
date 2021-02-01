<?php

namespace Drupal\wmscaffold;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\wmscaffold\Service\Helper\ModelMethodGeneratorHelper;
use PhpParser\Builder\Method;
use PhpParser\BuilderFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ModelMethodGeneratorBase extends PluginBase implements ModelMethodGeneratorInterface, ContainerFactoryPluginInterface
{
    /** @var ModelMethodGeneratorHelper */
    protected $helper;
    /** @var BuilderFactory */
    protected $builderFactory;

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        ModelMethodGeneratorHelper $helper
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->helper = $helper;
        $this->builderFactory = new BuilderFactory();
    }

    public static function create(
        ContainerInterface $container,
        array $configuration,
        $plugin_id,
        $plugin_definition
    ) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('wmscaffold.model_method_generator.helper')
        );
    }

    abstract public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void;

    public function buildSetter(): void
    {
        // TODO: Implement field setters
    }
}
