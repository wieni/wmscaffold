<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmscaffold\Service\Generator\ControllerClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;

class WmControllerCommands extends DrushCommands
{
    use RunCommandTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ControllerClassGenerator */
    protected $controllerClassGenerator;
    /** @var \PhpParser\PrettyPrinter\Standard */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ControllerClassGenerator $controllerClassGenerator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->controllerClassGenerator = $controllerClassGenerator;
        $this->prettyPrinter = new Standard();
        $this->fileSystem = new Filesystem();
    }

    /**
     * Generates a wmcontroller controller
     *
     * @command wmcontroller:generate
     * @aliases wmcontroller-generate,wmcg
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     * @option module
     *      The custom module in which to generate the file
     */
    public function generateController($entityType, $bundle, $options = [
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
        'output-module' => InputOption::VALUE_REQUIRED,
    ])
    {
        $className = $this->controllerClassGenerator->buildClassName($entityType, $bundle, $options['output-module']);
        $destination = $this->controllerClassGenerator->buildControllerPath($entityType, $bundle, $options['output-module']);
        $statements = [];

        try {
            new \ReflectionClass($className);

            if (file_exists($destination) && !$this->io()->confirm("{$className} already exists. Replace existing class?", false)) {
                return;
            }
        } catch (\ReflectionException $e) {
            // noop
        }

        $statements[] = $this->controllerClassGenerator->generateNew($entityType, $bundle, $options['output-module']);
        $output = $this->prettyPrinter->prettyPrintFile($statements);
        $this->fileSystem->remove($destination);
        $this->fileSystem->appendToFile($destination, $output);

        $this->logger()->success('Successfully created controller class.');
    }

    /**
     * @hook interact wmcontroller:generate
     */
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
    }

    /**
     * @hook validate wmcontroller:generate
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

    /**
     * @hook post-command wmcontroller:generate
     */
    public function formatController($result, CommandData $commandData)
    {
        $entityType = $commandData->input()->getArgument('entityType');
        $bundle = $commandData->input()->getArgument('bundle');
        $module = $commandData->input()->getOption('output-module');
        $destination = $this->controllerClassGenerator->buildControllerPath($entityType, $bundle, $module);

        $this->logger()->notice('Formatting controller class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success('Successfully formatted controller class.');
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

    protected function entityTypeBundleExists(string $entityType, string $bundleName)
    {
        return isset($this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundleName]);
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
}
