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

if (!isset($argv[1])) {
	return;
}

$spectacle = file_get_contents(TEMP_DIR . 'gimle/spectacle/' . $argv[1]);

echo html_entity_decode(strip_tags(json_decode($spectacle, true)['tabs']['spectacle']['content']));

return true;
