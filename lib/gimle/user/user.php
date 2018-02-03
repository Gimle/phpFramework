<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\Exception;
use \gimle\Config;
use \gimle\MainConfig;

use const \gimle\IS_SUBSITE;

class User
{
	use trick\SigninToken;
	use trick\SigninException;

	/* Error constants */
	public const UNKNOWN = 1;
	public const ALREADY_SIGNED_IN = 2;
	public const MISSING_PAYLOAD = 3;
	public const INVALID_PAYLOAD = 4;
	public const UNKNOWN_OPERATION = 5;
	public const USER_NOT_FOUND = 6;
	public const INVALID_PASSWORD = 7;
	public const USER_NOT_VALIDATED = 8;
	public const STATE_ERROR = 9;
	public const OAUTH_ERROR = 10;
	public const OAUTH_REJECT = 11;
	public const OTHER_ERROR = 12;

	/**
	 * Holder for configuration.
	 *
	 * @var ?array
	 */
	private static $config = null;

	private static $currentUser = null;

	/**
	 * Passthru for the static methods in the data access layer.
	 *
	 * @throws Inherited
	 *
	 * @param string $name
	 * @param ?mixed $args
	 * @return mixed
	 */
	public static function __callStatic (string $name, $args)
	{
		if (self::$config === null) {
			self::configure();
		}

		return call_user_func([self::$config['object'], $name], ...$args);
	}

	public static function current ()
	{
		if ((self::$currentUser === null) && (isset($_SESSION['gimle']['user']))) {
			self::$currentUser = $_SESSION['gimle']['user'];
		}
		return self::$currentUser;
	}

	/**
	 * Get the current configuration.
	 *
	 * @throws \gimle\Exception If configuration error found.
	 *
	 * @return array
	 */
	public static function getConfig (): array
	{
		if (self::$config === null) {
			self::configure();
		}

		return self::$config;
	}

	/**
	 * Generate a random password.
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
	 * Load the configuration.
	 *
	 * @throws \gimle\Exception If configuration error found.
	 *
	 * @return void
	 */
	private static function configure ()
	{
		if (self::$config === null) {
			if (IS_SUBSITE) {
				self::$config = MainConfig::get('user');
			}
			else {
				self::$config = Config::get('user');
			}

			if (!isset(self::$config['object'])) {
				throw new Exception('User object configuration missing.');
			}

			if (strpos(self::$config['object'], '\\') === false) {
				self::$config['object'] = '\\' . __NAMESPACE__ . '\\' . self::$config['object'];
			}

			if (!isset(self::$config['create'])) {
				self::$config['create'] = false;
			}

			if (!isset(self::$config['gimle'])) {
				self::$config['gimle'] = false;
			}

			if (!isset(self::$config['ldap'])) {
				self::$config['ldap'] = false;
			}

			if (!isset(self::$config['pam'])) {
				self::$config['pam'] = false;
			}

			if (!isset(self::$config['oauth'])) {
				self::$config['oauth'] = [];
			}
			elseif (is_array(self::$config['oauth'])) {
				foreach (self::$config['oauth'] as $name => $oauth) {
					if ((!isset($oauth['clientId'])) || (!isset($oauth['clientSecret']))) {
						throw new Exception($name . ' login configuration missing.');
					}
				}
			}
			else {
				throw new Exception('Invalid oauth configuration.');
			}
		}
	}
}
