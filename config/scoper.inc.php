<?php declare( strict_types=1 );
/**
 * This is the configuration file for PHP-Scoper.
 *
 * @link https://github.com/humbug/php-scoper/blob/main/docs/configuration.md
 */
use Isolated\Symfony\Component\Finder\Finder;
use Kestrel\Aviary\Patcher\DocblockPatcher;

/**
 * Load the base configuration
 *
 * This file is generated at runtime by @see \Kestrel\Aviary\Plugin::createPhpScoperConfig()
 */
$config = require_once realpath( __DIR__ . '/aviary.config.php' );

if ( ! $config ) {
	throw new RuntimeException( 'Could not load Aviary base configuration' );
}

// load project customizations - this script runs inside a tmp directory of the project root,
// so we need to go up two levels to get to the project root
if ( $project_customizations = realpath( __DIR__ . '/../aviary.custom.php' ) ) {
	require_once $project_customizations;
}

// `customize_php_scoper_config` can be defined in the project customizations file customize the config - we'll fall
// back to a no-op function if it's not defined
if ( ! function_exists( 'customize_php_scoper_config' ) ) {
	function customize_php_scoper_config( array $config = [] ): array {
		return $config;
	}
}

$source = $config['source'];

// remove source and destination from the config, as they're not part of php-scoper config
unset( $config['source'], $config['destination'] );

// return the configuration, piping it through any project-specific customizations
return customize_php_scoper_config( array_merge( $config, [
	'finders' => [
		Finder::create()
		      ->files()
		      ->ignoreVCS( true )
		      ->in( $source . DIRECTORY_SEPARATOR . 'vendor' ),
		Finder::create()
		      ->append( [
			      $source . '/composer.json',
			      $source . '/composer.lock'
			  ]),
	],
	'patchers' => [
		new DocblockPatcher( $config ),
	],
	// in WordPress plugins, we don't want to expose global constants, classes, or functions
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
] ) );
