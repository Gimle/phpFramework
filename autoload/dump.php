<?php
declare(strict_types=1);
namespace gimle;

/**
 * Dumps a varialble from the global scope.
 *
 * @param mixed $var The variable to dump.
 * @param bool $return Return output? (Default: false)
 * @param ?string $title string|null Alternate title for the dump.
 * @param ?string $background Override the default background.
 * @param string $mode Default "auto", can be: "cli" or "web".
 * @return ?string
 */
function d ($var, bool $return = false, ?string $title = null, ?string $background = null, string $mode = 'auto'): ?string
{
	if ($title === null) {
		$title = [
			'steps' => 1,
			'match' => '/d\((.*)/'
		];
	}
	return var_dump($var, $return, $title, $background, $mode);
}

/**
 * Dumps a varialble from the global scope terminal style. No colours, no tags, no web escapes.
 *
 * @param mixed $var The variable to dump.
 * @param bool $return Return output? (Default: false)
 * @param ?string $title string|null Alternate title for the dump.
 * @param ?string $background Override the default background.
 * @return ?string
 */
function terminal_dump ($var, bool $return = false, ?string $title = null, ?string $background = 'black'): ?string
{
	if ($title === null) {
		$title = [
			'steps' => 1,
			'match' => '/terminal_dump\((.*)/'
		];
	}
	return var_dump($var, $return, $title, $background, 'terminal');
}

/**
 * Dumps a varialble from the global scope cli style. Cli colours, no tags, no web escapes.
 *
 * @param mixed $var The variable to dump.
 * @param bool $return Return output? (Default: false)
 * @param ?string $title string|null Alternate title for the dump.
 * @param ?string $background Override the default background.
 * @return ?string
 */
function cli_dump ($var, bool $return = false, ?string $title = null, ?string $background = 'black'): ?string
{
	if ($title === null) {
		$title = [
			'steps' => 1,
			'match' => '/cli_dump\((.*)/'
		];
	}
	return var_dump($var, $return, $title, $background, 'cli');
}

/**
 * Dumps a varialble from the global scope web style. Web colours, Tags, Web escapes.
 *
 * @param mixed $var The variable to dump.
 * @param bool $return Return output? (Default: false)
 * @param ?string $title string|null Alternate title for the dump.
 * @param ?string $background Override the default background.
 * @return ?string
 */
function web_dump ($var, bool $return = false, ?string $title = null, ?string $background = null): ?string
{
	if ($title === null) {
		$title = [
			'steps' => 1,
			'match' => '/web_dump\((.*)/'
		];
	}
	return var_dump($var, $return, $title, $background, 'web');
}

/**
 * Dumps a varialble from the global scope.
 *
 * @param mixed $var The variable to dump.
 * @param bool $return Return output? (Default: false)
 * @param ?string $title string|bool|array Alternate title for the dump, or to backtrace.
 * @param ?string $background Override the default background.
 * @param string $mode Default "auto", can be: "cli" or "web".
 * @return ?string
 */
