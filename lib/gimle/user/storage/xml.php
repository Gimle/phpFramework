<?php
declare(strict_types=1);
namespace gimle\user\storage;

use \gimle\xml\SimpleXmlElement;
use \gimle\User;
use \gimle\Exception;

use function \gimle\sp;

use const \gimle\MAIN_STORAGE_DIR;

class Xml extends \gimle\user\UserBase
{

	private static $xmlFileLocation = null;

	public function save (): ?int
	{
		if (!$this->canSave()) {
			return null;
		}

		$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');

		if ($this->id === null) {
			$this->id = max($this->getAi(), $sxml->getNextId('id', 'user'));
			$this->updateAi($this->id + 1);
			$user = $sxml->addChild('user');
			$user['id'] = $this->id;
			$user['created'] = $sxml->asDateTime();
		}
		else {
			$user = current($sxml->xpath('/users/user[@id=' . $this->id . ']'));
			$user[0] = null;
		}

		$name = $user->addChild('name');
		$sub = $name->addChild('first');
		$sub[0] = $this->firstName;
		if ($this->middleName !== null) {
			$sub = $name->addChild('middle');
			$sub[0] = $this->middleName;
		}
		$sub = $name->addChild('last');
		$sub[0] = $this->lastName;

		$email = $user->addChild('email');
		$email[0] = $this->email;

		$this->callUses('save');

		unset($user['groups']);
		if (!empty($this->groups)) {
			ksort($this->groups);
			$user['groups'] = implode(',', array_keys($this->groups));
		}

		$auth = $user->addChild('auth');
		foreach ($this->auth as $method => $params) {
			foreach ($params as $param) {
				$sub = $auth->addChild($method);
				foreach ($param as $key => $value) {
					if ($key === 'last_used') {
						$value = $sxml->asDateTime($value);
					}
					$sub[$key] = $value;
				}
			}
		}

		if (!empty($this->field)) {
			$fields = $user->addChild('fields');
			foreach ($this->field as $method => $values) {
				$group = $fields->addChild('group');
				$group['name'] = $method;
				foreach ($values as $index => $value) {
					$sub = $group->addChild('string');
					$sub['name'] = $index;
					$sub[0] = $value;
				}
			}
		}

		if (method_exists($this, 'postSave')) {
			$this->postSave($user);
		}

		$sxml->save(self::getXmlLocation(), true);

		return $this->id;
	}

	public function authLoad (string $type, array $params): bool
	{
		$user = $this->authGet($type, $params);
		if ($user !== null) {
			self::xmlToUser($user, $this);
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

	public static function asDateTime ($input = null): ?string
	{
		$sxml = new SimpleXmlElement('<dt/>');
		return $sxml->asDateTime($input);
	}

	public static function getGroups (): array
	{
		$return = [];
		$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');
		foreach ($sxml->xpath('/users/group') as $group) {
			$return[(int) $group['id']] = [
				'id' => (int) $group['id'],
				'name' => (string) $group['name'],
				'description' => trim((string) $group),
			];
		}
		return $return;
	}

	public static function getUser (int $id): User
	{
		$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');
		$user = current($sxml->xpath('/users/user[@id=' . $id . ']'));
		return self::xmlToUser($user, new User());
	}

	public static function deleteUser (int $id): bool
	{
		$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');
		$user = current($sxml->remove('/users/user[@id=' . $id . ']'));
		if ((int) $user['id'] === $id) {
			$sxml->save(self::getXmlLocation(), true);
			return true;
		}
		return false;
	}

	public static function getUsers (): array
	{
		$return = [];
		$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');
		foreach ($sxml->xpath('/users/user') as $user) {
			$return[] = self::xmlToUser($user, new User());
		}
		return $return;
	}

	public static function getUserCount (): int
	{
		$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');
		$count = count($sxml->xpath('/users/user'));
		return $count;
	}

	public static function setXmlLocation (string $location): void
	{
		self::$xmlFileLocation = $location;
	}

	private static function getXmlLocation (): string
	{
		if (self::$xmlFileLocation === null) {
			self::$xmlFileLocation = MAIN_STORAGE_DIR . 'users.xml';
		}
		return self::$xmlFileLocation;
	}

	private function authGet (string $type, array $params): ?SimpleXmlElement
	{
		$type = strtolower($type);
		if (in_array($type, $this->authLoadTypes)) {
			$sxml = SimpleXmlElement::open(self::getXmlLocation(), '<users/>');
			$xp = '/users/user/auth/' . $type;
			foreach ($params as $name => $value) {
				$xp .= '[@' . $name . '=' . $sxml->real_escape_string($value) . ']';
			}
			$xp .= '/../..';
			$user = current($sxml->xpath($xp));

			if ($user !== false) {
				return $user;
			}
		}
		return null;
	}

	private static function xmlToUser (SimpleXmlElement $sxml, User $user): User
	{
		$user->id = (int) $sxml['id'];
		$user->firstName = (string) $sxml->name->first;
		if ((string) $sxml->name->middle !== '') {
			$user->middleName = (string) $sxml->name->middle;
		}
		$user->lastName = (string) $sxml->name->last;
		$user->email = (string) $sxml->email;

		$user->created = date('Y-m-d H:i:s', strtotime((string) $sxml['created']));

		$user->groups = [];
		$groups = explode(',', (string) $sxml['groups']);
		foreach ($groups as $group) {
			if (trim($group) !== '') {
				$user->groups[trim($group)] = '';
			}
		}

		$user->field = [];
		foreach ($sxml->xpath('./fields/group') as $group) {
			foreach ($group->xpath('./*') as $field) {
				$user->field[(string) $group['name']][(string) $field['name']] = (string) $field;
			}
		}

		$user->auth = [];
		foreach ($sxml->xpath('./auth/*') as $auth) {
			$sauth = [];
			foreach ($auth->attributes() as $name => $value) {
				if ($name === 'last_used') {
					$value = date('Y-m-d H:i:s', strtotime((string) $value));
				}
				$sauth[$name] = (string) $value;
			}
			$user->auth[$auth->getName()][] = $sauth;
		}

		$user->setNames();

		if (method_exists(static::class, 'postXmlToUser')) {
			static::postXmlToUser($sxml, $user);
		}

		return $user;
	}
}
