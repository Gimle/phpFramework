<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\sql\Mysql;
use \gimle\xml\SimpleXmlElement;
use \gimle\Exception;
use \gimle\Config;

use function \gimle\sp;

class UserMysql
{
	/**
	 * Fetch information about a user.
	 *
	 * @throws gimle\Exception If the user could not be found.
	 * @throws mysqli_sql_exception If there was a mysql problem.
	 *
	 * @param ?mixed $id If type is null, The user id, else the auth id, or null to use currently signed in user.
	 * @param ?string $type Based on auth type, or null to use user id.
	 * @return array
	 */
	public static function getUser ($id = null, ?string $type = null): array
	{
		$db = Mysql::getInstance('gimle');

		if ($id === null) {
			$current = User::current();
			$id = $current['id'];
			return $current;
		}

		if ($type === 'local') {
			$query = sprintf("SELECT
					`account_info_view`.*
				FROM
					`account_auth_local`
				LEFT JOIN
					`account_info_view` ON `account_info_view`.`id` = `account_auth_local`.`account_id`
				WHERE
					`account_auth_local`.`id` = '%s'
				;",
				$db->real_escape_string($id)
			);
			$result = $db->query($query);
		}
		else {
			if (strpos($type, '.') !== false) {
				$type = explode('.', $type);
				$query = sprintf("SELECT `id` FROM `account_auth_remote_providers` WHERE `name` = '%s' AND `type` = '%s';",
					$db->real_escape_string($type[1]),
					$db->real_escape_string($type[0])
				);
			}
			else {
				$query = sprintf("SELECT `id` FROM `account_auth_remote_providers` WHERE `type` = '%s';",
					$db->real_escape_string($type)
				);
			}
			$result = $db->query($query);
			$row = $result->get_assoc();
			if ($row === false) {
				throw new Exception('Unknown signin type.', User::UNKNOWN_OPERATION);
			}

			$query = sprintf("SELECT
					`account_info_view`.*
				FROM
					`account_auth_remote`
				LEFT JOIN
					`account_info_view` ON `account_info_view`.`id` = `account_auth_remote`.`account_id`
				WHERE
					(
						`account_auth_remote`.`id` = '%s'
					AND
						`account_auth_remote`.`provider_id` = '{$row['id']}'
					)
				;",
				$db->real_escape_string($id)
			);
			$result = $db->query($query);
		}

		if ($row = $result->get_assoc()) {
			if ($row['local'] !== null) {
				$row['local'] = json_decode($row['local'], true);
			}
			if ($row['remote'] !== null) {
				$row['remote'] = json_decode($row['remote'], true);
			}
			return $row;
		}

		throw new Exception('User not found.', User::USER_NOT_FOUND);
	}

