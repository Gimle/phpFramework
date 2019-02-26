<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\xml\SimpleXmlElement;
use \gimle\Exception;
use \gimle\Config;

use const \gimle\MAIN_STORAGE_DIR;

use function \gimle\sp;
use function \gimle\d;

class UserXml
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
	 * Holder for the SimpleXml user object.
	 *
	 * @var ?SimpleXmlElement
	 */
	private static $sxml = null;

	/**
	 * Fetch information about a user.
	 *
	 * @param ?mixed $id If type is null, The user id, else the auth id, or null to use currently signed in user.
	 * @param ?string $type Based on auth type, or null to use user id.
	 * @return array
	 */
	public static function getUser ($id = null, ?string $type = null): ?array
	{
		self::loadXml();

		if ($id === null) {
			$current = User::current();
			$id = $current['id'];
			return $current;
		}

		if ($type === 'local') {
			$query = '/users/user/auth/local[text()=' . self::$sxml->real_escape_string($id) . ']/../..';
			$result = current(self::$sxml->xpath($query));
			$row = null;
			if ($result !== false) {
				$firstName = (string) $result->name->first;
				$middleName = (string) $result->name->middle;
				if ($middleName === '') {
					$middleName = null;
				}
				$lastName = (string) $result->name->last;
				$fullName = $firstName;
				if ($middleName !== null) {
					$fullName .= ' ' . $middleName;
				}
				$fullName .= ' ' . $lastName;
				$firstSignin = (string) $result['first_signin'];
				if ($firstSignin === '') {
					$firstSignin = null;
				}
				$groups = json_decode('[' . str_replace(' ', ',', (string) $result['groups']) . ']', true);

				$row = [
					'id' => (int) $result['id'],
					'first_name' => $firstName,
					'middle_name' => $middleName,
					'last_name' => $lastName,
					'full_name' => $fullName,
					'screen_name' => $firstName,
					'email' => (string) $result->email,
					'created' => (string) $result['created'],
					'first_signin' => $firstSignin,
					'disabled' => null,
					'disabled_reason' => null,
					'local' => [
						'id' => (string) $result->auth->local,
						'verification' => null,
					],
					'remote' => null,
					'groups' => (empty($groups) ? null : $groups),
				];

				return $row;
			}
		}
		else {
			throw new Exception('Not implemented.');
		}

		throw new Exception('User not found.', User::USER_NOT_FOUND);
	}

	/**
	 * Check if a user exists.
	 *
	 * @param ?mixed $id If type is null, The user id, else the auth id, or null to use currently signed in user.
	 * @param ?string $type Based on auth type, or null to use user id.
	 * @return bool
	 */
	public static function exists ($id = null, ?string $type = null): bool
	{
		$exists = false;
		try {
			$test = User::getUser($id, $type);
			$exists = true;
		}
		catch (\gimle\Exception $e) {
		}
		return $exists;
	}

	/**
	 * Currently not handled for xml.
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
		self::loadXml();

		$providerId = null;
		$user = [];
		if ($type === 'local') {
			$user = $data;
			if (!isset($user['email'])) {
				$user['email'] = $user['username'];
			}
		}
		else {
			throw new Exception('Not implemented.');
		}
		if (($type !== 'local') && ($providerId === null)) {
			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}

		if (self::exists($user['email'], $type)) {
			throw new Exception('User exists.', User::OTHER_ERROR);
		}

		if ($type === 'local') {
			$verification = sha1(openssl_random_pseudo_bytes(16));
			$hash = User::hashPassword($user['password']);

			$userXml = self::$sxml->addChild('user');
			$userXml['id'] = self::$sxml->getNextId('id', 'user');
			$userXml['created'] = self::$sxml->asDateTime();
			$nameXml = $userXml->addChild('name');
			$nameXml->addChild('first', $user['first_name']);
			$nameXml->addChild('middle', '');
			$nameXml->addChild('last', $user['last_name']);
			$userXml->addChild('email', $user['email']);
			$authXml = $userXml->addChild('auth');
			$passXml = $authXml->addChild('local', $user['username']);
			$passXml['password'] = $hash;
			$passXml['verification'] = $verification;

			self::saveXml();
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
		self::loadXml();

		$user = self::getUser($id, $type);

		if ($type === 'local') {
			// Check password.
			$query = '/users/user/auth/local[text()=' . self::$sxml->real_escape_string($id) . ']';
			$result = current(self::$sxml->xpath($query));
			$row = [
				'password' => (string) $result['password'],
				'verification' => ($result['verification'] === null ? null : (string) $result['verification']),
			];

			if (!password_verify($password, $row['password'])) {
				throw new Exception('Incorrect password.', User::INVALID_PASSWORD);
			}
			if ($row['verification'] !== null) {
				throw new Exception('User not validated.', User::USER_NOT_VALIDATED);
			}
		}
		else {
			throw new Exception('Not implemented.');
		}

		if (Config::get('user.callback') !== null) {
			call_user_func_array([Config::get('user.callback'), 'login'], [$user, $id, $type]);
		}

		if ($user['first_signin'] === null) {
			// @todo update xml.
			$_SESSION['gimle']['first_signin'] = true;
		}

		$_SESSION['gimle']['signin_performed'] = true;

		return $user;
	}

	private static function loadXml ()
	{
		if (self::$sxml === null) {
			if (!is_readable(MAIN_STORAGE_DIR . 'users.xml')) {
				$xml = '<users/>';
				self::$sxml = new SimpleXmlElement($xml);
				return;
			}

			self::$sxml = new SimpleXmlElement(file_get_contents(MAIN_STORAGE_DIR . 'users.xml'));
		}
	}

	private static function saveXml ()
	{
		if (self::$sxml !== null) {
			file_put_contents(MAIN_STORAGE_DIR . 'users.xml', self::$sxml->pretty() . "\n");
		}
	}
}
