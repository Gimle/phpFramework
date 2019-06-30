<?php
declare(strict_types=1);
namespace gimle\user\storage;

use \gimle\xml\SimpleXmlElement;

use const \gimle\MAIN_STORAGE_DIR;

class Xml extends \gimle\user\UserBase
{
	public function save (): ?int
	{
		if (!$this->canSave()) {
			return null;
		}

		$sxml = SimpleXmlElement::open(MAIN_STORAGE_DIR . 'users.xml', '<users/>');

		if ($this->id === null) {
			$this->id = $sxml->getNextId();
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
					$sub[$key] = $value;
				}
			}
		}

		if (!empty($this->field)) {
			$field = $user->addChild('field');
			foreach ($this->field as $method => $values) {
				foreach ($values as $value) {
					$sub = $field->addChild('string');
					$sub['name'] = $method;
					$sub[0] = $value;
				}
			}
		}

		$sxml->save(MAIN_STORAGE_DIR . 'users.xml', true);

		return $this->id;
	}

	public function authLoad ($type, $params)
	{
		$user = $this->authGet($type, $params);
		if ($user !== false) {
			self::xmlToUser($user, $this);
			return true;
		}
		return false;
	}

	public function authUsed ($type, $params)
	{
		$user = $this->authGet($type, $params);
		if ($user !== false) {
			return true;
		}
		return false;
	}

	private function authGet ($type, $params)
	{
		$type = strtolower($type);
		if (in_array($type, $this->authLoadTypes)) {
			$sxml = SimpleXmlElement::open(MAIN_STORAGE_DIR . 'users.xml', '<users/>');
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
		return false;
	}

	private static function xmlToUser ($sxml, $user)
	{
		$user->id = (int) $sxml['id'];
		$user->firstName = (string) $sxml->name->first;
		if ((string) $sxml->name->middle !== '') {
			$user->middleName = (string) $sxml->name->middle;
		}
		$user->lastName = (string) $sxml->name->last;
		$user->email = (string) $sxml->email;

		$user->groups = [];
		$groups = explode(',', (string) $sxml['groups']);
		foreach ($groups as $group) {
			$user->groups[trim($group)] = '';
		}

		$user->field = [];
		foreach ($sxml->xpath('./field/*') as $field) {
			$user->field[$field->getName()][] = (string) $field;
		}

		$user->auth = [];
		foreach ($sxml->xpath('./auth/*') as $auth) {
			$sauth = [];
			foreach ($auth->attributes() as $name => $value) {
				$sauth[$name] = (string) $value;
			}
			$user->auth[$auth->getName()][] = $sauth;
		}

		$user->setNames();
	}
}
