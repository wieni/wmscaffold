<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmscaffold\Service\Generator\EntityBundleClassGenerator;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\field\EntityTypeBundleAskTrait;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class EntityBundleClassCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use RunCommandTrait;
    use EntityTypeBundleAskTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfoInterface */
    protected $entityTypeBundleInfo;
    /** @var ConfigFactoryInterface */
    protected $configFactory;
    /** @var EntityBundleClassGenerator */
    protected $entityBundleClassGenerator;
    /** @var PrettyPrinter */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        ConfigFactoryInterface $configFactory,
        EntityBundleClassGenerator $entityBundleClassGenerator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->configFactory = $configFactory;
        $this->entityBundleClassGenerator = $entityBundleClassGenerator;
        $this->prettyPrinter = new PrettyPrinter();
        $this->fileSystem = new Filesystem();
    }

    /**
     * Generate an entity bundle class
     *
     * @command entity:bundle-class-generate
     * @aliases entity-bundle-class-generate,ebcg
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
     * @usage drush entity-bundle-class-generate taxonomy_term tag
     *      Generate an entity bundle class.
     * @usage drush entity:bundle-class-generate
     *      Generate an entity bundle class and fill in the remaining information through prompts.
     *
     * @throws PluginNotFoundException
     * @throws \ReflectionException
     */
    public function generate(string $entityType, ?string $bundle = null, array $options = [
        'output-module' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        if (empty($options['output-module'])) {
            throw new \InvalidArgumentException('You must specify an output module through --output-module or through configuration.');
        }

        if (!$bundle) {
            $this->input->setArgument('bundle', $bundle = $this->askBundle());
        }

        $statements = [];
        $definition = $this->entityTypeManager->getDefinition($entityType);
        $existingClassName = $this->entityTypeManager->getStorage($entityType)->getEntityClass($bundle);
        $hasExisting = false;

        if ($existingClassName && $existingClassName !== $definition->getClass()) {
            $hasExisting = true;
            $destination = (new \ReflectionClass($existingClassName))->getFileName();

            if (file_exists($destination) && !$this->io()->confirm(sprintf('%s already exists. Append to existing class?', $existingClassName), false)) {
                return;
            }

            $statements[] = $this->entityBundleClassGenerator->generateExisting($entityType, $bundle);
        } else {
            $destination = $this->entityBundleClassGenerator->buildEntityBundleClassPath($entityType, $bundle, $options['output-module']);
            $statements[] = $this->entityBundleClassGenerator->generateNew($entityType, $bundle, $options['output-module']);
        }

        $output = $this->prettyPrinter->prettyPrintFile($statements);
        $this->fileSystem->remove($destination);
        $this->fileSystem->appendToFile($destination, $output);

        $this->logger()->notice('Formatting entity bundle class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success(
            sprintf('Successfully %s entity bundle class.', $hasExisting ? 'updated' : 'created')
        );
    }

    /** @hook init entity:bundle-class-generate */
    public function init(): void
    {
        $module = $this->input->getOption('output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.bundle_class.output_module');

            $this->input->setOption('output-module', $default);
        }
    }

    /** @hook post-command entity:bundle-class-generate */
    public function formatClass(): void
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');

        $definition = $this->entityTypeManager->getDefinition($entityType);
        $existingClassName = $this->entityTypeManager->getStorage($entityType)->getEntityClass($bundle);

        if ($existingClassName === $definition->getClass()) {
            return;
        }

        $destination = (new \ReflectionClass($existingClassName))->getFileName();

        $this->logger()->notice('Formatting entity bundle class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success('Successfully formatted entity bundle class.');
    }
}
