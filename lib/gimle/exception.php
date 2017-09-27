<?php
declare(strict_types=1);
namespace gimle;

class Exception extends \Exception
{
	/**
	 * A holder for the private properties.
	 */
	private $params;

	/**
	 * Set a custom property to the exception.
	 *
	 * @param string $key The name of the property.
	 * @param mixed $value The value of the property.
	 * @return void
	 */
	public function set (string $key, $value): void
	{
		$this->params = array_merge_distinct($this->params, string_to_nested_array($key, $value));
	}

	public function setMessage (string $message): void
	{
		$this->message = $message;
	}

	public function setCode (int $code): void
	{
		$this->code = $code;
    }

	/**
	 * Get a custom property to the exception.
	 *
	 * @param ?string $key The name of the property or null to get all.
	 * @return mixed The value of the given property, or all if $key = null
	 */
	public function get (?string $key = null)
	{
		if ($key === null) {
			return $this->params;
		}

		if (isset($this->params[$key])) {
			return $this->params[$key];
		}

		return null;
	}
}
