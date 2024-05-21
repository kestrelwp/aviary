<?php
/**
 * This is the base configuration file for Aviary.
 */

return [
	'prefix' => 'MyNamespaceForDeps',
	'source' => getcwd() . '/vendor-source/',
	'destination' => getcwd() . '/vendor-scoped/',
];
