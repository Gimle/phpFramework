<?php
declare(strict_types=1);
namespace gimle\rest;

use \gimle\xml\SimpleXmlElement;
use \gimle\Exception;
use const \gimle\FILTER_SANITIZE_FILENAME;
use const \gimle\FILTER_VALIDATE_DATE;
use const \gimle\ENV_MODE;
use function \gimle\filter_var;
use function \gimle\d;

/**
 * Fetch some data from a web service.
 */
class FetchCache extends FetchBase
{
	/**
	 * Expect result in plain text format.
	 *
	 * @var int
	 */
	public const TEXT = 0;

	/**
	 * Expect result in xml format.
	 *
	 * @var int
	 */
	public const XML = 1;

	/**
	 * Expect result in json format.
	 *
	 * @var int
	 */
	public const JSON = 2;

	/**
	 * Expect result in php serialized format.
	 *
	 * @var int
	 */
	public const PHP_SERIALIZED = 3;

	/**
	 * Expect result in binary format.
	 *
	 * @var int
	 */
	public const BINARY = 4;


	/**
	 * There was no previous cache available.
	 *
	 * @var int
	 */
	public const NO_CACHE = 1;

	/**
	 * The cache was older than the ttl.
	 *
	 * @var int
	 */
	public const TTL_TIMEOUT = 2;

	/**
	 * The previous cache said it was outdated.
	 *
	 * @var int
	 */
	public const CONTENT_TIMEOUT = 3;

	/**
	 * What type of content to expect back.
	 *
	 * @var ?int
	 */
	private $expect = null;

	/**
	 * The location of the result that specifies when the cache should be renewed.
	 *
	 * @var ?string
	 */
	private $expire = null;

	/**
	 * The minimum time to keep the cache in seconds.
	 *
	 * @var int
	 */
	private $minTtl = 60;   // Default one minute.

	/**
	 * The maximum time to keep the cache in seconds.
	 *
	 * @var int
	 */
	private $maxTtl = 3600; // Default one hour.

	/**
	 * What type of content to expect.
	 *
	 * @param ?int $type
	 * @return self
	 */
	public function expect (?int $type)
	{
		$this->expect = $type;
		return $this;
	}

	/**
	 * Reset the object, so it can be reused.
	 *
	 * @param ?bool $full Should options and headers be kept?
	 * @return sef
	 */
	public function reset (bool $full = false)
	{
		parent::reset($full);
		$this->expect = null;
		$this->expire = null;
		$this->minTtl = 60;
		$this->maxTtl = 3600;
		return $this;
	}

	/**
	 * A string specifying where to find information in the result about when the server has a new result ready.
	 * If the expected content is xml, this would be an xpath.
	 *
	 * @param ?string $search
	 * @return self
	 */
	public function expire (?string $search)
	{
		$this->expire = $search;
		return $this;
	}

	/**
	 * How long to atleast keep the cache.
	 * The cache will be kept for this long even if the expire is set to renew it.
	 *
	 * @param int $value In seconds.
	 * @return self
	 */
	public function minTtl (int $value)
	{
		$this->minTtl = $value;
		return $this;
	}

	/**
	 * How long to maximum keep the cache.
	 * If no expire is supplied, the cache also be renewed after this amount of time.
	 * Renew the cache ater this amount of time, even if the expire says it is not due yet.
	 * If ttl is null, then do not renew if a cache exist.
	 * This option is added so a website can utilize a cached value without having to perform a query that might slow the page.
	 * The cache in this setup can be renewed by a cron job or other means with a different ttl setting.
	 *
	 * @param ?int $value In seconds.
	 * @return self
	 */
	public function maxTtl (?int $value)
	{
		$this->maxTtl = $value;
		return $this;
	}

