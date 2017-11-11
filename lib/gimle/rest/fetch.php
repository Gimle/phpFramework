<?php
declare(strict_types=1);
namespace gimle\rest;

/**
 * Fetch some data from a web service.
 */
class Fetch extends FetchBase
{
	/**
	 * Send the request.
	 *
	 * @param string $endpoint The url to query.
	 * @param ?string $method The request method to use.
	 * @return array
	 */
	public function query (string $endpoint, ?string $method = null, ?bool $returnBody = false): array
	{
		$return = $this->wrapper->query($endpoint, $method, $returnBody);

		foreach ($return['header'] as $header) {
			if (substr($header, 0, 7) === 'HTTP/1.') {
				$return['code'][] = (int) substr($header, 9, 3);
			}
		}

		return $return;
	}
}
