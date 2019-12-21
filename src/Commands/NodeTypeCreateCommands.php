<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\NodeType;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NodeTypeCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use AskBundleMachineNameTrait;
    use AskLanguageDefaultTrait;
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
    /** @var LanguageManagerInterface */
    protected $languageManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        EntityFieldManager $entityFieldManager,
        ModuleHandler $moduleHandler,
        LanguageManagerInterface $languageManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
        $this->moduleHandler = $moduleHandler;
        $this->languageManager = $languageManager;
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
     *      The human-readable name of this content type.
     * @option machine-name
     *      A unique machine-readable name for this content type. It must only contain
     *      lowercase letters, numbers, and underscores.
     * @option description
     *      This text will be displayed on the Add new content page.
     *
     * @option title-label
     *      The label of the title field
     * @option preview-before-submit
     *      Preview before submitting (disabled, optional or required)
     * @option submission-guidelines
     *      Explanation or submission guidelines. This text will be displayed at the top
     *      of the page when creating or editing content of this type.
     *
     * @option status
     *      The default value of the Published field
     * @option promote
     *      The default value of the Promoted to front page field
     * @option sticky
     *      The default value of the Sticky at top of lists field
     * @option create-revision
     *      The default value of the Create new revision field
     *
     * @option default-language
     *      The default language of new nodes
     * @option show-language-selector
     *      Whether to show the language selector on create and edit pages
     *
     * @option display-submitted
     *      Display author username and publish date
     *
     * @usage drush nodetype:create
     *      Create a node type by answering the prompts.
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     * @throws EntityStorageException
     */
    public function create(array $options = [
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
    ]): void
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

    /** @hook interact nodetype:create */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData): void
    {
        $this->input->setOption(
            'label',
            $this->input->getOption('label') ?? $this->askLabel()
        );
        $this->input->setOption(
            'machine-name',
            $this->input->getOption('machine-name') ?? $this->askMachineName('node_type')
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

    protected function askLabel(): string
    {
        return $this->io()->ask('Human-readable name');
    }

    protected function askDescription(): ?string
    {
        return $this->askOptional('Description');
    }

    protected function askSubmissionTitleLabel(): string
    {
        return $this->io()->ask('Title field label', 'Title');
    }

    protected function askSubmissionPreviewMode(): int
    {
        $options = [
            DRUPAL_DISABLED => t('Disabled'),
            DRUPAL_OPTIONAL => t('Optional'),
            DRUPAL_REQUIRED => t('Required'),
        ];

        return $this->choice('Preview before submitting', $options, false, DRUPAL_OPTIONAL);
    }

    protected function askSubmissionHelp(): ?string
    {
        return $this->askOptional('Explanation or submission guidelines');
    }

    protected function askPublished(): bool
    {
        return $this->confirm('Published', true);
    }

    protected function askPromoted(): bool
    {
        return $this->confirm('Promoted to front page', true);
    }

    protected function askSticky(): bool
    {
        return $this->confirm('Sticky at top of lists', false);
    }

    protected function askCreateRevision(): bool
    {
        return $this->confirm('Create new revision', true);
    }

    protected function askDisplaySubmitted(): bool
    {
        return $this->confirm('Display author and date information', true);
    }

    protected function askLanguageShowSelector(): bool
    {
        return $this->confirm('Show language selector on create and edit pages', false);
    }

    private function logResult(NodeType $type): void
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