	/**
	 * Send the request or fetch the cache.
	 *
	 * @param string $endpoint The url to query.
	 * @param ?string $method The request method to use.
	 * @param ?callable $validationCallback If the result should be verified before cached.
	 * @return array
	 */
	public function query (string $url, ?string $method = null, ?callable $validationCallback = null)
	{
		// The result will be the same as from the normal query, but with some additional fields:
		$return = [
			'created' => null, // The datetime when the cache was created.
			'queried' => null,
			'hadCache' => true,
			'usedCache' => true,
			'formatError' => null,
			'validationError' => null,
		];
		// Also the result will contain an array with info about the last successful query.

		$dir = 'cache://gimle/fetchCache/' . filter_var($url, FILTER_SANITIZE_FILENAME, ['replace_char' => '★']) . '/';

		if ((!file_exists($dir . 'reply')) || (!file_exists($dir . 'meta'))) {
			// There is no cache, so have to query.
			$return['queried'] = self::NO_CACHE;
			$return['hadCache'] = false;
			if (!file_exists($dir)) {
				mkdir($dir, 0777, true);
			}
		}
		else {
			$replyTime = time() - filemtime($dir . 'reply');
			$metaTime = time() - filemtime($dir . 'meta');

			if (($replyTime < $this->minTtl) || ($metaTime < $this->minTtl)) {
				// Prevent hammering if the cache is ok.
			}
			elseif (($this->maxTtl !== null) && (($replyTime > $this->maxTtl) || ($metaTime > $this->maxTtl))) {
				// Ttl says to refresh, so lets do it.
				$return['queried'] = self::TTL_TIMEOUT;
			}
			elseif ($this->expire !== null) {
				// A check for expire is set, so check before querying again.
				$expire = null;
				if ($this->expect === self::XML) {
					$sxml = new SimpleXmlElement(file_get_contents($dir . 'reply'));

					$test = current($sxml->xpath($this->expire));
					if ($test !== false) {
						$test = (string) $test;
						if (filter_var($test, FILTER_VALIDATE_DATE)) {
							$expire = strtotime($test);
						}
					}
				}
				elseif ($this->expect === self::JSON) {
					throw new Exception('Json expire not supported yet.');
				}
				else {
					throw new Exception('Can only set expire for xml and json.');
				}

				if ($expire !== null) {
					// Found an expire value, so checking against that one.
					if (time() > $expire) {
						$return['queried'] = self::CONTENT_TIMEOUT;
					}
				}
			}
		}

		$queryResult = [];
		if ($return['queried'] !== null) {
			$result = $queryResult = $this->_query($url, $method);
			if ($queryResult['error'] === 0) {
				$reply = $result['reply'];
				unset($result['reply']);
				$result['query'] = [];
				$result['query']['mode'] = ENV_MODE;
				$result['query']['user'] = get_current_user();

				$cacheIt = true;
				$decoded = null;
				if ($this->expect === self::XML) {
					try {
						$decoded = new SimpleXmlElement($reply);
					}
					catch (Exception $e) {
						$validationCallback = null;
						$cacheIt = false;
						$return['formatError'] = $e->getMessage();
					}
				}
				elseif ($this->expect === self::JSON) {
					$decoded = json_decode($reply, true);
					$jerr = json_last_error();
					if ($jerr !== JSON_ERROR_NONE) {
						$validationCallback = null;
						$cacheIt = false;
						$return['formatError'] = $jerr;
					}
				}
				if ($validationCallback !== null) {
					$valid = $validationCallback($queryResult, $decoded);
					if ($valid !== true) {
						if (is_string($valid)) {
							$reply = $valid;
						}
						else {
							$cacheIt = false;
							$return['validationError'] = $valid;
						}
					}
				}

				if ($cacheIt === true) {
					$return['usedCache'] = false;
					file_put_contents($dir . 'reply', $reply);
					file_put_contents($dir . 'meta', json_encode($result));
				}
			}
		}

		if ((file_exists($dir . 'reply')) && (file_exists($dir . 'meta'))) {
			$builder = [];
			$builder['reply'] = file_get_contents($dir . 'reply');
			$builder = array_merge($builder, json_decode(file_get_contents($dir . 'meta'), true));
			$return['created'] = date('Y-m-d H:i:s', filemtime($dir . 'reply'));
			$return = array_merge($builder, $return);
		}
		else {
			$return['usedCache'] = false;
			$return = array_merge($queryResult, $return);
		}

		return $return;
	}

	/**
	 * Send the request.
	 *
	 * @param string $endpoint The url to query.
	 * @param ?string $method The request method to use.
	 * @return array
	 */
	private function _query (string $endpoint, ?string $method = null): array
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
