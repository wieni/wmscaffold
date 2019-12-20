<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eck\Entity\EckEntityType;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EckTypeCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use QuestionTrait;

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
     * @command eck:type:create
     * @aliases eck:type-create,etc
     *
     * @option label
     *      The human-readable name of this entity bundle. This name must be unique.
     * @option machine-name
     *      The machine name of the entity type
     * @option created
     *      Install the created base field
     * @option changed
     *      Install the changed base field
     * @option author
     *      Install the author base field
     * @option title
     *      Install the title base field
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush eck:type:create
     *      Create a eck entity type by answering the prompts.
     *
     * @throws PluginNotFoundException
     * @throws InvalidPluginDefinitionException
     * @throws EntityStorageException
     */
    public function create(array $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'created' => InputOption::VALUE_OPTIONAL,
        'changed' => InputOption::VALUE_OPTIONAL,
        'author' => InputOption::VALUE_OPTIONAL,
        'title' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $definition = $this->entityTypeManager->getDefinition('eck_entity_type');
        $storage = $this->entityTypeManager->getStorage('eck_entity_type');

        $values = [
            $definition->getKey('status') => true,
            $definition->getKey('id') => $this->input()->getOption('machine-name'),
            $definition->getKey('label') => $this->input()->getOption('label'),
            'created' => $this->input()->getOption('created'),
            'changed' => $this->input()->getOption('changed'),
            'uid' => $this->input()->getOption('author'),
            'title' => $this->input()->getOption('title'),
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('eck-type-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $type = $storage->create($values);
        $type->save();

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($type);
    }

    /** @hook interact eck:type:create */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $this->input->setOption(
            'label',
            $this->input->getOption('label') ?? $this->askLabel()
        );
        $this->input->setOption(
            'machine-name',
            $this->input->getOption('machine-name') ?? $this->askMachineName()
        );

        foreach (['created', 'changed', 'author', 'title'] as $fieldName) {
            $this->input->setOption(
                $fieldName,
                $this->input->getOption($fieldName) ?? $this->askBaseField($fieldName)
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

            if (strlen($answer) > ECK_ENTITY_ID_MAX_LENGTH) {
                $this->logger()->error('The machine-readable name must not be longer than :maxLength characters.', [':maxLength' => ECK_ENTITY_ID_MAX_LENGTH]);
                continue;
            }

            if ($this->entityTypeExists($answer)) {
                $this->logger()->error('An entity type with this name already exists.');
                continue;
            }

            $machineName = $answer;
        }

        return $machineName;
    }

    protected function askBaseField(string $fieldName)
    {
        return $this->confirm("Add the '{$fieldName}' base field?");
    }

    protected function entityTypeExists(string $id)
    {
        try {
            $this->entityTypeManager->getDefinition($id);
        } catch (PluginNotFoundException $e) {
            return false;
        }

        return true;
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
        $machineName = substr($machineName, 0, ECK_ENTITY_ID_MAX_LENGTH);

        return $machineName;
    }

    private function logResult(EckEntityType $type)
    {
        $this->logger()->success(
            sprintf('Successfully created eck entity type \'%s\'', $type->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . $type->toUrl()
                ->setAbsolute(true)
                ->toString()
        );
    }
}
