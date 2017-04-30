<?php
declare(strict_types=1);
namespace gimle\sql;

/**
 * MySQL Result class.
 */
class Mysqliresult
{
	/**
	 * Result set
	 *
	 * @var object mysqli_result Object
	 */
	private $result;

	/**
	 * Create a new mysqli_result object.
	 *
	 * @param \mysqli_result $result mysqli_result Object
	 * @return object mysqli_result Object
	 */
	public function __construct (\mysqli_result $result)
	{
		$this->result = $result;
	}

	/**
	 * Fetch rows and return them all in a typesensitive array.
	 *
	 * @return mixed
	 */
	public function get_assoc ()
	{
		for ($i = 0; $i < $this->field_count; $i++) {
			$tmp = $this->fetch_field_direct($i);
			$finfo[$tmp->name] = $tmp->type;
			unset($tmp);
		}
		$result = $this->fetch_assoc();
		if ($result === null) {
			return false;
		}
		foreach ($result as $key => $value) {
			if ($result[$key] === null) {
			}
			elseif (in_array($finfo[$key], [1, 2, 3, 8, 9])) {
				$result[$key] = (int)$result[$key];
			}
			elseif (in_array($finfo[$key], [4, 5, 246])) {
				$result[$key] = (float)$result[$key];
			}
		}
		return $result;
	}

	/**
	 * Performs different operations depending on argument types.
	 *
	 * @param string $name Method name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call (string $name, array $arguments)
	{
		return call_user_func_array([$this->result, $name], $arguments);
	}

	/**
	 * Set a value.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set (string $name, $value): void
	{
		$this->result->$name = $value;
	}

	/**
	 * Retrieve a value.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get (string $name)
	{
		return $this->result->$name;
	}
}
