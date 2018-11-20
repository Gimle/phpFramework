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
 * Removes all files and folders within a directory.
 *
 * @param string $path Path to root directory to clear.
 * @param bool $deleteRoot Also delete the root directory (Default: false)
 * @return void
 */
function clear_dir ($path, $deleteRoot = false)
{
	$files = glob(rtrim(str_replace('\\', '\\\\', $path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');
	foreach ($files as $file) {
		if ((is_dir($file)) && (!is_link($file))) {
			clear_dir($file, true);
		}
		else {
			unlink($file);
		}
	}
	if ($deleteRoot === true) {
		rmdir($path);
	}
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
	}

	return ['mime' => $matches[1], 'charset' => $matches[2]];
}
