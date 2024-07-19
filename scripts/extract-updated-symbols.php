<?php

/**
 * This script will only extract symbols if a package version has changed.
 *
 * It's meant to run as a post-update-cmd script in composer.json.
 */

$originalLockPath = __DIR__ . '/../composer.lock.bak'; // see composer.json / pre-update-cmd
$updatedLockPath = __DIR__ . '/../composer.lock';

// Check if lock files exist
if (!file_exists($originalLockPath) || !file_exists($updatedLockPath)) {
	exit('One or both lock files do not exist.');
}

$originalLock = json_decode(file_get_contents($originalLockPath), true);
$updatedLock = json_decode(file_get_contents($updatedLockPath), true);

$packagesToCheck = [
	'johnpbloch/wordpress',
	'wpackagist-plugin/woocommerce'
];

// find the versions of all `$packagesToCheck` in both lock files
$originalVersions = [];

foreach ($originalLock['packages-dev'] as $package) {
	if (in_array($package['name'], $packagesToCheck)) {
		$originalVersions[$package['name']] = $package['version'];
	}
}

$updatedVersions = [];

foreach ($updatedLock['packages-dev'] as $package) {
	if (in_array($package['name'], $packagesToCheck)) {
		$updatedVersions[$package['name']] = $package['version'];
	}
}

$shouldExtract = false;

// Check if the versions of the packages have changed
foreach ($updatedVersions as $name => $version) {
	if (isset($originalVersions[$name]) && $originalVersions[$name] !== $version) {
		$shouldExtract = true;
		break; // Exit the loop as we only need one change to proceed
	}
}

// Additionally, check if there are differences in the number of packages
if (!$shouldExtract && count($originalVersions) !== count($updatedVersions)) {
	$shouldExtract = true;
}


if ($shouldExtract) {
	require_once __DIR__ . '/extract-symbols.php';
}

// Cleanup
unlink(__DIR__ . '/../composer.lock.bak');