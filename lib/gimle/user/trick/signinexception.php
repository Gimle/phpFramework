<?php
declare(strict_types=1);
namespace gimle\user\trick;

use \gimle\Exception;

trait SigninException
{
	/**
	 * Used by the signin procedure to store an exception.
	 *
	 * @param Exception $e
	 * @return void
	 */
	public static function setSigninException (Exception $e): void
	{
		$_SESSION['gimle']['signinException'] = $e;
	}

	/**
	 * Retrieve a signin exception.
	 *
	 * @return ?Exception
	 */
	public static function getSigninException ()
	{
		if (isset($_SESSION['gimle']['signinException'])) {
			return $_SESSION['gimle']['signinException'];
		}
		return null;
	}

	/**
	 * Clear any signin exception.
	 *
	 * @return void
	 */
	public static function clearSigninException (): void
	{
		if (isset($_SESSION['gimle']['signinException'])) {
			unset($_SESSION['gimle']['signinException']);
		}
	}
}
