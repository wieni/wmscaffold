<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Vocabulary;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class VocabularyCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ModuleHandler */
    protected $moduleHandler;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ModuleHandler $moduleHandler
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * Create a new vocabulary
     *
     * @command vocabulary:create
     * @aliases vocabulary-create,vc
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @option label
     * @option machine-name
     * @option description
     *
     * @option default-language
     * @option show-language-selector
     *
     * @usage drush vocabulary:create
     *      Create a taxonomy vocabulary by answering the prompts.
     */
    public function create($options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'default-language' => InputOption::VALUE_OPTIONAL,
        'show-language-selector' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $bundle = $this->input()->getOption('machine-name');
        $definition = $this->entityTypeManager->getDefinition('taxonomy_term');
        $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');

        $values = [
            $definition->getKey('bundle') => $bundle,
            'status' => true,
            'name' => $this->input()->getOption('label'),
            'description' => $this->input()->getOption('description') ?? '',
            'hierarchy' => 0,
            'weight' => 0,
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('vocabulary-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $type = $storage->create($values);
        $type->save();

        // Update language options
        if ($this->moduleHandler->moduleExists('language')) {
            $values['langcode'] = $this->input()->getOption('default-language');

            $config = ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_vocabulary', $bundle);
            $config->setDefaultLangcode($this->input()->getOption('default-language'))
                ->setLanguageAlterable((bool) $this->input()->getOption('show-language-selector'))
                ->save();
        }

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($type);
    }

    /**
     * @hook interact vocabulary:create
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
        $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo('taxonomy_vocabulary');

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

    /**
     * @param string $question
     * @param array $choices
     *   If an associative array is passed, the chosen *key* is returned.
     * @param bool $multiSelect
     * @param null $default
     * @return mixed
     */
    protected function choice($question, array $choices, $multiSelect = false, $default = null)
    {
        $choicesValues = array_values($choices);
        $question = new ChoiceQuestion($question, $choicesValues, $default);
        $question->setMultiselect($multiSelect);
        $return = $this->io()->askQuestion($question);

        if ($multiSelect) {
            return array_map(
                function ($value) use ($choices) {
                    return array_search($value, $choices);
                },
                $return
            );
        }

        return array_search($return, $choices);
    }

    protected function confirm($question, $default = false)
    {
        return $this->io()->askQuestion(
            new ConfirmationQuestion($question, $default)
        );
    }

    protected function askOptional($question)
    {
        return $this->io()->ask($question, null, function () {});
    }

    private function logResult(Vocabulary $type)
    {
        $this->logger()->success(
            sprintf('Successfully created vocabulary with bundle \'%s\'', $type->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $type->id()])
                ->setAbsolute(true)
                ->toString()
        );
    }
}
