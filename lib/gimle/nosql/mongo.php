<?php
declare(strict_types=1);
namespace gimle\nosql;

use \gimle\Config;
use \gimle\MainConfig;
use \gimle\Exception;

use const gimle\IS_SUBSITE;

class Mongo
{
	use \gimle\trick\Multiton;

	private $config = null;

	public function __construct (string $key)
	{
		$this->config = Config::get('mongo.' . $key);
		if (($this->config === null) && (IS_SUBSITE)) {
			$this->config = MainConfig::get('mongo.' . $key);
		}
		if (!is_array($this->config)) {
			throw new Exception('Could not find db configuration.');
		}

		$this->connection = new \MongoDB\Driver\Manager($this->config['host']);
	}

	public static function one ($cursor)
	{
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		return $it->current();
	}

	public function query (array $filter, array $options = [], ?string $table = null)
	{
		if ($table === null) {
			$table = $this->config['table'];
		}
		$query = new \MongoDB\Driver\Query($filter, $options);
		$cursor = $this->connection->executeQuery($this->config['database'] . '.' . $table, $query);
		return $cursor;
	}

	public function count (array $filter, ?string $table = null): int
	{
		if ($table === null) {
			$table = $this->config['table'];
		}

		$query = new \MongoDB\Driver\Command(['count' => $table, 'query' => $filter]);
		$cursor = $this->connection->executeCommand($this->config['database'], $query);
		return current($cursor->toArray())->n;
	}

	public function write (\MongoDB\Driver\BulkWrite $bulkWrite, ?string $table = null): \MongoDB\Driver\WriteResult
	{
		if ($table === null) {
			$table = $this->config['table'];
		}
		return $this->connection->executeBulkWrite($this->config['database'] . '.' . $table, $bulkWrite);
	}

	public function getById ($id, ?string $table = null)
	{
		$filter = [
			'_id' => self::oid($id),
		];
		$options = [];

		$it = new \IteratorIterator($this->query($filter, $options, $table));
		$it->rewind();
		$document = $it->current();

		return $document;
	}

	public static function oid ($id): \MongoDB\BSON\ObjectID
	{
		if (is_string($id)) {
			$id = new \MongoDB\BSON\ObjectID($id);
		}
		return $id;
	}

	public static function asDateTime ($input = null): ?\MongoDB\BSON\UTCDateTime
	{
		if ($input === null) {
			return new \MongoDB\BSON\UTCDateTime(time() * 1000);
		}
		else if (is_int($input)) {
			return new \MongoDB\BSON\UTCDateTime($input * 1000);
		}
		else if (is_string($input)) {
			return new \MongoDB\BSON\UTCDateTime(strtotime($input) * 1000);
		}
		return null;
	}
}
