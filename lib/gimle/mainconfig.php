<?php
declare(strict_types=1);
namespace gimle;

class MainConfig
{
	/**
	 * The holder for the config.
	 */
	private static $config = [];

	/**
	 * Set a config value if not set.
	 *
	 * @param string $key The config key, a dot separated to set sub values.
	 * @param mixed $value The value to be set.
	 * @return bool true = the value was set, false = the value was already set.
	 */
	public static function set (string $key, $value): bool
	{
		if (!self::exists($key)) {
			$set = string_to_nested_array($key, $value);
			self::$config = array_merge_distinct(self::$config, $set);
			return true;
		}
		return false;
	}

	/**
	 * Set all config, will override if there is config set before.
	 *
	 * @param array $config The config to set.
	 * @return void
	 */
	public static function setAll (array $config): void
	{
		self::$config = $config;
	}

	/**
	 * Retrieves all the config set.
	 *
	 * @return array The set config.
	 */
	public static function getAll (): array
	{
		return self::$config;
	}

	/**
	 * Check if a value exists in the config.
	 *
	 * @param string $key A dot separated index for the the key to check.
	 * @return bool If the key exists.
	 */
	public static function exists (string $key): bool
	{
		$params = explode('.', $key);
		$check = self::$config;
		foreach ($params as $param) {
			if (isset($check[$param])) {
				$check = $check[$param];
			}
			else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the value for a specific dot separated key.
	 *
	 * @param string $key A dot separated index for the the key to check.
	 * @return mixed The value for the key.
	 */
	public static function get (string $key)
	{
		$params = explode('.', $key);
		$return = self::$config;
		foreach ($params as $param) {
			if (isset($return[$param])) {
				$return = $return[$param];
			}
			else {
				return null;
			}
		}
		return $return;
	}
}
