<?php
declare(strict_types=1);
namespace gimle\user\storage;

use \gimle\nosql\Mongo as MongoDb;
use \gimle\User;
use \gimle\Config;
use \gimle\Exception;

class Mongo extends \gimle\user\UserBase
{
	public function save (): ?int
	{
		if (!$this->canSave()) {
			return null;
		}

		$mongo = MongoDb::getInstance('users');

		$user = [];

		if ($this->id === null) {
			$filter = [];
			$options = [
				'sort' => ['id' => -1],
				'limit' => 1,
			];

			$cursor = $mongo->query($filter, $options);
			$it = new \IteratorIterator($cursor);
			$it->rewind();
			$document = $it->current();

			$newId = 1;
			if ($document !== null) {
				$newId = $document->id + 1;
			}
			$user['id'] = max($this->getAi(), $newId);
			$this->updateAi($user['id'] + 1);
			$user['created'] = self::asDateTime();
		}
		else {
			$user['id'] = $this->id;
			$user['created'] = self::asDateTime($this->created);
		}

		$user['name'] = [
			'first' => $this->firstName,
		];
		if ($this->middleName !== null) {
			$user['name']['middle'] = $this->middleName;
		}
		$user['name']['last'] = $this->lastName;

		$user['email'] = $this->email;

		$this->callUses('save');

		if (!empty($this->groups)) {
			ksort($this->groups);
			$user['groups'] = array_keys($this->groups);
		}
		else {
			$user['groups'] = [];
		}

		$user['auth'] = [];
		foreach ($this->auth as $method => $params) {
			foreach ($params as $param) {
				$auth = [
					'type' => $method,
				];
				foreach ($param as $key => $value) {
					$auth[$key] = $value;
				}
				$user['auth'][] = $auth;
			}
		}

		if (!empty($this->field)) {
			foreach ($this->field as $method => $values) {
				foreach ($values as $index => $value) {
					$user['fields'][$method][$index] = $value;
				}
			}
		}

		if (method_exists($this, 'postSave')) {
			$user = $this->postSave($user);
		}

		$bulkWrite = new \MongoDB\Driver\BulkWrite;
		if ($this->id === null) {
			$this->id = $user['id'];
			$bulkWrite->insert($user);
		}
		else {
			$filter = [
				'id' => $this->id,
			];
			$cursor = $mongo->query($filter);
			$it = new \IteratorIterator($cursor);
			$it->rewind();
			$document = $it->current();
			$bulkWrite->update([
				'_id' => $document->_id,
			], [
				'$set' => $user,
			]);
		}
		$result = $mongo->write($bulkWrite);

		return $this->id;
	}

	public static function getGroupMembers ($group, $limit = 100)
	{
		$return = [];
		$mongo = MongoDb::getInstance('users');
		$cursor = $mongo->query(['groups' => $group], ['limit' => $limit, 'sort' => ['groups' => -1, 'name.first' => 1, 'name.last' => 1]]);
		foreach ($cursor as $document) {
			$return[] = self::mongoToUser($document, new User());
		}
		return $return;
	}

	public static function updateGroup (string $name, string $description, $edit, ?int $newid = null): bool
	{
		if (!is_int($edit)) {
			throw new \Exception('Not implemented.');
		}
		if ($newid !== null) {
			// Updating group id not implemented yet. Remember to update all user members of this group.
			return false;
		}
		if (!preg_match('/^[a-z\-]+$/', $name)) {
			return false;
		}

		$mongo = MongoDb::getInstance('users');

		$cursor = $mongo->query(['name' => $name], [], 'groups');
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		$group = $it->current();
		if ($group === null) {
			return false;
		}

		$id = $group->_id;
		$group = json_decode(json_encode($group), true);
		unset($group['_id']);

		$group['name'] = $name;
		$group['description'] = $description;

		$bulkWrite = new \MongoDB\Driver\BulkWrite;
		$bulkWrite->update([
			'_id' => $id,
		], [
			'$set' => $group,
		]);
		$result = $mongo->write($bulkWrite, 'groups');

		return true;
	}

	public static function addGroup (string $name, string $description, int $id = null): bool
	{
		if (($id !== null) && ($id < 3)) {
			return false;
		}
		if (!preg_match('/^[a-z\-]+$/', $name)) {
			return false;
		}
		$mongo = MongoDb::getInstance('users');
		$groups = self::getGroups();

		foreach ($groups as $group) {
			if ($group['name'] === $name) {
				return false;
			}
		}

		if ($id === null) {
			$id = 1001;
			foreach ($groups as $group) {
				if ($group['id'] >= $id) {
					$id = $group['id'] + 1;
				}
			}
		}
		else {
			foreach ($groups as $group) {
				if ($group['id'] === $id) {
					return false;
				}
			}
		}

		$group = [
			'id' => $id,
			'name' => $name,
			'description' => $description,
		];

		$bulkWrite = new \MongoDB\Driver\BulkWrite;
		$bulkWrite->insert($group);
		$result = $mongo->write($bulkWrite, 'groups');

		return true;
	}

