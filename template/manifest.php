<?php
declare(strict_types=1);
namespace gimle;

if (!file_exists(MAIN_SITE_DIR . 'config/manifest.php')) {
	return false;
}

$manifest = [];

$manifest = inc(MAIN_SITE_DIR . 'config/manifest.php', SITE_ID);

if (!isset($manifest['start_url'])) {
	$manifest['start_url'] = BASE_PATH;
}
if (!isset($manifest['display'])) {
	$manifest['display'] = 'standalone';
}
if (!isset($manifest['short_name'])) {
	$manifest['short_name'] = $manifest['name'];
}
if (!isset($manifest['description'])) {
	$manifest['description'] = $manifest['name'];
}

echo preg_replace('/^    |\G    /m', "\t", json_encode($manifest, JSON_PRETTY_PRINT));

return true;
