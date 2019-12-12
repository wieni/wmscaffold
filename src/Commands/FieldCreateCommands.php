<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class FieldCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use QuestionTrait;

    /** @var FieldTypePluginManager */
    protected $fieldTypePluginManager;
    /** @var WidgetPluginManager */
    protected $widgetPluginManager;
    /** @var SelectionPluginManager */
    protected $selectionPluginManager;
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var ModuleHandler */
    protected $moduleHandler;
    /** @var ContentTranslationManagerInterface */
    protected $contentTranslationManager;

    public function __construct(
        FieldTypePluginManager $fieldTypePluginManager,
        WidgetPluginManager $widgetPluginManager,
        SelectionPluginManager $selectionPluginManager,
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ModuleHandler $moduleHandler,
        EntityFieldManager $entityFieldManager
    ) {
        $this->fieldTypePluginManager = $fieldTypePluginManager;
        $this->widgetPluginManager = $widgetPluginManager;
        $this->selectionPluginManager = $selectionPluginManager;
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->moduleHandler = $moduleHandler;
        $this->entityFieldManager = $entityFieldManager;
    }

    public function setContentTranslationManager(ContentTranslationManagerInterface $manager): void
    {
        $this->contentTranslationManager = $manager;
    }

    /**
     * Create a new field
     *
     * @command field:create
     * @aliases field-create,fc
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
     *      Instructions to present to the user below this field on the editing form.
     * @option field-type
     *      The field type
     * @option field-widget
     *      The field widget
     * @option is-required
     *      Whether the field is required
     * @option is-translatable
     *      Whether the field is translatable
     * @option cardinality
     *      The allowed number of values
     * @option target-type
     *      The target entity type. Only necessary for entity reference fields.
     * @option target-bundle
     *      The target bundle(s). Only necessary for entity reference fields.
     *
     * @option existing
     *      Re-use an existing field.
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush field:create
     *      Create a field by answering the prompts.
     * @usage drush field-create taxonomy_term tag
     *      Create a field and fill in the remaining information through prompts.
     * @usage drush field-create taxonomy_term tag --field-name=field_tag_label --field-label=Label --field-type=string --field-widget=string_textfield --is-required=1 --cardinality=2
     *      Create a field in a completely non-interactive way.
     *
     * @see \Drupal\field_ui\Form\FieldConfigEditForm
     * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
     */
    public function create($entityType, $bundle, $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'field-label' => InputOption::VALUE_REQUIRED,
        'field-description' => InputOption::VALUE_OPTIONAL,
        'field-type' => InputOption::VALUE_REQUIRED,
        'field-widget' => InputOption::VALUE_REQUIRED,
        'is-required' => InputOption::VALUE_OPTIONAL,
        'is-translatable' => InputOption::VALUE_OPTIONAL,
        'cardinality' => InputOption::VALUE_REQUIRED,
        'target-type' => InputOption::VALUE_OPTIONAL,
        'target-bundle' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
        'existing' => false,
    ])
    {
        $fieldName = $this->input->getOption('field-name');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldDescription = $this->input->getOption('field-description');
        $fieldType = $this->input->getOption('field-type');
        $fieldWidget = $this->input->getOption('field-widget');
        $isRequired = $this->input->getOption('is-required');
        $isTranslatable = (bool) $this->input->getOption('is-translatable');
        $cardinality = $this->input->getOption('cardinality');
        $targetType = $this->input->getOption('target-type');

        if (!$options['existing']) {
            $this->createFieldStorage($fieldName, $fieldType, $entityType, $targetType, $cardinality);
        }

        $field = $this->createField($fieldName, $fieldType, $fieldLabel, $fieldDescription, $entityType, $bundle, $targetType, $isRequired, $isTranslatable);
        $this->createFieldFormDisplay($fieldName, $fieldWidget, $entityType, $bundle);
        $this->createFieldViewDisplay($fieldName, $entityType, $bundle);

        $this->logResult($field);
    }

    /**
     * @hook interact field:create
     */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }

        if (!$bundle || !$this->entityTypeBundleExists($entityType, $bundle)) {
            $bundle = $this->askBundle();
            $this->input->setArgument('bundle', $bundle);
        }

        if ($this->input->getOption('existing')) {
            $fieldName = $this->input->getOption('field-name') ?? $this->askExisting();
            $fieldStorage = $this->entityFieldManager->getFieldStorageDefinitions($entityType)[$fieldName];

            $this->input->setOption('field-name', $fieldName);
            $this->input->setOption('field-type', $fieldStorage->getType());
            $this->input->setOption('target-type', $fieldStorage->getSetting('target_type'));

            if (
                $this->moduleHandler->moduleExists('content_translation')
                && $this->contentTranslationManager->isEnabled($entityType, $bundle)
            ) {
                $this->input->setOption(
                    'is-translatable',
                    (bool) ($this->input->getOption('is-translatable') ?? $this->askTranslatable())
                );
            }

            $this->input->setOption(
                'field-label',
                $this->input->getOption('field-label') ?? $this->askFieldLabel()
            );
            $this->input->setOption(
                'field-description',
                $this->input->getOption('field-description') ?? $this->askFieldDescription()
            );
            $this->input->setOption(
                'is-required',
                $this->input->getOption('is-required') ?? $this->askRequired()
            );

            /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $formDisplay */
            $formDisplay = $this->getEntityDisplay('form', $entityType, $bundle);

            if (!$formDisplay || $this->input->getOption('field-widget')) {
                return;
            }

            $component = $formDisplay->getComponent($this->input->getOption('field-name'));
            $this->input->setOption('field-widget', $component['type']);
        } else {
            $this->input->setOption(
                'field-label',
                $this->input->getOption('field-label') ?? $this->askFieldLabel()
            );
            $this->input->setOption(
                'field-name',
                $this->input->getOption('field-name') ?? $this->askFieldName()
            );
            $this->input->setOption(
                'field-description',
                $this->input->getOption('field-description') ?? $this->askFieldDescription()
            );
            $this->input->setOption(
                'field-type',
                $this->input->getOption('field-type') ?? $this->askFieldType()
            );
            $this->input->setOption(
                'field-widget',
                $this->input->getOption('field-widget') ?? $this->askFieldWidget()
            );
            $this->input->setOption(
                'is-required',
                (bool) ($this->input->getOption('is-required') ?? $this->askRequired())
            );

            if (
                $this->moduleHandler->moduleExists('content_translation')
                && $this->contentTranslationManager->isEnabled($entityType, $bundle)
            ) {
                $this->input->setOption(
                    'is-translatable',
                    (bool) ($this->input->getOption('is-translatable') ?? $this->askTranslatable())
                );
            }

            $this->input->setOption(
                'cardinality',
                $this->input->getOption('cardinality') ?? $this->askCardinality()
            );

            if (
                $this->input->getOption('field-type') === 'entity_reference'
                && !$this->input->getOption('target-type')
            ) {
                $this->input->setOption('target-type', $this->askReferencedEntityType());
            }
        }
    }

    /**
     * @hook validate field:create
     */
    public function validateEntityType(CommandData $commandData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }
    }

    protected function askExisting()
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $choices = $this->getExistingFieldStorageOptions($entityType, $bundle);
        return $this->choice('Choose an existing field', $choices);
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

    protected function askFieldName()
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldName = null;
        $machineName = null;

        if (!empty($fieldLabel)) {
            $machineName = $this->generateFieldName($fieldLabel, $bundle);
        }

        while (!$fieldName) {
            $answer = $this->io()->ask('Field name', $machineName);

            if (!preg_match('/^[_a-z]+[_a-z0-9]*$/', $answer)) {
                $this->logger()->error('Only lowercase alphanumeric characters and underscores are allowed, and only lowercase letters and underscore are allowed as the first character.');
                continue;
            }

            if (strlen($answer) > 32) {
                $this->logger()->error('Field name must not be longer than 32 characters.');
                continue;
            }

            if ($this->fieldStorageExists($answer, $entityType)) {
                $this->logger()->error('A field with this name already exists.');
                continue;
            }

            $fieldName = $answer;
        }

        return $fieldName;
    }

    protected function askFieldLabel()
    {
        return $this->io()->ask('Field label');
    }

    protected function askFieldDescription()
    {
        return $this->askOptional('Field description');
    }

    protected function askFieldType()
    {
        $definitions = $this->fieldTypePluginManager->getDefinitions();
        $choices = [];

        foreach ($definitions as $definition) {
            $label = $this->input->getOption('show-machine-names') ? $definition['id'] : $definition['label']->render();
            $choices[$definition['id']] = $label;
        }

        return $this->choice('Field type', $choices);
    }

    protected function askFieldWidget()
    {
        $choices = [];
        $fieldType = $this->input->getOption('field-type');
        $widgets = $this->widgetPluginManager->getOptions($fieldType);

        foreach ($widgets as $name => $label) {
            $label = $this->input->getOption('show-machine-names') ? $name : $label->render();
            $choices[$name] = $label;
        }

        return $this->choice('Field widget', $choices, false, 0);
    }

    protected function askRequired()
    {
        return $this->io()->askQuestion(new ConfirmationQuestion('Required', false));
    }

    protected function askTranslatable()
    {
        return $this->io()->askQuestion(new ConfirmationQuestion('Translatable', false));
    }

    protected function askCardinality()
    {
        $fieldType = $this->input->getOption('field-type');
        $definition = $this->fieldTypePluginManager->getDefinition($fieldType);

        // Some field types choose to enforce a fixed cardinality.
        if (isset($definition['cardinality'])) {
            return $definition['cardinality'];
        }

        $choices = ['Limited', 'Unlimited'];
        $cardinality = $this->choice(
            'Allowed number of values',
            array_combine($choices, $choices),
            false,
            0
        );

        $limit = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
        while ($cardinality === 'Limited' && $limit < 1) {
            $limit = $this->io()->ask('Allowed number of values', 1);
        }

        return (int) $limit;
    }

    protected function askReferencedEntityType()
    {
        $definitions = $this->entityTypeManager->getDefinitions();
        $choices = [];

        /** @var \Drupal\Core\Config\Entity\ConfigEntityType $definition */
        foreach ($definitions as $name => $definition) {
            $label = $this->input->getOption('show-machine-names')
                ? $name
                : sprintf('%s: %s', $definition->getGroupLabel()->render(), $definition->getLabel());
            $choices[$name] = $label;
        }

        return $this->choice('Referenced entity type', $choices);
    }

    protected function askReferencedBundles(FieldDefinitionInterface $fieldDefinition)
    {
        $choices = [];
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo(
            $fieldDefinition->getFieldStorageDefinition()->getSetting('target_type')
        );

        if (empty($bundleInfo)) {
            return null;
        }

        foreach ($bundleInfo as $bundle => $info) {
            $label = $this->input->getOption('show-machine-names') ? $bundle : $info['label'];
            $choices[$bundle] = $label;
        }

        return $this->choice('Referenced bundles', $choices, true, 0);
    }

    protected function createField(string $fieldName, string $fieldType, $fieldLabel, ?string $fieldDescription, string $entityType, string $bundle, $targetType, bool $isRequired, bool $isTranslatable)
    {
        $values = [
            'field_name' => $fieldName,
            'entity_type' => $entityType,
            'bundle' => $bundle,
            'translatable' => $isTranslatable,
            'required' => $isRequired,
            'field_type' => $fieldType,
            'description' => $fieldDescription,
        ];

        if (!empty($fieldLabel)) {
            $values['label'] = $fieldLabel;
        }

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-field-config');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        /** @var FieldConfig $field */
        $field = $this->entityTypeManager
            ->getStorage('field_config')
            ->create($values);

        if ($fieldType === 'entity_reference') {
            $targetTypeDefinition = $this->entityTypeManager->getDefinition($targetType);
            // For the 'target_bundles' setting, a NULL value is equivalent to "allow
            // entities from any bundle to be referenced" and an empty array value is
            // equivalent to "no entities from any bundle can be referenced".
            $targetBundles = null;

            if ($targetTypeDefinition->hasKey('bundle')) {
                if ($referencedBundle = $this->input->getOption('target-bundle')) {
                    $referencedBundles = [$referencedBundle];
                } else {
                    $referencedBundles = $this->askReferencedBundles($field);
                }

                if (!empty($referencedBundles)) {
                    $targetBundles = array_combine($referencedBundles, $referencedBundles);
                }
            }

            $settings = $field->getSetting('handler_settings') ?? [];
            $settings['target_bundles'] = $targetBundles;
            $field->setSetting('handler_settings', $settings);
        }

        $field->save();

        return $field;
    }

    protected function createFieldStorage(string $fieldName, string $fieldType, string $entityType, $targetType, int $cardinality)
    {
        $values = [
            'field_name' => $fieldName,
            'entity_type' => $entityType,
            'type' => $fieldType,
            'cardinality' => $cardinality,
            'translatable' => true,
        ];

        if ($targetType) {
            $values['settings']['target_type'] = $targetType;
        }

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-field-storage');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        /** @var FieldStorageConfigInterface $fieldStorage */
        $fieldStorage = $this->entityTypeManager
            ->getStorage('field_storage_config')
            ->create($values);

        $fieldStorage->save();

        return $fieldStorage;
    }

    protected function createFieldFormDisplay(string $fieldName, $fieldWidget, string $entityType, string $bundle)
    {
        $values = [];

        if ($fieldWidget) {
            $values['type'] = $fieldWidget;
        }

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-form-display');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $storage = $this->getEntityDisplay('form', $entityType, $bundle);

        if (!$storage instanceof EntityDisplayInterface) {
            $this->logger()->info(
                sprintf('Form display storage not found for %s type \'%s\', creating now.', $entityType, $bundle)
            );

            $storage = $this->createEntityDisplay('form', $entityType, $bundle);
        }

        $storage->setComponent($fieldName, $values)->save();
    }

    protected function createFieldViewDisplay(string $fieldName, string $entityType, string $bundle)
    {
        $values = [];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('field-create-view-display');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $storage = $this->getEntityDisplay('view', $entityType, $bundle);

        if (!$storage instanceof EntityDisplayInterface) {
            $this->logger()->info(
                sprintf('View display storage not found for %s type \'%s\', creating now.', $entityType, $bundle)
            );

            $storage = $this->createEntityDisplay('view', $entityType, $bundle);
        }

        $storage->setComponent($fieldName, $values)->save();
    }

    /**
     * Load an entity display object.
     *
     * @param string $context
     *      eg. form, view
     * @param string $entityType
     * @param string $bundle
     *
     * @return EntityDisplayInterface|null
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    protected function getEntityDisplay(string $context, string $entityType, string $bundle)
    {
        return $this->entityTypeManager
            ->getStorage(sprintf('entity_%s_display', $context))
            ->load("$entityType.$bundle.default");
    }

    /**
     * Create and save a new entity display object.
     *
     * @param string $context
     *      eg. form, view
     * @param string $entityType
     * @param string $bundle
     *
     * @return \Drupal\Core\Entity\EntityInterface
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    protected function createEntityDisplay(string $context, string $entityType, string $bundle)
    {
        $storageValues = [
            'id' => "$entityType.$bundle.default",
            'targetEntityType' => $entityType,
            'bundle' => $bundle,
            'mode' => 'default',
            'status' => true,
        ];

        $display = $this->entityTypeManager
            ->getStorage(sprintf('entity_%s_display', $context))
            ->create($storageValues);

        $display->save();

        return $display;
    }

    protected function logResult(FieldConfig $field)
    {
        $this->logger()->success(
            sprintf(
                'Successfully created field \'%s\' on %s type with bundle \'%s\'',
                $field->get('field_name'),
                $field->get('entity_type'),
                $field->get('bundle')
            )
        );

        /** @var EntityTypeInterface $entityType */
        $entityType = $this->entityTypeManager->getDefinition($field->get('entity_type'));

        $routeName = "entity.field_config.{$entityType->id()}_field_edit_form";
        $routeParams = [
            'field_config' => $field->id(),
            $entityType->getBundleEntityType() => $field->get('bundle'),
        ];

        if ($this->moduleHandler->moduleExists('field_ui')) {
            $this->logger()->success(
                'Further customisation can be done at the following url:'
                . PHP_EOL
                . Url::fromRoute($routeName, $routeParams)
                    ->setAbsolute(true)
                    ->toString()
            );
        }
    }

    protected function generateFieldName(string $source, string $bundle)
    {
        // Only lowercase alphanumeric characters and underscores
        $machineName = preg_replace('/[^_a-z0-9]/i', '_', $source);
        // Only lowercase letters and underscores as the first character
        $machineName = preg_replace('/^[^_a-z]/i', '_', $machineName);
        // Maximum one subsequent underscore
        $machineName = preg_replace('/_+/', '_', $machineName);
        // Only lowercase
        $machineName = strtolower($machineName);
        // Add the prefix
        $machineName = sprintf('field_%s_%s', $bundle, $machineName);
        // Maximum 32 characters
        $machineName = substr($machineName, 0, 32);

        return $machineName;
    }

    protected function fieldStorageExists(string $fieldName, string $entityType)
    {
        $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($entityType);

        return isset($fieldStorageDefinitions[$fieldName]);
    }

    protected function entityTypeBundleExists(string $entityType, string $bundleName)
    {
        return isset($this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundleName]);
    }

    protected function getExistingFieldStorageOptions(string $entityType, string $bundle)
    {
        $fieldTypes = $this->fieldTypePluginManager->getDefinitions();
        $options = [];

        foreach ($this->entityFieldManager->getFieldStorageDefinitions($entityType) as $fieldName => $fieldStorage) {
            // Do not show:
            // - non-configurable field storages,
            // - locked field storages,
            // - field storages that should not be added via user interface,
            // - field storages that already have a field in the bundle.
            $fieldType = $fieldStorage->getType();
            $label = $this->input->getOption('show-machine-names')
                ? $fieldTypes[$fieldType]['id']
                : $fieldTypes[$fieldType]['label'];

            if (
                $fieldStorage instanceof FieldStorageConfigInterface
                && !$fieldStorage->isLocked()
                && empty($fieldTypes[$fieldType]['no_ui'])
                && !in_array($bundle, $fieldStorage->getBundles(), true)
            ) {
                $options[$fieldName] = sprintf('%s (%s)', $fieldName, $label);
            }
        }

        asort($options);

        return $options;
    }
}
