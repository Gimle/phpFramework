<?php
declare(strict_types=1);
namespace gimle;

/**
 * Convert domain name to IDNA ASCII form.
 *
 * @param string $domain The domain / url to convert, which must be UTF-8 encoded.
 * @return mixed The domain name / url encoded in ASCII-compatible form, or FALSE on failure.
 */
function idn_to_ascii (string $domain): string
{
	$proto = '';
	$path = '';
	$start = strpos($domain, '://');
	if ($start !== false) {
		$proto = substr($domain, 0, $start + 3);
		$domain = substr($domain, $start + 3);
	}
	$start = strpos($domain, '/');
	if ($start !== false) {
		$path = substr($domain, $start);
		$domain = substr($domain, 0, $start);
	}
	/*
		* Seems like php7.2 thinks it is a good idea to have the third parameter to this function call mandatory.
		* Guess this just has to be a lingering bug in here until php7.2 support is removed from this project.
		*/
	$domain = \idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
	if ($domain === false) {
		return false;
	}
	return $proto . $domain . $path;
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
 * Encodes data with MIME base64url
 *
 * @param string $data
 * @return string
 */
function base64url_encode (string $data): string
{
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decodes data with MIME base64url
 *
 * @param string $data
 * @return string
 */
function base64url_decode (string $data): string
{
	return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

/**
 * Convert entities to utf8.
 *
 * @param string $string
 * @param array $exclude
 * @return string
 */
function ent2utf8 (string $string, array $exclude = ['&', ';'], array $append = []): string
{
	$html_translation_table = get_entities($append, $exclude);

	$string = strtr($string, $html_translation_table);

	$string = preg_replace_callback('/&#x([0-9a-f]+);/i', function ($param) {
		return code2utf8(hexdec($param[1]));
	}, $string);
	$string = preg_replace_callback('/&#([0-9]+);/', function ($param) {
		return code2utf8($param[1]);
	}, $string);

	return $string;
}

/**
 * Convert utf8 to entities.
 *
 * @param string $string
 * @param array $exclude
 * @return string
 */
function utf82ent (string $string, array $exclude = ['.', ',', '-'], array $append = []): string
{
	$html_translation_table = array_flip(get_entities($append, $exclude));
	$string = strtr($string, $html_translation_table);

	return $string;
}

/**
 * Convert n indentation spaces to tab.
 *
 * @param string $string The input string.
 * @param int $spaces The amount of spaces pr indentation level.
 * @return string
 */
function tab_indent (string $string, int $spaces = 4): string
{
	$spaces = str_repeat(' ', $spaces);
	$regex = '/^' . $spaces . '|\G' . $spaces . '/m';
	return preg_replace($regex, "\t", $string);
}

/**
 * Generate a random string.
 *
 * @param ?string $characters The possible characters for the string.
 * @param ?int $length The length of the string.
 * @return string The generated string.
 */
function random ($characters = null, $length = null)
{
	if ($length === null) {
		$length = rand(18, 26);
	}
	if ($characters === null) {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	}
	$return = '';
	$count = mb_strlen($characters);
	for ($i = 0; $i < $length; $i++) {
		$random = rand(0, $count);
		$return .= mb_substr($characters, $random, 1);
	}

	return $return;
}
