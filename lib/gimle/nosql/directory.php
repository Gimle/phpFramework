<?php
declare(strict_types=1);
namespace gimle\nosql;

/**
 * Support directory structure depth up to size of an bigint.
 *
 * BIGINT
 * 18 446 744 073 709 551 615
 *  0   0   0   0   0   0   1
*/
class Directory
{
	/**
	 * The base directory.
	 *
	 * @var ?string $base
	 */
	private $base = null;

	/**
	 * Create a new Directory object
	 *
	 * @param string $base
	 */
	public function __construct (string $base)
	{
		$this->base = $base;

		if (!file_exists($base)) {
			mkdir($base, 0777, true);
		}
	}

	/**
	 * Create a new folder with an anto increment id.
	 *
	 * @return int
	 */
	public function create (): int
	{
		$max = $this->max();
		$new = $max + 1;
		$dir = $this->todir($new);
		mkdir($this->base . $dir, 0777, true);
		return $new;
	}

	/**
	 * Get the highest id.
	 *
	 * @return int
	 */
	public function max (): int
	{
		$result = $this->_max($this->base);
		if ($result === null) {
			return 0;
		}
		return $this->toint($result);
	}

	/**
	 * Get the full directory for an id.
	 *
	 * @param int $id
	 * @return string
	 */
	public function dir (int $id): string
	{
		return $this->base . $this->todir($id);
	}

	/**
	 * Convert an id to a directory structure.
	 *
	 * @param int $int
	 * @return string
	 */
	private function todir (int $int): string
	{
		$result = [];
		$parts = str_split(strrev((string) $int), 3);
		for ($i = 0; $i < 7; $i++) {
			if (isset($parts[$i])) {
				$result[$i] = (int) strrev($parts[$i]);
			}
			else {
				$result[$i] = '0';
			}
		}
		$result = array_reverse($result);
		return implode('/', $result) . '/';
	}

	/**
	 * Convert a directory structure to an id.
	 *
	 * @param string $dir
	 * @return int
	 */
	private function toint (string $dir): int
	{
		$values = explode('/', rtrim($dir, '/'));
		$prev = false;
		foreach ($values as &$value) {
			if ($value === 'null') {
				$prev = true;
				$value = '000';
				continue;
			}
			$value = str_pad($value, 3, '0', STR_PAD_LEFT);
		}
		if (count($values) < 7) {
			for ($i = 0; $i < 7; $i++) {
				if (!isset($values[$i])) {
					$values[$i] = '000';
				}
			}
		}

		$result = (int) implode('', $values);
		if (($prev === true) && ($result > 0)) {
			$result = $result - 1;
		}
		return $result;
	}

	/**
	 * Internal helper function for geting the highest id.
	 *
	 * @param string $start
	 * @param int $level
	 * @return ?string
	 */
	private function _max (string $start, int $level = 0): ?string
	{
		$max = null;
		foreach (new \DirectoryIterator($start) as $fileinfo) {
			$filename = $fileinfo->getFilename();
			if (substr($filename, 0, 1) === '.') {
				continue;
			}
			$digit = (int) $filename;
			if ($max === null) {
				$max = $digit;
				continue;
			}
			if ($digit > $max) {
				$max = $digit;
			}
		}


		if ($max === null) {
			return 'null';
		}

		$max = (string) $max;
		if ($level < 6) {
			$result = $this->_max($start . $max . '/', $level + 1);
		}
		else {
			return $max . '/';
		}
		return $max . '/' . $result;
	}
}
