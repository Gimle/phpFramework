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
	 * Returns the absolute paths for all templates.
	 *
	 * @param string $template Template name.
	 * @param ?mixed $params Optional format parameters.
	 * @return array Full paths.
	 */
	public static function getTemplatePaths (string $template, ...$params): array
	{
		if (!empty($params)) {
			return self::formattedPathResolver($template, 'template', $params, true);
		}
		return self::pathResolver($template, 'template', true);
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
	 * @param bool $multiple Should it return all?
	 * @return ?string|array Full path or null if not found. If multiple, then array of found.
	 */
	protected static function formattedPathResolver (string $template, string $dir, array $params, bool $multiple = false): null|string|array
	{
		foreach ($params as $param) {
			if (is_array($param)) {
				$check = self::pathResolver(vsprintf($template, $param), $dir, $multiple);
			}
			else {
				$check = self::pathResolver(sprintf($template, $param), $dir, $multiple);
			}
			if ($check !== null) {
				return $check;
			}
		}
		if (!$multiple) {
			return null;
		}
		return [];
	}

	/**
	 * Returns the absolute path for a file in a location or null if not found.
	 *
	 * @param string $template File name.
	 * @param string $dir The directory.
	 * @param bool $multiple Should it return all?
	 * @return ?string|array Full path or null if not found. If multiple, then array of found.
	 */
	protected static function pathResolver (string $template, string $dir, bool $multiple = false): null|string|array
	{
		$return = [];
		if (strpos($template, '.') === false) {
			$template .= '.php';
		}
		if (str_starts_with($template, '/')) {
			return $template;
		}

		if (is_readable(SITE_DIR . $dir . '/' . $template)) {
			if (!$multiple) {
				return SITE_DIR . $dir . '/' . $template;
			}
			$return[] = SITE_DIR . $dir . '/' . $template;
		}
		if (IS_SUBSITE) {
			if (is_readable(MAIN_SITE_DIR . SITE_ID . '/' . $dir . '/' . $template)) {
				if (!$multiple) {
					return MAIN_SITE_DIR . SITE_ID . '/' . $dir . '/' . $template;
				}
				$return[] = MAIN_SITE_DIR . SITE_ID . '/' . $dir . '/' . $template;
			}
			$mainModules = MainConfig::get('subsite.' . SITE_ID . '.modules');
			if ($mainModules !== null) {
				foreach ($mainModules as $module) {
					if (is_readable(MAIN_SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template)) {
						if (!$multiple) {
							return MAIN_SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template;
						}
						$return[] = MAIN_SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template;
					}
				}
			}
		}
		foreach (System::getModules(MODULE_GIMLE) as $module) {
			if (is_readable(SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template)) {
				if (!$multiple) {
					return SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template;
				}
				$return[] = SITE_DIR . 'module/' . $module . '/' . $dir . '/' . $template;
			}
		}
		if (is_readable(SITE_DIR . 'module/' . MODULE_GIMLE . '/' . $dir . '/' . $template)) {
			if (!$multiple) {
				return SITE_DIR . 'module/' . MODULE_GIMLE . '/' . $dir . '/' . $template;
			}
			$return[] = SITE_DIR . 'module/' . MODULE_GIMLE . '/' . $dir . '/' . $template;
		}
		if (!$multiple) {
			return null;
		}
		return $return;
	}
}
