#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace gimle;

/**
 * The local absolute location of the site.
 *
 * @var string
 */
define('gimle\\SITE_DIR', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);

$cli = [
	'description' => 'Get config for a subsite',
	'options' => [],
];
if (isset($argv[1])) {
	$cli['index'] = 0;
}

require dirname(__DIR__) . '/init.php';

if (!MainConfig::exists('dir.storage')) {
	MainConfig::set('dir.storage', MAIN_STORAGE_DIR);
}
if (!MainConfig::exists('dir.static')) {
	MainConfig::set('dir.storage', MAIN_STATIC_DIR);
}
if (!MainConfig::exists('dir.temp')) {
	MainConfig::set('dir.temp', MAIN_STATIC_DIR);
}
if (!MainConfig::exists('dir.cache')) {
	MainConfig::set('dir.cache', MAIN_STATIC_DIR);
}

echo json_encode(MainConfig::get(), JSON_PRETTY_PRINT);

return true;
