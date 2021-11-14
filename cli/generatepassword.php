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

if ((isset($argv[1])) && (ctype_digit($argv[1])) && ((int) $argv[1] >= 4) && ((int) $argv[1] <= 30)) {
	$cost = (int) $argv[1];
	if ($cost > 12) {
		echo "Warning, cost over 12 may take some time to generate.\n";
	}
	echo "Cost set to {$cost}.\n";
}
else {
	$cost = 12;
	echo "No cost specified. Using default cost of {$cost}.\n";
}

echo 'Enter password (input hidden): ';
$command = '/usr/bin/env bash -c \'IFS= read -r -s password && echo "$password"\'';

$password = substr(shell_exec($command), 0, -1);
echo "\n";

$options = [
	'cost' => $cost,
];
$start = microtime(true);
$hash = password_hash($password, PASSWORD_BCRYPT, $options);
$time = (microtime(true) - $start);

echo "Password hash generated in " . round($time, 4) . " seconds.\n";
echo "The password hash is: {$hash}\n";

exit(0);
