<?php
declare(strict_types=1);
namespace gimle;

/**
 * Get the number of seconds since the file was last modified.
 *
 * @param string $name The file name.
 * @return int The age of the file.
 */
function get_modified_age (string $name): int
{
	return (time() - filemtime($name));
}

/**
 * Returns a parent directory's path
 *
 * @param string $path A path.
 * @param int $level The number of parent directories to go up.
 * @return string Returns the path of a parent directory.
 */
function dirname (string $path, int $level = 1): string
{
	$result = \dirname($path, $level);
	if ($result !== '/') {
		return $result . '/';
	}
	return  '/';
}


/**
 * Get a unique file name.
 *
 * @param string $dir In which directory to check for uniqueness.
 * @param string $prefix Custom prefix, default = ''.
 * @param string $postfix Custom postfix, default = ''.
 * @return string The unique file name.
 */
function uniquename ($dir = TEMP_DIR, $prefix = '', $postfix = '', $len = null): string
{
	$name = random(null, $len);
	if (!file_exists($dir . $prefix . $name . $postfix)) {
		return $prefix . $name . $postfix;
	}
	return uniquename($dir, $prefix, $postfix);
}


/**
 * Returns the full size of a directory.
 *
 * @param string $path
 * @return int Total size in bytes.
 */
function dirsize (string $path): int
{
	$io = popen('/usr/bin/du -sb ' . escapeshellarg($path), 'r');
	$size = (int) fgets($io, 80);
	pclose($io);
	return $size - 4096;
}


/**
 * Check if a path only travels downwards, as in it has no references to self or parents.
 *
 * This function is intended to check if user input to file locations are not manipulated to travel outside intended root.
 *
 * @param string $path The path to check.
 * @param bool $urldecode Should the path be url decoded?
 * @return ?string The optionally decoded path without leading or trailing slashes.
 */
function sub_path (?string $dir, bool $urldecode = true): ?string
{
	if ($dir === null) {
		return null;
	}
	if ($urldecode === true) {
		$dir = urldecode($dir);
	}

	$dir = trim($dir, '/');
	if ($dir === '.') {
		return null;
	}
	if ($dir === '..') {
		return null;
	}
	if (strpos($dir, '/../') !== false) {
		return null;
	}
	if (strpos($dir, '/./') !== false) {
		return null;
	}
	if (strpos($dir, '//') !== false) {
		return null;
	}
	if (substr($dir, -3, 3) === '/..') {
		return null;
	}
	if (substr($dir, 0, 3) === '../') {
		return null;
	}
	if (substr($dir, -2, 2) === '/.') {
		return null;
	}
	if (substr($dir, 0, 2) === './') {
		return null;
	}

	return $dir;
}

/**
 * Removes all files and folders within a directory.
 *
 * @param string $path Path to root directory to clear.
 * @param bool $deleteRoot Also delete the root directory (Default: false)
 * @return array The paths of the deleted files
 */
