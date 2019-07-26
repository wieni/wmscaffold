<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmscaffold\Service\Generator\ModelClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class WmModelCommands extends DrushCommands
{
    use RunCommandTrait;
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ModelClassGenerator */
    protected $modelClassGenerator;
    /** @var \PhpParser\PrettyPrinter\Standard */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ModelClassGenerator $modelClassGenerator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->modelClassGenerator = $modelClassGenerator;
        $this->prettyPrinter = new Standard();
        $this->fileSystem = new Filesystem();
    }

    /**
     * Generates a wmmodel model
     *
     * @command wmmodel:generate
     * @aliases wmmodel-generate,wmlg
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     * @option module
     *      The custom module in which to generate the file
     */
    public function generateModel($entityType, $bundle, $options = [
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
        'output-module' => InputOption::VALUE_REQUIRED,
    ])
    {
        $className = $this->modelClassGenerator->buildClassName($entityType, $bundle, $options['output-module']);
        $destination = $this->modelClassGenerator->buildModelPath($entityType, $bundle, $options['output-module']);
        $hasExistingClass = false;
        $statements = [];

        try {
            new \ReflectionClass($className);
            $hasExistingClass = true;

            if (file_exists($destination) && !$this->io()->confirm("{$className} already exists. Append to existing class?", false)) {
                return;
            }

            $statements[] = $this->modelClassGenerator->generateExisting($entityType, $bundle);
        } catch (\ReflectionException $e) {
            // No existing class, generate a new one
            $statements[] = $this->modelClassGenerator->generateNew($entityType, $bundle, $options['output-module']);
        }

        $output = $this->prettyPrinter->prettyPrintFile($statements);
        $this->fileSystem->remove($destination);
        $this->fileSystem->appendToFile($destination, $output);

        $this->logger()->success(
            sprintf('Successfully %s model class.', $hasExistingClass ? 'updated' : 'created')
        );
    }

    /**
     * @hook interact wmmodel:generate
     */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        if (!$entityType) {
            return;
        }

        if (
            $this->entityTypeHasBundles($entityType)
            && (!$bundle || !$this->entityTypeBundleExists($entityType, $bundle))
        ) {
            $this->input->setArgument('bundle', $this->askBundle());
        }
    }

    /**
     * @hook validate wmmodel:generate
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
     * @hook post-command wmmodel:generate
     */
    public function formatModel($result, CommandData $commandData)
    {
        $entityType = $commandData->input()->getArgument('entityType');
        $bundle = $commandData->input()->getArgument('bundle');
        $module = $commandData->input()->getOption('output-module');
        $destination = $this->modelClassGenerator->buildModelPath($entityType, $bundle, $module);

        $this->logger()->notice('Formatting model class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success('Successfully formatted model class.');
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

    protected function entityTypeBundleExists(string $entityType, string $bundleName): bool
    {
        return isset($this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundleName]);
    }

    protected function entityTypeHasBundles(string $entityType): bool
    {
        return !empty($this->entityTypeBundleInfo->getBundleInfo($entityType));
    }
}
