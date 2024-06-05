# Aviary - A PHP scoper for WordPress plugins and themes

### Based on [WPify Scoper](https://github.com/wpify/scoper) - modified to fit the Kestrel workflow.

Using Composer in your WordPress plugin or theme can benefit from that. But it also comes with a danger of conflicts
with dependencies of other plugins or themes. Luckily, a great tool
called [PHP Scoper](https://github.com/humbug/php-scoper) adds all your needed dependencies to your namespace to prevent
conflicts. Unfortunately, the configuration is non-trivial, and for that reason, we created the Composer plugin to make
scoping easy in WordPress projects.

The main issue with PHP Scoper is that it also scopes global functions, constants and classes. Usually, that is what you
want, but that also means that WordPress functions, classes and constants will be scoped. This Composer plugin solves
that. It has an up-to-date database of all WordPress and WooCommerce symbols that we want to keep unscoped.

## Requirements

`PHP >= 8.1`

## Installation

At the moment, `aviary` should be installed globally. Until/if we can package this, and until we tag a release, you will
need to edit your global `/Users/{yourname}/.composer/composer.json` as follows:

```json
{
   "require": {
      "kestrelwp/aviary": "dev-main",
   },
   "config": {
      "allow-plugins": {
         "kestrelwp/aviary": true
      }
   },
   "repositories": [
      {
         "type": "vcs",
         "url": "git@github.com:kestrelwp/aviary.git"
      }
   ]
}
```

**Updating** aviary to latest version is then as simple as:

```bash
composer global update kestrelwp/aviary
```

## Usage

1. This composer plugin is meant to be installed globally, to avoid PHP & dependency version conflicts between this tool and your plugin dependencies.
2. The configuration requires creating `composer-prefixed.json` file, that has exactly same structure like `composer.json`
   file, but serves only for scoped dependencies. Dependencies that you don't want to scope comes to `composer.json`.
3. Add `extra.aviary.prefix` to you `composer.json`, where you can specify the namespace, where your dependencies
   will be in. All other config options (`folder`, `globals`, `composerjson`, `composerlock`, `autorun`) are optional.
4. After each `composer install` or `composer update`, all the dependencies specified in `composer-prefixed.json` will be
   scoped for you.
5. Add a `config.platform` option in your `composer.json` and `composer-prefixed.json`. This settings will make sure that the
   dependencies will be installed with the correct PHP version.

**Example of `composer.json` with its default values**

```json
{
  "config": {
    "platform": {
      "php": "7.4"
    }
  },
  "scripts": {
    "aviary": "aviary"
  },
  "extra": {
    "aviary": {
      "prefix": "MyPrefixForDependencies",
      "folder": "vendor-prefixed",
      "globals": [
        "wordpress",
        "woocommerce"
      ],
      "composerjson": "composer-prefixed.json",
      "composerlock": "composer-prefixed.lock",
      "autorun": true
    }
  }
}
```

6. Option `autorun` defaults to `true` so that scoping is run automatically upon composer `update` or `install` command.
   That is not what you want in all cases, so you can set it `false` if you need.
   To start prefixing manually, you need to add for example the line `"aviary": "aviary"` to the `"scripts"` section of your `composer.json`. 
   You then run the script with the command `composer aviary install` or `composer aviary update`.

7. Scoped dependencies will be in `vendor-prefixed` folder of your project. There's no need to include multiple autoloaders - `aviary` provides a single `aviary-autload.php` file which loads all the required autoload files for both prefixed and non-prefixed dependencies.

8. After that, you can simply use dependencies with the prefixed namespace in your code.

**Example PHP file:**

```php
<?php
require_once __DIR__ . '/vendor-prefixed/aviary-autoload.php';

new \MyPrefixForDependencies\Example\Dependency();
```

## Deployment

### Deployment with Gitlab CI

To use Aviary with Gitlab CI, you can add the following job to your `.gitlab-ci.yml` file:

```yaml
composer:
  stage: .pre
  image: composer:2
  artifacts:
    paths:
      - $CI_PROJECT_DIR/vendor-prefixed
      - $CI_PROJECT_DIR/vendor
    expire_in: 1 week
  script:
    - PATH=$(composer global config bin-dir --absolute --quiet):$PATH
    - composer global config --no-plugins allow-plugins.kestrelwp/aviary true
    - composer global require kestrelwp/aviary
    - composer install --prefer-dist --optimize-autoloader --no-ansi --no-interaction --no-dev
```

### Deployment with GitHub Actions

To use Aviary with GitHub Actions, you can add the following action:

```yaml
name: Build vendor

jobs:
  install:
    runs-on: ubuntu-20.04

    steps:
      - name: Checkout
        uses: actions/checkout@v2

      - name: Cache Composer dependencies
        uses: actions/cache@v2
        with:
          path: /tmp/composer-cache
          key: ${{ runner.os }}-${{ hashFiles('**/composer.lock') }}

      - name: Install composer
        uses: php-actions/composer@v6
        with:
          php_extensions: json
          version: 2
          dev: no
      - run: composer global config --no-plugins allow-plugins.kestrelwp/aviary true
      - run: composer global require kestrelwp/aviary
      - run: sudo chown -R $USER:$USER $GITHUB_WORKSPACE/vendor
      - run: composer install --no-dev --optimize-autoloader

      - name: Archive plugin artifacts
        uses: actions/upload-artifact@v2
        with:
          name: vendor
          path: |
            vendor-prefixed/
            vendor/
```

## Advanced configuration

PHP Scoper has plenty
of [configuration options](https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#configuration). You
can modify this configuration array by creating `aviary.custom.php` file in root of your project. The file should
contain `customize_php_scoper_config` function, where the first parameter is the preconfigured configuration array. Expected output is
valid [PHP Scoper configuration array](https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#configuration).

**Example `aviary.custom.php` file**

```php
<?php

function customize_php_scoper_config( array $config ): array {
    $config['patchers'][] = function( string $filePath, string $prefix, string $content ): string {
        if ( strpos( $filePath, 'guzzlehttp/guzzle/src/Handler/CurlFactory.php' ) !== false ) {
            $content = str_replace( 'stream_for($sink)', 'Utils::streamFor()', $content );
        }
        
        return $content;
    };
    
    return $config;
}
```
