<?php
declare(strict_types=1);
namespace gimle\rest;

/**
 * Fetch some data from a web service.
 */
class Fetch
{
	/**
	 * Will be thrown if adding post data to a raw post request.
	 *
	 * @var int
	 */
	public const E_RAW_POST = 1;

	/**
	 * Will be thrown if adding raw data to a post data request.
	 *
	 * @var int
	 */
	public const E_POST_RAW = 2;

	/**
	 * Will be thrown if adding a file to a raw post request.
	 *
	 * @var int
	 */
	public const E_RAW_FILE = 3;

	/**
	 * A holder for the current wrapper object.
	 *
	 * @var mixed
	 */
	private $wrapper;

	/**
	 * Initialize a new Fetch object.
	 */
	public function __construct ()
	{
		if (function_exists('curl_init')) {
			$this->wrapper = new Curl();
		}
		else {
			$this->wrapper = new Stream();
		}

		$this->reset(true);
	}

	/**
	 * Reset the object, so it can be reused.
	 *
	 * @param ?bool $full Should options and headers be kept?
	 * @return sef
	 */
	public function reset (bool $full = false): self
	{
		$this->wrapper->reset($full);

		if ($full === true) {
			$this->wrapper->option('connectionTimeout', 1);
			$this->wrapper->option('resultTimeout', 1);
			$this->wrapper->option('followRedirect', true);

			$this->wrapper->header('Connection', 'close');
			$this->wrapper->header('Accept', '*/*');
		}
		return $this;
	}

	/**
	 * Sets the time the request will wait for the connections to be established in seconds.
	 *
	 * @param float $float
	 * @return sef
	 */
	public function connectionTimeout (float $float): self
	{
		$this->wrapper->option('connectionTimeout', $float);
		return $this;
	}

	/**
	 * Sets the time the request will wait for an answer in seconds.
	 *
	 * @param float $float
	 * @return sef
	 */
	public function resultTimeout (float $float): self
	{
		$this->wrapper->option('resultTimeout', $float);
		return $this;
	}

	/**
	 * Should redirects be followed?
	 *
	 * @param bool $bool
	 * @return sef
	 */
	public function followRedirect (bool $bool = true): self
	{
		$this->wrapper->option('followRedirect', $bool);
		return $this;
	}

	/**
	 * Sets a post value for the request.
	 *
	 * @throws gimle\rest\Exception If tying to add post field to a raw post request, or the other way around.
	 * @param string $key The post name, or the whole raw post data.
	 * @param ?string $value The post value.
	 * @return sef
	 */
	public function post (string $key, $data = null): self
	{
		$this->wrapper->post($key, $data);
		return $this;
	}

	/**
	 * Add a file to the request, and make it a multipart request.
	 *
	 * @throws gimle\rest\Exception If tying to attach a file to a raw post request.
	 * @param string $key The post name.
	 * @param string $path The local path of the file to attach.
	 * @param ?string $value Give a custom name to the file.
	 * @return sef
	 */
	public function file (string $key, $data, ?string $name = null): self
	{
		$this->wrapper->file($key, $data, $name);
		return $this;
	}

	/**
	 * Sets a header for the request.
	 *
	 * @param string $key The header name.
	 * @param string $value The header value.
	 * @return sef
	 */
	public function header (string $key, string $value): self
	{
		$this->wrapper->header($key, $value);
		return $this;
	}

	/**
	 * Send the request.
	 *
	 * @param string $endpoint The url to query.
	 * @param ?string $method The request method to use.
	 * @return array
	 */
	public function query (string $endpoint, ?string $method = null): array
	{
		$return = $this->wrapper->query($endpoint, $method);

		foreach ($return['header'] as $header) {
			if (substr($header, 0, 7) === 'HTTP/1.') {
				$return['code'][] = (int) substr($header, 9, 3);
			}
		}

		return $return;
	}
}
