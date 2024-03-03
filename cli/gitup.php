#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace gimle;

/*
#!/bin/bash

if ! [ -f ./module/gimle/cli/gitup.php ]; then
	echo "Update gimle first."
	exit;
fi

php ./module/gimle/cli/gitup.php
*/

/**
 * The local absolute location of the site.
 *
 * @var string
 */
define('gimle\\SITE_DIR', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);

$cli = [
	'description' => 'Update all submodules tho their set branch.',
	'options' => [],
];

require dirname(__DIR__) . '/init.php';

$exec = 'cd ' . SITE_DIR . '; git pull';
exec($exec);

$gitmodules = parse_config_file(SITE_DIR . '.gitmodules', false);
foreach ($gitmodules as $name => $config) {
	echo colorize($name, 'string') . "\n";
	if (!isset($config['branch'])) {
		echo "{$config['path']} does not have a branch to follow.\n";
		continue;
	}

	$exec = 'cd ' . SITE_DIR . $config['path'] . '; git checkout ' . $config['branch'] . '; git pull';
	passthru($exec);
}
