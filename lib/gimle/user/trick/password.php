<?php
declare(strict_types=1);
namespace gimle\user\trick;

use \gimle\MainConfig;

trait Password
{
	/**
	 * Generate a random password.
	 *
	 * @return string A new randomly generated password.
	 */
	public static function generatePassword (): string
	{
		$length = rand(18, 26);
		$return = '';
		$characters = '!#$%&()+-./:;<=>?@\\~|abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		$count = mb_strlen($characters);
		for ($i = 0; $i < $length; $i++) {
			$random = rand(0, $count);
			$return .= mb_substr($characters, $random, 1);
		}

		return $return;
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
