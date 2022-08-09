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
			throw new Exception('Could not find db configuration for "' . $key . '".');
		}

		$this->connection = new \MongoDB\Driver\Manager($this->config['host']);
	}

	public static function one ($cursor)
	{
		$it = new \IteratorIterator($cursor);
		$it->rewind();
		return $it->current();
	}

	public function command (array $command, ?string $db = null)
	{
		$query = new \MongoDB\Driver\Command($command);
		$cursor = $this->connection->executeCommand(($db !== null ? $db : $this->config['database']), $query);
		return $cursor;
	}

	public function query (array $filter, array $options = [], ?string $namespace = null)
	{
		$namespace = $this->rs($namespace);
		$query = new \MongoDB\Driver\Query($filter, $options);
		$cursor = $this->connection->executeQuery($namespace, $query);
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

	public function write (\MongoDB\Driver\BulkWrite $bulkWrite, ?string $namespace = null): \MongoDB\Driver\WriteResult
	{
		$namespace = $this->rs($namespace);
		return $this->connection->executeBulkWrite($namespace, $bulkWrite);
	}

	public function getById ($id, ?string $namespace = null)
	{
		$filter = [
			'_id' => self::oid($id),
		];
		$options = [];

		$it = new \IteratorIterator($this->query($filter, $options, $namespace));
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

	public static function asUtime (string|\MongoDB\BSON\UTCDateTime $input): int
	{
		if (is_string($input)) {
			return strtotime($input);
		}
		return $input->toDateTime()->getTimestamp();
	}

	private function rs (?string $namespace = null): string
	{
		if ($namespace === null) {
			return $this->config['database'] . '.' . $this->config['table'];
		}
		if (strpos($namespace, '.') === false) {
			return $this->config['database'] . '.' . $namespace;
		}
		return $namespace;
	}
}
