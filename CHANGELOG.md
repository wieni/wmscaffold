# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.11.1] - 2021-02-12
### Fixed
- Remove flexible HEREDOC syntax to keep PHP 7.2 compatibility
- `base-field-override-create`: Fix error when description is empty
- Fix issue with link hooks input validation
- Change minimum version of `friendsofphp/php-cs-fixer` to 2.15.4. This is the version where FixCommand::$defaultName is 
  introduced, which is referenced in `PhpCsFixerCommands` (see 
  [commit](https://github.com/FriendsOfPHP/PHP-CS-Fixer/commit/ae86d4f1750720ba46a97baa05654fb63aae6e29))

## [1.11.0] - 2021-02-01
### Added
- Add changelog
- Add code style fixers
- Add issue & pull request templates

### Changed
- `wmmodel-generate`: Rebuild the mapping after generating
- `wmmodel-generate`: Use wmmodel to get the model class
- `wmmodel-generate`: Change entity reference getter return type to
  always be optional
- `wmmodel-generate`: Change boolean getter return type to
  always be a boolean
- `wmmodel-generate`: Add entity type class as fallback for base class
- `field-create`: Add extra validation checking for field name conflicts
- `field-create`: Add custom options for link fields
- Add argument/return types
- Apply code style-related fixes
- Move duplicated code to separate classes
- Update README
- Update .gitignore
- Update module description
- Normalize composer.json
- Allow v2 and v3 of the `composer/semver` package
- Add Composer 2 as dev dependency

### Fixed
- `field-create`: Fix error when content_translation module is not
  installed
- `field-create`: Fix hooks setting options not always being triggered
- `field-info`, `base-field-info`: Fix help screen

## [1.10.0] - 2019-12-12
### Added
- Add a dependency for PHP 7.2
- Document all command options and arguments
  ([#7](https://github.com/wieni/wmscaffold/issues/7))
- `wmmodel-generate`, `wmcontroller-generate`: Add outputModule config
  option to be used as a default for the controller & model generators
  ([#10](https://github.com/wieni/wmscaffold/issues/10))
- `base-field-info`: Add command to list all base fields of an entity
  type ([#9](https://github.com/wieni/wmscaffold/issues/9))
- `field-create`: Add the is-translatable option
  ([#5](https://github.com/wieni/wmscaffold/issues/5))
- `field-create`: Add the field-description option
  ([#4](https://github.com/wieni/wmscaffold/issues/4))

### Changed
- `wmcontroller-generate`: Change the controller generator baseClass
  option to be optional
- `wmcontroller-generate`: Change the controller generator to use
  wmmodel to get the model class
- `wmmodel-generate`, `wmcontroller-generate`: Change the automatic
  controller/model generation to be skipped when the output module
  option is empty
- `field-delete`: Delete the field storage if its last instance is being
  deleted ([#11](https://github.com/wieni/wmscaffold/issues/11))
- `field-create`: Change the is-required option to be optional

### Fixed
- Fix errors in most command help screens
  ([#7](https://github.com/wieni/wmscaffold/issues/7))
- `wmcontroller-generate`: Fix issue in controller generation hook

## [1.9.12] - 2019-12-03
### Fixed
- `phpcs-fix`: Replace reference to an internal constant of a class in
  the [php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer)
  package.

## [1.9.11] - 2019-11-22
### Changed
- Increase drupal/core version constraint to support version 9

## [1.9.10] - 2019-11-13
### Added
- `field-create`: Add patch for Drush 10.0.0

## [1.9.9] - 2019-11-13
### Changed
- Expand Drush version constraint to support version 10

## [1.9.8] - 2019-10-18
### Changed
- Normalize composer.json
- Add drupal/core dependency to composer.json
- Replace usages of third-party deprecated code

### Removed
- Remove drupal-composer packagist from composer.json

## [1.9.7] - 2019-09-16
### Added
- `wmmodel-generate`: Add nullable return types for scalar field getters

### Changed
- `wmmodel-generate`: Clean up code of model method generators
- `wmmodel-generate`: Use array_column in multiple scalar field getters
- `wmmodel-generate`: Don't cast return value of getter if it has a
  return type

### Fixed
- `wmmodel-generate`: Fix issue with scalar field getter generator

## [1.9.6] - 2019-08-01
### Fixed
- `field-create`: Fix missing target-type when using the --existing
  option

## [1.9.5] - 2019-08-01
### Fixed
- `field-create`: Fix issue with entity reference fields

## [1.9.4] - 2019-07-26
### Changed
- `wmmodel-generate`, `wmcontroller-generate`: Use machine name instead
  of label when generating class names
- `field-create`: Entity type validation in @interact hook

## [1.9.3] - 2019-07-26
### Changed
- `field-create`: Improve target_bundles handling of entity reference fields

## [1.9.2] - 2019-07-26
### Changed
- `wmmodel-generate`: Get class name of existing classes from wmmodel

## [1.9.1] - 2019-06-28
### Added
- `wmmodel-generate`: Add support for field type decimal

### Changed
- `wmmodel-generate`: Rebuild the wmmodel class mapping before
  generating models

### Fixed
- `wmmodel-generate`: Fix issue where numbers are stripped from
  generated model class names
- `wmmodel-generate`: Only ask for bundle when entity type has bundles

## [1.9.0] - 2019-03-25
