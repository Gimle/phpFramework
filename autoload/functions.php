<?php
declare(strict_types=1);
namespace gimle;

use \gimle\Spectacle;

/**
 * Check if a session is available.
 *
 * @return bool
 */
function session_available (): bool
{
	$sessionName = 'gimle' . ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', MAIN_SITE_ID));
	if (isset($_COOKIE[$sessionName])) {
		if (is_readable(session_save_path() . '/sess_' . $_COOKIE[$sessionName])) {
			return true;
		}
	}
	return false;
}

/**
 * Set some default values on session start.
 *
 * @throws gimle\ErrorException If session.use_only_cookies can not be set.
 * @return void
 */
function session_start (): void
{
	if (session_status() === PHP_SESSION_NONE) {
		if (ini_set('session.use_only_cookies', '1') === false) {
			throw new Exception('Could not start session.');
		}

		$secure = false;
		$urlPartsBase = parse_url(MAIN_BASE_PATH);
		if ($urlPartsBase['scheme'] === 'https') {
			$secure = true;
		}
		session_set_cookie_params(0, $urlPartsBase['path'], '', $secure, true);
		if (ENV_MODE & ENV_WEB) {
			session_name('gimle' . ucfirst(preg_replace('/[^a-zA-Z0-9]/', '', MAIN_SITE_ID)));
		}
		\session_start();
	}
}

/**
 * Get information about the current url.
 *
 * @param mixed $part The part you want returned. (Optional).
 * @return mixed If no $part passed in, and array of available parts is returned.
 *               If a valid part is part is passed in, that part is returned as a string.
 *               If part was not found, null will be returned.
 */
function page ($part = null)
{
	return router\Router::getInstance()->page($part);
}

/**
 * Include a file and pass arguments to it.
 *
 * @param string $file The file to include.
 * @param mixed ...$args One or more arguments..
 * @return mixed The return value from the included file.
 */
