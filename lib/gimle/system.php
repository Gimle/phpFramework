<?php
declare(strict_types=1);
namespace gimle;

class System
{
	public const PARENT = 1;
	/**
	 * Configuration for paths to search for classes when autoloading.
	 *
	 * @var array
	 */
	private static $autoload = [
		['path' => SITE_DIR . 'module/' . MODULE_GIMLE . '/lib/', 'options' => ['toLowercase' => true]]
	];

	/**
	 * Cache for the getModules method.
	 *
	 * @var array
	 */
	private static $modules = null;
	private static $modulesParent = null;

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
	 * Cache for autoloaded files.
	 *
	 * @var array
	 */
	private static $autoloaded = [];

	/**
	 * Register an autoload path.
	 *
	 * @param string $path The path.
	 * @param bool $toLowercase look for lowercase name of the autoload.
	 * @param bool $initFunction Run init when loading.
	 * @param bool $end Push to the end instead of first.
	 * @return void
	 */
	public static function autoloadRegister (string $path, array $options = [], bool $end = false): void
	{
		if ($end === true) {
			array_push(self::$autoload, ['path' => $path, 'options' => $options]);
			return;
		}
		array_unshift(self::$autoload, ['path' => $path, 'options' => $options]);
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
			$class = $name;

			if (isset($autoload['options']['stripRootNamespace'])) {
				$rounds = false;
				if ($autoload['options']['stripRootNamespace'] === true) {
					$rounds = 1;
				}
				elseif (is_int($autoload['options']['stripRootNamespace'])) {
					$rounds = $autoload['options']['stripRootNamespace'];
				}

				if (is_int($rounds)) {
					for ($i = 0; $i < $rounds; $i++) {
						$pos = strpos($class, '\\');
						if ($pos !== false) {
							$class = substr($class, $pos + 1);
						}
					}
				}
			}
			if ((isset($autoload['options']['toLowercase'])) && ($autoload['options']['toLowercase'] === true)) {
				$file .= str_replace('\\', '/', strtolower($class)) . '.php';
			}
			else {
				$file .= str_replace('\\', '/', $class) . '.php';
			}
			if (is_readable($file)) {
				// If a class has been renamed in a use statement, it might be sent to the autoloader again. So, we need to keep track of the loaded files.
				if (!in_array($file, self::$autoloaded)) {
					self::$autoloaded[] = $file;
					include $file;
					if ((isset($autoload['options']['init'])) && ($autoload['options']['init'] !== false) && (method_exists($class, $autoload['options']['init']))) {
						call_user_func([$class, $autoload['options']['init']]);
					}
				}
				if (class_exists($name, false)) {
					break;
				}
			}
		}
	}

	/**
	 * Get modules included in this project.
	 *
	 * @param int|string $exclude with possible flag at end.
	 * @return array
	 */
	public static function getModules (int|string ...$exclude): array
	{
		$mode = 0;
		if (is_int(end($exclude))) {
			$mode = array_pop($exclude);
		}
		if ($mode & self::PARENT) {
			if (self::$modulesParent === null) {
				self::$modulesParent = [];
				foreach (new \DirectoryIterator(MAIN_SITE_DIR . 'module/') as $item) {
					$name = $item->getFileName();
					if ((substr($name, 0, 1) === '.') || (!$item->isDir()) || (!$item->isExecutable())) {
						continue;
					}
					self::$modulesParent[] = $name;
				}
				rsort(self::$modulesParent, SORT_NATURAL | SORT_FLAG_CASE);
			}

			return array_diff(self::$modulesParent, $exclude);
		}
		if (self::$modules === null) {
			self::$modules = [];
			foreach (new \DirectoryIterator(SITE_DIR . 'module/') as $item) {
				$name = $item->getFileName();
				if ((substr($name, 0, 1) === '.') || (!$item->isDir()) || (!$item->isExecutable())) {
					continue;
				}
				self::$modules[] = $name;
			}
			rsort(self::$modules, SORT_NATURAL | SORT_FLAG_CASE);
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
			self::$uploadLimit = (int) min(string_to_bytes(ini_get('post_max_size')), string_to_bytes(ini_get('upload_max_filesize')));
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
