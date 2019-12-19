<?php
declare(strict_types=1);
namespace gimle\user\trick;

use \gimle\MainConfig;

use function \gimle\random;

trait Password
{
	/**
	 * Generate a random password.
	 *
	 * @return string A new randomly generated password.
	 */
	public static function generatePassword (): string
	{
		return random('!#$%&()+-./:;<=>?@\\~|abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');
	}

	/**
	 * Hash a password.
	 *
	 * @param string $password The password.
	 * @return string A hash of the password,
	 */
	public static function hashPassword (string $password): string
	{
		$cost = MainConfig::get('user.local.passwordCost');
		$options = [
			'cost' => ($cost === null ? 12 : $cost),
		];

		$hash = password_hash($password, PASSWORD_BCRYPT, $options);
		return $hash;
	}
}