function clear_dir ($path, $deleteRoot = false): array
{
	$result = [
		'dir' => [],
		'file' => [],
	];
	$files = glob(rtrim(str_replace('\\', '\\\\', $path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');
	foreach ($files as $file) {
		if ((is_dir($file)) && (!is_link($file))) {
			$res = clear_dir($file, true);
			$result['dir'] = $result['dir'] + $res['dir'];
			$result['file'] = $result['file'] + $res['file'];
		}
		else {
			unlink($file);
			$result['file'][] = $file;
		}
	}
	if ($deleteRoot === true) {
		rmdir($path);
		$result['dir'][] = $path;
	}
	return $result;
}

/**
 * Recursivly copy files.
 *
 * @param string $source The source location.
 * @param string $target The target destination.
 * @return void
 */
function rcopy ($source, $target): void
{
	if (!is_dir($source)) {
		copy($source, $target);
		return;
	}

	$dir = opendir($source);
	if (!file_exists($target)) {
		mkdir($target);
	}
    while (($file = readdir($dir)) !== false) {
        if (($file !== '.') && ($file !== '..')) {
            if (is_dir($source . '/' . $file)) {
                rcopy($source . '/' . $file, $target . '/' . $file);
            }
            else {
                copy($source . '/' . $file, $target . '/' . $file);
            }
        }
    }
    closedir($dir);
}

/**
 * Get mime information about a file.
 *
 * @throws gimle\ErrorException If the file is not readable.
 * @param string $file The file to get mime information about.
 * @return array With mime and charset
 */
function get_mimetype (string $file): array
{
	if (!is_readable($file)) {
		throw new Exception('get_mimetype(' . $file . '): failed to open stream: Permission denied');
	}
	$result = exec('file --brief --mime ' . escapeshellarg($file));

	if (($result['return'] !== 0) || (!isset($result['stout'][0]))) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE | FILEINFO_MIME_ENCODING);
		$result = finfo_file($finfo, $file);
	}
	else {
		$result = $result['stout'][0];
	}

	$regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+); charset=(.+)?$/';

	if (!preg_match($regexp, $result, $matches)) {
		return ['mime' => 'application/octet-stream', 'charset' => 'binary'];
	}

	if ($matches[2] === 'binary') {
		if ($matches[1] === 'application/octet-stream') {
			$size = filesize($file);
			if ($size > 12) {
				$fp = fopen($file, 'r');
				$check1 = fread($fp, 4);
				fseek($fp, 8);
				$check2 = fread($fp, 4);

				fclose($fp);

				if (($check1 === 'RIFF') && (($check2 === 'WEBP'))) {
					$matches[1] = 'image/webp';
				}
			}
		}

		if ($matches[1] === 'application/octet-stream') {
			$size = filesize($file);
			if ($size > 7) {
				$fp = fopen($file, 'r');
				$check1 = fread($fp, 7);

				fclose($fp);

				if ($check1 === 'BLENDER') {
					$matches[1] = 'application/vnd.blender.blend';
				}
			}
		}

		if ($matches[1] === 'application/octet-stream') {
			$size = filesize($file);
			if ($size > 4) {
				$fp = fopen($file, 'r');
				$check1 = fread($fp, 4);

				fclose($fp);

				if ($check1 === 'wOF2') {
					$matches[1] = 'application/x-font-woff';
				}
			}
		}

		if ($matches[1] === 'application/octet-stream') {
			$exec = 'avprobe -v 0 -show_format -show_streams ' . escapeshellarg($file) . ' -of json 2>&1';
			\exec($exec, $out, $exitCode);
			if ($exitCode === 0) {
				$json = json_decode(implode('', $out), true);
				if ((is_array($json)) && (!empty($json))) {
					if (isset($json['streams'][0])) {
						foreach ($json['streams'] as $stream) {
							if ($stream['codec_name'] === 'h264') {
								$matches[1] = 'video/x-unknown';
							}
						}
					}
				}
			}
		}

		if ($matches[1] === 'application/octet-stream') {
			$size = filesize($file);
			if ($size > 4) {
				$fp = fopen($file, 'r');
				$check1 = fread($fp, 4);

				fclose($fp);

				if ($check1 === hex2bin('01000000')) {
					if (str_ends_with($file, '.emf')) {
						$matches[1] = 'image/x-emf';
					}
				}

			}
		}
	}

	return ['mime' => $matches[1], 'charset' => $matches[2]];
}

/**
 * Get the path to reach a public file.
 *
 * @param string $file The filename.
 * @return ?array Local and public path to the file or null if not found.
 */
