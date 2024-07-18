<?php

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;

require_once __DIR__ . '/../vendor/autoload.php';

function get_parser(): Parser {
	static $parser;

	return $parser ??= ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );
}

function resolve( Node $node ): array {

	// Special handling for WC constants, as these are defined using a method call, instead of calling `\define` directly
	if ( $node instanceof Node\Stmt\Class_ && $node->name->name === 'WooCommerce' ) {
		$result = ['exclude-classes' => [$node->name->name]];

		foreach ( $node->stmts as $stmt ) {
			if ( $stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === 'define_constants' ) {

				$constants = [];

				foreach( $stmt->stmts as $subStmt ) {
					if ( $subStmt instanceof Node\Stmt\Expression && $subStmt->expr instanceof Node\Expr\MethodCall && $subStmt->expr->name->name === 'define' ) {
						$constants[] = $subStmt->expr->args[0]->value->value;
					}
				}

				$result['exclude-constants'] = $constants;

				break;
			}
		}

		return $result;
	}

	return match (true) {

		$node instanceof Node\Stmt\Namespace_ =>
			['exclude-namespaces' => join('\\', $node->name->getParts())],

		$node instanceof Node\Stmt\Class_,
		$node instanceof Node\Stmt\Trait_,
		$node instanceof Node\Stmt\Interface_ =>
			['exclude-classes' => [$node->name->name]],

		$node instanceof Node\Stmt\Function_ =>
			['exclude-functions' => [$node->name->name]],

		$node instanceof Node\Stmt\If_ =>
			resolve_if_node($node),

		$node instanceof Node\Stmt\Expression &&
		$node->expr instanceof Node\Expr\FuncCall &&
		in_array('define', $node->expr->name->getParts()) =>
			['exclude-constants' => [$node->expr->args[0]->value->value]],

		default => []
	};
}

function resolve_if_node( Node\Stmt\If_ $node ): array {

	$symbols = [];

	foreach ( $node->stmts as $subNode ) {
		foreach ( resolve( $subNode ) as $key => $result ) {
			$symbols[ $key ] = array_merge( $symbols[ $key ] ?? [], $result );
		}
	}

	return $symbols;
}

function get_files( string $folder ): array {

	if ( ! file_exists( $folder = realpath( $folder ) ) ) {
		return [];
	}

	$found = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $folder ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	$files  = [];

	foreach ( $found as $file ) {
		$real_path       = $file->getRealPath();
		$normalized_path = str_replace( realpath( __DIR__ . '/../sources' ) . '/', '', $real_path );

		if ( preg_match( "/\/vendor\//i", $normalized_path ) || preg_match( "/\/wp-content\//i", $normalized_path ) ) {
			continue;
		}

		if ( preg_match( "/\.php$/i", $real_path ) ) {
			$files[] = $real_path;
		}
	}

	return $files;
}

function extract_symbols( string $where, string $result ): void {

	$symbols = [];

	foreach ( get_files( $where ) as $file ) {
		try {
			$ast = get_parser()->parse( file_get_contents( $file ) );

			foreach ( $ast as $node ) {
				$symbols = array_merge_recursive( $symbols, resolve( $node ) );
			}
		} catch ( Error $error ) {
			echo "Parse error: {$error->getMessage()} in {$file}" . PHP_EOL;
		}
	}

	$count = 0;

	foreach ( $symbols as $exclusion => $values ) {
		$symbols[ $exclusion ] = array_unique( $values );
		$count                += count( $values );
	}

	$content = join( [
		"<?php return " . var_export( $symbols, true ) . ';',
	] );

	file_put_contents( $result, $content );

	echo ">>> " . $count . " symbols exported to " . $result . PHP_EOL;
}

extract_symbols( __DIR__ . '/../sources/wordpress', realpath( __DIR__ . '/../symbols' ) . '/wordpress.php' );
extract_symbols( __DIR__ . '/../sources/plugin-woocommerce', realpath( __DIR__ . '/../symbols' ) . '/woocommerce.php' );
