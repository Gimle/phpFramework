<?php
declare(strict_types=1);
namespace gimle\user\trick\auth;

use \gimle\nosql\Ldap as LdapCore;
use \gimle\MainConfig;
use \gimle\Exception;

use function \gimle\filter_var;

use const gimle\IS_SUBSITE;
use const \gimle\FILTER_SANITIZE_NAME;

trait Ldap
{
	protected $ldapServers = null;

	public function initLdap ()
	{
		$this->authLoadTypes[] = 'ldap';
	}

	public function loginLdap (string $email, string $password): bool
	{
		$this->ldapLoadServers();

		foreach ($this->ldapServers as $server => $config) {
			$ldap = LdapCore::getInstance($server);
			$result = $ldap->search($config['users'], '(' . $config['email'] . '=' . ldap_escape($email) . ')');
			$row = $result->fetch();
			if ($row !== null) {
				$result = $ldap->login($row['dn'], $password);
				if ($result === true) {
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
					return true;
				}
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
	}
}
