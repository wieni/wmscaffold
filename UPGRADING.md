# Upgrade Guide

This document describes breaking changes and how to upgrade. For a complete list of changes including minor and patch releases, please refer to the [`CHANGELOG`](CHANGELOG.md).

## 2.0.0
### Changes
#### Classes
- `Drupal\wmscaffold\Service\Generator\ModelClassGenerator` (`wmscaffold.model_class_generator`) was renamed to 
  `Drupal\wmscaffold\Service\Generator\EntityBundleClassGenerator` (`wmscaffold.entity_bundle_class_generator`)
- `Drupal\wmscaffold\Service\Helper\ModelMethodGeneratorHelper` (`wmscaffold.model_method_generator.helper`) was renamed
  to `Drupal\wmscaffold\Service\Helper\EntityBundleClassMethodGeneratorHelper` (`wmscaffold.entity_bundle_class_method_generator.helper`)
- `Drupal\wmscaffold\ModelMethodGeneratorManager` (`plugin.manager.model_method_generator`) was renamed to 
  `Drupal\wmscaffold\EntityBundleClassMethodGeneratorManager` (`plugin.manager.entity_bundle_class_method_generator`)

#### Methods
- `ModelMethodGeneratorHelper::getFieldModelClass` was renamed to `EntityBundleClassMethodGeneratorHelper::getFieldEntityClass`

#### Plugins
- The `ModelMethodGenerator` plugin was renamed to `EntityBundleClassMethodGenerator`, together with the plugin 
  namespace, the base class and the interface.

#### Drush commands
- The `wmmodel:generate` (`wmmodel-generate`, `wmlg`) command was renamed to `entity:bundle-class-generate` (`entity-bundle-class-generate`, `ebcg`)
- The `wmmodel-output-module` option was renamed to `bundle-class-output-module`

#### Hooks
- `hook_wmscaffold_info_alter` was renamed to `hook_wmscaffold_entity_bundle_class_method_generator_alter`

#### Config
- `generators.model` in the `wmscaffold.settings` config was renamed to `generators.bundle_class`

### Instructions
1. Use the bash script in `scripts/update-to-v2.sh` for an
   automatic upgrade of your project. Paths that have to be scanned should be passed as arguments:

```bash
chmod +x ./public/modules/contrib/wmscaffold/scripts/update-to-v2.sh
./public/modules/contrib/wmscaffold/scripts/update-to-v2.sh config/* public/modules/custom/* public/themes/custom/* public/sites/*
```

If you're using macOS, make sure to run this before the script:
```bash
brew install gnu-sed
PATH="$(brew --prefix gnu-sed)/libexec/gnubin:$PATH"
```

3. Apply any changes:

```bash
drush cr
drush cim -y
drush updb -y
```

4. Deploy these changes to all your environments