	/**
	 * Check if a user exists.
	 *
	 * @throws mysqli_sql_exception If there was a mysql problem.
	 *
	 * @param ?mixed $id If type is null, The user id, else the auth id, or null to use currently signed in user.
	 * @param ?string $type Based on auth type, or null to use user id.
	 * @return array
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

	public static function create ($data, $type)
	{
		$db = Mysql::getInstance('gimle');

		$user = [];
		if ($type === 'ldap') {
			$user = [
				'username' => $data['username'][0],
				'first_name' => $data['first_name'][0],
				'last_name' => $data['last_name'][0],
				'email' => $data['email'][0],
			];
		}
		elseif ($type === 'local') {
			$user = $data;
			if (!isset($user['email'])) {
				$user['email'] = $user['username'];
			}
		}

		$query = sprintf("INSERT INTO `accounts` (`first_name`, `last_name`, `email`) VALUES (%s, %s, %s);",
			($user['first_name'] !== null ? "'" . $db->real_escape_string($user['first_name']) . "'" : 'null'),
			($user['last_name'] !== null ? "'" . $db->real_escape_string($user['last_name']) . "'" : 'null'),
			($user['email'] !== null ? "'" . $db->real_escape_string($user['email']) . "'" : 'null')
		);
		$result = $db->query($query);
		$accountid = $db->insert_id;

		if ($type === 'local') {
			$verification = sha1(openssl_random_pseudo_bytes(16));
			$cost = Config::get('user.local.passwordCost');
			$options = [
				'cost' => ($cost === null ? 12 : $cost),
			];

			$hash = password_hash($user['password'], PASSWORD_BCRYPT, $options);
			$query = sprintf("INSERT INTO `account_auth_local` (`id`, `account_id`, `password`, `verification`) VALUES ('%s', %u, '%s', '%s');",
				$db->real_escape_string($user['username']),
				$accountid,
				$db->real_escape_string($hash),
				$db->real_escape_string($verification)
			);
		}
		else {
			$query = sprintf("INSERT INTO `account_auth_remote` (`id`, `account_id`, `provider_id`, `data`) VALUES ('%s', %u, %u, '%s');",
				$db->real_escape_string($user['username']),
				$accountid,
				Config::get('user.ldap.providerId'),
				$db->real_escape_string(json_encode($data))
			);
		}
		$result = $db->query($query);
	}

	/**
	 * Log a user in.
	 *
	 * @throws gimle\Exception If the user could not be loggen in.
	 * @throws mysqli_sql_exception If there was a mysql problem.
	 *
	 * @param string $id The user id.
	 * @param string $type The user type.
	 * @param ?string $password
	 * @return array
	 */
	public static function login (string $id, string $type, ?string $password = null): array
	{
		$db = Mysql::getInstance('gimle');

		$user = self::getUser($id, $type);

		$providerId = 'null';
		if ($type === 'local') {
			// Check password.
			$query = sprintf("SELECT `password`, `verification` FROM `account_auth_local` WHERE `id` = '%s'",
				$db->real_escape_string($id)
			);
			$result = $db->query($query);
			$row = $result->fetch_assoc();
			if (!password_verify($password, $row['password'])) {
				$query = sprintf("INSERT INTO `account_logins` (`account_id`, `user_ip`, `status`, `remote_provider_id`) VALUES ({$user['id']}, '%s', 'passfail', null);",
					$db->real_escape_string($_SERVER['REMOTE_ADDR']),
					$providerId
				);
				$db->query($query);

				throw new Exception('Incorrect password.', User::INVALID_PASSWORD);
			}
			if ($row['verification'] !== null) {
				$query = sprintf("INSERT INTO `account_logins` (`account_id`, `user_ip`, `status`, `remote_provider_id`) VALUES ({$user['id']}, '%s', 'notverified', null);",
					$db->real_escape_string($_SERVER['REMOTE_ADDR']),
					$providerId
				);
				$db->query($query);

				throw new Exception('User not validated.', User::USER_NOT_VALIDATED);
			}
		}
		else {
			if (strpos($type, '.') !== false) {
				$type = explode('.', $type);
				$query = sprintf("SELECT `id` FROM `account_auth_remote_providers` WHERE `name` = '%s' AND `type` = '%s';",
					$db->real_escape_string($type[1]),
					$db->real_escape_string($type[0])
				);
			}
			else {
				$query = sprintf("SELECT `id` FROM `account_auth_remote_providers` WHERE `type` = '%s';",
					$db->real_escape_string($type)
				);
			}
			$result = $db->query($query);
			$row = $result->get_assoc();
			$providerId = (string) $row['id'];
		}

		$query = "DELETE FROM `account_logins`
			WHERE (`id` NOT IN (
				SELECT `id`
				FROM (
					SELECT `id`
					FROM `account_logins`
					WHERE `account_id` = {$user['id']}
					ORDER BY `datetime` DESC
					LIMIT 99
				) foo
			)
			AND `account_id` = {$user['id']})
		;";
		$db->query($query);

		$query = sprintf("INSERT INTO `account_logins` (`account_id`, `user_ip`, `status`, `remote_provider_id`) VALUES ({$user['id']}, '%s', 'ok', %s);",
			$db->real_escape_string($_SERVER['REMOTE_ADDR']),
			$providerId
		);
		$db->query($query);

		return $user;
	}
}
