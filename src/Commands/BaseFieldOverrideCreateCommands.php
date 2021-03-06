<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class BaseFieldOverrideCreateCommands extends DrushCommands
{
    use AskBundleTrait;
    use QuestionTrait;

    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManager */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeBundleInfo $entityTypeBundleInfo,
        EntityFieldManager $entityFieldManager
    ) {
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * Create a new base field override
     *
     * @command base-field-override:create
     * @aliases base-field-override-create,bfoc
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
    public function create(string $entityType, ?string $bundle = null, array $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'field-label' => InputOption::VALUE_REQUIRED,
        'field-description' => InputOption::VALUE_REQUIRED,
        'is-required' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        if (!$bundle) {
            $this->input->setArgument('bundle', $bundle = $this->askBundle());
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

        $fieldName = $this->input->getOption('field-name');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldDescription = $this->input->getOption('field-description');
        $isRequired = $this->input->getOption('is-required');

        $baseFieldOverride = $this->createBaseFieldOverride($entityType, $bundle, $fieldName, $fieldLabel, $fieldDescription, $isRequired);

        $this->logResult($baseFieldOverride);
    }

    protected function askFieldName(string $entityType): string
    {
        /** @var BaseFieldDefinition[] $definitions */
        $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);
        $choices = [];

        foreach ($definitions as $definition) {
            $label = $this->input->getOption('show-machine-names') ? $definition->getName() : $definition->getLabel()->render();
            $choices[$definition->getName()] = $label;
        }

        return $this->choice('Field name', $choices);
    }

    protected function askFieldLabel(string $default): string
    {
        return $this->io()->ask('Field label', $default);
    }

    protected function askFieldDescription(?string $default): ?string
    {
        return $this->io()->ask('Field description', $default);
    }

    protected function askRequired(bool $default): bool
    {
        return $this->io()->askQuestion(new ConfirmationQuestion('Required', $default));
    }

    protected function createBaseFieldOverride(string $entityType, string $bundle, string $fieldName, $fieldLabel, $fieldDescription, bool $isRequired): BaseFieldOverride
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

    protected function logResult(BaseFieldOverride $baseFieldOverride): void
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

    protected function getBaseFieldDefinition(string $entityType, string $fieldName): ?BaseFieldDefinition
    {
        /** @var BaseFieldDefinition[] $definitions */
        $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        return $definitions[$fieldName] ?? null;
    }
}
