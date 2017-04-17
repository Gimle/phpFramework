<?php
declare(strict_types=1);
namespace gimle;

class System
{
	/**
	 * Configuration for paths to search for classes when autoloading.
	 *
	 * @var array
	 */
	private static $autoload = [
		['path' => SITE_DIR . 'module/' . MODULE_GIMLE . '/lib/', 'toLowercase' => true, 'init' => false]
	];

	/**
	 * Cache for the getModules method.
	 *
	 * @var array
	 */
	private static $modules = null;

	/**
	 * Cache for the getLogname method.
	 *
	 * @var string
	 */
	private static $logname = null;

	/**
	 * Cache for the getUploadLimit method.
	 *
	 * @var int
	 */
	private static $uploadLimit = null;

	/**
	 * Register an autoload path.
	 *
	 * @param string $path The path.
	 * @param bool $toLowercase look for lowercase name of the autoload.
	 * @param bool $initFunction Run init when loading.
	 * @return void
	 */
	public static function autoloadRegister (string $path, bool $toLowercase = true, bool $initFunction = false): void
	{
		self::$autoload[] = ['path' => $path, 'toLowercase' => $toLowercase, 'init' => $initFunction];
	}

	/**
	 * Autoload.
	 *
	 * @param string $name
	 * @return void
	 */
	public static function autoload (string $name): void
	{
		foreach (static::$autoload as $autoload) {
			$file = $autoload['path'];
			if ($autoload['toLowercase'] === true) {
				$file .= str_replace('\\', '/', strtolower($name)) . '.php';
			}
			else {
				$file .= str_replace('\\', '/', $name) . '.php';
			}
			if (is_readable($file)) {
				include $file;
				if (($autoload['init'] !== false) && (method_exists($name, $autoload['init']))) {
					call_user_func([$name, $autoload['init']]);
				}
				break;
			}
		}
	}

	/**
	 * Get modules included in this project.
	 *
	 * @param string $exclude
	 * @return array
	 */
	public static function getModules (string ...$exclude): array
	{
		if (self::$modules === null) {
			self::$modules = [];
			foreach (new \DirectoryIterator(SITE_DIR . 'module/') as $item) {
				$name = $item->getFileName();
				if ((substr($name, 0, 1) === '.') || (!$item->isDir()) || (!$item->isExecutable())) {
					continue;
				}
				self::$modules[] = $name;
			}
			sort(self::$modules, SORT_NATURAL | SORT_FLAG_CASE);
		}

		return array_diff(self::$modules, $exclude);
	}

	/**
	 * Checks for the maximum size uploads.
	 *
	 * @return int Maximum number of bytes.
	 */
	public static function getUploadLimit (): int
	{
		if (self::$uploadLimit === null) {
			self::$uploadLimit = (int) min(DiskIO::stringToBytes(ini_get('post_max_size')), DiskIO::stringToBytes(ini_get('upload_max_filesize')));
		}
		return self::$uploadLimit;
	}

	/**
	 * Get the current logged in user.
	 *
	 * @return string
	 */
	public static function getLogname (): string
	{
		if (self::$logname === null) {
			$exec = 'logname';
			$result = exec($exec);

			self::$logname = '';
			if (isset($result['stout'][0])) {
				self::$logname = $result['stout'][0];
			}
		}

		return self::$logname;
	}

}
