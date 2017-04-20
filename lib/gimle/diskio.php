<?php
declare(strict_types=1);
namespace gimle;

class DiskIO
{
	/**
	 * Converts a config file formatted filesize string to bytes.
	 *
	 * @param string $size
	 * @return int Number of bytes.
	 */
	public static function stringToBytes (string $size): int
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
	 * @return ?int The age of the file, or null if not found.
	 */
	public static function getModifiedAge (string $name): ?int
	{
		return (time() - filemtime($name));
	}

}
