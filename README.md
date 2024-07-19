# Aviary - A PHP scoper for WordPress plugins and themes

Aviary is a Composer plugin that scopes (isolates) your dependencies for WordPress plugins and themes.
Under the hood, it uses the latest version of [PHP Scoper](https://github.com/humbug/php-scoper). The implementation is based on  [WPify Scoper](https://github.com/wpify/scoper), which we modified to fit the Kestrel workflow.

## Why Aviary?

### The problem

Using Composer in a WordPress plugin or theme has traditionally been challenging, because of the potential dependency conflicts with
other plugins or themes. Unlike JS / node modules, PHP / composer does not have a built-in mechanism to isolate (or scope) dependencies.

For example, given 2 plugins that both require `illuminate/support` in composer.json, but different versions - PHP will loadteh version for the plugin that was loaded first and ignore the other. If there are breaking changes between the two versions, it can lead to a broken site. 

### The (general) solution

A solution to this problem is to prefix namespaces of dependencies. For example:
* Original namespace: `Illuminate\Support\Carbon`
* Namespace in plugin 1: `MyPluginDeps\Illuminate\Support\Carbon`
* Namespace in plugin 2: `MyOtherPluginDeps\Illuminate\Support\Carbon`

This way, both plugins may require a totally different version of `illuminate/support` without any conflicts and PHP will load both dependencies.

There are a few tools which help do this, such as:
* [PHP Scoper](https://github.com/humbug/php-scoper)
* [Mozart](https://github.com/coenjacobs/mozart) (no longer maintained)
* [PHP Prefixer](https://php-prefixer.com/)

### The problem with existing solutions

We tried all of them, but none worked for us out-of the box. PHP Scoper came closest to what we needed, but it was designed to be used as a build-step tool. This means that it was meant to scope the code before it is deployed, not during development.

We wanted a workflow where we could scope dependencies _during_ development, so that we could work on multiple plugins locally without getting into conflicts. It would also serve to ensure we catch any scoping issues (which do occur sometimes) locally, not after deploying the plugin.

Another issue with PHP Scoper is that it also scopes global functions, constants and classes. Usually, that is what you
want, but that also means that WordPress functions, classes and constants will be scoped. Since WordPress is a global dependency shared across all plugins and themes, it should be left unscoped. The same goes for WooCommerce and any other global requirements.

### How Aviary solves the problem

Aviary uses PHP Scoper under the hood, but it solves the above problems:
* It scopes dependencies as they are installed or updated (during development).
* It has a database of almost all [WordPress and WooCommerce symbols](symbols) that we want to keep unscoped.
* It also supports requiring non-scoped dev dependencies

## Requirements

`PHP >= 8.2`

## Installation

Until/if we can package this, and until we tag a release, you will
need to edit your project `composer.json` as follows:

```json
{
   "require": {
      "kestrelwp/aviary": "dev-main"
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

Then, run `composer install`.

## Usage

Aviary requires 2 composer files:
* `composer.json` - this is the standard composer file that you use for your project. All basic package configuration should live here, including the Aviary configuration. Dependencies that should not be scoped (such as dev deps) should be here.
* `composer-scoped.json` - this is the file that contains the dependencies that you want to scope. 

### Getting started

1. Add `extra.aviary.prefix` to `composer.json` to specify the namespace that will be prefixed to the dependencies:
    
    ```json
    {
        "extra": {
            "aviary": {
                "prefix": "MyPrefixForDependencies"
            }
        }
    }
    ```
2. Create `composer-scoped.json` with the dependencies that you want to scope, and your platform requirements. For example:

    ```json
    {
        "config": {
            "platform": {
                "php": "7.4" // this will hint composer to install versions compatible with the specified PHP version
            }
        },
        "require": {
            "guzzlehttp/guzzle": "^7.0"
        }
    }
    ```
3. Run `composer install` or `composer update` to install/update the dependencies and prefix them.
4. In your main plugin file, require the `aviary-autoload.php` file from the `vendor-scoped` folder (there's no need to include any other autoloaders):

    ```php
    require_once __DIR__ . '/vendor-scoped/aviary-autoload.php';
    ```
5. Use the prefixed dependencies in your code:

    ```php
   use MyPrefixForDependencies\GuzzleHttp\Client;
   ```

### Running manually

To invoke prefixing manually, you need to add `"aviary": "aviary"` to the `"scripts"` section of your `composer.json`.
You then run the script with the command `composer aviary install` or `composer aviary update`.

### Caveats

At the moment, it's not possible to install scoped deps via the CLI using `composer require`. You need to add them to `composer-scoped.json` and run `composer install` or `composer update`.

## Configuration

Aviary is configured with sensible defaults, but you can customize it by adding the following to your `composer.json`:

```json
{
    "extra": {
        "aviary": {
            "prefix": "MyPrefixForDependencies",
            // The folder where the dependencies will be installed
            "folder": "vendor-scoped",
            // List of global dependencies that should not be scoped (for now, only WordPress and WooCommerce are supported)
            "globals": [
                "wordpress",
                "woocommerce"
            ],
           // The name of the composer.json file that contains the dependencies that should be scoped
            "composerjson": "composer-scoped.json",
            "composerlock": "composer-scoped.lock",
           // Whether to run scoping automatically on composer install/update
            "autorun": true
        }
    }
}
```

### Platform requirements and platform check.

It's possible to require different PHP platforms in `composer.json` and `composer-scoped.json`. For example, Aviary itself requires PHP 8.2, but many WordPress plugins may need to support PHP 7.4. In this case, it's totally valid to configure the platform value in `composer.json` and `composer-scoped.json` separately.

Composer has a built-in platform check that will produce a fatal error if the site is loaded on an unsupported PHP version.
This is usually not what you want on production websites, as it will prevent the whole site from loading if even one of the plugins requires a higher PHP version.

In order to disable the platform check, you can add the following to **both** your `composer.json` and `composer-scoped.json`:


```json
{
    "config": {
        "platform-check": false
    }
}

```

**Note: it is important to disable the platform check for both**, because aviary needs to load both the scoped and non-scoped autoloaders. If the `composer.json`  requires PHP 8.2, but the `composer-scoped.json` requires PHP 7.4, and platform check is _not_ disabled in `composer.json`, a fatal error will be thrown when the site is loaded.


It may also be a good idea to disable composer's platform check. This would allow the site to load on an unsupported PHP version,but you'd be responsible in checking the  

### Advanced configuration

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

## Development

### Updating the symbols database

The symbols database is a list of all WordPress and WooCommerce classes, functions and constants that should not be scoped. It is used to generate the PHP Scoper configuration file. The database is stored in the `symbols` folder and is generated by running the `composer extract` script.

Note that in order to keep the database up-to-date, we should keep WordPress and WooCommerce dependencies up-to-date in the `composer.json` file.

> TODO: we should automate this process and auto-generate a new Aviary release when the database is updated.

## Deployment

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
            vendor-scoped/
            vendor/
```