<?php
declare(strict_types=1);
namespace gimle;

/**
 * Dumps a varialble from the global scope.
 *
 * @param mixed $var The variable to dump.
 * @param array $mode
 *          return = bool
 *          title = string
 *          background = string
 *          mode = string: "console", "cli" or "web"
 *          document = bool
 *          string_callback = function
 * @return ?string
 */
function d ($var, array $mode = [])
{
	if (!isset($mode['title'])) {
		$mode['title'] = [
			'steps' => 1,
			'match' => '/d\((.*)/'
		];
	}
	return var_dump($var, $mode);
}

/**
 * Dumps a varialble from the global scope.
 *
 * @param mixed $var The variable to dump.
 * @param array $mode
 *          return = bool
 *          title = string
 *          background = string
 *          mode = string: "console", "cli" or "web"
 *          document = bool
 *          string_callback = function
 * @return ?string
 */
function var_dump ($var, array $mode = []): ?string
{
	if (!isset($mode['background'])) {
		$mode['background'] = 'white';
	}

	if (!isset($mode['mode'])) {
		$mode['mode'] = 'auto';
	}

	if ($mode['mode'] === 'auto') {
		$webmode = (ENV_MODE & ENV_WEB ? true : false);
	}
	elseif ($mode['mode'] === 'web') {
		$webmode = true;
	}
	elseif ($mode['mode'] === 'cli') {
		$webmode = false;
	}
	elseif ($mode['mode'] === 'console') {
		$webmode = false;
	}
	else {
		trigger_error('Invalid mode.', E_USER_WARNING);
	}

	$fixDumpString = function (string $name, string $value, bool $htmlspecial = true) use (&$background, &$mode): string {
		if (in_array($name, ['[\'pass\']', '[\'password\']', '[\'PHP_AUTH_PW\']'])) {
			$value = '********';
		}
		elseif (isset($mode['string_callback'])) {
			$value = $mode['string_callback']($value, $mode);
		}
		else {
			$fix = [
				"\r\n" => colorize('¤¶', 'gray', $mode['background'], $mode['mode']) . "\n", // Windows linefeed.
				"\n\r" => colorize('¶¤', 'gray', $mode['background'], $mode['mode']) . "\n\n", // Erronumous (might be interpeted as double) linefeed.
				"\n"   => colorize('¶', 'gray', $mode['background'], $mode['mode']) . "\n", // UNIX linefeed.
				"\r"   => colorize('¤', 'gray', $mode['background'], $mode['mode']) . "\n" // Old mac linefeed.
			];
			$value = strtr(($htmlspecial ? htmlspecialchars($value) : $value), $fix);
		}
		return $value;
	};

	$recursionClasses = [];

	$showComment = function ($block, $indent) use (&$mode) {
		if ((isset($mode['document'])) && ($mode['document'] === true)) {
			$comment = $block->getDocComment();
			if ($comment !== false) {
				$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
				echo str_repeat($doDump_indent, $indent + 1) . "\n";
				$comment = preg_replace('/^\s+/m', ' ', $comment);
				$lines = explode("\n", $comment);
				foreach ($lines as $line) {
					echo str_repeat($doDump_indent, $indent + 1) . colorize($line, 'gray') . "\n";
				}
			}
		}
	};

	$dodump = function ($var, ?string $var_name = null, int $indent = 0, array $params = []) use (&$dodump, &$fixDumpString, &$webmode, &$mode, &$recursionClasses, &$showComment): void {
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

		$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
		echo str_repeat($doDump_indent, $indent) . colorize(($webmode === true ? htmlentities($var_name) : $var_name), 'varname', $mode['background'], $mode['mode']);

		if (is_callable($var)) {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('*CALLABLE*', 'recursion', $mode['background'], $mode['mode']);
		}
		elseif (is_array($var)) {
			echo ' ' . colorize('=>', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Array (' . count($var) . ')', 'gray', $mode['background'], $mode['mode']) . "\n" . str_repeat($doDump_indent, $indent) . colorize('(', 'lightgray', $mode['background'], $mode['mode']) . "\n";
			foreach ($var as $key => $value) {
				if (is_callable($var[$key])) {
					$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
					echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities((string) $key) : (string) $key) . '\']', 'varname', $mode['background'], $mode['mode']);
					echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ';
					if (!is_string($var[$key])) {
						echo colorize('*CALLABLE*', 'recursion', $mode['background'], $mode['mode']);
					}
					else {
						echo colorize('\'' . (string) $var[$key] . '\'', 'recursion', $mode['background'], $mode['mode']);
					}
					echo "\n";
					continue;
				}
				if (strpos(print_r($var[$key], true), '*RECURSION*') !== false) {
					$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
					echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities((string) $key) : (string) $key) . '\']', 'varname', $mode['background'], $mode['mode']);
					echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ';
					if (!is_string($var[$key])) {
						echo colorize('*RECURSION*', 'recursion', $mode['background'], $mode['mode']);
					}
					else {
						echo colorize('\'' . (string) $var[$key] . '\'', 'recursion', $mode['background'], $mode['mode']);
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
						$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
						echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities((string) $key) : (string) $key) . '\']', 'varname', $mode['background'], $mode['mode']);
						echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ';
						echo colorize(get_class($value) . '()', 'recursion', $mode['background'], $mode['mode']);
						echo "\n";
					}
					elseif (get_class($value) === 'Closure') {
						$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
						echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities((string) $key) : (string) $key) . '\']', 'varname', $mode['background'], $mode['mode']);
						echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ';
						echo colorize(get_class($value) . '()', 'recursion', $mode['background'], $mode['mode']);
						echo "\n";
					}
					else {
						$dodump($value, '[\'' . $key . '\']', $indent + 1);
					}
					continue;
				}
				$dodump($value, '[\'' . $key . '\']', $indent + 1);
			}
			echo str_repeat($doDump_indent, $indent) . colorize(')', 'lightgray', $mode['background'], $mode['mode']);
		}
		elseif (is_string($var)) {
			if ((isset($params['error'])) && ($params['error'] === true)) {
				echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Error: ' . $fixDumpString($var_name, $var, $webmode), 'error', $mode['background'], $mode['mode']);
			}
			elseif ((isset($params['omitted'])) && ($params['omitted'] === true)) {
				echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Omitted: ' . $fixDumpString($var_name, $var, $webmode), 'recursion', $mode['background'], $mode['mode']);
			}
			else {
				echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('String(' . strlen($var) . ')', 'gray', $mode['background'], $mode['mode']) . ' ' . colorize('\'' . $fixDumpString($var_name, $var, $webmode) . '\'', 'string', $mode['background'], $mode['mode']);
			}
		}
		elseif (is_int($var)) {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Integer(' . strlen((string) $var) . ')', 'gray', $mode['background'], $mode['mode']) . ' ' . colorize((string) $var, 'int', $mode['background'], $mode['mode']);
		}
		elseif (is_bool($var)) {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Boolean', 'gray', $mode['background'], $mode['mode']) . ' ' . colorize(($var === true ? 'true' : 'false'), 'bool', $mode['background'], $mode['mode']);
		}
		elseif ((is_object($var)) && ($var instanceof \SimpleXmlElement)) {
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

			echo ' ' . colorize('=>', 'black', $mode['background'], $mode['mode']) . ' ' . colorize($class->getName() . ' Object' . $parents, 'recursion', $mode['background'], $mode['mode']);
			echo colorize(' (' . $var->getName() . ')', 'gray', $mode['background'], $mode['mode']);
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
				echo ' ' . colorize('=>', 'black', $mode['background'], $mode['mode']) . ' ' . colorize($class->getName() . ' Object (Iterator)' . $parents, 'gray', $mode['background'], $mode['mode']) . "\n" . str_repeat($doDump_indent, $indent) . colorize('(', 'lightgray', $mode['background'], $mode['mode']) . "\n";
				var_dump($var);
			}
			else {
				echo ' ' . colorize('=>', 'black', $mode['background'], $mode['mode']) . ' ' . colorize($class->getName() . ' Object' . $parents, 'gray', $mode['background'], $mode['mode']) . "\n" . str_repeat($doDump_indent, $indent) . colorize('(', 'lightgray', $mode['background'], $mode['mode']) . "\n";

				$dblcheck = [];
				foreach ((array) $var as $key => $value) {
					if (!property_exists($var, (string) $key)) {
						$key = ltrim((string) $key, "\x0*");
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
				ob_start();
				\var_dump($var);
				$hiddenProps = ob_get_contents();
				ob_end_clean();
				preg_match_all('/\  \[\"([^\"]+)\"\]\=\>\n/', $hiddenProps, $matches);
				$reflectionPropsFound = [];
				if (!empty($props)) {
					foreach ($props as $prop) {
						$reflectionPropsFound[] = $prop->name;
					}
				}
				foreach ($matches[1] as $match) {
					if ((!in_array($match, $reflectionPropsFound)) && (property_exists($var, $match))) {
						if (!is_object($var->{$match})) {
							$dodump($var->{$match}, '[\'' . $match . '\']', $indent + 1, []);
						}
						else {
							$dodump('(object value omitted)', '[\'' . $match . '\']', $indent + 1, ['omitted' => true]);
						}
					}
				}
				if (!empty($props)) {
					foreach ($props as $prop) {
						$showComment($prop, $indent);
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
								$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
								echo str_repeat($doDump_indent, $indent + 1) . colorize('[\'' . ($webmode === true ? htmlentities($prop->name . '\'' . $append) : $prop->name . '\'' . $append) . ']', 'varname', $mode['background'], $mode['mode']);
								echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ';
								echo colorize(get_class($value) . '()', 'recursion', $mode['background'], $mode['mode']);
								echo "\n";
							}
							else {
								$dodump($value, '[\'' . $prop->name . '\'' . $append . ']', $indent + 1, ['error' => $error]);
							}
						}
						else {
							$dodump($value, '[\'' . $prop->name . '\'' . $append . ']', $indent + 1, ['error' => $error]);
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
						$showComment($method, $indent);

						$doDump_indent = colorize('|', 'lightgray', $mode['background'], $mode['mode']) . '   ';
						echo str_repeat($doDump_indent, $indent + 1);

						if ($method->getModifiers() & \ReflectionMethod::IS_ABSTRACT) {
							echo colorize('abstract ', 'gray', $mode['background'], $mode['mode']);
						}
						elseif ($method->getModifiers() & \ReflectionMethod::IS_FINAL) {
							echo colorize('final ', 'gray', $mode['background'], $mode['mode']);
						}

						if ($method->getModifiers() & \ReflectionMethod::IS_PUBLIC) {
							echo colorize('public ', 'gray', $mode['background'], $mode['mode']);
						}
						elseif ($method->getModifiers() & \ReflectionMethod::IS_PROTECTED) {
							echo colorize('protected ', 'gray', $mode['background'], $mode['mode']);
						}
						elseif ($method->getModifiers() & \ReflectionMethod::IS_PRIVATE) {
							echo colorize('private ', 'gray', $mode['background'], $mode['mode']);
						}

						echo colorize($method->class, 'gray', $mode['background'], $mode['mode']);

						$type = '->';
						if ($method->getModifiers() & \ReflectionMethod::IS_STATIC) {
							$type = '::';
						}

						echo colorize($type . $method->name . '(', 'recursion', $mode['background'], $mode['mode']);

						$reflectMethod = new \ReflectionMethod($method->class, $method->name);
						$methodParams = $reflectMethod->getParameters();
						if (!empty($methodParams)) {
							$mParams = [];
							foreach ($methodParams as $mParam) {
								$pre = '';
								$mPropType = $mParam->getType();
								if ($mPropType !== null) {
									if ($mPropType->allowsNull() === true) {
										$pre = '?';
									}
									$pre .= (string) $mPropType . ' ';
									$pre = colorize($pre, (in_array((string) $mPropType, ['string', 'int', 'bool', 'float', 'array']) ? (string) $mPropType : 'gray'));
								}

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
									$mParams[] = $pre . colorize(($mParam->isPassedByReference() ? '&amp;' : '') . '$' . $mParam->name . ' = ' . $default, 'gray', $mode['background'], $mode['mode']);
								}
								else {
									$mParams[] = $pre . colorize(($mParam->isPassedByReference() ? '&amp;' : '') . '$' . $mParam->name, 'black', $mode['background'], $mode['mode']);
								}
							}
							echo implode(', ', $mParams);
						}

						echo colorize(')', 'recursion', $mode['background'], $mode['mode']);
						$returnType = $method->getReturnType();
						if ($returnType !== null) {
							$pre = ': ';
							if ($returnType->allowsNull() === true) {
								$pre .= '?';
							}
							echo colorize($pre . (string) $returnType, (in_array((string) $returnType, ['string', 'int', 'bool', 'float', 'array']) ? (string) $returnType : 'gray'));
//							echo colorize($pre . (string) $returnType, 'string');
						}

						echo "\n";

					}
				}
				unset($props, $reflect);
			}
			unset($class);
			echo str_repeat($doDump_indent, $indent) . colorize(')', 'lightgray', $mode['background'], $mode['mode']);
		}
		elseif (is_null($var)) {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('null', 'black', $mode['background'], $mode['mode']);
		}
		elseif (is_float($var)) {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Float(' . strlen((string) $var) . ')', 'gray', $mode['background']) . ' ' . colorize((string) $var, 'float', $mode['background'], $mode['mode']);
		}
		elseif (is_resource($var)) {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Resource', 'gray', $mode['background'], $mode['mode']) . ' ' . $var;
		}
		else {
			echo ' ' . colorize('=', 'black', $mode['background'], $mode['mode']) . ' ' . colorize('Unknown', 'gray', $mode['background'], $mode['mode']) . ' ' . $var;
		}
		echo "\n";
	};

	$prefix = 'unique';
	$suffix = 'value';

	if ((isset($mode['return'])) && ($mode['return'] === true)) {
		ob_start();
	}
	if ($webmode) {
		echo '<pre class="vardump">';
	}

	if (isset($mode['title'])) {
		$title = $mode['title'];
	}
	else {
		$title = null;
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
	if ((isset($mode['return'])) && ($mode['return'] === true)) {
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
	}
	return null;
}
