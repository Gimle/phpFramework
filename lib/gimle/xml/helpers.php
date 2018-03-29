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
				$id = (string)$id;
				if (substr($id, 0, strlen($prefix)) === $prefix) {
					if (ctype_digit(substr($id, strlen($prefix)))) {
						$list[] = (int)substr($id, strlen($prefix));
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

	public function asDateTime ($input = null)
	{
		if ($input === null) {
			return date('Y-m-d\TH:i:s');
		}
		else if (is_int($input)) {
			return date('Y-m-d\TH:i:s', $input);
		}
		else if (is_string($input)) {
			return date('Y-m-d\TH:i:s', strtotime($input));
		}
		return null;
	}
}
