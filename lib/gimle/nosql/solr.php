<?php
declare(strict_types=1);
namespace gimle\nosql;

use \gimle\Config;
use \gimle\MainConfig;
use \gimle\rest\Fetch;
use \gimle\xml\SimpleXmlElement;
use \gimle\Exception;
use const \gimle\IS_SUBSITE;
use function \gimle\ent2utf8;
use function \gimle\sp;

/**
 * Solr Utilities class.
 */
class Solr
{
	public const STATUS = 1;
	public const RELOAD = 2;

	/**
	 * The url of the solr server.
	 *
	 * @var string
	 */
	protected $url = 'http://localhost:8983/solr/';

	/**
	 * The url of the solr server.
	 *
	 * @var string
	 */
	protected $core = null;

	/**
	 * Create a new Solr object.
	 *
	 * @param ?string $url The url of the solr server.
	 * @param ?string $url The core name.
	 * @return object
	 */
	public function __construct (?string $url = null, ?string $core = null)
	{
		if ($url !== null) {
			$this->url = $url;
		}
		elseif ((IS_SUBSITE) && (MainConfig::get('solr.url') !== null)) {
			$this->url = MainConfig::get('solr.url');
		}
		elseif (Config::get('solr.url') !== null) {
			$this->url = Config::get('solr.url');
		}
		if ($core !== null) {
			$this->core = $core;
		}
		elseif ((IS_SUBSITE) && (MainConfig::get('solr.core') !== null)) {
			$this->core = MainConfig::get('solr.core');
		}
		elseif (Config::get('solr.core') !== null) {
			$this->core = Config::get('solr.core');
		}
	}

	/**
	 * Commit to solr.
	 *
	 * @return array
	 */
	public function commit (): array
	{
		return $this->put('<?xml version="1.0" encoding="UTF-8"?><commit/>');
	}

	/**
	 * Optimize solr.
	 *
	 * @return array
	 */
	public function optimize (): array
	{
		return $this->put('<?xml version="1.0" encoding="UTF-8"?><optimize/>');
	}

	/**
	 * Rollback solr.
	 *
	 * @return array
	 */
	public function rollback (): array
	{
		return $this->put('<?xml version="1.0" encoding="UTF-8"?><rollback/>');
	}

	/**
	 * Perform a search.
	 *
	 * @param string $q The search query.
	 * @param array $params Parameters for the search.
	 * @param bool $parseResult Should xml and json responces be returned as their datatype instead of plain text.
	 * @return array
	 */
	public function search (string $q, array $params = [], bool $parseResult = true): array
	{
		$params['q'] = $q;

		if (!isset($params['wt'])) {
			$params['wt'] = 'xml';
		}

		if (!isset($params['fl'])) {
			$params['fl'] = '*,score';
		}

		if (!isset($params['facet.missing'])) {
			$params['facet.missing'] = 'true';
		}

		return $this->get($params, $parseResult);
	}

	/**
	 * Perform a query.
	 *
	 * @param string $q The query.
	 * @param array $params Parameters for the query.
	 * @param string $handler The handler to query.
	 * @param bool $parseResult Should xml and json responces be returned as their datatype instead of plain text.
	 * @return array
	 */
	public function query (string $q, array $params = [], string $handler = 'select', bool $parseResult = true): array
	{
		$params['q'] = $q;

		return $this->get($params, $parseResult, $handler);
	}

	/**
	 * Get solr metrics
	 *
	 * @param string $group The group to query metrics from.
	 * @param array $params Parameters for the metrics.
	 * return @array
	 */
	public function metrics (string $group, array $params = []): array
	{
		$params = ['group' => $group] + $params;

		$fetch = new Fetch();
		$fetch->connectionTimeout(2);
		$fetch->resultTimeout(3);

		$queryString = http_build_query($params, '', '&');

		$searchUrl = $this->url . 'admin/metrics?' . $queryString;

		$result = $fetch->query($searchUrl);

		return json_decode($result['reply'], true);
	}

	/**
	 * Delete a record.
	 *
	 * @param mixed $id int to delete by id, or string to delete by query.
	 * @return array
	 */
	public function delete ($id): array
	{
		if (is_int($id)) {
			return $this->put('<?xml version="1.0" encoding="UTF-8"?><delete><id>' . (string) $id . '</id></delete>');
		}
		return $this->put('<?xml version="1.0" encoding="UTF-8"?><delete><query>' . $id . '</query></delete>');
	}

	/**
	 * Index an article.
	 *
	 * @param array $array Data for the article to index.
	 * @param ?int $boost Should this article be boosted
	 * @return array
	 */
	public function index (array $array = [], ?int $boost = null): array
	{
		$xml = $this->prepare($array, $boost);
		return $this->put($xml->asXml());
	}

