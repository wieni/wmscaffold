<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eck\EckEntityTypeBundleInfo;
use Drupal\eck\Entity\EckEntityBundle;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EckBundleCreateCommands extends DrushCommands implements CustomEventAwareInterface
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
     * Create a new eck entity type
     *
     * @command eck:bundle:create
     * @aliases eck-bundle-create,ebc
     *
     * @param string $entityType
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
     * @usage drush eck:bundle:create
     *      Create a eck entity type by answering the prompts.
     */
    public function create($entityType, $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $definition = $this->entityTypeManager->getDefinition("{$entityType}_type");
        $storage = $this->entityTypeManager->getStorage("{$entityType}_type");

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

    /**
     * @hook interact eck:bundle:create
     */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$entityType) {
            return;
        }

        $this->input->setOption(
            'label',
            $this->input->getOption('label') ?? $this->askLabel()
        );
        $this->input->setOption(
            'machine-name',
            $this->input->getOption('machine-name') ?? $this->askMachineName()
        );
        $this->input->setOption(
            'description',
            $this->input->getOption('description') ?? $this->askDescription()
        );
    }

    /**
     * @hook validate eck:bundle:create
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

    protected function askLabel()
    {
        return $this->io()->ask('Human-readable name');
    }

    protected function askMachineName()
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
                $this->logger()->error('The machine-readable name must not be longer than :maxLength characters.', [':maxLength' => EntityTypeInterface::BUNDLE_MAX_LENGTH]);
                continue;
            }

            if ($this->bundleExists($answer)) {
                $this->logger()->error('A bundle with this name already exists.');
                continue;
            }

            $machineName = $answer;
        }

        return $machineName;
    }

    protected function askDescription()
    {
        return $this->askOptional('Description');
    }

    protected function bundleExists(string $id)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityType);

        return isset($bundleInfo[$id]);
    }

    protected function generateMachineName(string $source)
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

    private function logResult(EckEntityBundle $bundle)
    {
        $this->logger()->success(
            sprintf('Successfully created %s bundle \'%s\'', $bundle->getEckEntityTypeMachineName(), $bundle->id())
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
