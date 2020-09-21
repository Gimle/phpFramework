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

		$cursor = $mongo->query([], [], 'groups');
		$it = new \IteratorIterator($cursor);
		$it->rewind();
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

	public static function getUsers (): array
	{
		$return = [];
		$mongo = MongoDb::getInstance('users');

		$cursor = $mongo->query([]);
		foreach ($cursor as $document) {
			$return[] = self::mongoToUser($document, new User());
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

	private static function mongoToUser (\stdClass $document, User $user): User
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
		foreach ($document->fields as $group => $data) {
			foreach ($data as $key => $value) {
				if (is_object($value)) {
					$value = json_decode(json_encode($value), true);
				}
				$user->field[$group][$key] = $value;
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