function inc (string $file, ...$args)
{
	$GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh'][] = $args;
	$res = include $file;
	array_pop($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh']);
	if (empty($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh'])) {
		unset($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh']);
	}
	return $res;
}

/**
 * Get arguments set when file was included.
 *
 * @return array The arguments passed in.
 */
function inc_get_args (): array
{
	if (isset($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh'])) {
		return end($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh']);
	}
	return [];
}

/**
 * Get the users preferred language.
 *
 * @param array $avail A list of the available languages.
 * @return string or null if empty array passed in.
 */
function get_preferred_language (array $avail): ?string
{
	if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		return null;
	}
	$accepts = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
	$result = [];
	foreach ($accepts as $accept) {
		$accept = explode(';q=', $accept);
		if (!isset($accept[1])) {
			$accept[1] = 1.0;
		}
		else {
			$accept[1] = (float) $accept[1];
		}
		$result[$accept[1] * 100][] = $accept[0];
	}
	krsort($result);
	foreach ($result as $values) {
		foreach ($values as $value) {
			if (in_array($value, $avail)) {
				return $value;
			}
			elseif (array_key_exists($value, $avail)) {
				return $avail[$value];
			}
		}
	}
	return null;
}

/**
 * Translate a message.
 *
 * @param string ...$message Message to translate.
 * @param array An array of options for the translation.
 * @return string The translated message.
 */
function _ (...$message)
{
	return i18n::getInstance()->_(...$message);
}

/**
 * Parse a ini or php config file and keep typecasting.
 *
 * For ini files, this is similar to the parse_ini_file function, but keeps typecasting and require "" around strings.
 * For php files this function will use the return value.
 *
 * @throws gimle\ErrorException If confog file is not valid.
 * @param string $filename the full path to the file to parse.
 * @return array or null. Array with the read configuration file, or false upon failure.
 */
function parse_config_file (string $filename): ?array
{
	if (!is_readable($filename)) {
		return null;
	}

	$return = [];
	$lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return null;
	}
	if (!empty($lines)) {
		foreach ($lines as $linenum => $linestr) {
			if (substr($linestr, 0, 1) === ';') {
				continue;
			}
			$line = explode(' = ', $linestr);
			$key = trim($line[0]);
			if ((isset($line[1])) && (substr($key, 0, 1) !== '[')) {
				if (isset($value)) {
					unset($value);
				}
				if ((substr($line[1], 0, 1) === '"') && (substr($line[1], -1, 1) === '"')) {
					$value = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($line[1], 1, -1));
				}
				elseif ((ctype_digit($line[1])) || ((substr($line[1], 0, 1) === '-') && (ctype_digit(substr($line[1], 1))))) {
					$num = $line[1];
					if (substr($num, 0, 1) === '-') {
						$num = substr($line[1], 1);
					}
					if (substr($num, 0, 1) === '0') {
						if (substr($line[1], 0, 1) === '-') {
							$value = -octdec($line[1]);
						}
						else {
							$value = octdec($line[1]);
						}
					}
					else {
						$value = (int) $line[1];
					}
					unset($num);
				}
				elseif ($line[1] === 'true') {
					$value = true;
				}
				elseif ($line[1] === 'false') {
					$value = false;
				}
				elseif ($line[1] === 'null') {
					$value = null;
				}
				elseif (preg_match('/^0[xX][0-9a-fA-F]+$/', $line[1])) {
					$value = hexdec(substr($line[1], 2));
				}
				elseif (preg_match('/^\-0[xX][0-9a-fA-F]+$/', $line[1])) {
					$value = -hexdec(substr($line[1], 3));
				}
				elseif (preg_match('/^0b[01]+$/', $line[1])) {
					$value = bindec(substr($line[1], 2));
				}
				elseif (preg_match('/^\-0b[01]+$/', $line[1])) {
					$value = -bindec(substr($line[1], 3));
				}
				elseif (filter_var($line[1], FILTER_VALIDATE_FLOAT) !== false) {
					$value = (float) $line[1];
				}
				elseif (defined($line[1])) {
					$value = constant($line[1]);
				}
				elseif (defined(__NAMESPACE__ . '\\' . $line[1])) {
					$value = constant(__NAMESPACE__ . '\\' . $line[1]);
				}
				elseif ((in_array(substr($line[1], 0, 1), ['[', '{'])) && (in_array(substr($line[1], -1, 1), [']', '}']))) {
					$value = json_decode($line[1], true);
				}
				else {
					throw new Exception('Unknown value in ini file on line ' . ($linenum + 1) . ': ' . $linestr);
				}
				if (isset($value)) {
					if (!isset($lastkey)) {
						$return[$key] = $value;
					}
					else {
						$return = array_merge_distinct($return, string_to_nested_array($lastkey, [$key => $value]));
					}
				}
			}
			else {
				$lastkey = substr($key, 1, -1);
			}
		}
	}
	return $return;
}

/**
 * Execute a command.
 *
 * @param string $command The command to run.
 * @return array
 */
function exec (string $command): array
{
	$filename = tempnam(TEMP_DIR, 'gimle_exec_');
	touch($filename);
	\exec($command . ' 2> ' . $filename, $stout, $return);
	$sterr = explode("\n", trim(file_get_contents($filename)));
	unlink($filename);
	return ['stout' => $stout, 'sterr' => $sterr, 'return' => $return];
}

/**
 * Merge two or more arrays recursivly and preserve keys.
 *
 * Values will overwrite previous array for every additional array passed to the method.
 * Add the boolean value false to the end to have latest array control the order.
 *
 * @param array $array Variable list of arrays to recursively merge.
 * @return array The merged array.
 */
function array_merge_distinct (...$arrays): array
{
	$array = current($arrays);
	$reposition = false;
	if (is_bool($arrays[count($arrays) - 1])) {
		if ($arrays[count($arrays) - 1]) {
			$reposition = true;
		}
		array_pop($arrays);
	}
	if (count($arrays) > 1) {
		array_shift($arrays);
		foreach ($arrays as $array2) {
			if (!empty($array2)) {
				foreach ($array2 as $key => $val) {
					if (is_array($array2[$key])) {
						$array[$key] = ((isset($array[$key])) && (is_array($array[$key])) ? array_merge_distinct($array[$key], $array2[$key], $reposition) : $array2[$key]);
					}
					else {
						if ((isset($array[$key])) && ($reposition === true)) {
							unset($array[$key]);
						}
						$array[$key] = $val;
					}
				}
			}
		}
	}
	return $array;
}

/**
 * Check if two arrays are equal.
 *
 * One level deep check.
 * key valur order independent.
 *
 * @param array $a
 * @param array $b
 * @return bool
 */
function array_equal (array $a, array $b): bool
{
	if (count($a) !== count($b)) {
		return false;
	}
	if (array_diff($a, $b) === array_diff($b, $a)) {
		return true;
	}
	return false;
}

/**
 * Convert bytes to readable number.
 *
 * @param int $filesize Number of bytes.
 * @param int $decimals optional Number of decimals to include in string.
 * @return array containing prefix, float value and readable string.
 */
function bytes_to_array (int $filesize = 0, int $decimals = 2): array
{
	$return = [];
	$count = 0;
	$units = ['', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
	while ((($filesize / 1024) >= 1) && ($count < (count($units) - 1))) {
		$filesize = $filesize / 1024;
		$count++;
	}
	if (round($filesize, $decimals) === (float) 1024) {
		$filesize = $filesize / 1024;
		$count++;
	}
	$return['units']  = $units[$count];
	$return['value']  = (float) $filesize;
	$return['short'] = round($filesize, $decimals) . (($count > 0) ? ' ' . $units[$count] : '');
	$return['full'] = round($filesize, $decimals) . ' ' . $units[$count] . 'B';
	return $return;
}

/**
 * A shorthand to push data to the Spectacles Spectacle tab.
 *
 * @param mixed ...$data
 * @return void
 */
function sp (...$data): void
{
	if (ENV_MODE & ENV_CLI) {
		d($data, ['title' => 'sp()']);
		return;
	}
	Spectacle::getInstance()->match(['match' => '/(sp\((.*))/', 'steps' => 2, 'index' => 1])->tab('Spectacle')->push(...$data);
}

/**
 * Get full translation table.
 *
 * @param array $append Append custom values.
 * @param array $remove Remove unwanted values.
 * @return array
 */
function get_entities (array $append = [], array $remove = []): array
{
	$table = [];

	foreach (get_html_translation_table(HTML_ENTITIES) as $key => $value) {
		$table[$value] = $key;
	}
	foreach (get_html_translation_table(HTML_ENTITIES, ENT_HTML5 | ENT_QUOTES) as $key => $value) {
		$table[$value] = $key;
	}

	/* Additional entities */
	$table['&ap;']     = '≈';
	$table['&there;']  = '∴';
	$table['&lsquor;'] = '‚';
	$table['&rdquor;'] = '„';
	$table['&dash;']   = '‐';
	$table['&lsqb;']   = '[';
	$table['&verbar;'] = '|';

	/* Add custom entities if provided */
	if (!empty($append)) {
		$table = array_merge($table, $append);
	}

	/* Remove custom entities if provided */
	if (!empty($remove)) {
		foreach ($remove as $value) {
			if (($key = array_search($value, $table)) !== false) {
				unset($table[$key]);
			}
		}
	}

	return $table;
}

/**
 * Convert code to utf-8.
 *
 * @param int $num
 * @return string
 */
function code2utf8 (int $num)
{
	if ($num < 128) {
		return chr($num);
	}
	if ($num < 2048) {
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	}
	if ($num < 65536) {
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	}
	if ($num < 2097152) {
		return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	}
	return '';
}

/**
 * Json pretty print.
 *
 * @param mixed $value
 * @return string
 */
function json_pretty ($value): string
{
	$result = \json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
	$result = tab_indent($result);
	return $result;
}
