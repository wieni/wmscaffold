<?php

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the information provided in \Drupal\wmscaffold\Annotation\EntityBundleClassMethodGenerator.
 *
 * @param array $generators
 *   The array of generator plugins, keyed by the machine-readable name.
 */
function hook_wmscaffold_entity_bundle_class_method_generator_alter(array &$generators) {
    $generators['link']['class'] = \Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator\FieldHelperLink::class;
}

/**
 * @} End of "addtogroup hooks".
 */
