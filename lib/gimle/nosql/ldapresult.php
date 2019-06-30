<?php
declare(strict_types=1);
namespace gimle\nosql;

class LdapResult
{
	private $result = null;

	public function __construct ($result)
	{
		$this->result = $result;
		unset($this->result['count']);
	}

	public function fetch ()
	{
		$return = [];
		$result = current($this->result);
		if ($result === false) {
			return null;
		}
		next($this->result);

		foreach ($result as $name => $entry) {
			if (is_int($name)) {
				continue;
			}
			if ($name === 'count') {
				continue;
			}
			$return[$name] = $this->removeCount($entry);
		}
		return $return;
	}

	private function removeCount ($array)
	{
		if (!is_array($array)) {
			return $array;
		}

		$return = [];
		foreach ($array as $name => $entry) {
			if ($name === 'count') {
				continue;
			}
			$return[$name] = $entry;
		}
		return $return;
	}
}
