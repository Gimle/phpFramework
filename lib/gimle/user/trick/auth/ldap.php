<?php
declare(strict_types=1);
namespace gimle\user\trick\auth;

use \gimle\MainConfig;
use \gimle\Exception;

use function \gimle\filter_var;
use function \gimle\sp;

use const gimle\IS_SUBSITE;
use const \gimle\FILTER_SANITIZE_NAME;

trait Ldap
{
	protected $ldapServers = null;
	protected $activeLdap = [];

	public function initLdap ()
	{
		$this->authLoadTypes[] = 'ldap';
	}

	public function loginLdap (string $email, string $password, bool $save = true): bool
	{
		$this->ldapLoadServers();

		foreach ($this->ldapServers as $server => $config) {
			if ($this->loginLdapServer($server, $email, $password, $save) === true) {
				foreach ($this->auth['ldap'] as $index => $test) {
					if (($test['server'] === $server) && ($test['email'] === $email)) {
						$this->auth['ldap'][$index]['last_used'] = $this->asDateTime();
						if ($save !== false) {
							$this->save();
						}
					}
				}
				return true;
			}
		}

		return false;
	}

	public function addLdapAuth (string $email)
	{
		$this->ldapLoadServers();
		foreach ($this->ldapServers as $server => $config) {
			$ldap = \gimle\nosql\Ldap::getInstance($server);
			$config = $this->ldapServers[$server];
			$result = $ldap->search($config['users'], '(' . $config['email'] . '=' . ldap_escape($email) . ')');
			if (!is_array($result)) {
				$row = $result->fetch();
			}
			else {
				foreach ($result as $res) {
					$row = $res->fetch();
					if ($row !== null) {
						break;
					}
				}
			}
			if ($row === null) {
				continue;
			}

			$authExists = false;
			if (isset($this->auth['ldap'])) {
				foreach ($this->auth['ldap'] as $test) {
					if (($test['server'] === $server) && ($test['email'] === $email)) {
						$authExists = true;
					}
				}
			}

			if ($authExists === false) {
				$test = $this->authUsed('ldap', [
					'server' => $server,
					'email' => $email,
				]);
				if ($test === true) {
					throw new Exception('Login already in use: ' . $server . ' ' . $email);
				}
				$this->auth['ldap'][] = [
					'server' => $server,
					'email' => $email,
				];
			}

			if (method_exists($this, 'ldapRow')) {
				$this->ldapRow($row);
			}
			$this->activeLdap = [
				'server' => $server,
				'dn' => $row['dn'],
			];
			$this->save();
			return true;
		}
		return false;
	}

	public function loginLdapServer (string $server, string $email, string $password, bool $save = true): bool
	{
		$this->ldapLoadServers();

		$ldap = \gimle\nosql\Ldap::getInstance($server);
		$config = $this->ldapServers[$server];
		$result = $ldap->search($config['users'], '(' . $config['email'] . '=' . ldap_escape($email) . ')');
		if (!is_array($result)) {
			$row = $result->fetch();
		}
		else {
			foreach ($result as $res) {
				$row = $res->fetch();
				if ($row !== null) {
					break;
				}
			}
		}
		if ($row === null) {
			return false;
		}

		$result = $ldap->login($row['dn'], $password);
		if ($result !== true) {
			return false;
		}

		foreach ($config['field'] as $attribute => $field) {
			if (in_array($attribute, ['firstName', 'middleName', 'lastName'])) {
				if ($this->$attribute === null) {
					$this->$attribute = filter_var($row[strtolower($field)][0], FILTER_SANITIZE_NAME);
				}
			}
			elseif ($attribute === 'email') {
				if ($this->email === null) {
					$this->$attribute = mb_strtolower($row[strtolower($field)][0]);
				}
			}
			else {
				foreach ($row[strtolower($field)] as $value) {
					$this->field[$attribute][] = $value;
				}
			}
		}
		$this->setNames();

		$authExists = false;
		if (isset($this->auth['ldap'])) {
			foreach ($this->auth['ldap'] as $test) {
				if (($test['server'] === $server) && ($test['email'] === $email)) {
					$authExists = true;
				}
			}
		}

		if ($authExists === false) {
			$test = $this->authUsed('ldap', [
				'server' => $server,
				'email' => $email,
			]);
			if ($test === true) {
				throw new Exception('Login already in use: ' . $server . ' ' . $email);
			}
			$this->auth['ldap'][] = [
				'server' => $server,
				'email' => $email,
			];
		}

		if (method_exists($this, 'ldapRow')) {
			$this->ldapRow($row);
		}
		$this->activeLdap = [
			'server' => $server,
			'dn' => $row['dn'],
		];
		if ($save !== false) {
			$this->save();
		}

		return true;
	}

	public function updateLdapPassword (string $email, string $password, ?string $server = null): bool
	{
		if (!isset($this->auth['ldap'])) {
			return false;
		}
		foreach ($this->auth['ldap'] as &$ldapXml) {
			if (($server !== null) && ((string) $ldapXml['server'] !== $server)) {
				continue;
			}
			if ((string) $ldapXml['email'] === $email) {
				$result = $this->_updateLdapPassword($email, $password, (string) $ldapXml['server']);
				if ($result === true) {
					if (isset($ldapXml['verify'])) {
						unset($ldapXml['verify']);
						$ldapXml['verified'] = $user->asDateTime();
					}
					if (isset($ldapXml['recover'])) {
						unset($ldapXml['recover']);
					}
					if (isset($ldapXml['recover_dt'])) {
						unset($ldapXml['recover_dt']);
					}
					return true;
				}
			}
		}
		return false;
	}

	protected function _updateLdapPassword (string $email, string $password, string $server): bool
	{
		$ldap = \gimle\nosql\Ldap::getInstance($server);
		$ldapConfig = MainConfig::get('auth.ldap.' . $server);
		$result = $ldap->search($ldapConfig['users'], '(' . $ldapConfig['email'] . '=' . ldap_escape($email) . ')');
		foreach ($result as $entry) {
			$row = $entry->fetch();
			if ($row !== null) {
				$password = \gimle\nosql\Ldap::hashPassword($password);
				$ldap->modify($row['dn'], 'userPassword', $password);
				return true;
			}
		}
		return false;
	}

	protected function ldapLoadServers (): void
	{
		if ($this->ldapServers !== null) {
			return;
		}
		$this->ldapServers = MainConfig::get('auth.ldap');
		if ($this->ldapServers === null) {
			throw new Exception('Could not find configuration for "auth.ldap".');
		}
	}

	protected function ldapRowMatch (string $ou, string $valid): bool
	{
		$ou = explode(',', $ou);
		$valid = explode(',', $valid);
		if (!empty(array_diff($ou, $valid))) {
			return false;
		}
		if (!empty(array_diff($valid, $ou))) {
			return false;
		}
		return true;
	}
}
