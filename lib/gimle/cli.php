<?php
declare(strict_types=1);
namespace gimle;

class Cli
{

	/**
	 * A holder for the description from the start method.
	 *
	 * @var string
	 */
	private static $description = null;

	/**
	 * A holder for the options from the start method.
	 *
	 * @var array
	 */
	private static $options = [];

	/**
	 * Start a cli script.
	 *
	 * @param string $description The description of the program. Will show up in the help menu.
	 * @param array $options Possible command line options this the program supports.
	 * @return void
	 */
	public static function start (string $description, array $options = []): void
	{
		self::$description = $description;
		self::$options = $options;

		$_SERVER['options'] = $_SERVER['params'] = [];
		$nextMightBeValue = false;
		foreach ($_SERVER['argv'] as $key => $value) {
			if ($key > 0) {
				if (($nextMightBeValue) && (substr($value, 0, 1) === '-')) {
					$_SERVER['options'][$nextMightBeValue][] = true;
				}
				elseif ($nextMightBeValue) {
					$_SERVER['options'][$nextMightBeValue][] = $value;
					$nextMightBeValue = false;
					continue;
				}

				if (substr($value, 0, 2) === '--') {
					$nextMightBeValue = substr($value, 2);
					if (!isset($options[$nextMightBeValue])) {
						self::createHelp();
					}
					continue;
				}
				if (substr($value, 0, 1) === '-') {
					$shorts = str_split(substr($value, 1));
					$fulls = [];
					foreach ($shorts as $short) {
						foreach ($options as $full => $option) {
							if ((isset($option['short'])) && ($option['short'] === $short)) {
								$fulls[] = $full;
								continue 2;
							}
						}
						self::createHelp();
					}
					$nextMightBeValue = array_pop($fulls);
					foreach ($fulls as $full) {
						$_SERVER['options'][$full][] = true;
					}
					continue;
				}

				$_SERVER['params'][] = $value;
			}
		}
		if ($nextMightBeValue) {
			$_SERVER['options'][$nextMightBeValue][] = true;
		}

		if (!empty($options)) {
			foreach ($options as $key => $value) {
				if ((isset($value['short'])) && (isset($_SERVER['options'][$value['short']]))) {
					if (!isset($_SERVER['options'][$key])) {
						$_SERVER['options'][$key] = $_SERVER['options'][$value['short']];
					}
					unset($_SERVER['options'][$value['short']]);
				}
			}

			foreach (self::$options as $key => $value) {
				if ((isset($value['required'])) && ($value['required'] === true)) {
					if (!isset($_SERVER['options'][$key])) {
						self::createHelp();
					}
				}
			}

			if (isset($_SERVER['options']['help']) && ($_SERVER['options']['help'] === true)) {
				self::createHelp();
			}
		}
	}

	/**
	 * Create cli help screen and exit the program.
	 *
	 * @return void
	 */
	public static function createHelp (): void
	{
		$options = array_reverse(self::$options);
		$options['help'] = [
			'short' => 'h',
			'description' => 'Show help',
		];
		$options = array_reverse($options);

		$len = 1;
		$result = '';
		foreach (array_keys($options) as $value) {
			if (strlen($value) > $len) {
				$len = strlen($value);
			}
		}
		foreach ($options as $key => $value) {
			$result .= '  ';
			if (isset($value['short'])) {
				$result .= '-' . $value['short'] . ', ';
			}
			else {
				$result .= '    ';
			}
			$result .= '--' . str_pad($key, $len + 2, ' ', STR_PAD_RIGHT);
			$result .= $value['description'] . "\n";
		}
		echo "\n" . self::$description;
		echo "\n\nOptions:\n";
		echo $result . "\n";
		exit(0);
	}
}
