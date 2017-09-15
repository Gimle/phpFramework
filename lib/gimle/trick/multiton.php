<?php
declare(strict_types=1);
namespace gimle\trick;

trait Multiton
{
	/**
	 * The object instances this multiton holds.
	 *
	 * @var array<mixed>
	 */
	private static $instances = [];

	/**
	 * Retrieve an object. Create it if it does not exist.
	 *
	 * @param string $identifier The indentifier for the instance.
	 * @param mixed ...$args Arguments for the object.
	 * @return object
	 */
	public static function getInstance (string $identifier, ...$args): self
	{
		if (!isset(self::$instances[$identifier])) {
			$me = get_called_class();

			self::$instances[$identifier] = new $me($identifier, ...$args);
		}

		return self::$instances[$identifier];
	}

	/**
	 * Get all instances of this object.
	 *
	 * @return array The instances created.
	 */
	public static function getInstances (): array
	{
		return array_keys(self::$instances);
	}

	/**
	 * Overridable constructor to make it private.
	 */
	private function __construct ()
	{
	}
}
