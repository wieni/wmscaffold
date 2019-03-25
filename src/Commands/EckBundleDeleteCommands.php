<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
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
     *      Name of bundle to attach fields to.
     * @param string $bundle
     *      Type of entity (e.g. node, user, comment).
     * @param array $options
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @option label
     * @option machine-name
     * @option description
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     *
     * @usage drush eck:bundle:delete
     *      delete a eck entity type by answering the prompts.
     */
    public function delete($entityType, $bundle, $options = [
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
