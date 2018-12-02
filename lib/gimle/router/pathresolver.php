<?php
declare(strict_types=1);
namespace gimle\router;

use const \gimle\SITE_DIR;
use const \gimle\MAIN_SITE_DIR;
use const \gimle\IS_SUBSITE;
use const \gimle\SITE_ID;
use const \gimle\MODULE_GIMLE;

use \gimle\MainConfig;
use \gimle\System;

class PathResolver
{
	/**
	 * Returns the absolute path for a template or null if not found.
	 *
	 * @param string $template Template name.
	 * @param ?mixed $params Optional format parameters.
	 * @return ?string Full path or null if not found.
	 */
	public static function getTemplatePath (string $template, ...$params): ?string
	{
		if (!empty($params)) {
			return self::formattedPathResolver($template, 'template', $params);
		}
		return self::pathResolver($template, 'template');
	}

	/**
	 * Returns the absolute path for a canvas or null if not found.
	 *
	 * @param string $template Canvas name.
	 * @param ?mixed $params Optional format parameters.
	 * @return ?string Full path or null if not found.
	 */
	public static function getCanvasPath (string $canvas, ...$params): ?string
	{
		if (!empty($params)) {
			return self::formattedPathResolver($template, 'canvas', $params);
		}
		return self::pathResolver($canvas, 'canvas');
	}

	/**
	 * Returns the absolute path for an inc or null if not found.
	 *
	 * @param string $template inc name.
	 * @param ?mixed $params Optional format parameters.
	 * @return ?string Full path or null if not found.
	 */
	public static function getIncPath (string $file, ...$params): ?string
	{
		if (!empty($params)) {
			return self::formattedPathResolver($template, 'inc', $params);
		}
		return self::pathResolver($file, 'inc');
	}

	/**
	 * Returns the absolute path for a file in a location or null if not found.
	 *
	 * @param string $template File name.
	 * @param string $dir The directory.
	 * @param array $params Format parameters.
	 * @return ?string Full path or null if not found.
	 */
	protected static function formattedPathResolver (string $template, string $dir, array $params): ?string
	{
		foreach ($params as $param) {
			if (is_array($param)) {
				$check = self::pathResolver(vsprintf($template, $param), $dir);
			}
			else {
				$check = self::pathResolver(sprintf($template, $param), $dir);
			}
			if ($check !== null) {
				return $check;
			}
		}
		return null;
	}

	/**
	 * Returns the absolute path for a file in a location or null if not found.
	 *
	 * @param string $template File name.
	 * @param string $dir The directory.
	 * @return ?string Full path or null if not found.
	 */
	protected static function pathResolver (string $template, string $dir): ?string
	{
		if (strpos($template, '.') === false) {
			$template .= '.php';
		}

		if (is_readable(SITE_DIR . $dir . '/' . $template)) {
			return SITE_DIR . $dir . '/' . $template;
		}
		if (IS_SUBSITE) {
			$mainModules = MainConfig::get('subsite.' . SITE_ID . '.modules');
			if ($mainModules !== null) {
				foreach ($mainModules as $module) {
					if (is_readable(MAIN_SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template)) {
						return MAIN_SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template;
					}
				}
			}
		}
		foreach (System::getModules(MODULE_GIMLE) as $module) {
			if (is_readable(SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template)) {
				return SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template;
			}
		}
		if (is_readable(SITE_DIR . 'module/' . MODULE_GIMLE . '/' . $dir . '/' . $template)) {
			return SITE_DIR . 'module/' . MODULE_GIMLE . '/' . $dir . '/' . $template;
		}
		return null;
	}
}
