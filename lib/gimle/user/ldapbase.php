<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\Exception;
use \gimle\trick\Singelton;

use function \gimle\d;
use function \gimle\sp;

abstract class LdapBase
{
	use Singelton;

	public const UNKNOWN_ERROR = 1;
	public const CONNECTION_ERROR = 2;
	public const USER_NOT_FOUND = 3;
	public const INVALID_PASSWORD_OR_DISABLED = 4;

	protected $config = null;
	protected $masterConnection = null;

	protected function __construct ()
	{
		$this->config = User::getConfig()['auth']['ldap'];
		$this->connect();
	}

	protected function handleEntry ($entry)
	{
		$return = [];
		$fields = $this->config['entries'];
		foreach ($fields as $name => $field) {
			$index = mb_strtolower($field['field']);
			if (isset($entry[$index])) {
				if ((!isset($field['count'])) || ((isset($field['count'])) && ($field['count'] === $entry[$index]['count']))) {
					if (!isset($field['type'])) {
						if ((isset($this->config['utf8'])) && ($this->config['utf8'] === false)) {
							foreach ($entry[$index] as $key => $value) {
								if (is_string($value)) {
									$return[$name][$key] = utf8_encode($value);
								}
							}
						}
						else {
							$return[$name] = $entry[$index];
						}
						unset($return[$name]['count']);
					}
					else if ($field['type'] === 'datetime') {
						foreach ($entry[$index] as $key => $value) {
							$return[$name][$key] = self::utime((int) $value);
						}
						unset($return[$name]['count']);
					}
					else if ($field['type'] === 'int') {
						foreach ($entry[$index] as $key => $value) {
							$return[$name][$key] = (int) preg_replace('/[^0-9]+/', '', $value);
						}
						unset($return[$name]['count']);
					}
					else {
						throw new Exception('Unknown type. (' . $field['type'] . ')');
					}
				}
				else {
					throw new Exception('Count mismatch. (' . $field['field'] . ')');
				}

				if (isset($field['callback'])) {
					$return[$name] = call_user_func([$this, $field['callback']], $return[$name]);
				}
			}
			else if (isset($field['count'])) {
				throw new Exception('Count required, but none found. (' . $field['field'] . ')');
			}
		}
		return $return;
	}

	public function getUsers ()
	{
		$dn = $this->config['dn']['users'];
		if (is_array($dn)) {
			$ldapSearch = [];
			for ($i = 0; $i < count($dn); $i++) {
				$ldapSearch[] = $this->masterConnection;
			}
		}
		else {
			$ldapSearch = $this->masterConnection;
		}
		$fields = $this->config['entries'];
		array_walk($fields, function (&$item) {
			$item = $item['field'];
		});
		$results = ldap_search($ldapSearch, $dn, '(objectClass=user)', array_values($fields));
		foreach ($results as $result) {
			$entries = ldap_get_entries($this->masterConnection, $result);
			if ($entries['count'] > 0) {
				break;
			}
		}

		$users = [];
		$allGroups = [];
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			try {
				$entry = $this->handleEntry($entry);
			}
			catch (Exception $e) {
				continue;
			}
			$users[] = $entry;
		}

		return $users;
	}

	protected function entriesToUtf8 ($entries)
	{
		foreach ($entries as $key => $values) {
			if (is_array($values)) {
				$entries[$key] = $this->entryToUtf8($values);
			}
		}
		return $entries;
	}

	protected function entryToUtf8 ($entry)
	{
		foreach ($entry as $key => $values) {
			if (is_array($values)) {
				foreach ($values as $index => $value) {
					if (is_string($value)) {
						$entry[$key][$index] = utf8_encode($value);
					}
				}
			}
		}
		return $entry;
	}

	public function login ($username, $password)
	{
		$dn = $this->config['dn']['users'];
		if (is_array($dn)) {
			$ldapSearch = [];
			for ($i = 0; $i < count($dn); $i++) {
				$ldapSearch[] = $this->masterConnection;
			}
		}
		else {
			$ldapSearch = $this->masterConnection;
		}

		$fields = $this->config['entries'];
		array_walk($fields, function (&$item) {
			$item = $item['field'];
		});
		$results = ldap_search($ldapSearch, $dn, '(sAMAccountName=' . $username . ')', array_values($fields));
		foreach ($results as $result) {
			$entries = ldap_get_entries($this->masterConnection, $result);
			if ($entries['count'] > 0) {
				break;
			}
		}
		if (($entries['count'] > 0) && (isset($entries[0]['userprincipalname'][0]))) {
			$entry = $this->handleEntry($entries[0]);
			$userConnection = ldap_connect($this->config['server']);

			if ((isset($this->config['utf8'])) && ($this->config['utf8'] === false)) {
				$password = utf8_decode($password);
			}
			try {
				ldap_bind($userConnection, $entries[0]['userprincipalname'][0], $password);
				ldap_unbind($userConnection);
			}
			catch (\Exception $e) {
				throw new Exception('Invalid password or user disabled.', self::INVALID_PASSWORD_OR_DISABLED);
			}
		}
		else {
			throw new Exception('User not found in ldap.', self::USER_NOT_FOUND);
		}
		return $entry;
	}

	private function connect ()
	{
		if ($this->masterConnection === null) {
			$this->masterConnection = ldap_connect($this->config['server']);
			$bind = ldap_bind($this->masterConnection, $this->config['user'], $this->config['pass']);
		}
	}

	public function __destruct ()
	{
		ldap_unbind($this->masterConnection);
	}

	public static function utime (?int $time)
	{
		if ($time === null) {
			return null;
		}
		$ldapSecs = (int) round($time / 10000000);
		$unixTime = ($ldapSecs - 11644473600);
		return $unixTime;
	}
}
