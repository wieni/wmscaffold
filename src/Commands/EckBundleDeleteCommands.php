<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eck\EckEntityTypeBundleInfo;
use Drupal\eck\Entity\EckEntityBundle;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EckBundleDeleteCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EckEntityTypeBundleInfo */
    protected $entityTypeBundleInfo;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EckEntityTypeBundleInfo $entityTypeBundleInfo
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    }

    /**
     * Delete an eck entity type
     *
     * @command eck:bundle:delete
     * @aliases eck-bundle-delete,ebd
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     * @param array $options
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
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function delete($entityType, $bundle, $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $definition = $this->entityTypeManager->getDefinition("{$entityType}_type");
        $storage = $this->entityTypeManager->getStorage("{$entityType}_type");

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

    /**
     * @hook interact eck:bundle:delete
     */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        if (!$entityType) {
            return;
        }

        if (!$bundle || !$this->bundleExists($bundle)) {
            $this->input->setArgument('bundle', $this->askBundle());
        }
    }

    /**
     * @hook validate eck:bundle:delete
     */
    public function validateEntityType(CommandData $commandData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }

        $definition = $this->entityTypeManager->getDefinition($entityType);

        if ($definition->getProvider() !== 'eck') {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' is not an ECK entity type.', [':entityType' => $entityType])
            );
        }
    }

    protected function askBundle()
    {
        $entityType = $this->input->getArgument('entityType');
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityType);
        $choices = [];

        foreach ($bundleInfo as $bundle => $data) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $data['label'];
            $choices[$bundle] = $label;
        }

        return $this->choice('Bundle', $choices);
    }

    protected function bundleExists(string $id)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityType);

        return isset($bundleInfo[$id]);
    }

    private function logResult(EckEntityBundle $bundle)
    {
        $this->logger()->success(
            sprintf('Successfully deleted %s bundle \'%s\'', $bundle->getEckEntityTypeMachineName(), $bundle->id())
        );
    }
}
