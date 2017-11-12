<?php
declare(strict_types=1);
namespace gimle\user;

use \gimle\sql\Mysql;
use \gimle\xml\SimpleXmlElement;
use \gimle\Exception;

class UserMysql
{
	/**
	 * Fetch information about a user.
	 *
	 * @throws gimle\Exception If the user could not be found.
	 *
	 * @param ?mixed $id If type is null, The user id, else the auth id, or null to use currently signed in user.
	 * @param ?string $type Based on auth type, or null to use user id.
	 * @return array
	 */
	public static function getUser ($id = null, ?string $type = null): array
	{
		$db = Mysql::getInstance('gimle');

		if ($id === null) {
			// $id = User::current();
			throw new Exception('Not implemented.');
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
			return $row;
		}

		throw new Exception('User not found.', User::USER_NOT_FOUND);
	}

	/**
	 * Log a user in.
	 *
	 * @throws gimle\Exception If the user could not be loggen in.
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
		else {
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
					LIMIT 9
				) foo
			)
			AND `account_id` = {$user['id']})
		;";
		$db->query($query);

		$query = "INSERT INTO `account_logins` (`account_id`, `status`) VALUES ({$user['id']}, 'ok');";
		$db->query($query);

		return $user;
	}
}
