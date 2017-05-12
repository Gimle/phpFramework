<?php
declare(strict_types=1);
namespace gimle\rest;

use const \gimle\TEMP_DIR;
use function \gimle\get_mimetype;

class Stream
{
	/**
	 * Options for the request. Set via the option() method.
	 *
	 * @var array
	 */
	private $option = [];

	/**
	 * Headers for the request. Set via the header() method.
	 *
	 * @var array
	 */
	private $header = [];

	/**
	 * Post data for the request. Set via the post() method.
	 *
	 * @var mixed null, string or array.
	 */
	private $post = null;

	/**
	 * Files for the request. Set via the file() method.
	 *
	 * @var ?array
	 */
	private $file = null;

	/**
	 * Is this a multipart form data request. Set automatically.
	 *
	 * @var bool
	 */
	private $multipart = false;

	/**
	 * Reset the object, so it can be reused.
	 *
	 * @param ?bool $full Should options and headers be kept?
	 * @return void
	 */
	public function reset (bool $full = false): void
	{
		if ($full === true) {
			$this->option = [];
			$this->header = [];
		}

		$this->post = null;
		$this->file = [];
	}

	/**
	 * Sets an option for the request.
	 *
	 * @param string $key The options name.
	 * @param mixed $value The option value.
	 * @return void
	 */
	public function option (string $key, $value): void
	{
		$this->option[$key] = $value;
	}

	/**
	 * Sets a header for the request.
	 *
	 * @param string $key The header name.
	 * @param string $value The header value.
	 * @return void
	 */
	public function header (string $key, string $value): void
	{
		$this->header[$key] = $value;
	}

	/**
	 * Sets a post value for the request.
	 *
	 * @throws gimle\rest\Exception If tying to add post field to a raw post request, or the other way around.
	 * @param string $key The post name, or the whole raw post data.
	 * @param ?string $value The post value.
	 * @return void
	 */
	public function post (string $key, ?string $data = null): void
	{
		if ($data === null) {
			if (!is_array($this->post)) {
				$this->post = $key;
			}
			else {
				throw new Exception('Can not send raw data when post data is sendt.', Fetch::E_POST_RAW);
			}
		}
		elseif (!is_string($this->post)) {
			$this->post[$key] = $data;
		}
		else {
			throw new Exception('Can not send post data when raw data is sendt.', Fetch::E_RAW_POST);
		}
	}

	/**
	 * Add a file to the request, and make it a multipart request.
	 *
	 * @throws gimle\rest\Exception If tying to attach a file to a raw post request.
	 * @param string $key The post name.
	 * @param string $path The local path of the file to attach.
	 * @param ?string $value Give a custom name to the file.
	 * @return void
	 */
	public function file (string $key, string $path, ?string $name = null): void
	{
		if (($this->post === null) || (is_array($this->post))) {
			if ($name === null) {
				$name = basename($path);
			}
			$mimetype = implode('; ', get_mimetype($path));
			$this->file[$key] = ['path' => $path, 'mimetype' => $mimetype, 'name' => $name];
			$this->multipart = true;
		}
		else {
			throw new Exception('Can not send file when raw data is sendt.', Fetch::E_RAW_FILE);
		}
	}

	/**
	 * Send the request.
	 *
	 * @throws gimle\ErrorException If the stream can not be opened.
	 * @param string $endpoint The url to query.
	 * @param ?string $method The request method to use.
	 * @return array
	 */
	public function query (string $endpoint, ?string $method = null): array
	{
		$return = [];

		$options = ['http' => ['protocol_version' => 1.1]];

		if (($this->post !== null) || ($this->multipart === true)) {
			$options['http']['method'] = 'POST';
			if ($this->multipart === true) {
				$boundary = '--------------------------' . microtime(true);
				$this->header['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
				$options['http']['content'] = '';
				if ($this->post !== null) {
					foreach ($this->post as $key => $value) {
						$options['http']['content'] .= "--$boundary\r\n" .
						"Content-Disposition: form-data; name=\"$key\"\r\n\r\n" .
						"$value\r\n";
					}
				}
				if ($this->file !== null) {
					foreach ($this->file as $key => $value) {
						$options['http']['content'] .= "--$boundary\r\n" .
						"Content-Disposition: form-data; name=\"$key\"; filename=\"{$value['name']}\"\r\n" .
						"Content-Type: {$value['mimetype']}\r\n\r\n" .
						file_get_contents($value['path']) . "\r\n";
					}
				}
				$options['http']['content'] .= "--" . $boundary . "--\r\n";
			}
			elseif (is_array($this->post)) {
				$this->header['Content-Type'] = 'application/x-www-form-urlencoded';
				$options['http']['content'] = http_build_query($this->post);
			}
			else {
				if (!isset($this->header['Content-Type'])) {
					$temp = tempnam(TEMP_DIR, 'get_content_type_');
					file_put_contents($temp, $this->post);
					$mimetype = get_mimetype($temp);
					unlink($temp);
					$this->header['Content-Type'] = implode('; ', $mimetype);
				}
				$options['http']['content'] = $this->post;
			}
		}

		if (!empty($this->header)) {
			$options['http']['header'] = implode("\r\n", array_map(function (string $v, string $k): string {
				return $k . ': ' . $v;
			}, $this->header, array_keys($this->header)));
		}
		foreach ($this->option as $key => $value) {
			if (($key === 'followRedirect') && ($value === false)) {
				$options['http']['follow_location'] = 0;
			}
		}
		if ((isset($this->option['connectionTimeout'])) || (isset($this->option['resultTimeout']))) {
			$options['http']['timeout'] = 0;
			if (isset($this->option['connectionTimeout'])) {
				$options['http']['timeout'] += $this->option['connectionTimeout'];
			}
			if (isset($this->option['resultTimeout'])) {
				$options['http']['timeout'] += $this->option['resultTimeout'];
			}
		}

		$this->wrapper = stream_context_create($options);

		if ($stream = fopen($endpoint, 'r', false, $this->wrapper)) {
			$metadata = stream_get_meta_data($stream);
			$return['reply'] = stream_get_contents($stream);
			fclose($stream);
			$return['header'] = $metadata['wrapper_data'];
			unset($metadata['wrapper_data']);
			$return['info'] = $metadata;
			$return['error'] = 0;
		}
		else {
			$return['header'] = [];
			$return['reply'] = false;
			$return['info'] = [];
			$return['error'] = 7;
		}

		return $return;
	}
}
