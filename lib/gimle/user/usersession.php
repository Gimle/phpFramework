<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\xml\SimpleXmlElement;
use \gimle\Exception;
use \gimle\Config;

use function \gimle\sp;
use function \gimle\d;

/**
 * This class is only meny to be used in development stages before a user system is implemented.
 */
class UserSession
{
	/**
	 * Holder for the first signin value.
	 *
	 * @var bool
	 */
	private static $isFirstSignin = false;

	/**
	 * Holder for if the signin was performed.
	 *
	 * @var bool
	 */
	private static $signinPerformed = false;

	/**
	 * There is no limit if the user exists or not in a session based sign in.
	 * However, we might need the result from the provider in the session,
	 * so we return false here, to catch this in the create method.
	 *
	 * @param ?mixed $id If type is null, The user id, else the auth id, or null to use currently signed in user.
	 * @param ?string $type Based on auth type, or null to use user id.
	 * @return bool
	 */
	public static function exists ($id = null, ?string $type = null): bool
	{
		return false;
	}

	/**
	 * No records for session, nothing to update.
	 *
	 * @return void
	 */
	public static function updateActive (): void
	{
	}

	/**
	 * Was this the first time this user have ever signed in?
	 *
	 * @return bool
	 */
	public static function isFirstSignin (): bool
	{
		if (isset($_SESSION['gimle']['first_signin'])) {
			self::$isFirstSignin = true;
			unset($_SESSION['gimle']['first_signin']);
		}
		return self::$isFirstSignin;
	}

	/**
	 * Create a new user.
	 *
	 * @throws gimle\Exception if the signin type was not understood.
	 *
	 * @var array $data Information about the user.
	 * @var string $type The signin type.
	 * @return void
	 */
	public static function create (array $data, string $type): void
	{
		$_SESSION['gimle']['userSessionDummyUser'] = [
			'id' => null,
		];
		if (isset($data['first_name'])) {
			if (is_array($data['first_name'])) {
				$_SESSION['gimle']['userSessionDummyUser']['first_name'] = current($data['first_name']);
			}
		}
		if (isset($data['last_name'])) {
			if (is_array($data['last_name'])) {
				$_SESSION['gimle']['userSessionDummyUser']['last_name'] = current($data['last_name']);
			}
		}
		if (isset($data['email'])) {
			if (is_array($data['email'])) {
				$_SESSION['gimle']['userSessionDummyUser']['email'] = current($data['email']);
			}
		}
	}

	/**
	 * Log a user in.
	 *
	 * @throws gimle\Exception If the user could not be loggen in.
	 *
	 * @param string $id The user id.
	 * @param string $type The user type.
	 * @param ?string $password
	 * @return array
	 */
	public static function login (string $id, string $type, ?string $password = null): array
	{
		$_SESSION['gimle']['signin_performed'] = true;

		if ($type === 'ldap') {

			if (isset($_SESSION['gimle']['userSessionDummyUser'])) {
				$return = $_SESSION['gimle']['userSessionDummyUser'];
				unset($_SESSION['gimle']['userSessionDummyUser']);
				return $return;
			}

			return [
				'id' => null,
			];
		}

		if ($type === 'pam') {

			if (isset($_SESSION['gimle']['userSessionDummyUser'])) {
				$return = $_SESSION['gimle']['userSessionDummyUser'];
				unset($_SESSION['gimle']['userSessionDummyUser']);
				return $return;
			}

			return [
				'id' => null,
			];
		}

		return [
			'id' => null,
			'full_name' => 'John Doe',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'email' => 'john.doe@example.com',
		];
	}
}