function getPublicFile (string $file): ?array
{
	if (is_readable(SITE_DIR . 'public/' . $file)) {
		return [
			'local' => SITE_DIR . 'public/' . $file,
			'public' => BASE_PATH . $file
		];
	}
	if (IS_SUBSITE === true) {
		if (is_readable(MAIN_SITE_DIR . SITE_ID . '/public/' . $file)) {
			return [
				'local' => MAIN_SITE_DIR . SITE_ID . '/public/' . $file,
				'public' => BASE_PATH . 'module/local/' . $file
			];
		}
		$subsiteModules = MainConfig::get('subsite.' . SITE_ID . '.modules');
		if ($subsiteModules !== null) {
			sort($subsiteModules);
			foreach ($subsiteModules as $module) {
				if (is_readable(MAIN_SITE_DIR . 'module/' . $module . '/public/' . $file)) {
					return [
						'local' => MAIN_SITE_DIR . 'module/' . $module . '/public/' . $file,
						'public' => MAIN_BASE_PATH . 'module/' . $module . '/' . $file
					];
				}
			}
		}
	}
	foreach (System::getModules(MODULE_GIMLE) as $module) {
		if (is_readable(SITE_DIR . 'module/' . $module . '/public/' . $file)) {
			return [
				'local' => SITE_DIR . 'module/' . $module . '/public/' . $file,
				'public' => BASE_PATH . 'module/' . $module . '/' . $file
			];
		}
	}
	if (is_readable(SITE_DIR . 'module/' . MODULE_GIMLE . '/public/' . $file)) {
		return [
			'local' => SITE_DIR . 'module/' . MODULE_GIMLE . '/public/' . $file,
			'public' => BASE_PATH . 'module/' . MODULE_GIMLE . '/' . $file
		];
	}
	return null;
}

/**
 * Get a versioned unique path to a public file.
 *
 * @param string $file The filename.
 * @return ?string The public versioned unique path to the file or null if not found.
 */
function getPublicResource (string $file): ?string
{
	$location = getPublicFile($file);
	if ($location !== null) {
		if (is_readable($location['local'])) {
			return $location['public'] . '?' . filemtime($location['local']);
		}
		else {
			return $location['public'];
		}
	}
	return null;
}

/**
 * Load a file from the filesystem, or get a default string if file not found.
 *
 * @param string $file The filename.
 * @param string $default The default content if file not found.
 * @return string
 */
function loadFile (string $filename, string $default = ''): string
{
	if (!file_exists($filename)) {
		return $default;
	}
	$f = fopen($filename, 'rb');
	if (flock($f, LOCK_SH)) {
		clearstatcache(true, $filename);
		$contents = fread($f, filesize($filename));
		flock($f, LOCK_UN);
	}
	fclose($f);

	return $contents;
}

/**
 * Convert a large number into a folder structure.
 *
 * @param int $num The input number.
 * @param int $length The maximum length of the number.
 * @param int $split How many digits to put in each subfolder.
 * @return string The resulting folder structure.
 */
function numeric_dir (int $num, int $length, int $split = 3): string
{
	$result = str_pad((string) $num, $length, '0', STR_PAD_LEFT);
	$result = str_split($result, $split);
	$result = implode('/', $result) . '/';
	return $result;
}


/**
 * Make sure that the filename is not in use. If requested name is in use, add () with a new id.
 *
 * @param string|array $folder The folder for where to look for identical filename, or an array containing taken filenames.
 * @param string $filename The wanted filename.
 * @return string The original filename, or the modified one if the original was in use.
 */
function fileuname (string|array $folder, string $filename): string
{
	if (((is_string($folder)) && (file_exists($folder . $filename))) || ((is_array($folder)) && (in_array($filename, $folder)))) {
		$pos = strrpos($filename, '.');
		if ($pos === false) {
			$name = $filename;
			$ext = '';
		}
		else {
			$name = substr($filename, 0, $pos);
			$ext = substr($filename, $pos);
		}
		$i = 1;
		while (true) {
			$filename = $name . ' (' . $i . ')' . $ext;
			if (is_string($folder)) {
				if (!file_exists($folder . $filename)) {
					break;
				}
			}
			else {
				if (!in_array($filename, $folder)) {
					break;
				}
			}
			$i++;
		}
	}
	return $filename;
}
