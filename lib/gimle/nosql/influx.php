<?php
declare(strict_types=1);
namespace gimle\nosql;

use \gimle\Config;
use \gimle\MainConfig;
use \gimle\Exception;
use \gimle\rest\Fetch;

use function \gimle\sp;

use const \gimle\IS_SUBSITE;

class Influx
{
	use \gimle\trick\Multiton;

	private $config = null;

	public function __construct (string $key)
	{
		$this->config = Config::get('influx.' . $key);
		if (($this->config === null) && (IS_SUBSITE)) {
			$this->config = MainConfig::get('influx.' . $key);
		}
	}

	public function query (string $q)
	{
		$fetch = new Fetch();
		$fetch->post('q', $q);
		$fetch->connectionTimeout(10);
		$fetch->resultTimeout(60);
		$result = $fetch->query($this->config['host'] . ':8086/query?pretty=true&db=' . $this->config['database']);
		return json_decode($result['reply'], true);
	}
}
