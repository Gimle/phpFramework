<?php
declare(strict_types=1);
/**
 * Canvas Utilities.
 */

namespace gimle\canvas;

/**
 * Canvas class.
 */
class Canvas
{
	/**
	 * Error constant for reserved method names.
	 *
	 * @var int
	 */
	public const E_RESERVED_NAME = 1;

	/**
	 * The template to use.
	 *
	 * @var string
	 */
	private static $template = '';

	/**
	 * Magically generated variables for the canvas.
	 *
	 * @var array
	 */
	private static $magic = [];

	/**
	 * Load a canvas.
	 *
	 * @param string $filename
	 * @return mixed The return value of the file.
	 */
	public static function _set (string $filename)
	{
		ob_start();
		$return = include $filename;
		$canvas = ob_get_contents();
		ob_end_clean();
		self::$template = $canvas;
		ob_start();
		return $return;
	}

	/**
	 * Override a previously set canvas.
	 *
	 * @param string $filename
	 * @return mixed The return value of the file.
	 */
	public static function _override (string $filename)
	{
		ob_end_clean();
		return self::_set($filename);
	}

	/**
	 * Create the canvas from template.
	 *
	 * @param bool $return Return or print the canvas.
	 * @return ?string The canvas if $return is true, or null if $return is false.
	 */
	public static function _create (bool $return = false): ?string
	{
		$content = ob_get_contents();
		ob_end_clean();

		$template = self::$template;
		$replaces = ['%content%'];
		$withs = [$content];

		if (!empty(self::$magic)) {
			foreach (self::$magic as $replace => $with) {
				if (is_array($with)) {
					$withTmp = [];
					foreach ($with as $value) {
						if (!is_array($value)) {
							$withTmp[] = $value;
						}
					}
					$with = implode("\n", $withTmp);
					unset($withTmp);
				}
				$replaces[] = '%' . $replace . '%';
				$withs[] = $with;
			}
		}

		preg_match_all('/%[a-z]+%/', $template, $matches);
		if (!empty($matches)) {
			foreach ($matches[0] as $match) {
				if (!in_array($match, $replaces)) {
					$template = str_replace($match, '', $template);
				}
			}
		}
		$template = str_replace($replaces, $withs, $template);

		if ($return === false) {
			echo $template;
			return null;
		}
		return $template;
	}

	/**
	 * Set or get custom variables.
	 *
	 * This method will overwrite previous value by default.
	 * To append instead of overwrite, set second parameter to true.
	 * To unset a value, set the value to null.
	 *
	 * <p>Example setting a value.</p>
	 * <code>Canvas::title('My page');</code>
	 *
	 * <p>Example appending a value.</p>
	 * <code>Canvas::title('My page', true);</code>
	 *
	 * <p>Example setting a value at a position (You can also use named positions).</p>
	 * <code>Canvas::title('My page', $pos);</code>
	 *
	 * <p>Example removing a variable.</p>
	 * <code>Canvas::title(null);</code>
	 *
	 * @param string $method
	 * @param array $params
	 * @return mixed
	 */
	public static function __callStatic (string $method, array $params)
	{
		if (substr($method, 0, 1) === '_') {
			throw new \Exception('Methods starting with underscore is reserved for functionality, and should not be used for variables.', self::E_RESERVED_NAME);
		}

		if (empty($params)) {
			if (isset(self::$magic[$method])) {
				return self::$magic[$method];
			}
			return false;
		}
		if (!isset($params[1])) {
			if (($params[0] !== null) && (!is_bool($params[0]))) {
				self::$magic[$method] = array($params[0]);
			}
			elseif ($params[0] === null) {
				unset(self::$magic[$method]);
			}
		}
		else {
			if (($params[1] !== null) && (!is_bool($params[1]))) {
				self::$magic[$method][$params[1]] = $params[0];
			}
			elseif ($params[1] === true) {
				self::$magic[$method][] = $params[0];
			}
		}

		return;
	}
}
