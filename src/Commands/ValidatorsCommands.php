<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

class ValidatorsCommands extends DrushCommands
{
    use ValidatorsTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Validate that the entity type passed as argument exists.
     *
     * @hook validate @validate-entity-type-argument
     */
    public function hookValidateEntityType(CommandData $commandData): void
    {
        $argumentName = $commandData->annotationData()->get('validate-entity-type-argument');
        $entityType = $commandData->input()->getArgument($argumentName);

        $this->validateEntityType($entityType);
    }

    /**
     * Validate that the entity type passed as argument exists.
     *
     * @hook validate @validate-eck-entity-type-argument
     */
    public function hookValidateEckEntityType(CommandData $commandData): void
    {
        $argumentName = $commandData->annotationData()->get('validate-eck-entity-type-argument');
        $entityType = $commandData->input()->getArgument($argumentName);

        $this->validateEckEntityType($entityType);
    }

    /**
     * Validate that the bundle passed as argument exists.
     *
     * @hook validate @validate-bundle-argument
     */
    public function hookValidateBundle(CommandData $commandData): void
    {
        $annotation = $commandData->annotationData()->get('validate-bundle-argument');
        [$entityTypeArgumentName, $bundleArgumentName] = explode(' ', $annotation);

        $entityType = $commandData->input()->getArgument($entityTypeArgumentName);
        $bundle = $commandData->input()->getArgument($bundleArgumentName);

        $this->validateBundle($entityType, $bundle);
    }
}
