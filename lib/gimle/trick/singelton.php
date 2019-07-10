<?php
declare(strict_types=1);
namespace gimle\trick;

trait Singelton
{
	/**
	 * The object instance this singelton holds.
	 *
	 * @var mixed
	 */
	private static $instance = false;

	/**
	 * Retrieve the object. Create it if it does not exist.
	 *
	 * @param mixed ...$args Arguments for the object.
	 * @return object
	 */
	public static function getInstance (...$args): self
	{
		if (self::$instance === false) {
			self::$instance = new static(...$args);
		}

		return self::$instance;
	}

	/**
	 * Overridable constructor to make it private.
	 */
	private function __construct ()
	{
	}
}