	public function leaveGroup ($group): void
	{
		if (!is_int($group)) {
			throw new \Exception('Not implemented.');
		}
		unset($this->groups[$group]);
		$this->save();
	}

	public static function deleteGroup ($group): bool
	{
		if (!is_int($group)) {
			throw new \Exception('Not implemented.');
		}

		if ($group < 3) {
			throw new \Exception('Can not delete this group.');
		}

		$mongo = MongoDb::getInstance('users');

		$members = self::getGroupMembers($group);
		foreach ($members as $member) {
			$member->leaveGroup($group);
		}
		$bulkWrite = new \MongoDB\Driver\BulkWrite;
		$bulkWrite->delete(['id' => $group]);
		$result = $mongo->write($bulkWrite, 'groups');

		return true;
	}

	public function authLoad (string $type, array $params): bool
	{
		$user = $this->authGet($type, $params);
		if ($user !== null) {
			self::mongoToUser($user, $this);
			return true;
		}
		return false;
	}

	public function authUsed (string $type, array $params): bool
	{
		$user = $this->authGet($type, $params);
		if ($user !== null) {
			return true;
		}
		return false;
	}

	public static function asDateTime ($input = null): ?\MongoDB\BSON\UTCDateTime
	{
		$mongo = MongoDb::getInstance('users');
		return $mongo->asDateTime($input);
	}

	public static function getGroups (): array
	{
		$return = [];

		$mongo = MongoDb::getInstance('users');

		$cursor = $mongo->query([], ['sort' => ['id' => 1]], 'groups');
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		if ($it->current() === null) {
			$bulkWrite = new \MongoDB\Driver\BulkWrite;
			$bulkWrite->insert([
				'id' => 2,
				'name' => 'root',
				'description' => 'Server root.',
			]);
			$result = $mongo->write($bulkWrite, 'groups');

			$cursor = $mongo->query([], [], 'groups');
			$it = new \IteratorIterator($cursor);
			$it->rewind();
		}
		foreach ($it as $group) {
			$return[$group->id] = [
				'id' => $group->id,
				'name' => $group->name,
				'description' => $group->description,
			];
		}

		return $return;
	}

	public static function getUser (int $id): User
	{
		$mongo = MongoDb::getInstance('users');

		$filter = [
			'id' => $id,
		];

		$cursor = $mongo->query($filter);
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		$document = $it->current();

		return self::mongoToUser($document, new User());
	}

	public static function deleteUser (int $id): bool
	{
		$mongo = MongoDb::getInstance('users');

		$bulkWrite = new \MongoDB\Driver\BulkWrite;
		$bulkWrite->delete([
			'id' => $id,
		]);
		$result = $mongo->write($bulkWrite);
		if ($result->getDeletedCount() > 0) {
			return true;
		}
		return false;
	}

	public static function getUsers (int $limit = 100, array $options = []): array
	{
		$return = [];
		$mongo = MongoDb::getInstance('users');

		$command = [
			'aggregate' => 'users',
			'pipeline' => [],
			'cursor' => new \StdClass(),
		];

		$match = [];
		if (isset($options['q'])) {
			$q = explode(' ', $options['q']);
			array_walk($q, function (&$item) {
				// $item = str_replace(['&'], ['\\&'], preg_quote($item));
				$item = preg_quote($item);
			});
			$q = '(?=.*' . implode(')(?=.*', $q) . ')';
			$q = new \MongoDB\BSON\Regex(str_replace(' ', '|', $q), 'i');
			$match[] = ['$or' => [
				['name.first' => ['$regex' => $q]],
				['name.last' => ['$regex' => $q]],
				['email' => ['$regex' => $q]],
				['auth.email' => ['$regex' => $q]],
			]];
		}

		if (isset($options['auth'])) {
			$match[] = ['auth.type' => $options['auth']];
		}
		if (isset($options['group'])) {
			$match[] = ['groups' => $options['group']];
		}
		if (isset($options['hasField'])) {
			if (!is_array($options['hasField'])) {
				$options['hasField'] = [$options['hasField']];
			}
			foreach ($options['hasField'] as $field) {
				$match[] = ['fields.' . $field => ['$ne' => null]];
			}
		}

		if (!empty($match)) {
			$command['pipeline'][] = ['$match' => [
				'$and' => $match,
			]];
		}

		$command['pipeline'][] = ['$addFields' => [
			'hasfirst' => ['$eq' => ['$name.first', '']],
			'haslast' => ['$eq' => ['$name.last', '']],
			'hasgroups' => ['$ifNull' => [['$min' => '$groups'], '']],
			// 'numauth' => ['$size' => '$auth'],
		]];
		$command['pipeline'][] = ['$sort' => [
			'hasgroups' => 1,
			'hasfirst' => 1,
			'haslast' => 1,
			// 'numauth' => -1,
			'name.first' => 1,
			// 'name.last' => 1,
			'email' => 1,
		]];
		$skip = 0;
		if (isset($options['offset'])) {
			$skip = $options['offset'];
		}
		$command['pipeline'][] = ['$facet' => [
			'result' => [
				['$skip' => $skip],
				['$limit' => $limit],
			],
			'total' => [
				['$count' => 'count'],
			],
		]];

		$cursor = $mongo->command($command);

		$return = [
			'total' => 0,
			'users' => [],
		];
		foreach ($cursor as $facet) {
			$total = current($facet->total);
			if ($total === false) {
				$total = 0;
			}
			else {
				$total = $total->count;
			}
			$return['total'] = $total;
			foreach ($facet->result as $document) {
				$return['users'][] = self::mongoToUser($document, new User());
			}
		}
		return $return;
	}

