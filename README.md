wmscaffold
======================

[![Latest Stable Version](https://poser.pugx.org/wieni/wmscaffold/v/stable)](https://packagist.org/packages/wieni/wmscaffold)
[![Total Downloads](https://poser.pugx.org/wieni/wmscaffold/downloads)](https://packagist.org/packages/wieni/wmscaffold)
[![License](https://poser.pugx.org/wieni/wmscaffold/license)](https://packagist.org/packages/wieni/wmscaffold)

> Provides Drupal services and Drush 9 commands for easy creation of fields & entity types, code style fixing, model & controller generating and more.

## Why?
- Managing entity types, bundles & fields by clicking through the
  interface is inefficient, time consuming and not developer friendly.
- Creating entity models & controllers is equally repetitive and time
  consuming

## Installation

This package requires PHP 7.2, Drupal 8 or higher. The Drush commands
require version 9 or higher. The package can be installed using
Composer:

```bash
 composer require wieni/wmscaffold
```

## How does it work?
### Commands
This package provides a whole range of Drush commands for managing
entity types, bundles & fields and for generating code.

For more information about command aliases, arguments, options & usage
examples, call the command with the `-h` / `--help` argument

#### Coding standards
- `phpcs:fix`: Fixes PHP coding standards using the
  [friendsofphp/php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer)
  package.
  
#### node module
- `nodetype:create`: Create a new node type

#### taxonomy module
- `vocabulary:create`: Create a new vocabulary

#### [eck](https://www.drupal.org/project/eck) module
- `eck:bundle:create`: Create a new eck entity type
- `eck:bundle:delete`: Delete an eck entity type
- `eck:type:create`: Delete an eck entity type

#### [wmcontroller](https://github.com/wieni/wmcontroller) module
- `wmcontroller:generate`: Generate a wmcontroller controller

#### [wmmeta](https://github.com/wieni/wmmeta) module
- `field:meta`: Create a new instance of field_meta

#### [wmmodel](https://github.com/wieni/wmmodel) module
- `wmmodel:generate`: Generate a wmcontroller controller

### Code generator
This package provides Drupal services & Drush commands/hooks that can be
used to generate entity models and controllers for the wmmodel and
wmcontroller modules.

Controllers are generated with a single _show_ method, having the entity
injected as an argument and rendering a template following our naming
conventions. The template itself is not (yet) generated.

Models are generated with field getters. The content of the
getters is based on the field type and can be customized through
`ModelMethodGenerator` plugins. Out of the box, implementations for all
common field types are provided.

## Changelog
All notable changes to this project will be documented in the
[CHANGELOG](CHANGELOG.md) file.

## Security
If you discover any security-related issues, please email
[security@wieni.be](mailto:security@wieni.be) instead of using the issue
tracker.

## License
Distributed under the MIT License. See the [LICENSE](LICENSE.md) file
for more information.
