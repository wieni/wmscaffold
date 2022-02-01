<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eck\Entity\EckEntityBundle;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EckBundleCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use BundleMachineNameAskTrait;
    use CustomEventAwareTrait;
    use ValidatorsTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Create a new eck entity type
     *
     * @command eck:bundle:create
     * @aliases eck-bundle-create,ebc
     *
     * @validate-eck-entity-type-argument entityType
     *
     * @param string $entityType
     *      The machine name of the entity type
     *
     * @option label
     *      The human-readable name of this entity bundle. This name must be unique.
     * @option machine-name
     *      A unique machine-readable name for this entity type bundle. It must only contain
     *      lowercase letters, numbers, and underscores.
     * @option description
     *      Describe this entity type bundle.
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush eck:bundle:create
     *      Create an eck entity type by answering the prompts.
     *
     * @validate-module-enabled eck
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function create(string $entityType, array $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $definition = $this->entityTypeManager->getDefinition(sprintf('%s_type', $entityType));
        $storage = $this->entityTypeManager->getStorage(sprintf('%s_type', $entityType));

        $values = [
            'status' => true,
            $definition->getKey('id') => $this->input()->getOption('machine-name'),
            $definition->getKey('label') => $this->input()->getOption('label'),
            'description' => $this->input()->getOption('description') ?? '',
            'entity_type' => $entityType,
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('eck-bundle-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $bundle = $storage->create($values);
        $bundle->save();

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($bundle);
    }

    /** @hook interact eck:bundle:create */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void
    {
        $entityTypeId = $this->input->getArgument('entityType');

        if (!$entityTypeId) {
            return;
        }

        $this->validateEckEntityType($entityTypeId);

        $this->input->setOption(
            'label',
            $this->input->getOption('label') ?? $this->askLabel()
        );
        $this->input->setOption(
            'machine-name',
            $this->input->getOption('machine-name') ?? $this->askMachineName($entityTypeId)
        );
        $this->input->setOption(
            'description',
            $this->input->getOption('description') ?? $this->askDescription()
        );
    }

    protected function askLabel(): string
    {
        return $this->io()->askRequired('Human-readable name');
    }

    protected function askDescription(): ?string
    {
        return $this->io()->ask('Description');
    }

    private function logResult(EckEntityBundle $bundle): void
    {
        $this->logger()->success(
            sprintf("Successfully created %s bundle '%s'", $bundle->getEckEntityTypeMachineName(), $bundle->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . $bundle->toUrl()
                ->setAbsolute(true)
                ->toString()
        );
    }
}
