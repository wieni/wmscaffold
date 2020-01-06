<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @property EntityTypeManagerInterface $entityTypeManager
 */
trait ValidatorsTrait
{
    public function validateEntityType(string $entityTypeId): void
    {
        if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityTypeId])
            );
        }
    }

    public function validateEckEntityType(string $entityTypeId): void
    {
        $this->validateEntityType($entityTypeId);

        $definition = $this->entityTypeManager->getDefinition($entityTypeId);

        if ($definition->getProvider() !== 'eck') {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' is not an ECK entity type.', [':entityType' => $entityTypeId])
            );
        }
    }

    public function validateBundle(string $entityTypeId, string $bundle): void
    {
        if ($entityTypeDefinition = $this->entityTypeManager->getDefinition($entityTypeId)) {
            if ($bundleEntityType = $entityTypeDefinition->getBundleEntityType()) {
                $bundleDefinition = $this->entityTypeManager
                    ->getStorage($bundleEntityType)
                    ->load($bundle);
            }
        }

        if (!isset($bundleDefinition)) {
            throw new \InvalidArgumentException(
                t('Bundle \':bundle\' does not exist on entity type with id \':entityType\'.', [
                    ':bundle' => $bundle,
                    ':entityType' => $entityTypeId,
                ])
            );
        }
    }
}
