<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

class ParagraphsTypeCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use BundleMachineNameAskTrait;
    use CustomEventAwareTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Create a new paragraph type
     *
     * @command paragraphs:type:create
     * @aliases paragraphs-type-create,ptc
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @option label
     *      The human-readable name of this paragraphs type.
     * @option machine-name
     *      A unique machine-readable name for this paragraphs type. It must only contain
     *      lowercase letters, numbers, and underscores.
     * @option description
     *
     * @usage drush paragraphs-type:create
     *      Create a paragraphs type by answering the prompts.
     *
     * @validate-module-enabled paragraphs
     *
     * @version 11.0
     * @see \Drupal\paragraphs\Form\ParagraphsTypeForm
     */
    public function create(array $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $this->ensureOption('label', [$this, 'askLabel'], true);
        $this->ensureOption('machine-name', [$this, 'askParagraphsTypeMachineName'], true);
        $this->ensureOption('description', [$this, 'askDescription'], false);

        // Command files may set additional options as desired.
        $handlers = $this->getCustomEventHandlers('paragraphs-type-set-options');
        foreach ($handlers as $handler) {
            $handler($this->input);
        }

        $bundle = $this->input()->getOption('machine-name');
        $storage = $this->entityTypeManager->getStorage('paragraphs_type');

        $values = [
            'status' => true,
            'id' => $bundle,
            'name' => $this->input()->getOption('label'),
            'description' => $this->input()->getOption('description') ?? '',
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('paragraphs-type-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $type = $storage->create($values);
        $type->save();

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($type);
    }

    protected function askParagraphsTypeMachineName(): string
    {
        return $this->askMachineName('paragraph');
    }

    protected function askLabel(): string
    {
        return $this->io()->askRequired('Human-readable name');
    }

    protected function askDescription(): ?string
    {
        return $this->io()->ask('Description');
    }

    protected function ensureOption(string $name, callable $asker, bool $required): void
    {
        $value = $this->input->getOption($name);

        if ($value === null) {
            $value = $asker();
        }

        if ($required && $value === null) {
            throw new \InvalidArgumentException(dt('The %optionName option is required.', [
                '%optionName' => $name,
            ]));
        }

        $this->input->setOption($name, $value);
    }

    protected function logResult(ParagraphsType $type): void
    {
        $this->logger()->success(
            sprintf('Successfully created paragraphs type \'%s\'', $type->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . Url::fromRoute('entity.paragraphs_type.edit_form', ['paragraphs_type' => $type->id()])
                ->setAbsolute(true)
                ->toString()
        );
    }
}
