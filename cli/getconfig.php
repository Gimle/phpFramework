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

require dirname(__DIR__) . '/init.php';

if (isset($argv[1])) {
	define('gimle\\MAIN_SITE_DIR', Config::get('subsite.of')[$argv[1]]);
}
else {
	define('gimle\\MAIN_SITE_DIR', SITE_DIR);
}

$config = parse_config_file(MAIN_SITE_DIR . 'config.ini');
if (get_cfg_var('gimle') !== false) {
	$config = array_merge_distinct(parse_config_file(get_cfg_var('gimle')), $config, true);
}
if (is_readable(MAIN_SITE_DIR . 'config.php')) {
	$config = array_merge_distinct(include MAIN_SITE_DIR . 'config.php', $config, true);
}

if (!isset($config['dir']['storage'])) {
	$config['dir']['storage'] = MAIN_SITE_DIR . 'storage/';
}
if (!isset($config['dir']['static'])) {
	$config['dir']['static'] = MAIN_SITE_DIR . 'static/';
}
define('gimle\\MAIN_STORAGE_DIR', $config['dir']['storage']);
define('gimle\\MAIN_STATIC_DIR', $config['dir']['static']);
MainConfig::setAll($config);
if (is_readable(MAIN_SITE_DIR . 'post.php')) {
	$config = array_merge_distinct($config, include MAIN_SITE_DIR . 'post.php');
}
if (is_readable(MAIN_SITE_DIR . 'post.ini')) {
	$config = array_merge_distinct($config, parse_config_file(MAIN_SITE_DIR . 'post.ini'));
}

echo json_encode($config, JSON_PRETTY_PRINT);

return true;
