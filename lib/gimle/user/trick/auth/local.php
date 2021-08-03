<?php
declare(strict_types=1);
namespace gimle\user\trick\auth;

use \gimle\Exception;
use \gimle\Config;

trait Local
{
	public function initLocal ()
	{
		$this->authLoadTypes[] = 'local';
	}

	public function loginLocal (string $email, string $password): bool
	{
		if (!isset($this->auth['local'])) {
			return false;
		}
		foreach ($this->auth['local'] as $index => $auth) {
			if ($auth['email'] === $email) {
				if (password_verify($password, $auth['password'])) {
					if ((Config::get('user.auth.local.verification') === 'require') && (!isset($auth['verified']))) {
						throw new Exception('E-Mail verification required', self::VERIFICATION_REQUIRED);
					}
					$this->auth['local'][$index]['last_used'] = $this->asDateTime();
					return true;
				}
			}
		}
		return false;
	}

	public function getLocalAuth ($email)
	{
		if (!isset($this->auth['local'])) {
			return null;
		}
		foreach ($this->auth['local'] as $local) {
			if ($local['email'] === $email) {
				return $local;
			}
		}
		return null;
	}

	public function updatePassword (string $email, string $password): bool
	{
		if (!isset($this->auth['local'])) {
			return false;
		}
		foreach ($this->auth['local'] as &$local) {
			if ($local['email'] === $email) {
				$local['password'] = self::hashPassword($password);
				if (isset($local['recover'])) {
					unset($local['recover']);
				}
				if (isset($local['recover_dt'])) {
					unset($local['recover_dt']);
				}
				return true;
			}
		}

		return false;
	}

	public function addLocalAuth (string $email, string $password, bool $verified = false): bool
	{
		$email = mb_strtolower($email);
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new Exception('Can not set user property: email', self::INVALID_EMAIL);
		}
		$isUsed = $this->authUsed('local', ['email' => $email]);
		if ($isUsed === true) {
			return false;
		}
		if ($this->email === null) {
			$this->email = $email;
		}

		$auth = [
			'email' => $email,
			'password' => $this->hashPassword($password),
		];
		if ($verified === false) {
			$auth['verify'] = sha1(openssl_random_pseudo_bytes(16));
		}
		$this->auth['local'][] = $auth;
		return true;
	}
}
