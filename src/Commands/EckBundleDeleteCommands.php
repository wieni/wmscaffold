<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eck\Entity\EckEntityBundle;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\field\EntityTypeBundleAskTrait;
use Symfony\Component\Console\Input\InputOption;

class EckBundleDeleteCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use EntityTypeBundleAskTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Delete an eck entity type
     *
     * @command eck:bundle:delete
     * @aliases eck-bundle-delete,ebd
     *
     * @validate-eck-entity-type-argument entityType
     * @validate-optional-bundle-argument entityType bundle
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @option label
     *      The human-readable name of this entity bundle. This name must be unique.
     * @option machine-name
     *      A unique machine-readable name for this entity type bundle. It must only contain
     *      lowercase letters, numbers, and underscores.
     * @option description
     *      Describe this entity type bundle.
     *
     * @usage drush eck:bundle:delete
     *      Delete an eck entity type by answering the prompts.
     *
     * @validate-module-enabled eck
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function delete(string $entityType, ?string $bundle = null, array $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        if (!$bundle) {
            $this->input->setArgument('bundle', $bundle = $this->askBundle());
        }

        $definition = $this->entityTypeManager->getDefinition(sprintf('%s_type', $entityType));
        $storage = $this->entityTypeManager->getStorage(sprintf('%s_type', $entityType));

        $bundles = $storage->loadByProperties([$definition->getKey('id') => $bundle]);
        $bundle = reset($bundles);

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('eck-bundle-delete');
        foreach ($handlers as $handler) {
            $handler($bundle);
        }

        $storage->delete([$bundle]);

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($bundle);
    }

    private function logResult(EckEntityBundle $bundle): void
    {
        $this->logger()->success(
            sprintf("Successfully deleted %s bundle '%s'", $bundle->getEckEntityTypeMachineName(), $bundle->id())
        );
    }
}
