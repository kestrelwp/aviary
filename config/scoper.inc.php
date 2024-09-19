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
		// TODO: do we even need this patcher? We're not using Twig in our plugins at the moment {@itambek 2024-05-21}
		function ( string $filePath, string $prefix, string $content ) use ( $config ): string {

			if ( str_contains( $filePath, 'guzzlehttp/guzzle/src/Handler/CurlFactory.php' ) ) {
				$content = str_replace( 'stream_for($sink)', 'Utils::streamFor()', $content );
			}

			if ( str_contains( $filePath, 'php-di/php-di/src/Compiler/Template.php' ) ) {
				$content = str_replace( "namespace $prefix;", '', $content );
			}

			if ( str_contains( $filePath, 'twig/src/Node/ModuleNode.php' ) ) {
				$content = str_replace( 'write("use Twig', 'write("use ' . $prefix . '\\\\Twig', $content );
				$content = str_replace( 'Template;\\n\\n', 'Template;\\n\\n use function ' . $prefix . '\\\\twig_escape_filter; \\n\\n', $content );
			}

			if ( str_contains( $filePath, '/vendor/twig/twig/' ) ) {
				$content = str_replace( "'twig_escape_filter_is_safe'", "'" . $prefix . "\\\\twig_escape_filter_is_safe'", $content );
				$content = str_replace( "'twig_get_attribute(", "'" . $prefix . "\\\\twig_get_attribute(", $content );
				$content = str_replace( " = twig_ensure_traversable(", " = " . $prefix . "\\\\twig_ensure_traversable(", $content );
				$content = preg_replace( '/new TwigFilter\(\s*\'([^\']+)\'\s*,\s*\'(_?twig_[^\']+)\'/m', 'new TwigFilter(\'$1\', \'' . $prefix . '\\\\$2\'', $content );
				$content = preg_replace( '/\\$compiler->raw\(\s*\'(twig_[^(]+)\(/m', '\$compiler->raw(\'' . $prefix . '\\\\$1(', $content );
				$content = str_replace( "'\\\\Twig\\\\", "'\\\\" . $prefix . "\\\\Twig\\\\", $content );
			}

			usort( $config['exclude-classes'], function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			} );

			$count        = 0;
			$searches     = [];
			$replacements = [];

			foreach ( $config['exclude-classes'] as $symbol ) {
				$searches[]     = "\\$prefix\\$symbol";
				$replacements[] = "\\$symbol";

				$searches[]     = "use $prefix\\$symbol";
				$replacements[] = "use $symbol";
			}

			return str_replace( $searches, $replacements, $content, $count );
		},
		new DocblockPatcher( $config ),
	],
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
] ) );
