<?php
declare(strict_types=1);
namespace gimle\user\trick\auth;

use \gimle\Exception;

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
		foreach ($this->auth['local'] as $auth) {
			if ($auth['email'] === $email) {
				if (password_verify($password, $auth['password'])) {
					return true;
				}
			}
		}
		return false;
	}

	public function updatePassword (string $email, string $password): bool
	{
		if (!isset($this->auth['local'])) {
			return false;
		}
		foreach ($this->auth['local'] as &$local) {
			if ($local['email'] === $email) {
				$local['password'] = self::hashPassword($password);
				return true;
			}
		}

		return false;
	}

	public function addLocalAuth (string $email, string $password): bool
	{
		$email = mb_strtolower($email);
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new Exception('Can not set user property: email');
		}
		$isUsed = $this->authUsed('local', ['email' => $email]);
		if ($isUsed === true) {
			return false;
		}
		if ($this->email === null) {
			$this->email = $email;
		}
		$this->auth['local'][] = [
			'email' => $email,
			'password' => $this->hashPassword($password),
		];
		return true;
	}
}
