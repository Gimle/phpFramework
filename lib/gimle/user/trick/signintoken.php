<?php
declare(strict_types=1);
namespace gimle\user\trick;

trait SigninToken
{
	/**
	 * Retrieve a signin token if exists, otherwise create a new one.
	 *
	 * @return string
	 */
	public static function getSigninToken (): string
	{
		$token = self::retrieveSigninToken();
		if ($token === null) {
			$token = generateSigninToken();
		}
		return $token;
	}

	/**
	 * Generate a new signin token.
	 *
	 * @return string
	 */
	public static function generateSigninToken (): string
	{
		$token = bin2hex(openssl_random_pseudo_bytes(12));
		$_SESSION['siginin']['token'] = $token;
		return $token;
	}

	/**
	 * Retrieve a signin token if exists.
	 *
	 * @return ?string
	 */
	public static function retrieveSigninToken (): ?string
	{
		if (isset($_SESSION['siginin']['token'])) {
			return $_SESSION['siginin']['token'];
		}
		return null;
	}

	/**
	 * Validate a signin token.
	 *
	 * @param string
	 * @return bool
	 */
	public static function validateSigninToken (string $token): bool
	{
		if ($token === self::retrieveSigninToken()) {
			return true;
		}
		return false;
	}

	/**
	 * Delete a signin token.
	 *
	 * @return void
	 */
	public static function deleteSigninToken (): void
	{
		if (isset($_SESSION['siginin']['token'])) {
			unset($_SESSION['siginin']['token']);
		}
	}
}