function var_dump ($var, bool $return = false, $title = null, ?string $background = null, ?string $mode = 'auto'): ?string
{
	if ($background === null) {
		$background = 'white';
	}

	if ($mode === 'auto') {
		$webmode = (ENV_MODE & ENV_WEB ? true : false);
	}
	elseif ($mode === 'web') {
		$webmode = true;
	}
	elseif ($mode === 'cli') {
		$webmode = false;
	}
	elseif ($mode === 'terminal') {
		$webmode = false;
	}
	else {
		trigger_error('Invalid mode.', E_USER_WARNING);
	}

	$fixDumpString = function (string $name, string $value, bool $htmlspecial = true) use (&$background, &$mode): string {
		if (in_array($name, array('[\'pass\']', '[\'password\']', '[\'PHP_AUTH_PW\']'))) {
			$value = '********';
		}
		else {
			$fix = array(
				"\r\n" => colorize('¤¶', 'gray', $background, $mode) . "\n", // Windows linefeed.
				"\n\r" => colorize('¶¤', 'gray', $background, $mode) . "\n\n", // Erronumous (might be interpeted as double) linefeed.
				"\n"   => colorize('¶', 'gray', $background, $mode) . "\n", // UNIX linefeed.
				"\r"   => colorize('¤', 'gray', $background, $mode) . "\n" // Old mac linefeed.
			);
			$value = strtr(($htmlspecial ? htmlspecialchars($value) : $value), $fix);
		}
		return $value;
	};

	$recursionClasses = array();

	$dodump = function ($var, ?string $var_name = null, int $indent = 0, array $params = []) use (&$dodump, &$fixDumpString, &$background, &$webmode, &$mode, &$recursionClasses): void {
		if (is_object($var)) {
			if (!empty($recursionClasses)) {
				$add = true;
				foreach ($recursionClasses as $class) {
					if ($var === $class) {
						$add = false;
					}
				}
				if ($add === true) {
					$recursionClasses[] = $var;
				}
			}
			else {
				$recursionClasses[] = $var;
			}
		}

		$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
		echo str_repeat($doDump_indent, $indent) . colorize(($webmode === true ? htmlentities($var_name) : $var_name), 'varname', $background, $mode);

		if (is_callable($var)) {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('*CALLABLE*', 'recursion', $background, $mode);
		}
		elseif (is_array($var)) {
			echo ' ' . colorize('=>', 'black', $background, $mode) . ' ' . colorize('Array (' . count($var) . ')', 'gray', $background, $mode) . "\n" . str_repeat($doDump_indent, $indent) . colorize('(', 'lightgray', $background, $mode) . "\n";
			foreach ($var as $key => $value) {
				if (is_callable($var[$key])) {
					$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
					echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities($key) : $key) . '\']', 'varname', $background, $mode);
					echo ' ' . colorize('=', 'black', $background, $mode) . ' ';
					if (!is_string($var[$key])) {
						echo colorize('*CALLABLE*', 'recursion', $background, $mode);
					}
					else {
						echo colorize('\'' . (string) $var[$key] . '\'', 'recursion', $background, $mode);
					}
					echo "\n";
					continue;
				}
				if (strpos(print_r($var[$key], true), '*RECURSION*') !== false) {
					$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
					echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities((string) $key) : $key) . '\']', 'varname', $background, $mode);
					echo ' ' . colorize('=', 'black', $background, $mode) . ' ';
					if (!is_string($var[$key])) {
						echo colorize('*RECURSION*', 'recursion', $background, $mode);
					}
					else {
						echo colorize('\'' . (string) $var[$key] . '\'', 'recursion', $background, $mode);
					}
					echo "\n";
					continue;
				}
				if (is_object($value)) {
					$same = false;
					foreach ($recursionClasses as $class) {
						if ($class === $value) {
							$same = true;
						}
					}
					if ($same === true) {
						$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
						echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities($key) : $key) . '\']', 'varname', $background, $mode);
						echo ' ' . colorize('=', 'black', $background, $mode) . ' ';
						echo colorize(get_class($value) . '()', 'recursion', $background, $mode);
						echo "\n";
					}
					elseif (get_class($value) === 'Closure') {
						$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
						echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities($key) : $key) . '\']', 'varname', $background, $mode);
						echo ' ' . colorize('=', 'black', $background, $mode) . ' ';
						echo colorize(get_class($value) . '()', 'recursion', $background, $mode);
						echo "\n";
					}
					else {
						$dodump($value, '[\'' . $key . '\']', $indent + 1);
					}
					continue;
				}
				$dodump($value, '[\'' . $key . '\']', $indent + 1);
			}
			echo str_repeat($doDump_indent, $indent) . colorize(')', 'lightgray', $background, $mode);
		}
		elseif (is_string($var)) {
			if ((isset($params['error'])) && ($params['error'] === true)) {
				echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('Error: ' . $fixDumpString($var_name, $var, $webmode), 'error', $background, $mode);
			}
			else {
				echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('String(' . strlen($var) . ')', 'gray', $background, $mode) . ' ' . colorize('\'' . $fixDumpString($var_name, $var, $webmode) . '\'', 'string', $background, $mode);
			}
		}
		elseif (is_int($var)) {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('Integer(' . strlen((string) $var) . ')', 'gray', $background, $mode) . ' ' . colorize((string) $var, 'int', $background, $mode);
		}
		elseif (is_bool($var)) {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('Boolean', 'gray', $background, $mode) . ' ' . colorize(($var === true ? 'true' : 'false'), 'bool', $background, $mode);
		}
		elseif (is_object($var)) {
			$class = new \ReflectionObject($var);
			$parents = '';
			if ($parent = $class->getParentClass()) {
				$parents .= ' extends ' . $class->getParentClass()->name;
			}
			unset($parent);
			$interfaces = $class->getInterfaces();
			if (!empty($interfaces)) {
				$parents .= ' implements ' . implode(', ', array_keys($interfaces));
			}
			unset($interfaces);


			if ($var instanceof Iterator) {
				echo ' ' . colorize('=>', 'black', $background, $mode) . ' ' . colorize($class->getName() . ' Object (Iterator)' . $parents, 'gray', $background, $mode) . "\n" . str_repeat($doDump_indent, $indent) . colorize('(', 'lightgray', $background, $mode) . "\n";
				var_dump($var);
			}
			else {
				echo ' ' . colorize('=>', 'black', $background, $mode) . ' ' . colorize($class->getName() . ' Object' . $parents, 'gray', $background, $mode) . "\n" . str_repeat($doDump_indent, $indent) . colorize('(', 'lightgray', $background, $mode) . "\n";

				$dblcheck = array();
				foreach ((array) $var as $key => $value) {
					if (!property_exists($var, $key)) {
						$key = ltrim($key, "\x0*");
						if (substr($key, 0, strlen($class->getName())) === $class->getName()) {
							$key = substr($key, (strlen($class->getName()) + 1));
						}
						else {
							$parents = class_parents($var);
							if (!empty($parents)) {
								foreach ($parents as $parent) {
									if (substr($key, 0, strlen($parent)) === $parent) {
										$key = $parent . '->' . substr($key, (strlen($parent) + 1));
									}
								}
							}
						}
					}
					$dblcheck[$key] = $value;
				}

				$reflect = new \ReflectionClass($var);

				$constants = $reflect->getConstants();
				if (!empty($constants)) {
					$className = $reflect->getName();
					foreach ($constants as $key => $value) {
						$visibility = 'private/protected';
						try {
							constant($className . '::' . $key);
							$visibility = 'public';
						}
						catch (\Throwable $e) {
						}
						$dodump($value, $visibility . ' const ' . $key, $indent + 1);
					}
				}
				unset($constants);

				$props = $reflect->getProperties();
				if (!empty($props)) {
					foreach ($props as $prop) {
						$append = '';
						$error = false;
						if ($prop->isPrivate()) {
							$append .= ' private';
						}
						elseif ($prop->isProtected()) {
							$append .= ' protected';
						}
						$prop->setAccessible(true);
						if ($prop->isStatic()) {
							$value = $prop->getValue();
							$append .= ' static';
						}
						else {
							set_error_handler(function ($errno, $errstr) {
								throw new \Exception($errstr);
							});
							try {
								$value = $prop->getValue($var);
							}
							catch (\Exception $e) {
								$value = $e->getMessage();
								$append .= ' error';
								$error = true;
							}
							restore_error_handler();
						}
						if (array_key_exists($prop->name, $dblcheck)) {
							unset($dblcheck[$prop->name]);
						}
						if (is_object($value)) {
							$same = false;
							foreach ($recursionClasses as $class) {
								if ($class === $value) {
									$same = true;
								}
							}
							if ($same === true) {
								$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
								echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities($prop->name . '\'' . $append) : $prop->name . '\'' . $append) . ']', 'varname', $background, $mode);
								echo ' ' . colorize('=', 'black', $background, $mode) . ' ';
								echo colorize(get_class($value) . '()', 'recursion', $background, $mode);
								echo "\n";
							}
							else {
								$dodump($value, '[\'' . $prop->name . '\'' . $append . ']', $indent + 1, array('error' => $error));
							}
						}
						else {
							$dodump($value, '[\'' . $prop->name . '\'' . $append . ']', $indent + 1, array('error' => $error));
						}
					}
				}

				if (!empty($dblcheck)) {
					foreach ($dblcheck as $key => $value) {
						$dodump($value, '[\'' . $key . '\' magic]', $indent + 1);
					}
				}

				$methods = $reflect->getMethods();
				if (!empty($methods)) {
					foreach ($methods as $method) {

						$doDump_indent = colorize('|', 'lightgray', $background, $mode) . '   ';
						echo str_repeat($doDump_indent, $indent + 1);

						if ($method->getModifiers() & \ReflectionMethod::IS_ABSTRACT) {
							echo colorize('abstract ', 'gray', $background, $mode);
						}
						elseif ($method->getModifiers() & \ReflectionMethod::IS_FINAL) {
							echo colorize('final ', 'gray', $background, $mode);
						}

						if ($method->getModifiers() & \ReflectionMethod::IS_PUBLIC) {
							echo colorize('public ', 'gray', $background, $mode);
						}
						elseif ($method->getModifiers() & \ReflectionMethod::IS_PROTECTED) {
							echo colorize('protected ', 'gray', $background, $mode);
						}
						elseif ($method->getModifiers() & \ReflectionMethod::IS_PRIVATE) {
							echo colorize('private ', 'gray', $background, $mode);
						}

						echo colorize($method->class, 'gray', $background, $mode);

						$type = '->';
						if ($method->getModifiers() & \ReflectionMethod::IS_STATIC) {
							$type = '::';
						}

						echo colorize($type . $method->name . '(', 'recursion', $background, $mode);

						$reflectMethod = new \ReflectionMethod($method->class, $method->name);
						$methodParams = $reflectMethod->getParameters();
						if (!empty($methodParams)) {
							$mParams = [];
							foreach ($methodParams as $mParam) {
								if ($mParam->isOptional()) {
									try {
										$default = $mParam->getDefaultValue();
										if (is_string($default)) {
											$default = "'" . $default . "'";
										}
										elseif ($default === true) {
											$default = 'true';
										}
										elseif ($default === false) {
											$default = 'false';
										}
										elseif ($default === null) {
											$default = 'null';
										}
										elseif (is_array($default)) {
											$default = 'Array';
										}
									}
									catch (\Exception $e) {
										$default = 'Unknown';
									}
									$mParams[] = colorize(($mParam->isPassedByReference() ? '&amp;' : '') . '$' . $mParam->name . ' = ' . $default, 'gray', $background, $mode);
								}
								else {
									$mParams[] = colorize(($mParam->isPassedByReference() ? '&amp;' : '') . '$' . $mParam->name, 'black', $background, $mode);
								}
							}
							echo implode(', ', $mParams);
						}

						echo colorize(')', 'recursion', $background, $mode);
						echo "\n";

					}
				}
				unset($props, $reflect);
			}
			unset($class);
			echo str_repeat($doDump_indent, $indent) . colorize(')', 'lightgray', $background, $mode);
		}
		elseif (is_null($var)) {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('null', 'black', $background, $mode);
		}
		elseif (is_float($var)) {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('Float(' . strlen($var) . ')', 'gray', $background) . ' ' . colorize($var, 'float', $background, $mode);
		}
		elseif (is_resource($var)) {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('Resource', 'gray', $background, $mode) . ' ' . $var;
		}
		else {
			echo ' ' . colorize('=', 'black', $background, $mode) . ' ' . colorize('Unknown', 'gray', $background, $mode) . ' ' . $var;
		}
		echo "\n";
	};

	$prefix = 'unique';
	$suffix = 'value';

	if ($return === true) {
		ob_start();
	}
	if ($webmode) {
		echo '<pre class="vardump">';
	}

	if (($title === null) || (is_array($title))) {
		$backtrace = debug_backtrace();
		if ((is_array($title)) && (isset($title['steps'])) && (isset($backtrace[$title['steps']]))) {
			$backtrace = $backtrace[$title['steps']];
		}
		else {
			$backtrace = $backtrace[0];
		}
		if (substr($backtrace['file'], -13) == 'eval()\'d code') {
			$title = 'eval()';
		}
		else {
			$con = explode("\n", file_get_contents($backtrace['file']));
			$callee = $con[$backtrace['line'] - 1];
			if ((is_array($title)) && (isset($title['match']))) {
				preg_match($title['match'], $callee, $matches);
			}
			else {
				preg_match('/([a-zA-Z\\\\]+|)var_dump\((.*)/', $callee, $matches);
			}
			if (!empty($matches)) {
				$i = 0;
				$title = '';
				foreach (str_split($matches[0], 1) as $value) {
					if ($value === '(') {
						$i++;
					}
					if (($i === 0) && ($value === ',')) {
						break;
					}
					if ($value === ')') {
						$i--;
					}
					if (($i === 0) && ($value === ')')) {
						$title .= $value;
						break;
					}
					$title .= $value;
				}
			}
			else {
				$title = 'Unknown dump string';
			}
		}
	}
	$dodump($var, $title);
	if ($webmode) {
		echo "</pre>\n";
	}
	if ($return === true) {
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
	}
	return null;
}
