<?php

namespace Kestrel\Aviary;

use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Plugin implements PluginInterface, EventSubscriberInterface {

	public const AVIARY_INSTALL_CMD        = 'aviary-install-cmd';
	public const AVIARY_INSTALL_NO_DEV_CMD = 'aviary-install-no-dev-cmd';
	public const AVIARY_UPDATE_CMD         = 'aviary-update-cmd';
	public const AVIARY_UPDATE_NO_DEV_CMD  = 'aviary-update-no-dev-cmd';

	protected Composer $composer;

	protected IOInterface $io;

	private string $folder;

	private string $prefix;

	private array $globals;

	private string $composerJsonFile;

	private string $composerLockFile;

	private string $tempDir;

	public static function getSubscribedEvents(): array {
		return [
			ScriptEvents::POST_INSTALL_CMD => 'execute',
			ScriptEvents::POST_UPDATE_CMD  => 'execute',
		];
	}

	public function activate( Composer $composer, IOInterface $io ): void {

		$this->composer = $composer;
		$this->io       = $io;
		$extra          = $composer->getPackage()->getExtra();
		$prefix         = '';

		$config   = [
			'folder'       => $this->path( getcwd(), 'vendor-scoped' ),
			'temp'         => $this->path( getcwd(), 'tmp-' . substr( str_shuffle( md5( microtime() ) ), 0, 10 ) ),
			'prefix'       => $prefix,
			'globals'      => [ 'wordpress', 'woocommerce', 'action-scheduler' ],
			'composerjson' => 'composer-scoped.json',
			'composerlock' => 'composer-scoped.lock',
		];

		if ( ! empty( $extra['aviary']['folder'] ) ) {
			$config['folder'] = $this->path( getcwd(), $extra['aviary']['folder'] );
		}

		if ( ! empty( $extra['aviary']['composerjson'] ) ) {
			$config['composerjson'] = $extra['aviary']['composerjson'];
			$config['composerlock'] = preg_replace( '/\.json$/', '.lock', $extra['aviary']['composerjson'] );
		}

		if ( ! empty( $extra['aviary']['composerlock'] ) ) {
			$config['composerlock'] = $extra['aviary']['composerlock'];
		}

		if ( ! empty( $extra['aviary']['prefix'] ) ) {
			$config['prefix'] = $extra['aviary']['prefix'];
		}

		if ( ! empty( $extra['aviary']['globals'] ) && is_array( $extra['aviary']['globals'] ) ) {
			$config['globals'] = $extra['aviary']['globals'];
		}

		if ( ! empty( $extra['aviary']['temp'] ) ) {
			$config['temp'] = $this->path( getcwd(), $extra['aviary']['temp'] );
		}

		$this->folder           = $config['folder'];
		$this->prefix           = $config['prefix'];
		$this->globals          = $config['globals'];
		$this->tempDir          = $config['temp'];
		$this->composerJsonFile = $config['composerjson'];
		$this->composerLockFile = $config['composerlock'];
	}

	public function deactivate( Composer $composer, IOInterface $io ) {
	}

	public function uninstall( Composer $composer, IOInterface $io ) {
	}

	public function getCapabilities(): array {
		return [
			CommandProvider::class => self::class,
		];
	}

	public function path( ...$parts ): array|string {
		$path = join( DIRECTORY_SEPARATOR, $parts );

		return str_replace( DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path );
	}

	public function execute( Event $event ): void {

		$extra = $event->getComposer()->getPackage()->getExtra();

		// skip on install/update if autorun is disabled
		if (
			isset( $extra['aviary']['autorun'] ) &&
			$extra['aviary']['autorun'] === false &&
			( $event->getName() === ScriptEvents::POST_UPDATE_CMD || $event->getName() === ScriptEvents::POST_INSTALL_CMD )
		) {
			return;
		}

		// skip if no prefix is set
		if ( empty( $this->prefix ) ) {
			return;
		}

		$source           = $this->path( $this->tempDir, 'source' );
		$destination      = $this->path( $this->tempDir, 'destination' );
		$phpScoperConfig  = $this->createPhpScoperConfig( $this->tempDir, $source, $destination );
		$composerJsonPath = $this->path( $source, 'composer.json' );
		$composerLockPath = $this->path( $source, 'composer.lock' );

		if ( file_exists( $this->path( getcwd(), $this->composerJsonFile ) ) ) {
			$composerJson = json_decode( file_get_contents( $this->path( getcwd(), $this->composerJsonFile ) ), false );
		} else {
			$composerJson = (object) [
				'require' => (object) [],
				'scripts' => (object) [],
			];
			$this->createJson( $this->path( getcwd(), $this->composerJsonFile ), $composerJson );
		}

		if ( empty( $composerJson->scripts ) ) {
			$composerJson->scripts = (object) [];
		}

		// copy stubs/aviary-autoload.stub into the temp folder rename to aviary-autoload.php
		copy( $this->path( __DIR__, '..', 'stubs', 'aviary-autoload.stub' ), $this->path( $this->tempDir, 'aviary-autoload.php' ) );

		$postInstall     = file_get_contents( __DIR__ . '/../scripts/postinstall.php' );
		$postInstall     = str_replace( '%%source%%', $source, $postInstall );
		$postInstall     = str_replace( '%%destination%%', $destination, $postInstall );
		$postInstall     = str_replace( '%%cwd%%', getcwd(), $postInstall );
		$postInstall     = str_replace( '%%composer_lock%%', $this->composerLockFile, $postInstall );
		$postInstall     = str_replace( '%%vendor_scoped%%', $this->folder, $postInstall );
		$postInstall     = str_replace( '%%temp%%', $this->tempDir, $postInstall );
		$postInstall     = str_replace( '%%prefix%%', $this->prefix, $postInstall );
		$postInstallPath = $this->path( $this->tempDir, 'postinstall.php' );

		file_put_contents( $postInstallPath, $postInstall );

		$scriptName = $event->getName();

		if ( $event->getName() === self::AVIARY_UPDATE_CMD || $event->getName() === self::AVIARY_UPDATE_NO_DEV_CMD ) {
			$scriptName = ScriptEvents::POST_UPDATE_CMD;
		}

		if ( $event->getName() === self::AVIARY_INSTALL_CMD || $event->getName() === self::AVIARY_INSTALL_NO_DEV_CMD ) {
			$scriptName = ScriptEvents::POST_INSTALL_CMD;
		}

		$phpScoperPath = realpath(__DIR__ . '/../vendor/bin/php-scoper');

		$composerJson->scripts->{$scriptName} = [
			$phpScoperPath . ' add-prefix --output-dir="' . $destination . '" --force --config="' . $phpScoperConfig . '"',
			'composer dump-autoload --working-dir="' . $destination . '" --optimize',
			'php "' . $postInstallPath . '"',
		];

		$this->createJson( $composerJsonPath, $composerJson );

		if ( file_exists( $this->path( getcwd(), $this->composerLockFile ) ) ) {
			copy( $this->path( getcwd(), $this->composerLockFile ), $composerLockPath );
		}

		$command = 'install';

		if (
			$event->getName() === ScriptEvents::POST_UPDATE_CMD ||
			$event->getName() === self::AVIARY_UPDATE_CMD ||
			$event->getName() === self::AVIARY_UPDATE_NO_DEV_CMD
		) {
			$command = 'update';
		}

		$useDevDependencies = true;

		if ( $event->getName() === self::AVIARY_UPDATE_NO_DEV_CMD || $event->getName() === self::AVIARY_INSTALL_NO_DEV_CMD ) {
			$useDevDependencies = false;
		}

		$this->runInstall( $source, $command, $useDevDependencies );
	}

	private function createPhpScoperConfig( string $path, string $source, string $destination ) {

		$inc_path    = $this->createPath( [ 'config', 'scoper.inc.php' ] );
		$config_path = $this->createPath( [ 'config', 'aviary.config.php' ] );
		$custom_path = $this->createPath( [ 'aviary.custom.php' ], true );
		$final_path  = $this->path( $path, 'scoper.inc.php' );
		$symbols_dir = $this->createPath( [ 'symbols' ] );

		$this->createFolder( $path );
		$this->createFolder( $source );
		$this->createFolder( $destination );

		$config = require_once $config_path;

		if ( ! is_array( $config ) ) {
			exit;
		}

		$config['prefix']            = $this->prefix;
		$config['source']            = $source;
		$config['destination']       = $destination;
		$config['exclude-constants'] = [ 'NULL', 'TRUE', 'FALSE' ];

		if ( in_array( 'wordpress', $this->globals ) ) {
			$config = array_merge_recursive(
				$config,
				require $this->path( $symbols_dir, 'wordpress.php' ),
			);
		}

		if ( in_array( 'woocommerce', $this->globals ) ) {
			$config = array_merge_recursive(
				$config,
				require $this->path( $symbols_dir, 'woocommerce.php' ),
			);
		}

		if ( file_exists( $custom_path ) ) {
			copy( $custom_path, $this->path( $path, 'scoper.custom.php' ) );
		}

		copy( $inc_path, $this->path( $path, 'scoper.inc.php' ) );

		file_put_contents( $this->path( $path, 'aviary.config.php' ), '<?php return ' . var_export( $config, true ) . ';' );

		return $final_path;
	}

	private function createPath( array $parts, bool $in_root = false ): string {

		$vendor = strpos( dirname( __DIR__ ), 'vendor' . DIRECTORY_SEPARATOR . 'kestrelwp' . DIRECTORY_SEPARATOR . 'aviary' );

		if ( ! $in_root || ! is_int( $vendor ) ) {
			return dirname( __DIR__ ) . DIRECTORY_SEPARATOR . join( DIRECTORY_SEPARATOR, $parts );
		}

		return getcwd() . DIRECTORY_SEPARATOR . join( DIRECTORY_SEPARATOR, $parts );
	}

	private function createFolder( string $path ): void {
		if ( ! file_exists( $path ) ) {
			mkdir( $path, 0755, true );
		}
	}

	private function createJson( string $path, $content ): void {

		$this->createFolder( dirname( $path ) );
		$json = json_encode( $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		file_put_contents( $path, $json );
	}

	private function runInstall( string $path, string $command = 'install', bool $useDevDependencies = true ): int {

		$output      = new ConsoleOutput();
		$application = new Application();

		return $application->run(
			new ArrayInput(
				[
					'command'               => $command,
					'--working-dir'         => $path,
					'--no-dev'              => ! $useDevDependencies,
					'--optimize-autoloader' => true,
				],
			),
			$output,
		);
	}
}
