<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\NodeType;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NodeTypeCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var ModuleHandler */
    protected $moduleHandler;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        EntityFieldManager $entityFieldManager,
        ModuleHandler $moduleHandler
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * Create a new node type
     *
     * @command nodetype:create
     * @aliases nodetype-create,ntc
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @option label
     * @option machine-name
     * @option description
     *
     * @option title-label
     * @option preview-before-submit
     * @option submission-guidelines
     *
     * @option status
     * @option promote
     * @option sticky
     * @option create-revision
     *
     * @option default-language
     * @option show-language-selector
     *
     * @option display-submitted
     *
     * @usage drush nodetype:create
     *      Create a node type by answering the prompts.
     */
    public function create($options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'title-label' => InputOption::VALUE_OPTIONAL,
        'preview-before-submit' => InputOption::VALUE_OPTIONAL,
        'submission-guidelines' => InputOption::VALUE_OPTIONAL,
        'status' => InputOption::VALUE_OPTIONAL,
        'promote' => InputOption::VALUE_OPTIONAL,
        'sticky' => InputOption::VALUE_OPTIONAL,
        'create-revision' => InputOption::VALUE_OPTIONAL,
        'default-language' => InputOption::VALUE_OPTIONAL,
        'show-language-selector' => InputOption::VALUE_OPTIONAL,
        'display-submitted' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $bundle = $this->input()->getOption('machine-name');
        $definition = $this->entityTypeManager->getDefinition('node');
        $storage = $this->entityTypeManager->getStorage('node_type');

        $values = [
            $definition->getKey('status') => true,
            $definition->getKey('bundle') => $bundle,
            'name' => $this->input()->getOption('label'),
            'description' => $this->input()->getOption('description') ?? '',
            'new_revision' => $this->input()->getOption('create-revision'),
            'help' => $this->input()->getOption('submission-guidelines') ?? '',
            'preview_mode' => $this->input()->getOption('preview-before-submit'),
            'display_submitted' => $this->input()->getOption('display-submitted'),
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('nodetype-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $type = $storage->create($values);
        $type->save();

        // Update language options
        if ($this->moduleHandler->moduleExists('language')) {
            $values['langcode'] = $this->input()->getOption('default-language');

            $config = ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);
            $config->setDefaultLangcode($this->input()->getOption('default-language'))
                ->setLanguageAlterable((bool) $this->input()->getOption('show-language-selector'))
                ->save();
        }

        // Update title field definition.
        $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
        $titleField = $fields['title'];
        $titleLabel = $this->input()->getOption('title-label');

        if ($titleLabel && $titleLabel !== $titleField->getLabel()) {
            $titleField->getConfig($bundle)
                ->setLabel($titleLabel)
                ->save();
        }

        // Update workflow options
        foreach (['status', 'promote', 'sticky'] as $fieldName) {
            $node = $this->entityTypeManager->getStorage('node')->create(['type' => $bundle]);
            $value = (bool) $this->input()->getOption($fieldName);

            if ($node->get($fieldName)->value != $value) {
                $fields[$fieldName]
                    ->getConfig($bundle)
                    ->setDefaultValue($value)
                    ->save();
            }
        }

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($type);
    }

    /**
     * @hook interact nodetype:create
     */
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
        $this->input->setOption(
            'description',
            $this->input->getOption('description') ?? $this->askDescription()
        );

        // Submission form settings
        $this->input->setOption(
            'title-label',
            $this->input->getOption('title-label') ?? $this->askSubmissionTitleLabel()
        );
        $this->input->setOption(
            'preview-before-submit',
            $this->input->getOption('preview-before-submit') ?? $this->askSubmissionPreviewMode()
        );
        $this->input->setOption(
            'submission-guidelines',
            $this->input->getOption('submission-guidelines') ?? $this->askSubmissionHelp()
        );

        // Publishing options
        $this->input->setOption(
            'status',
            $this->input->getOption('status') ?? $this->askPublished()
        );
        $this->input->setOption(
            'promote',
            $this->input->getOption('promote') ?? $this->askPromoted()
        );
        $this->input->setOption(
            'sticky',
            $this->input->getOption('sticky') ?? $this->askSticky()
        );
        $this->input->setOption(
            'create-revision',
            $this->input->getOption('create-revision') ?? $this->askCreateRevision()
        );

        // Language settings
        if ($this->moduleHandler->moduleExists('language')) {
            $this->input->setOption(
                'default-language',
                $this->input->getOption('default-language') ?? $this->askLanguageDefault()
            );
            $this->input->setOption(
                'show-language-selector',
                $this->input->getOption('show-language-selector') ?? $this->askLanguageShowSelector()
            );
        }

        // Display settings
        $this->input->setOption(
            'display-submitted',
            $this->input->getOption('display-submitted') ?? $this->askDisplaySubmitted()
        );
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
                $this->logger()->error('Field name must not be longer than :maxLength characters.', [':maxLength' => EntityTypeInterface::BUNDLE_MAX_LENGTH]);
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

    protected function askSubmissionTitleLabel()
    {
        return $this->io()->ask('Title field label', 'Title');
    }

    protected function askSubmissionPreviewMode()
    {
        $options = [
            DRUPAL_DISABLED => t('Disabled'),
            DRUPAL_OPTIONAL => t('Optional'),
            DRUPAL_REQUIRED => t('Required'),
        ];

        return $this->choice('Preview before submitting', $options, false, DRUPAL_OPTIONAL);
    }

    protected function askSubmissionHelp()
    {
        return $this->askOptional('Explanation or submission guidelines');
    }

    protected function askPublished()
    {
        return $this->confirm('Published', true);
    }

    protected function askPromoted()
    {
        return $this->confirm('Promoted to front page', true);
    }

    protected function askSticky()
    {
        return $this->confirm('Sticky at top of lists', false);
    }

    protected function askCreateRevision()
    {
        return $this->confirm('Create new revision', true);
    }

    protected function askDisplaySubmitted()
    {
        return $this->confirm('Display author and date information', true);
    }

    protected function askLanguageDefault()
    {
        $options = [
            LanguageInterface::LANGCODE_SITE_DEFAULT => t("Site's default language (@language)", ['@language' => \Drupal::languageManager()->getDefaultLanguage()->getName()]),
            'current_interface' => t('Interface text language selected for page'),
            'authors_default' => t("Author's preferred language"),
        ];

        $languages = \Drupal::languageManager()->getLanguages(LanguageInterface::STATE_ALL);
        foreach ($languages as $langcode => $language) {
            $options[$langcode] = $language->isLocked()
                ? t('- @name -', ['@name' => $language->getName()])
                : $language->getName();
        }

        return $this->choice('Default language', $options, false, 0);
    }

    protected function askLanguageShowSelector()
    {
        return $this->confirm('Show language selector on create and edit pages', false);
    }

    protected function bundleExists(string $id)
    {
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo('node_type');

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

    private function logResult(NodeType $type)
    {
        $this->logger()->success(
            sprintf('Successfully created node type with bundle \'%s\'', $type->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . Url::fromRoute('entity.node_type.edit_form', ['node_type' => $type->id()])
                ->setAbsolute(true)
                ->toString()
        );
    }
}