	/**
	 * Talk to the core manager.
	 *
	 * @param int $action One of the action constants.
	 * @param array $params Parameters for the quary.
	 * @param bool $parseResult Should xml and json responces be returned as their datatype instead of plain text.
	 * @return array
	 */
	public function core (int $action = null, array $params = [], bool $parseResult = true)
	{
		/* Potentional actions: https://wiki.apache.org/solr/CoreAdmin */

		$fetch = new Fetch();
		$fetch->connectionTimeout(2);
		$fetch->resultTimeout(3);

		if ($action === self::STATUS) {
			$url = $this->url . 'admin/cores?action=STATUS';
			if (isset($params['wt'])) {
				$url .= '&wt=' . $params['wt'];
			}
			$result = $fetch->query($url);
		}
		elseif ($action === self::RELOAD) {
			$url = $this->url . 'admin/cores?action=RELOAD&core=' . $this->core;
			if (isset($params['wt'])) {
				$url .= '&wt=' . $params['wt'];
			}
			$result = $fetch->query($url);
		}

		if ($parseResult === true) {
			$json = true;
			if (isset($params['wt'])) {
				$json = false;
				if ($params['wt'] === 'xml') {
					$result['reply'] = new SimpleXmlElement($result['reply']);
				}
				elseif ($params['wt'] === 'json') {
					$json = true;
				}
			}
			if ($json === true) {
				$result['reply'] = json_decode($result['reply'], true);
			}
		}

		return $result;
	}

	/**
	 * Format a date to solr compatible format.
	 *
	 * @param mixed $date int for unix time or string for variable date formats.
	 * @return string Solr compatible date format.
	 */
	public static function formatDate ($date)
	{
		if (is_int($date)) {
			return gmdate('Y-m-d\TH:i:s\Z', $date);
		}
		return gmdate('Y-m-d\TH:i:s\Z', strtotime($date));
	}

	/**
	 * Remove markup and special characters from text to prepare for indexing.
	 *
	 * @param string Text with markup and special characters.
	 * @return string Text without markup and special characters.
	 */
	public static function formatText (string $input): string
	{
		$input = str_replace('</p>', '</p> ', $input);
		$input = str_replace('<br>', '<br> ', $input);
		$input = str_replace('<br/>', '<br/> ', $input);
		$input = str_replace('<br />', '<br /> ', $input);
		$input = strip_tags($input);
		$input = ent2utf8($input);
		$input = str_replace('­', '-', $input); // Soft hyphen
		$input = str_replace('–', '-', $input);
		$input = str_replace(' ', ' ', $input); // non breaking space.
		$input = str_replace(' ', ' ', $input); // thinspace.
		$input = preg_replace('/\s+/s', ' ', $input);
		$input = trim($input);
		return $input;
	}

	protected function prepare (array $array = [], ?int $boost = null): ?SimpleXmlElement
	{
		if (!isset($array['id'])) {
			return null;
		}

		libxml_use_internal_errors(true);

		$xmlStr = '<?xml version="1.0" encoding="UTF-8"?><add></add>';
		$xmlObject = new SimpleXMLElement($xmlStr);
		$xmlDoc = $xmlObject->addChild('doc');
		if ($boost !== null) {
			$xmlDoc->addAttribute('boost', $boost);
		}

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				if (isset($value['value'])) {
					$field = $xmlDoc->addChild('field', htmlspecialchars($value['value']));
					if (isset($value['boost'])) {
						$field->addAttribute('boost', $value['boost']);
					}
				}
				else {
					$tmp = $value;
					if (isset($tmp['boost'])) {
						unset($tmp['boost']);
					}
					foreach ($tmp as $value2) {
						$field = $xmlDoc->addChild('field', $value2);
						if (isset($value['boost'])) {
							$field->addAttribute('boost', $value['boost']);
						}
						$field->addAttribute('name', $key);
						unset($field);
					}
				}
			}
			else if (is_int($value)) {
				$field = $xmlDoc->addChild('field', (string) $value);
			}
			else {
				$field = $xmlDoc->addChild('field', htmlspecialchars($value));
			}
			if (isset($field)) {
				$field->addAttribute('name', $key);
				unset($field);
			}
		}

		return $xmlObject;
	}

	protected function getUrl (): string
	{
		$return = $this->url;
		if ($this->core !== null) {
			$return .= $this->core . '/';
		}
		return $return;
	}

	protected function put ($xml): array
	{
		$url = $this->getUrl() . 'update/';
		$fetch = new Fetch();
		$fetch->connectionTimeout(2);
		$fetch->resultTimeout(3);

		$fetch->header('Content-type', 'text/xml; charset=utf-8');
		$fetch->post($xml);

		return $fetch->query($url);
	}

	protected function get (array $params = [], bool $parseResult = true, string $handler = 'select'): array
	{
		$url = $this->getUrl() . $handler;
		$fetch = new Fetch();
		$fetch->connectionTimeout(2);
		$fetch->resultTimeout(3);

		$fetch->header('Accept-Charset', 'UTF-8,utf-8;q=0.7,*;q=0.7');

		$queryString = http_build_query($params, '', '&');
		$queryString = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $queryString);

		$searchUrl = $url . '?' . $queryString;

		$result = $fetch->query($searchUrl);
		if ($parseResult === true) {
			if ($params['wt'] === 'xml') {
				try {
					$result['reply'] = new SimpleXmlElement($result['reply']);
				}
				catch (\Exception $t) {
					$e = new Exception($t->getMessage(), $t->getCode(), $t);
					if ($result['error'] === 7) {
						$e->set('debug', 'Is solr running and accessible by this server?');
					}
					array_walk($result, function ($value, $key, $e) {
						$e->set($key, $value);
					}, $e);
					throw $e;
				}
			}
			elseif ($params['wt'] === 'json') {
				$result['reply'] = json_decode($result['reply'], true);
			}
		}
		return $result;
	}
}