	public static function getUserCount (): int
	{
		$mongo = MongoDb::getInstance('users');
		return $mongo->count([
			'id' => ['$gt' => 0],
		]);
	}

	public static function verifyEmail (string $token): ?User
	{
		$mongo = MongoDb::getInstance('users');

		$filter = [
			'auth.type' => 'local',
			'auth.verify' => $token,
		];

		$cursor = $mongo->query($filter);
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		$document = $it->current();
		if ($document === null) {
			return null;
		}

		$user = self::mongoToUser($document, new User());

		foreach ($user->auth['local'] as &$auth) {
			if ($auth['verify'] === $_SERVER['QUERY_STRING']) {
				unset($auth['verify']);
				$auth['verified'] = $user->asDateTime();
				break;
			}
		}
		$user->save();

		return $user;
	}

	public static function recover (string $email): ?User
	{
		$mongo = MongoDb::getInstance('users');

		$filter = [
			'auth.type' => 'local',
			'auth.email' => $email,
		];

		$cursor = $mongo->query($filter);
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		$document = $it->current();
		if ($document === null) {
			return null;
		}
		return self::mongoToUser($document, new User());
	}

	public function setRecovered (string $token, string $password)
	{
		foreach ($this->auth['local'] as &$auth) {
			if (isset($auth['recover']) && ($auth['recover'] === $token)) {
				$this->updatePassword($auth['email'], $password);
				unset($auth['recover']);
				unset($auth['recover_dt']);
				$this->save();
			}
		}
	}

	public static function checkRecovery (string $token, string $email = null, $validity = '-1 hour'): ?User
	{
		$mongo = MongoDb::getInstance('users');

		$filter = [
			'auth.type' => 'local',
			'auth.recover' => $token,
			// 'auth.recover_dt' => [
			// 	'$gt' => User::asDateTime('-10 years'),
			// ],
		];
		if ($email !== null) {
			$filter['auth.email'] = $email;
		}

		if (is_string($validity)) {
			$validity = strtotime($validity);
		}

		$cursor = $mongo->query($filter);
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		$document = $it->current();
		if ($document === null) {
			return null;
		}

		$user = self::mongoToUser($document, new User());
		foreach ($user->auth['local'] as $auth) {
			if ($auth['recover'] === $token) {
				$dt = strtotime($auth['recover_dt']);
				if ($dt > $validity) {
					return $user;
				}
			}
		}
		return null;
	}

	private function authGet (string $type, array $params): ?\stdClass
	{
		$mongo = MongoDb::getInstance('users');

		$type = strtolower($type);

		if (in_array($type, $this->authLoadTypes)) {

			$filter = [
				'auth.type' => $type,
			];
			foreach ($params as $name => $value) {
				$filter['auth.' . $name] = $value;
			}

			$cursor = $mongo->query($filter);
			$it = new \IteratorIterator($cursor);
			$it->rewind();
			$document = $it->current();
			return $document;
		}
		return null;
	}

	protected static function mongoToUser (\stdClass $document, User $user): User
	{
		$user->id = $document->id;
		$user->firstName = $document->name->first;
		if (property_exists($document->name, 'middle')) {
			$user->middleName = $document->name->middle;
		}
		$user->lastName = $document->name->last;
		$user->email = $document->email;

		$user->created = date('Y-m-d H:i:s', $document->created->toDateTime()->getTimestamp());

		$user->groups = [];
		if (property_exists($document, 'groups')) {
			foreach ($document->groups as $group) {
				$user->groups[$group] = '';
			}
		}

		$user->field = [];
		if (property_exists($document, 'fields')) {
			foreach ($document->fields as $group => $data) {
				foreach ($data as $key => $value) {
					if (is_object($value)) {
						$value = json_decode(json_encode($value), true);
					}
					$user->field[$group][$key] = $value;
				}
			}
		}

		$user->auth = [];
		foreach ($document->auth as $auth) {
			$data = [];
			foreach ($auth as $key => $value) {
				if ($key === 'type') {
					continue;
				}
				if (is_string($value)) {
					$data[$key] = $value;
				}
				elseif ($value instanceof \MongoDB\BSON\UTCDateTime) {
					$data[$key] = date('Y-m-d H:i:s', $value->toDateTime()->getTimestamp());
				}
				else {
					$e = new \gimle\Exception('Unknown value.');
					$e->set('prop', $value);
					throw $e;
				}
			}
			$user->auth[$auth->type][] = $data;
		}

		$user->setNames();

		if (method_exists(static::class, 'postMongoToUser')) {
			static::postMongoToUser($document, $user);
		}

		return $user;
	}
}
