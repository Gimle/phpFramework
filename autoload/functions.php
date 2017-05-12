<?php
declare(strict_types=1);

namespace
{
	/**
	 * This block loads some functions that is not included in older / any php versions.
	 *
	 */

	if (!function_exists('mb_ucfirst')) {
		/**
		 * Make a string's first character uppercase.
		 *
		 * @param string $string The input string.
		 * @return string The resulting string.
		 */
		function mb_ucfirst (string $string): string
		{
			$return = '';
			$fc = mb_strtoupper(mb_substr($string, 0, 1));
			$return .= $fc . mb_substr($string, 1, mb_strlen($string));
			return $return;
		}
	}

	if (!function_exists('mb_str_pad')) {
		/**
		 * Pad a string to a certain length with another string.
		 *
		 * If the value of $pad_length is negative, less than, or equal to the length of the input string, no padding takes place.
		 * The $pad_string may be truncated if the required number of padding characters can't be evenly divided by the pad_string's length.
		 *
		 * @param string $input The input string.
		 * @param int $pad_length Pad length.
		 * @param string $pad_string Pad string.
		 * @param constant $pad_type Can be STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH.
		 * @param string $encoding The character encoding to use.
		 * @return string The padded string.
		 */
		function mb_str_pad (string $input, int $pad_length, string $pad_string = ' ', int $pad_type = STR_PAD_RIGHT, ?string $encoding = null): string
		{
			assert(in_array($pad_type, [STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_BOTH]));
			if ($encoding === null) {
				$diff = strlen($input) - mb_strlen($input);
			}
			else {
				$diff = strlen($input) - mb_strlen($input, $encoding);
			}
			return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
		}
	}

	if (!function_exists('is_binary')) {
		/**
		 * Checks if the input is binary.
		 *
		 * @param string $value The input string.
		 * @return bool True if binary, otherwise false.
		 */
		function is_binary (string $value): bool
		{
			$filename = tempnam(\gimle\TEMP_DIR, 'tmp_');
			file_put_contents($filename, $value);
			exec('file -i ' . $filename, $match);
			unlink($filename);
			$len = strlen($filename . ': ');
			$desc = substr($match[0], $len);
			if (substr($desc, 0, 4) == 'text') {
				return false;
			}
			return true;
		}
	}
}

namespace gimle
{
	/**
	 * Set some default values on session start.
	 *
	 * @throws gimle\ErrorException If session.use_only_cookies can not be set.
	 * @return void
	 */
	function session_start (): void
	{
		if (ini_set('session.use_only_cookies', '1') === false) {
			throw new Exception('Could not start session.');
		}

		$secure = false;
		if (isset($_SERVER['HTTPS'])) {
			$secure = true;
		}

		session_set_cookie_params(0, '/', '', $secure, true);
		session_name('gimle' . ucfirst(preg_replace('/[^a-zA-Z]/', '', MAIN_SITE_ID)));
		\session_start();
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
	 * Returns a parent directory's path
	 *
	 * @param string $path A path.
	 * @param int $level The number of parent directories to go up.
	 * @return string Returns the path of a parent directory.
	 */
	function dirname (string $path, int $level = 1): string
	{
		$result = \dirname($path, $level);
		if ($result !== '/') {
			return $result . '/';
		}
		return  '/';
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
	 * Convert a token separated string to a nested array.
	 *
	 * @param string $key The token separated index to the array.
	 * @param mixed $value The value for the array.
	 * @return array.
	 */
	function string_to_nested_array (string $key, $value, string $separator = '.'): array
	{
		if (strpos($key, $separator) === false) {
			return [$key => $value];
		}
		$key = explode($separator, $key);
		$pre = array_shift($key);
		$return = [$pre => string_to_nested_array(implode($separator, $key), $value, $separator)];
		return $return;
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
	 * Converts a config file formatted filesize string to bytes.
	 *
	 * @param string $size
	 * @return int Number of bytes.
	 */
	function string_to_bytes (string $size): int
	{
		$size = trim($size);
		$last = strtolower(substr($size, -1));
		$size = (int) $size;
		switch ($last) {
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}
		return $size;
	}

	/**
	 * Get the number of seconds since the file was last modified.
	 *
	 * @param string $name The file name.
	 * @return int The age of the file.
	 */
	function get_modified_age (string $name): int
	{
		return (time() - filemtime($name));
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
	 * Normalize spaces in the string, much like the browser would do it.
	 *
	 * @param string @string The input string.
	 * @return string The normalized string.
	 */
	function normalize_space (string $string): string
	{
		return preg_replace('/\s+/s', ' ', $string);
	}

	/**
	 * Get mime information about a file.
	 *
	 * @throws gimle\ErrorException If the file is not readable.
	 * @param string $file The file to get mime information about.
	 * @return array With mime and charset
	 */
	function get_mimetype (string $file): array
	{
		if (!is_readable($file)) {
			throw new Exception('get_mimetype(' . $file . '): failed to open stream: Permission denied');
		}
		$result = exec('file --brief --mime ' . escapeshellarg($file));

		if (($result['return'] !== 0) || (!isset($result['stout'][0]))) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE | FILEINFO_MIME_ENCODING);
			$result = finfo_file($finfo, $file);
		}
		else {
			$result = $result['stout'][0];
		}

		$regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+); charset=(.+)?$/';

		if (!preg_match($regexp, $result, $matches)) {
			return ['mime' => 'application/octet-stream', 'charset' => 'binary'];
		}

		if ($matches[2] === 'binary') {
			if ($matches[1] === 'application/octet-stream') {
				$size = filesize($file);
				if ($size > 12) {
					$fp = fopen($file, 'r');
					$check1 = fread($fp, 4);
					fseek($fp, 8);
					$check2 = fread($fp, 4);

					fclose($fp);

					if (($check1 === 'RIFF') && (($check2 === 'WEBP'))) {
						$matches[1] = 'image/webp';
					}
				}
			}

			if ($matches[1] === 'application/octet-stream') {
				$size = filesize($file);
				if ($size > 7) {
					$fp = fopen($file, 'r');
					$check1 = fread($fp, 7);

					fclose($fp);

					if ($check1 === 'BLENDER') {
						$matches[1] = 'application/vnd.blender.blend';
					}
				}
			}

			if ($matches[1] === 'application/octet-stream') {
				$size = filesize($file);
				if ($size > 4) {
					$fp = fopen($file, 'r');
					$check1 = fread($fp, 4);

					fclose($fp);

					if ($check1 === 'wOF2') {
						$matches[1] = 'application/x-font-woff';
					}
				}
			}

			if ($matches[1] === 'application/octet-stream') {
				$exec = 'avprobe -v 0 -show_format -show_streams ' . escapeshellarg($file) . ' -of json 2>&1';
				\exec($exec, $out, $exitCode);
				if ($exitCode === 0) {
					$json = json_decode(implode('', $out), true);
					if ((is_array($json)) && (!empty($json))) {
						if (isset($json['streams'][0])) {
							foreach ($json['streams'] as $stream) {
								if ($stream['codec_name'] === 'h264') {
									$matches[1] = 'video/x-unknown';
								}
							}
						}
					}
				}
			}
		}

		return ['mime' => $matches[1], 'charset' => $matches[2]];
	}
}
