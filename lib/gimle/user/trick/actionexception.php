<?php
declare(strict_types=1);
namespace gimle\user\trick;

use \gimle\Exception;

trait ActionException
{
	/**
	 * Holder for the action exception.
	 *
	 * @var ?Exception
	 */
	private static $actionException = null;

	/**
	 * Used by the action procedure to store an exception.
	 *
	 * @param Exception $e
	 * @return void
	 */
	public static function setActionException (Exception $e): void
	{
		$_SESSION['gimle']['actionException'] = $e;
	}

	/**
	 * Retrieve a action exception.
	 *
	 * @return ?Exception
	 */
	public static function getActionException (bool $clear = true)
	{
		if (isset($_SESSION['gimle']['actionException'])) {
			self::$actionException = $_SESSION['gimle']['actionException'];
			if ($clear === true) {
				unset($_SESSION['gimle']['actionException']);
			}
		}
		return self::$actionException;
	}

	/**
	 * Clear any action exception.
	 *
	 * @return void
	 */
	public static function clearActionException (): void
	{
		if (isset($_SESSION['gimle']['actionException'])) {
			unset($_SESSION['gimle']['actionException']);
		}
		self::$actionException = null;
	}
}
