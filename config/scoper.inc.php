<?php declare( strict_types=1 );
/**
 * This is the configuration file for PHP-Scoper.
 */

use Isolated\Symfony\Component\Finder\Finder;
use Kestrel\Aviary\Patcher\DocblockPatcher;

$config = require_once __DIR__ . '/aviary.config.php';
$project_customizations = __DIR__ . '/aviary.custom.php';

if ( file_exists( $project_customizations ) ) {
	require_once $project_customizations;
}

if ( ! function_exists( 'customize_php_scoper_config' ) ) {
	function customize_php_scoper_config( array $config = [] ): array {
		return $config;
	}
}

$prefix      = $config['prefix'];
$source      = $config['source'];
$destination = $config['destination'];

// remove source and destination from the config, as they're not part of php-scoper config
unset( $config['source'], $config['destination'] );

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
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
] ) );
