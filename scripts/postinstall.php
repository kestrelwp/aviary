<?php
function remove( string $src ): void {
	if (!file_exists($src)) {
		return;
	}

	if (is_file($src)) {
		unlink($src);
		return;
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($files as $file_info) {
		$action = ( $file_info->isDir() ? 'rmdir' : 'unlink' );
		$action( $file_info->getRealPath() );
	}

	rmdir($src);
}

function path( ...$parts ): string {

	return join( DIRECTORY_SEPARATOR, $parts );
}

// define variables - these placeholders are replaced in Plugin.php with actual values from the config
$source        = '%%source%%';
$destination   = '%%destination%%';
$cwd           = '%%cwd%%';
$composer_lock = '%%composer_lock%%';
$vendor_scoped = '%%vendor_scoped%%';
$temp          = '%%temp%%';
$prefix        = strtolower( preg_replace( "/[[a-zA-Z0-9]+]/", '', '%%prefix%%' ) );

// fix static files autoloader
$autoload_static_path = path( $destination, 'vendor', 'composer', 'autoload_static.php' );
$autoload_static      = file_get_contents( $autoload_static_path );
$autoload_static      = preg_replace(
	"/'([[:alnum:]]+)'\s*=>\s*([a-zA-Z0-9 .'\"\/\-_]+),/",
	"'" . $prefix . "\\1' => \\2,",
	$autoload_static
);

file_put_contents( $autoload_static_path, $autoload_static );

// copy composer.lock
remove( path( $cwd, $composer_lock ) );
copy( path( $destination, 'composer.lock' ), path( $cwd, $composer_lock ) );

// copy vendor-prefixed folder
remove( $vendor_scoped );
rename( path( $destination, 'vendor' ), $vendor_scoped );

// copy aviary-autoload.php into vendor-prefixed folder
copy( path( __DIR__, 'aviary-autoload.php' ), path( $vendor_scoped, 'aviary-autoload.php' ) );

// remove temp folder
remove( $temp );
