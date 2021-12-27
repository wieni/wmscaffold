<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmmodel\Factory\ModelFactory;
use Drupal\wmscaffold\Service\Generator\ModelClassGenerator;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\field\EntityTypeBundleAskTrait;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class WmModelCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use RunCommandTrait;
    use EntityTypeBundleAskTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfoInterface */
    protected $entityTypeBundleInfo;
    /** @var ConfigFactoryInterface */
    protected $configFactory;
    /** @var ModelFactory */
    protected $modelFactory;
    /** @var ModelClassGenerator */
    protected $modelClassGenerator;
    /** @var PrettyPrinter */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        ConfigFactoryInterface $configFactory,
        ModelFactory $modelFactory,
        ModelClassGenerator $modelClassGenerator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->configFactory = $configFactory;
        $this->modelFactory = $modelFactory;
        $this->modelClassGenerator = $modelClassGenerator;
        $this->prettyPrinter = new PrettyPrinter();
        $this->fileSystem = new Filesystem();
    }

    /**
     * Generate a wmmodel model
     *
     * @command wmmodel:generate
     * @aliases wmmodel-generate,wmlg
     *
     * @validate-entity-type-argument entityType
     * @validate-optional-bundle-argument entityType bundle
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
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
     *
     * @throws PluginNotFoundException
     * @throws \ReflectionException
     */
    public function generateModel(string $entityType, ?string $bundle = null, array $options = [
        'output-module' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        if (!$bundle) {
            $this->input->setArgument('bundle', $bundle = $this->askBundle());
        }

        $statements = [];
        $definition = $this->entityTypeManager->getDefinition($entityType);
        $existingClassName = $this->modelFactory->getClassName($definition, $bundle);
        $hasExisting = false;

        if ($existingClassName && $existingClassName !== $definition->getClass()) {
            $hasExisting = true;
            $destination = (new \ReflectionClass($existingClassName))->getFileName();

            if (file_exists($destination) && !$this->io()->confirm(sprintf('%s already exists. Append to existing class?', $existingClassName), false)) {
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

        $this->logger()->notice('Formatting model class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success(
            sprintf('Successfully %s model class.', $hasExisting ? 'updated' : 'created')
        );
    }

    /** @hook init wmmodel:generate */
    public function init(): void
    {
        $module = $this->input->getOption('output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.model.output_module');

            $this->input->setOption('output-module', $default);
        }
    }

    /** @hook post-command wmmodel:generate */
    public function formatModel(): void
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

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
}
