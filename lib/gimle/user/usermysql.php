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

		if ($type !== null) {
			$query = sprintf("SELECT
					`account_info_view`.*
				FROM
					`account_auth_%1\$s`
				LEFT JOIN
					`account_info_view` ON `account_info_view`.`id` = `account_auth_%1\$s`.`account_id`
				WHERE
					`account_auth_%1\$s`.`id` = '%2\$s'
				;",

				preg_replace('/[^a-z]/', '', $type),
				$db->real_escape_string($id)
			);
			$result = $db->query($query);
		}
		else {
			$query = sprintf("SELECT * FROM `account_info_view` WHERE `id` = %u;",
				(int) $id
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

	public static function create ($data, $type)
	{
		$db = Mysql::getInstance('gimle');

		$user = [];
		if ($type === 'ldap') {
			$user = [
				'username' => $data['username'][0],
				'first_name' => $data['first_name'][0],
				'last_name' => $data['first_name'][0],
				'email' => $data['email'][0],
			];
		}

		$query = sprintf("INSERT INTO `accounts` (`first_name`, `last_name`, `email`) VALUES ('%s', '%s', '%s');",
			$db->real_escape_string($user['first_name']),
			$db->real_escape_string($user['last_name']),
			$db->real_escape_string($user['email'])
		);
		$result = $db->query($query);
		$accountid = $db->insert_id;

		$query = sprintf("INSERT INTO `account_auth_remote` (`id`, `account_id`, `provider_id`, `data`) VALUES ('%s', %u, %u, '%s');",
			$db->real_escape_string($user['username']),
			$accountid,
			Config::get('user.ldap.providerId'),
			$db->real_escape_string(json_encode($data))
		);
		$result = $db->query($query);
		sp($query);
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

		if ($type === 'local') {
			// Check password.
			$query = sprintf("SELECT `password`, `verification` FROM `account_auth_local` WHERE `id` = '%s'",
				$db->real_escape_string($id)
			);
			$result = $db->query($query);
			$row = $result->fetch_assoc();
			if (!password_verify($password, $row['password'])) {
				throw new Exception('Incorrect password.', User::INVALID_PASSWORD);
			}
			if ($row['verification'] !== null) {
				throw new Exception('User not validated.', User::USER_NOT_VALIDATED);
			}
		}
		else if ($type !== 'remote') {
			throw new Exception('Unknown signin type.', User::UNKNOWN_OPERATION);
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

		$query = "INSERT INTO `account_logins` (`account_id`, `status`, `method`, `remote_provider_id`) VALUES ({$user['id']}, 'ok', '{$type}', null);";
		$db->query($query);

		return $user;
	}
}
