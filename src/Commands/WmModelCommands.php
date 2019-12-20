<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmmodel\Factory\ModelFactory;
use Drupal\wmscaffold\Service\Generator\ModelClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class WmModelCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use RunCommandTrait;
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ConfigFactoryInterface */
    protected $configFactory;
    /** @var ModelFactory */
    protected $modelFactory;
    /** @var ModelClassGenerator */
    protected $modelClassGenerator;
    /** @var \PhpParser\PrettyPrinter\Standard */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ConfigFactoryInterface $configFactory,
        ModelFactory $modelFactory,
        ModelClassGenerator $modelClassGenerator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->configFactory = $configFactory;
        $this->modelFactory = $modelFactory;
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
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     * @param array $options
     *
     * @option module
     *      The module in which to generate the file
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush wmmodel-generate taxonomy_term tag
     *      Generate a model.
     * @usage drush wmmodel:generate
     *      Generate a model and fill in the remaining information through prompts.
     */
    public function generateModel($entityType, $bundle, $options = [
        'output-module' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ])
    {
        $statements = [];
        $definition = $this->entityTypeManager->getDefinition($entityType);
        $existingClassName = $this->modelFactory->getClassName($definition, $bundle);

        if ($existingClassName && $existingClassName !== $definition->getClass()) {
            $destination = (new \ReflectionClass($existingClassName))->getFileName();

            if (file_exists($destination) && !$this->io()->confirm("{$existingClassName} already exists. Append to existing class?", false)) {
                return;
            }

            $statements[] = $this->modelClassGenerator->generateExisting($entityType, $bundle);
        } else {
            $destination = $this->modelClassGenerator->buildModelPath($entityType, $bundle, $options['output-module']);
            $statements[] = $this->modelClassGenerator->generateNew($entityType, $bundle, $options['output-module']);
        }

        $output = $this->prettyPrinter->prettyPrintFile($statements);
        $this->fileSystem->remove($destination);
        $this->fileSystem->appendToFile($destination, $output);

        $this->modelFactory->rebuildMapping();

        $this->logger()->success(
            sprintf('Successfully %s model class.', $existingClassName ? 'updated' : 'created')
        );
    }

    /** @hook interact wmmodel:generate */
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

    /** @hook init wmmodel:generate */
    public function init(InputInterface $input, AnnotationData $annotationData)
    {
        $module = $this->input->getOption('output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.model.outputModule');

            $this->input->setOption('output-module', $default);
        }
    }

    /** @hook validate wmmodel:generate */
    public function validateEntityType(CommandData $commandData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }
    }

    /** @hook post-command wmmodel:generate */
    public function formatModel($result, CommandData $commandData)
    {
        $entityType = $commandData->input()->getArgument('entityType');
        $bundle = $commandData->input()->getArgument('bundle');

        $definition = $this->entityTypeManager->getDefinition($entityType);
        $existingClassName = $this->modelFactory->getClassName($definition, $bundle);

        if ($existingClassName === $definition->getClass()) {
            return;
        }

        $destination = (new \ReflectionClass($existingClassName))->getFileName();

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
