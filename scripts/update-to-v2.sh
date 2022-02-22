#!/usr/bin/env bash

find "$@" -type f -print0 | xargs -0 sed -i -r \
  -e 's/ModelClassGenerator/EntityBundleClassGenerator/g' \
  -e 's/model_class_generator/entity_bundle_class_generator/g' \
  -e 's/ModelMethodGeneratorHelper/EntityBundleClassMethodGeneratorHelper/g' \
  -e 's/ModelMethodGenerator/EntityBundleClassMethodGenerator/g' \
  -e 's/model_method_generator/entity_bundle_class_method_generator/g' \
  -e 's/getFieldModelClass/getFieldEntityClass/g' \
  -e 's/wmmodel:generate/entity:bundle-class-generate/g' \
  -e 's/wmmodel-generate/entity-bundle-class-generate/g' \
  -e 's/wmlg/ebcg/g' \
  -e 's/wmmodel-output-module/bundle-class-output-module/g' \
  -e 's/_wmscaffold_info_alter/_wmscaffold_entity_bundle_class_method_generator_alter/g'

find "$@" -type f -iname 'wmscaffold.settings.yml' -print0 | xargs -0 sed -i -r \
  -e 's/model:/bundle_class:/g'

find "$@" -type d -iname 'ModelMethodGenerator' -execdir rename 's/ModelMethodGenerator/EntityBundleClassMethodGenerator/' '{}' \; 2>/dev/null
