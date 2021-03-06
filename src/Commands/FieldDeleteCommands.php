<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

class FieldDeleteCommands extends DrushCommands
{
    use AskBundleTrait;
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    }

    /**
     * Delete a field
     *
     * @command field:delete
     * @aliases field-delete,fd
     *
     * @validate-entity-type-argument entityType
     * @validate-optional-bundle-argument entityType bundle
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option field-name
     *      The machine name of the field
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush field:delete
     *      Delete a field by answering the prompts.
     * @usage drush field-delete taxonomy_term tag
     *      Delete a field and fill in the remaining information through prompts.
     * @usage drush field-delete taxonomy_term tag --field-name=field_tag_label
     *      Delete a field in a completely non-interactive way.
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    public function delete(string $entityType, ?string $bundle = null, array $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        if (!$bundle) {
            $this->input->setArgument('bundle', $bundle = $this->askBundle());
        }

        $fieldName = $this->input->getOption('field-name') ?? $this->askExisting($entityType, $bundle);
        $this->input->setOption('field-name', $fieldName);

        /** @var FieldConfig[] $results */
        $results = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'field_name' => $fieldName,
                'entity_type' => $entityType,
                'bundle' => $bundle,
            ]);

        $this->deleteFieldConfig(reset($results));

        // Fields are purged on cron. However field module prevents disabling modules
        // when field types they provided are used in a field until it is fully
        // purged. In the case that a field has minimal or no content, a single call
        // to field_purge_batch() will remove it from the system. Call this with a
        // low batch limit to avoid administrators having to wait for cron runs when
        // removing fields that meet this criteria.
        field_purge_batch(10);
    }

    protected function askExisting(string $entityType, string $bundle): string
    {
        $choices = [];
        /** @var FieldConfigInterface[] $fieldConfigs */
        $fieldConfigs = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => $entityType,
                'bundle' => $bundle,
            ]);

        foreach ($fieldConfigs as $fieldConfig) {
            $label = $this->input->getOption('show-machine-names')
                ? $fieldConfig->get('field_name')
                : $fieldConfig->get('label');

            $choices[$fieldConfig->get('field_name')] = $label;
        }

        return $this->choice('Choose a field to delete', $choices);
    }

    protected function deleteFieldConfig(FieldConfigInterface $fieldConfig): void
    {
        $fieldStorage = $fieldConfig->getFieldStorageDefinition();
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($fieldConfig->getTargetEntityTypeId());
        $bundleLabel = $bundles[$fieldConfig->getTargetBundle()]['label'];

        if ($fieldStorage && !$fieldStorage->isLocked()) {
            $fieldConfig->delete();

            // If there is only one bundle left for this field storage, it will be
            // deleted too, notify the user about dependencies.
            if (count($fieldStorage->getBundles()) <= 1) {
                $fieldStorage->delete();
            }

            $message = 'The field :field has been deleted from the :type content type.';
        } else {
            $message = 'There was a problem removing the :field from the :type content type.';
        }

        $this->logger()->success(
            t($message, [':field' => $fieldConfig->label(), ':type' => $bundleLabel])
        );
    }
}
