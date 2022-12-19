<?php
declare(strict_types=1);
namespace gimle;

abstract class FileStreamBase
{
	/**
	 * The stream handle.
	 *
	 * @var File pointer.
	 */
	protected $handle;

	/**
	 * The path for the current stream.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * A holder for the current context.
	 *
	 * @var mixed
	 */
	public $context = null;

	/**
	 * Sets the path of the wrapper.
	 *
	 * @param string $path The path.
	 * @return void
	 */
	protected function setPath (string $path): void
	{
		$scheme = substr($path, 0, strpos($path, ':'));
		$dir = substr($path, strlen($scheme . '://'));
		if (substr($dir, 0, 1) === '/') {
			throw new Exception('Path can not start with a slash.');
		}
		if (strpos($dir, '..') !== false) {
			throw new Exception('Path can not contain a double dot.');
		}
		$this->path = $this->base . $dir;
	}

	/*
	The following methods are inherited from php, see php.net for documentation.
	*/

	public function stream_open (string $path, string $mode, int $options, ?string &$opened_path)
	{
		$this->setPath($path);

		$this->handle = fopen($this->path, $mode);

		return $this->handle;
	}

	public function stream_read (int $count)
	{
		return fread($this->handle, $count);
	}

	public function stream_write (string $data): int
	{
		return fwrite($this->handle, $data);
	}

	public function stream_eof (): bool
	{
		return feof($this->handle);
	}

	public function stream_close (): void
	{
		fclose($this->handle);
	}

	public function stream_stat ()
	{
		return fstat($this->handle);
	}

	public function url_stat (string $path, int $flag)
	{
		$this->setPath($path);

		if (file_exists($this->path)) {
			return stat($this->path);
		}
		return false;
	}

	public function mkdir (string $path, int $mode, int $options): bool
	{
		$this->setPath($path);
		$recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
		return mkdir($this->path, $mode, $recursive);
	}

	public function rmdir (string $path, int $options): bool
	{
		$this->setPath($path);
		return rmdir($this->path);
	}

	public function dir_opendir (string $path, int $options): bool
	{
		$this->setPath($path);
		$this->handle = opendir($this->path);
		return $this->handle;
	}

	public function dir_readdir ()
	{
		return readdir($this->handle);
	}

	public function dir_closedir (): bool
	{
		throw new Exception('Not implemented, need test case.');
	}

	public function rename (string $path_from, string $path_to): bool
	{
		$this->setPath($path_from);
		$path_from = $this->path;
		$this->setPath($path_to);
		$path_to = $this->path;
		$this->path = null;
		return rename($path_from, $path_to);
	}

	public function stream_metadata (string $path, int $options, $value): bool
	{
		$this->setPath($path);

		if ($options === STREAM_META_TOUCH) {
			if (!isset($value[0])) {
				return touch($this->path);
			}
			elseif (!isset($value[1])) {
				return touch($this->path, $value[0]);
			}
			return touch($this->path, $value[0], $value[1]);
		}

		if ($options === STREAM_META_ACCESS) {
			return chmod($this->path, $value);
		}

		if (in_array($options, [STREAM_META_OWNER, STREAM_META_OWNER_NAME])) {
			return chown($this->path, $value);
		}

		if (in_array($options, [STREAM_META_GROUP, STREAM_META_GROUP_NAME])) {
			return chgrp($this->path, $value);
		}

		throw new Exception('Not implemented, need test case.');
	}

}
