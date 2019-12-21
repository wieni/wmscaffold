<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmscaffold\Service\Generator\ControllerClassGenerator;
use Drush\Commands\DrushCommands;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class WmControllerCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use AskBundleTrait;
    use RunCommandTrait;
    use QuestionTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ConfigFactoryInterface */
    protected $configFactory;
    /** @var ControllerClassGenerator */
    protected $controllerClassGenerator;
    /** @var PrettyPrinter */
    protected $prettyPrinter;
    /** @var Filesystem */
    protected $fileSystem;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ConfigFactoryInterface $configFactory,
        ControllerClassGenerator $controllerClassGenerator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->configFactory = $configFactory;
        $this->controllerClassGenerator = $controllerClassGenerator;
        $this->prettyPrinter = new PrettyPrinter();
        $this->fileSystem = new Filesystem();
    }

    /**
     * Generates a wmcontroller controller
     *
     * @command wmcontroller:generate
     * @aliases wmcontroller-generate,wmcg
     *
     * @validate-entity-type-argument entityType
     * @validate-bundle-argument entityType bundle
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option output-module
     *      The module in which to generate the file
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush wmcontroller-generate taxonomy_term tag
     *      Generate a controller.
     * @usage drush wmcontroller:generate
     *      Generate a controller and fill in the remaining information through prompts.
     */
    public function generateController(string $entityType, ?string $bundle = null, array $options = [
        'output-module' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        if (!$bundle) {
            $this->input->setArgument('bundle', $this->askBundle());
        }

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

    /** @hook init wmcontroller:generate */
    public function init(): void
    {
        $module = $this->input->getOption('output-module');

        if (!$module) {
            $default = $this->configFactory
                ->get('wmscaffold.settings')
                ->get('generators.controller.outputModule');

            $this->input->setOption('output-module', $default);
        }
    }

    /** @hook post-command wmcontroller:generate */
    public function formatController(): void
    {
        $entityType = $this->input->getArgument('entityType');
        $bundle = $this->input->getArgument('bundle');
        $module = $this->input->getOption('output-module');
        $destination = $this->controllerClassGenerator->buildControllerPath($entityType, $bundle, $module);

        $this->logger()->notice('Formatting controller class...');
        $this->drush('phpcs:fix', [], ['path' => $destination]);

        $this->logger()->success('Successfully formatted controller class.');
    }
}
