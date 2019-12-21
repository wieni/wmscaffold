<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @property InputInterface $input
 * @property EntityTypeBundleInfoInterface $entityTypeBundleInfo
 * @method choice(string $question, array $choices, bool $multiSelect = false, $default = null)
 */
trait AskBundleTrait
{
    protected function askBundle(): ?string
    {
        $entityType = $this->input->getArgument('entityType');
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityType);
        $choices = [];

        if (empty($bundleInfo)) {
            return null;
        }

        foreach ($bundleInfo as $bundle => $data) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        return $this->choice('Bundle', $choices);
    }
}
