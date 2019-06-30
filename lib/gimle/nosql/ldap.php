<?php
declare(strict_types=1);
namespace gimle\nosql;

use \gimle\Config;
use \gimle\MainConfig;
use const gimle\IS_SUBSITE;

class Ldap
{
	use \gimle\trick\Multiton;

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

	public function search (string $dn, string $filter, array $attributes = ['*'])
	{
		$result = ldap_search($this->connection, $dn, $filter, $attributes);
		$entries = ldap_get_entries($this->connection, $result);

		return new LdapResult($entries);
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
}
