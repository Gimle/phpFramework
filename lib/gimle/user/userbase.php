<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\System;
use \gimle\User;
use \gimle\Exception;

use function \gimle\filter_var;

use const \gimle\MAIN_SITE_DIR;
use const \gimle\FILTER_SANITIZE_NAME;

/**
 * This class requires Parser-PHP to be installed as a submodule.
 *
 * mkdir vendor; cd vendor; git submodule add https://github.com/WhichBrowser/Parser-PHP.git; cd ..
 */

System::autoloadRegister(MAIN_SITE_DIR . 'vendor/Parser-PHP/src/', ['stripRootNamespace' => true], true);

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
	public const OAUTH_ERROR = 10;
	public const OAUTH_REJECT = 11;
	public const OTHER_ERROR = 12;

	protected $id = null;
	protected $firstName = null;
	protected $middleName = null;
	protected $firstNames = null;
	protected $lastName = null;
	protected $fullName = null;
	protected $email = null;
	protected $userAgent = [
		'id' => null,
		'os' => null,
		'browser' => null,
	];
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
				$this->$property = filter_var($value, FILTER_SANITIZE_NAME);
				$this->setNames();
				return;
			}
			if (in_array($property, ['firstName', 'lastName'])) {
				throw new Exception('Property value required: ' . $property);
			}
			$this->$property = null;
			$this->setNames();
			return;
		}
		else if ($property === 'email') {
			if ((!is_string($value)) || (!filter_var($value, FILTER_VALIDATE_EMAIL))) {
				throw new Exception('Can not set user property: ' . $property);
			}
			$this->email = mb_strtolower($value);
			return;
		}
		throw new Exception('Can not set user property: ' . $property);
	}

	public function __get (string $property)
	{
		if (in_array($property, ['id', 'uses', 'groups', 'created', 'firstName', 'middleName', 'firstNames', 'lastName', 'fullName', 'email', 'auth', 'activeLdap'])) {
			return $this->$property;
		}
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

	public static function current ()
	{
		if (isset($_SESSION['gimle']['user'])) {
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
					break;
				}
			}
		}

		if ($logged === false) {
			return new User();
		}

		return $user;
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
}
