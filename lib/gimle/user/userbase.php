<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\System;
use \gimle\User;
use \gimle\Exception;

use function \gimle\filter_var;
use function \gimle\loadFile;
use function \gimle\sp;

use const \gimle\MAIN_SITE_DIR;
use const \gimle\MAIN_BASE_PATH;
use const \gimle\MAIN_STORAGE_DIR;
use const \gimle\FILTER_SANITIZE_NAME;

abstract class UserBase
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
	public const REMOTE_ERROR = 10;
	public const REMOTE_REJECT = 11;
	public const OTHER_ERROR = 12;

	protected $id = null;
	protected $firstName = null;
	protected $middleName = null;
	protected $firstNames = null;
	protected $lastName = null;
	protected $fullName = null;
	protected $email = null;
	protected $created = null;
	protected $isFirstLogin = null;
	protected $loginPerformed = null;

	protected $groups = [];

	protected $auth = [];
	protected $field = [];

	protected $uses = [];
	protected $authLoadTypes = [];

	public function __construct ()
	{
		$uses = class_uses($this);
		foreach ($uses as $use) {
			$use = substr($use, strrpos($use, '\\') + 1);
			$this->uses[] = $use;
		}

		$this->callUses('init');
	}

	public function __set (string $property, $value): void
	{
		if (in_array($property, ['firstName', 'middleName', 'lastName'])) {
			if ((!is_string($value)) && (!is_null($value))) {
				throw new Exception('Can not set user property: ' . $property);
			}
			if ($value === '') {
				$value = null;
			}
			if ($value !== null) {
				if (($property === 'lastName') && (strpos($value, ' ') !== false)) {
					throw new Exception('Can not set user property: ' . $property);
				}
				$this->$property = filter_var($value, FILTER_SANITIZE_NAME);
				$this->setNames();
				return;
			}
			$this->$property = null;
			$this->setNames();
			return;
		}
		else if ($property === 'email') {
			if ((is_string($value)) && ($value !== '') && (filter_var($value, FILTER_VALIDATE_EMAIL))) {
				$this->email = mb_strtolower($value);
			}
			else {
				$this->email = null;
			}
			return;
		}
		else if ($property === 'groups') {
			if (!is_array($value)) {
				throw new Exception('Can not set user property: ' . $property);
			}
			$this->groups = $value;
			return;
		}
		else if ($property === 'auth') {
			if (!is_array($value)) {
				throw new Exception('Can not set user property: ' . $property);
			}
			if (empty($value)) {
				throw new Exception('Can not set user property: ' . $property);
			}
			$this->auth = $value;
			return;
		}
		throw new Exception('Can not set user property: ' . $property);
	}

	public function __get (string $property)
	{
		if (in_array($property, ['id', 'uses', 'groups', 'created', 'firstName', 'middleName', 'firstNames', 'lastName', 'fullName', 'email', 'auth', 'activeLdap', 'authLoadTypes'])) {
			return $this->$property;
		}
	}

	public function getTitle (): string
	{
		return $this->fullName;
	}

	public function field (string $id, $value = null): ?array
	{
		if ($value === null) {
			if (isset($this->field[$id])) {
				return $this->field[$id];
			}
			return null;
		}

		if (is_string($value)) {
			$value = [$value];
		}
		if (is_array($value)) {
			$this->field[$id] = $value;
		}
		else {
			throw new Exception('Can not set custom field: ' . $id);
		}

		return null;
	}

	public function sendVerification (): bool
	{
		return true;
	}

	public function isMemberOf ($groups): bool
	{
		if (is_string($groups)) {
			$groups = [$groups];
		}
		if (is_string($groups[0])) {
			$allGroups = User::getGroups();
			$ids = [];
			foreach ($groups as $group) {
				foreach ($allGroups as $allGroup) {
					if ($allGroup['name'] === $group) {
						$ids[] = $allGroup['id'];
					}
				}
			}
			$groups = $ids;
		}
		foreach ($this->groups as $group => $name) {
			if (in_array($group, $groups)) {
				return true;
			}
		}

		return false;
	}

	public static function current ($reload = false)
	{
		if (isset($_SESSION['gimle']['user'])) {
			if ($reload === true) {
				$_SESSION['gimle']['user'] = User::getUser($_SESSION['gimle']['user']->id);
			}
			return $_SESSION['gimle']['user'];
		}
		return new User();
	}

	public static function login (string $email, string $password)
	{
		if (isset($_SESSION['gimle']['user'])) {
			throw new Exception('User already signed in.', User::ALREADY_SIGNED_IN);
		}
		$user = new User();
		$email = mb_strtolower($email);
		$logged = false;
		foreach ($user->authLoadTypes as $method) {
			$user->authLoad($method, ['email' => $email]);
			$method = 'login' . ucfirst($method);
			if (method_exists($user, $method)) {
				if ($user->$method($email, $password) === true) {
					$logged = true;
					if (method_exists($user, 'postLogin')) {
						$user->postLogin($email, $password);
					}
					break;
				}
			}
		}

		if ($logged === false) {
			return new User();
		}

		return $user;
	}

	public static function setCookie (string $name, string $value, ?int $expires = null): void
	{
		if ($expires === null) {
			$expires = time() + (86400 * 400);
		}
		$urlPartsBase = parse_url(MAIN_BASE_PATH);
		if (version_compare(PHP_VERSION, '7.3.0') === -1) {
			setcookie(
				session_name() . $name,
				$value,
				$expires,
				$urlPartsBase['path'],
				'',
				true,
				true
			);
		}
		else {
			setcookie(
				session_name() . $name,
				$value,
				[
					'expires' => $expires,
					'path' => $urlPartsBase['path'],
					'secure' => true,
					'httponly' => true,
					'samesite' => 'Lax',
				]
			);
		}
	}

	public function removeAuth (string $type, array $params): bool
	{
		$delete = null;
		foreach ($this->auth[$type] as $index => $auth) {
			foreach ($params as $key => $value) {
				if ($auth[$key] !== $value) {
					continue 2;
				}
			}
			$delete = $index;
		}
		$count = 0;
		foreach ($this->auth as $auth) {
			foreach ($auth as $value) {
				$count++;
				if ($count === 2) {
					break;
				}
			}
		}
		if ($count < 2) {
			$delete = null;
		}
		if ($delete !== null) {
			unset($this->auth[$type][$delete]);
			return true;
		}
		return false;
	}

	protected function canSave (): bool
	{
		if ($this->firstName === null) {
			return false;
		}
		if ($this->lastName === null) {
			return false;
		}
		if ($this->email === null) {
			return false;
		}
		if (empty($this->auth)) {
			return false;
		}

		return true;
	}

	protected function callUses ($prefix)
	{
		foreach ($this->uses as $method) {
			$method = $prefix . $method;
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
	}

	protected function setNames ()
	{
		$this->firstNames = $this->firstName;
		if ($this->middleName !== null) {
			$this->firstNames .= ' ' . $this->middleName;
		}
		$this->fullName = $this->firstNames . ' ' . $this->lastName;
	}

	protected function updateAi (int $newid)
	{
		if (!file_exists(MAIN_STORAGE_DIR . 'users/')) {
			mkdir(MAIN_STORAGE_DIR . 'users/');
		}
		file_put_contents(MAIN_STORAGE_DIR . 'users/userid.txt', (string) $newid, LOCK_EX);
	}

	protected function getAi (): int
	{
		return (int) loadFile(MAIN_STORAGE_DIR . 'users/userid.txt', '1');
	}
}
