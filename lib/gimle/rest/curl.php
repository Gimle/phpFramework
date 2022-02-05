<?php
declare(strict_types=1);
namespace gimle\rest;

use const \gimle\TEMP_DIR;
use function \gimle\get_mimetype;

/**
 * A Curl implementation for the Fetch() class.
 *
 * This class should not be called directly, but no access control is added because that would also give an unwated overhead when used correctly.
 */
class Curl
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
	 * Cookies for the request. Set via the cookie() method.
	 *
	 * @var array
	 */
	private $cookie = [];

	/**
	 * Post data for the request. Set via the post() and file() methods.
	 *
	 * @var mixed null, string or array.
	 */
	private $post = null;

	/**
	 * Is this a multipart form data request. Set automatically.
	 *
	 * @var bool
	 */
	private $multipart = false;

	/**
	 * The body of the request.
	 *
	 * @var ?string
	 */
	private $body = null;

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
	 * Sets a cookie for the request.
	 *
	 * @param string $key The cookie name.
	 * @param string $value The cookie value.
	 * @return void
	 */
	public function cookie (string $key, string $value): void
	{
		$this->cookie[$key] = $value;
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
			if ($this->post === null) {
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
			$this->post[$key] = new \CurlFile($path, $mimetype, $name);
			$this->multipart = true;
		}
		else {
			throw new Exception('Can not send file when raw data is sendt.', Fetch::E_RAW_FILE);
		}
	}

	private function prepare (string $endpoint, ?string $method = null)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if (($this->post !== null) || ($this->multipart === true)) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if (!isset($this->header['Expect'])) {
				$this->header['Expect'] = '';
			}

			if ($this->multipart === true) {
				$this->body = $this->post;
			}
			elseif (is_array($this->post)) {
				$this->body = http_build_query($this->post);
			}
			else {
				if (!isset($this->header['Content-Type'])) {
					$temp = tempnam(TEMP_DIR, 'get_content_type_');
					file_put_contents($temp, $this->post);
					$mimetype = get_mimetype($temp);
					unlink($temp);
					$this->header['Content-Type'] = implode('; ', $mimetype);
				}
				$this->body = $this->post;
			}

			if ($this->body !== null) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
			}
		}
		if (!empty($this->header)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function (string $v, string $k): string {
				return $k . ': ' . $v;
			}, $this->header, array_keys($this->header)));
		}

		foreach ($this->option as $key => $value) {
			if ($key === 'encoding') {
				curl_setopt($ch, CURLOPT_ENCODING, $value);
			}
			elseif (($key === 'followRedirect') && ($value === true)) {
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			}
			elseif ($key === 'connectionTimeout') {
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, (int) ($value * 1000));
			}
			elseif ($key === 'resultTimeout') {
				curl_setopt($ch, CURLOPT_TIMEOUT_MS, (int) ($value * 1000));
			}
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

		if ($method !== null) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			if ($method === 'HEAD') {
				curl_setopt($ch, CURLOPT_IGNORE_CONTENT_LENGTH, 1);
			}
		}

		if (!empty($this->cookie)) {
			$cookies = [];
			foreach ($this->cookie as $name => $value) {
				$cookies[] = $name . '=' . $value;
			}
			curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookies));
		}

		curl_setopt($ch, CURLINFO_HEADER_OUT, true);

		return $ch;
	}

	/**
	 * Send the request.
	 *
	 * @param string $endpoint The url to query.
	 * @param ?string $method The request method to use.
	 * @param ?bool $returnBody Include the body in the returned value.
	 * @return array
	 */
	public function query (string $endpoint, ?string $method = null, ?bool $returnBody = false): array
	{
		$ch = $this->prepare($endpoint, $method);

		$result = curl_exec($ch);

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		$return['reply'] = '';
		if ($result !== false) {
			$return['reply'] = substr($result, $header_size);
			$return['header'] = array_values(array_diff(explode("\r\n", substr($result, 0, $header_size)), ['']));
		}
		else {
			$return['header'] = [];
		}
		$return['info'] = curl_getinfo($ch);
		if ($returnBody === true) {
			$return['info']['request_body'] = $this->body;
		}
		$return['error'] = curl_errno($ch);

		curl_close($ch);

		return $return;
	}
}
