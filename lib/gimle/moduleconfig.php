<?php
declare(strict_types=1);
namespace gimle;

class ModuleConfig extends Configuration
{
	/**
	 * The holder for the config.
	 */
	protected static $config = [];

	public static function modules ()
	{
		return array_keys(self::$config);
	}
}
