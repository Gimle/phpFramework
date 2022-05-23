<?php
declare(strict_types=1);
namespace gimle;

use \gimle\Exception;

class DirIndex
{
	public function __construct ($root)
	{
		$this->root = $root;
		if (!file_exists($this->root . 'data/')) {
			mkdir($this->root . 'data/', 0777, true);
		}
		if (!file_exists($this->root . 'nextid.txt')) {
			file_put_contents($this->root . 'nextid.txt', "1\n");
		}
	}

	public function add (): int
	{
		$filename = $this->root . 'nextid.txt';
		$fp = fopen($filename, 'r+');
		if (flock($fp, LOCK_EX)) {
			$thisId = fread($fp, filesize($filename));
			rewind($fp);
			$thisId = (int) $thisId;
			$lastId = $thisId - 1;
			$nextId = $thisId + 1;

			if (($lastId > 1) && (strlen((string) $lastId) !== strlen((string) $thisId)) && ($thisId % 3 === 1)) {
				rename($this->root . 'data/', $this->root . 'temp/');
				mkdir($this->root . 'data/', 0777, true);
				rename($this->root . 'temp/', $this->root . 'data/000/');
			}

			ftruncate($fp, 0);
			fwrite($fp, $nextId . "\n");
			fflush($fp);
			flock($fp, LOCK_UN);
		}
		else {
			throw new Exception('Could not get lock.');
		}
		fclose($fp);

		mkdir($this->root . 'data/' . $this->pad($thisId) . '/', 0777, true);

		return $thisId;
	}

	public function get (int $id): string
	{
		$len = (int) file_get_contents($this->root . 'nextid.txt');
		$len--;
		$len = strlen((string) $len);
		$padded = str_pad((string) $id, $len, '0', STR_PAD_LEFT);
		return $this->pad($padded) . '/';
	}

	public function loop ()
	{
		$lastId = (int) file_get_contents($this->root . 'nextid.txt');
		$lastId--;
		$depth = count(str_split((string) $lastId, 3));
		yield from $this->recursion($depth);
	}

	private function recursion (int $left, string $prev = '')
	{
		$left--;
		foreach (new \DirectoryIterator($this->root . 'data/' . $prev) as $fileinfo) {
			$filename = $fileinfo->getFilename();
			if (str_starts_with($filename, '.')) {
				continue;
			}
			if ((!ctype_digit($filename)) || (strlen($filename) !== 3)) {
				continue;
			}

			if ($left === 0) {
				yield $prev . $filename . '/';
			}
			else {
				yield from $this->recursion($left, $prev . $filename . '/');
			}
		}
	}

	private function pad (int|string $id): string
	{
		$splitted = str_split(strrev((string) $id), 3);
		array_walk($splitted, function (&$item) {
			$item = str_pad($item, 3, '0', STR_PAD_RIGHT);
		});
		return strrev(implode('/', $splitted));
	}
}
