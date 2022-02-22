<?php

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the information provided in \Drupal\wmscaffold\Annotation\ModelMethodGenerator.
 *
 * @param array $generators
 *   The array of generator plugins, keyed by the machine-readable name.
 */
function hook_wmscaffold_info_alter(array &$generators) {
    $generators['link']['class'] = \Drupal\wmscaffold\Plugin\ModelMethodGenerator\FieldHelperLink::class;
}

/**
 * @} End of "addtogroup hooks".
 */
