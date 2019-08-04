<?php
declare(strict_types=1);
namespace gimle\xml;

trait Helpers
{
	/**
	 * Get the next available numeric attribute.
	 *
	 * @param string $name The attribute name.
	 * @param string $type The element names to search.
	 * @param string $prefix Prefix before the numeric value.
	 * @return int
	 */
	public function getNextId (string $name = 'id', string $type = '*', string $prefix = ''): int
	{
		$xpath = '//' . implode('/@' . $name . '|//', explode('|', $type)) . '/@' . $name;
		$ids = $this->xpath($xpath);
		$newId = 1;
		$list = [];
		if (!empty($ids)) {
			foreach ($ids as $id) {
				$id = (string) $id;
				if (substr($id, 0, strlen($prefix)) === $prefix) {
					if (ctype_digit(substr($id, strlen($prefix)))) {
						$list[] = (int) substr($id, strlen($prefix));
					}
				}
			}
		}
		if (!empty($list)) {
			$newId = max($list) + 1;
		}
		return $newId;
	}

	/**
	 * Escape a string for use in xpath queries.
	 *
	 * @param string Input string.
	 * @param string Escape character (default = ").
	 * @return string
	 */
	public function real_escape_string (string $escapestr, string $escapechar = '"'): string
	{
		if ((strpos($escapestr, '\'') !== false) || (strpos($escapestr, '"') !== false)) {
			$quotes = ['\'', '"'];
			$parts = [];
			$current = '';
			foreach (str_split($escapestr) as $character) {
				if (in_array($character, $quotes)) {
					if ($current !== '') {
						$parts[] = '\'' . $current . '\'';
					}
					if ($character === '\'') {
						$parts[] = '"' . $character . '"';
					}
					else {
						$parts[] = '\'' . $character . '\'';
					}
					$current = '';
				}
				else {
					$current .= $character;
				}
			}
			if ($current !== '') {
				$parts[] = '\'' . $current . '\'';
			}
			return 'concat(' . implode(',', $parts) . ')';
		}
		return $escapechar . $escapestr . $escapechar;
	}

	/**
	 * Set a given node value.
	 *
	 * @param string $string The new value.
	 * @param mixed $ref null = set new value to self. string = xpath to set new value, SimpleXmlElement = reference to set new value.
	 * @return void
	 */
	public function value (string $string, $ref = null): void
	{
		$refs = $this->resolveReference($ref);
		foreach ($refs as $ref) {
			dom_import_simplexml($ref)->nodeValue = $string;
		}
	}

	/**
	 * Get a node's indentation.
	 *
	 * @param mixed $ref null = set new value to self. string = xpath to set new value, SimpleXmlElement = reference to set new value.
	 * @return ?string null if node not found, or the indentation string of the node.
	 */
	public function getIndentation ($ref = null): ?string
	{
		$refs = $this->resolveReference($ref);
		if (!isset($refs[0])) {
			return null;
		}
		$dom = dom_import_simplexml($refs[0]);
		preg_match('/^[\s]+/s', $dom->parentNode->nodeValue, $leadingWhitespace);
		$leadingWhitespace = ($leadingWhitespace[0] ?? null);
		if ($leadingWhitespace !== null) {
			$start = strpos($leadingWhitespace, "\n");
			if ($start !== false) {
				$leadingWhitespace = substr($leadingWhitespace, $start + 1);
			}
			$end = strpos($leadingWhitespace, "\n");
			if ($end !== false) {
				$leadingWhitespace = substr($leadingWhitespace, 0, $end);
			}
		}
		return $leadingWhitespace;
	}

	/**
	 * Get a node's xpath.
	 *
	 * @param mixed $ref null = set new value to self. string = xpath to set new value, SimpleXmlElement = reference to set new value.
	 * @return ?string null if node not found, or a xpath string to the node.
	 */
	public function getXpath ($ref = null): ?string
	{
		$refs = $this->resolveReference($ref);
		if (!isset($refs[0])) {
			return null;
		}
		$dom = dom_import_simplexml($refs[0]);
		return $dom->getNodePath();
	}

	/**
	 * Convert a time input to a ISO 8601 time string.
	 *
	 * @param mixed $input null = current time, int = unix time, string = parse time (strtotime).
	 * @param bool $includeTimeZone Include timezone in the string.
	 * @return ?string null if input could be converted, or a datetime string.
	 */
	public static function asDateTime ($input = null, bool $includeTimeZone = true): ?string
	{
		$format = 'c';
		if ($includeTimeZone === false) {
			$format = 'Y-m-d\TH:i:s';
		}
		if ($input === null) {
			return date($format);
		}
		else if (is_int($input)) {
			return date($format, $input);
		}
		else if (is_string($input)) {
			return date($format, strtotime($input));
		}
		return null;
	}

	/**
	 * Get the raw xml contents of a file if exists, or create a new xml string.
	 *
	 * @param string $filename The file to open.
	 * @param mixed $new If the file does not exist, return null or string. (if SimpleXmlElement, return string representation).
	 * @return ?string
	 */
	public static function openXml (string $filename, $new = null): ?string
	{
		if (!file_exists($filename)) {
			if (is_string($new)) {
				return $new;
			}
			else {
				return $new->asXml();
			}
		}
		$f = fopen($filename, 'rb');
		if (flock($f, LOCK_SH)) {
			clearstatcache(true, $filename);
			$contents = fread($f, filesize($filename));
			flock($f, LOCK_UN);
		}
		fclose($f);

		return $contents;
	}

	/**
	 * Get a SimpleXmlElement representation of file if exists, or create a new SimpleXmlElement.
	 *
	 * @param string $filename The file to open.
	 * @param mixed $new If the file does not exist, return null or SimpleXmlElement. (SimpleXmlElement will be cloned, not referenced, string will be a new SimpleXmlElement).
	 * @return ?string
	 */
	public static function open (string $filename, $new = null): ?SimpleXmlElement
	{
		$contents = self::openXml($filename, $new);
		if (is_string($contents)) {
			return new static($contents);
		}

		return $new;
	}

	/**
	 * Save the current SimpleXmlElement to a file.
	 *
	 * @param string $filename The filename.
	 * @param bool $pretty Run pretty() on the xml before saving.
	 * @return void
	 */
	public function save (string $filename, bool $pretty = false): void
	{
		$f = fopen($filename, 'w');
		if (flock($f, LOCK_EX)) {
			ftruncate($f, 0);
			if ($pretty === true) {
				fwrite($f, $this->pretty() . "\n");
			}
			else {
				fwrite($f, $this->asXml() . "\n");
			}
			fflush($f);
			flock($f, LOCK_UN);
		}
		fclose($f);
	}
}
