<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

class FieldMetaCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use RunCommandTrait;

    /**
     * Create a new instance of field_meta
     *
     * @command field:meta
     * @aliases field-meta,fm
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     * @param array $options
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush field:meta node page
     *      Create an instance of field_meta on the page content type
     */
    public function create($entityType, $bundle = null, $options = ['show-machine-names' => InputOption::VALUE_OPTIONAL])
    {
        $arguments = compact('entityType', 'bundle');
        $options = [
            'existing' => true,
            'field-name' => 'field_meta',
            'field-label' => 'Meta',
            'field-widget' => 'inline_entity_form_simple',
            'is-required' => 1,
            'target-type' => 'meta',
            'target-bundle' => 'meta',
        ];

        $this->drush('field:create', $options, $arguments);
    }
}
