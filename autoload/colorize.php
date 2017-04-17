<?php
declare(strict_types=1);
namespace gimle;

/**
 * Colorize a string according to the envoriment settings.
 *
 * @param string $content The content to colorize.
 * @param string $color The color to use.
 * @param string $background The background for color overrides to maintain visibility.
 * @param string $mode Default "auto", can be: "cli", "web" or "terminal".
 * @param bool $getStyle return the style only (Default false).
 * @return string
 */
function colorize (string $content, string $color, ?string $background = null, string $mode = 'auto', bool $getStyle = false): string
{
	if ($mode === 'terminal') {
		return $content;
	}

	if ($background === null) {
		$background = 'white';
	}

	if ($mode === 'auto') {
		$climode = (ENV_MODE & ENV_CLI ? true : false);
	}
	elseif ($mode === 'web') {
		$climode = false;
	}
	elseif ($mode === 'cli') {
		$climode = true;
	}
	else {
		trigger_error('Invalid mode.', E_USER_WARNING);
	}

	if ($climode) {
		$template = "\033[%sm%s\033[0m";
	}
	elseif ($getStyle === false) {
		$template = '<span style="color: %s;">%s</span>';
	}
	else {
		$template = 'color: %s;';
	}
	if (substr($color, 0, 6) === 'range:') {
		$config = json_decode(substr($color, 6), true);
		if ($config['type'] === 'alert') {
			$state = ($config['value'] / $config['max']);
			if ($state >= 1) {
				if ($climode) {
					return sprintf($template, '38;5;9', $content);
				}
				return sprintf($template, '#ff0000', $content);
			}
			elseif ($climode) {
				if ($state < 0.1) {
					return sprintf($template, '38;5;2', $state);
				}
				elseif ($state < 0.25) {
					return sprintf($template, '38;5;118', $state);
				}
				elseif ($state < 0.4) {
					return sprintf($template, '38;5;148', $state);
				}
				elseif ($state < 0.5) {
					return sprintf($template, '38;5;220', $state);
				}
				elseif ($state < 0.6) {
					return sprintf($template, '38;5;220', $state);
				}
				elseif ($state < 0.8) {
					return sprintf($template, '38;5;178', $state);
				}
				return sprintf($template, '38;5;166', $state);
			}
			elseif ($state === 0.5) {
				if ($climode) {
					return sprintf($template, '38;5;11', $content);
				}
				return sprintf($template, '#ffff00', $content);
			}
			elseif ($state < 0.5) {
				return sprintf($template, '#' . str_pad(dechex(round($state * 511)), 2, '0', STR_PAD_LEFT) . 'ff00', $content);
			}
			else {
				$state = (0.5 - ($state - 0.5));
				return sprintf($template, '#ff' . str_pad(dechex(round(($state) * 511)), 2, '0', STR_PAD_LEFT) . '00', $content);
			}
		}
	}
	elseif ($color === 'gray') {
		if ($climode) {
			return sprintf($template, '38;5;240', $content);
		}
		return sprintf($template, 'gray', $content);
	}
	elseif ($color === 'string') {
		if ($climode) {
			return sprintf($template, '38;5;46', $content);
		}
		return sprintf($template, 'green', $content);
	}
	elseif ($color === 'int') {
		if ($climode) {
			return sprintf($template, '38;5;196', $content);
		}
		return sprintf($template, 'red', $content);
	}
	elseif ($color === 'lightgray') {
		if ($background === 'black') {
			if ($climode) {
				return sprintf($template, '38;5;240', $content);
			}
			return sprintf($template, 'darkgray', $content);
		}
		if ($climode) {
			return sprintf($template, '38;5;251', $content);
		}
		return sprintf($template, 'lightgray', $content);
	}
	elseif ($color === 'bool') {
		if ($climode) {
			return sprintf($template, '38;5;57', $content);
		}
		return sprintf($template, 'purple', $content);
	}
	elseif ($color === 'float') {
		if ($climode) {
			return sprintf($template, '38;5;39', $content);
		}
		return sprintf($template, 'dodgerblue', $content);
	}
	elseif ($color === 'error') {
		if ($climode) {
			return sprintf($template, '38;5;198', $content);
		}
		return sprintf($template, 'deeppink', $content);
	}
	elseif ($color === 'recursion') {
		if ($climode) {
			return sprintf($template, '38;5;208', $content);
		}
		return sprintf($template, 'darkorange', $content);
	}
	elseif ($background === 'black') {
		if ($climode) {
			return sprintf($template, '38;5;256', $content);
		}
		return sprintf($template, 'white', $content);
	}
	else {
		return $content;
	}
}
