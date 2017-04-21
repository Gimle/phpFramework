<?php
declare(strict_types=1);
/**
 * This file will import all constants and some functions from the gimle namespace to the root namespace;
 */

foreach (get_defined_constants(true)['user'] as $name => $value) {
	if (substr($name, 0, 6) === 'gimle\\') {
		$name = substr($name, 6);
		if (!defined($name)) {
			define($name, $value);
		}
	}
}

if (!function_exists('d')) {
	function d ($var, bool $return = false, ?string $title = null, ?string $background = null, string $mode = 'auto')
	{
		if ($title === null) {
			$title = [
				'steps' => 1,
				'match' => '/d\((.*)/'
			];
		}
		return \gimle\var_dump($var, $return, $title, $background, $mode);
	}
}

if (!function_exists('page')) {
	function page (?string $part = null)
	{
		return \gimle\router\Router::getInstance()->page($part);
	}
}
