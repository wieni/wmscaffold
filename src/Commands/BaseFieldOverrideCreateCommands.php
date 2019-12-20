<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class BaseFieldOverrideCreateCommands extends DrushCommands
{
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManager */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        EntityFieldManager $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * Create a new base field override
     *
     * @command base-field-override:create
     * @aliases base-field-override-create,bfoc
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     * @param array $options
     *
     * @option field-name
     *      A unique machine-readable name containing letters, numbers, and underscores.
     * @option field-label
     *      The field label
     * @option field-description
     *      The field description
     * @option is-required
     *      Whether the field is required
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush base-field-override:create
     *      Create a base field override by answering the prompts.
     * @usage drush base-field-override:create taxonomy_term tag
     *      Create a base field override and fill in the remaining information through prompts.
     * @usage drush base-field-override:create taxonomy_term tag --field-name=name --field-label=Label --is-required=1
     *      Create a base field override in a completely non-interactive way.
     *
     * @see \Drupal\field_ui\Form\FieldConfigEditForm
     * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
     */
    public function create($entityType, $bundle, $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'field-label' => InputOption::VALUE_REQUIRED,
        'field-description' => InputOption::VALUE_REQUIRED,
        'is-required' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $fieldName = $this->input->getOption('field-name');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldDescription = $this->input->getOption('field-description');
        $isRequired = $this->input->getOption('is-required');

        $baseFieldOverride = $this->createBaseFieldOverride($entityType, $bundle, $fieldName, $fieldLabel, $fieldDescription, $isRequired);

        $this->logResult($baseFieldOverride);
    }

    /** @hook interact base-field-override:create */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        if (!$entityType) {
            return;
        }

        if (!$bundle || !$this->entityTypeBundleExists($entityType, $bundle)) {
            $this->input->setArgument('bundle', $this->askBundle());
        }

        $fieldName = $this->input->getOption('field-name') ?? $this->askFieldName($entityType);
        $this->input->setOption('field-name', $fieldName);

        $definition = BaseFieldOverride::loadByName($entityType, $bundle, $fieldName)
            ?? $this->getBaseFieldDefinition($entityType, $fieldName);

        $this->input->setOption(
            'field-label',
            $this->input->getOption('field-label') ?? $this->askFieldLabel((string) $definition->getLabel())
        );
        $this->input->setOption(
            'field-description',
            $this->input->getOption('field-description') ?? $this->askFieldDescription($definition->getDescription())
        );
        $this->input->setOption(
            'is-required',
            (bool) ($this->input->getOption('is-required') ?? $this->askRequired($definition->isRequired()))
        );
    }

    /** @hook validate base-field-override:create */
    public function validateEntityType(CommandData $commandData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
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

    protected function askFieldName(string $entityType)
    {
        /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
        $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);
        $choices = [];

        foreach ($definitions as $definition) {
            $label = $this->input->getOption('show-machine-names') ? $definition->getName() : $definition->getLabel()->render();
            $choices[$definition->getName()] = $label;
        }

        return $this->choice('Field name', $choices);
    }

    protected function askFieldLabel(string $default)
    {
        return $this->io()->ask('Field label', $default);
    }

    protected function askFieldDescription(?string $default)
    {
        return $this->io()->ask('Field description', $default);
    }

    protected function askRequired(bool $default)
    {
        return $this->io()->askQuestion(new ConfirmationQuestion('Required', $default));
    }

    protected function createBaseFieldOverride(string $entityType, string $bundle, string $fieldName, $fieldLabel, $fieldDescription, bool $isRequired)
    {
        $definition = $this->getBaseFieldDefinition($entityType, $fieldName);
        $override = BaseFieldOverride::loadByName($entityType, $bundle, $fieldName)
            ?? BaseFieldOverride::createFromBaseFieldDefinition($definition, $bundle);

        $override
            ->setLabel($fieldLabel)
            ->setDescription($fieldDescription)
            ->setRequired($isRequired)
            ->save();

        return $override;
    }

    protected function logResult(BaseFieldOverride $baseFieldOverride)
    {
        $this->logger()->success(
            sprintf(
                'Successfully created base field override \'%s\' on %s type with bundle \'%s\'',
                $baseFieldOverride->getName(),
                $baseFieldOverride->getEntityTypeId(),
                $baseFieldOverride->getTargetBundle()
            )
        );
    }

    protected function entityTypeBundleExists(string $entityType, string $bundleName)
    {
        return isset($this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundleName]);
    }

    /** @return BaseFieldDefinition|null */
    protected function getBaseFieldDefinition(string $entityType, string $fieldName)
    {
        /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
        $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);
        return $definitions[$fieldName] ?? null;
    }
}
