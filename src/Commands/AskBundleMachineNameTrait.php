<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @property InputInterface $input
 * @property EntityTypeBundleInfoInterface $entityTypeBundleInfo
 */
trait AskBundleMachineNameTrait
{
    protected function askMachineName(string $entityTypeId): string
    {
        $label = $this->input->getOption('label');
        $suggestion = null;
        $machineName = null;

        if ($label) {
            $suggestion = $this->generateMachineName($label);
        }

        while (!$machineName) {
            $answer = $this->io()->ask('Machine-readable name', $suggestion);

            if (preg_match('/[^a-z0-9_]+/', $answer)) {
                $this->logger()->error('The machine-readable name must contain only lowercase letters, numbers, and underscores.');
                continue;
            }

            if (strlen($answer) > EntityTypeInterface::BUNDLE_MAX_LENGTH) {
                $this->logger()->error('Field name must not be longer than :maxLength characters.', [':maxLength' => EntityTypeInterface::BUNDLE_MAX_LENGTH]);
                continue;
            }

            if ($this->bundleExists($entityTypeId, $answer)) {
                $this->logger()->error('A bundle with this name already exists.');
                continue;
            }

            $machineName = $answer;
        }

        return $machineName;
    }

    protected function generateMachineName(string $source): string
    {
        // Only lowercase alphanumeric characters and underscores
        $machineName = preg_replace('/[^_a-z0-9]/i', '_', $source);
        // Maximum one subsequent underscore
        $machineName = preg_replace('/_+/', '_', $machineName);
        // Only lowercase
        $machineName = strtolower($machineName);
        // Maximum length
        $machineName = substr($machineName, 0, EntityTypeInterface::BUNDLE_MAX_LENGTH);

        return $machineName;
    }

    protected function bundleExists(string $entityTypeId, string $id): bool
    {
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

        return isset($bundleInfo[$id]);
    }
}
