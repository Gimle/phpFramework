<?php
declare(strict_types=1);
namespace gimle\template;

/**
 * Template utility class
 */
class Template
{
	/**
	 * Bound variables.
	 *
	 * @var array
	 */
	private $bindVars = [];

	/**
	 * Template holder.
	 *
	 * @var string
	 */
	private $template = '';

	/**
	 * Create a new Template object.
	 *
	 * @return object
	 */
	public function __construct (string $template)
	{
		$this->template = $template;
	}

	/**
	 * Bind a variable for the template.
	 *
	 * @var string $key The variable name.
	 * @var string $value The contents of the variable.
	 * @return void
	 */
	public function bind (string $key, string $value): void
	{
		$this->bindVars[$key] = $value;
	}

	/**
	 * Get the template.
	 *
	 * @return string
	 */
	public function get (): string
	{
		foreach ($this->bindVars as $key => $value) {
			$this->template = str_replace('%' . $key . '%', $value, $this->template);
		}

		return $this->template;
	}
}
