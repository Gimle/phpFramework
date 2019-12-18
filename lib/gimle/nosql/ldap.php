<?php
declare(strict_types=1);
namespace gimle\nosql;

use \gimle\Config;
use \gimle\MainConfig;
use \gimle\Exception;

use const gimle\IS_SUBSITE;

class Ldap
{
	use \gimle\trick\Multiton;

	const CRYPT_SHA_512 = 1;

	private $config = null;

	public function __construct (string $key)
	{
		$this->config = Config::get('ldap.' . $key);
		if (($this->config === null) && (IS_SUBSITE)) {
			$this->config = MainConfig::get('ldap.' . $key);
		}

		$this->connection = ldap_connect($this->config['host']);
		ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_bind($this->connection, $this->config['bind'], $this->config['password']);
	}

	public function search ($dn, string $filter, array $attributes = ['*'])
	{
		if (is_array($dn)) {
			$connections = [];
			foreach ($dn as $devnull) {
				$connections[] = $this->connection;
			}
			$result = ldap_search($connections, $dn, $filter, $attributes);
		}
		else {
			$result = ldap_search($this->connection, $dn, $filter, $attributes);
		}
		if (!is_array($result)) {
			$entries = ldap_get_entries($this->connection, $result);
			return new LdapResult($entries);
		}

		$return = [];
		foreach ($result as $res) {
			$entries = ldap_get_entries($this->connection, $res);
			$return[] = new LdapResult($entries);
		}
		return $return;
	}

	public function modify (string $dn, string $field, $values): void
	{
		$values = [$field => $values];
		ldap_modify($this->connection, $dn, $values);
	}

	public function login (string $bind, string $password)
	{
		$userConnection = ldap_connect($this->config['host']);
		ldap_set_option($userConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
		try {
			ldap_bind($userConnection, $bind, $password);
			ldap_unbind($userConnection);
			return true;
		}
		catch (\Throwable $t) {
		}
		return false;
	}

	public static function hashPassword (string $password, int $method = self::CRYPT_SHA_512): string
	{
		if ($method === self::CRYPT_SHA_512) {
			$hash = '';
			$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			$count = mb_strlen($characters);
			for ($i = 0; $i < 16; $i++) {
				$random = rand(0, $count);
				$hash .= mb_substr($characters, $random, 1);
			}

			$password = '{CRYPT}' . crypt($password, '$6$rounds=500000$' . $hash . '$');

			return $password;
		}

		throw new Exception('Unknown hashing method.');
	}
}
