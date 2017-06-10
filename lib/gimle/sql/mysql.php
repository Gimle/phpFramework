<?php
declare(strict_types=1);
namespace gimle\sql;

use gimle\Config;
use gimle\MainConfig;
use const gimle\IS_SUBSITE;

/**
 * MySQL Utilities class.
 */
class Mysql extends \mysqli
{
	use \gimle\trick\Multiton;
	use Explain;

	/**
	 * Information of the performed queries.
	 *
	 * @var array
	 */
	private $queryCache = [];

	/**
	 * Create a new Mysqli object.
	 *
	 * @param string $key
	 * @return object
	 */
	public function __construct (string $key)
	{
		mysqli_report(MYSQLI_REPORT_STRICT);

		$params = Config::get('mysql.' . $key);
		if (($params === null) && (IS_SUBSITE)) {
			$params = MainConfig::get('mysql.' . $key);
		}
		if ($params === null) {
			$params = [];
		}
		parent::init();
		$params['user'] = (isset($params['user']) ? $params['user'] : 'root');
		$params['pass'] = (isset($params['pass']) ? $params['pass'] : '');
		$params['host'] = (isset($params['host']) ? $params['host'] : '127.0.0.1');
		$params['port'] = (isset($params['port']) ? $params['port'] : 3306);
		$params['timeout'] = (isset($params['timeout']) ? $params['timeout'] : 30);
		$params['charset'] = (isset($params['charset']) ? $params['charset'] : 'utf8');
		$params['database'] = (isset($params['database']) ? $params['database'] : false);
		parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, $params['timeout']);
		parent::real_connect($params['host'], $params['user'], $params['pass'], $params['database'], $params['port']);
		if ($this->errno === 0) {
			$this->set_charset($params['charset']);
			if ((isset($params['cache'])) && ($params['cache'] === false)) {
				$this->cache(false);
			}
		}
	}

	/**
	 * Turn Mysql cache on or off.
	 *
	 * @param ?bool $mode bool|null true = on, false = off, null (Default) = return current state.
	 * @return mixed bool|array
	 */
	public function cache (?bool $mode = null)
	{
		if ($mode === true) {
			return parent::query("SET SESSION query_cache_type = ON;");
		}
		elseif ($mode === false) {
			return parent::query("SET SESSION query_cache_type = OFF;");
		}
		else {
			return parent::query("SHOW VARIABLES LIKE 'query_cache_type';")->fetch_assoc();
		}
	}

	/**
	 * Perform a mysql query.
	 *
	 * @see mysqli::query()
	 *
	 * @param string $query
	 * @param int $resultmode MYSQLI_STORE_RESULT
	 * @return mixed bool|object
	 */
	public function query ($query, int $resultmode = MYSQLI_STORE_RESULT)
	{
		$t = microtime(true);
		$error = false;
		if (!$result = parent::query($query, $resultmode)) {
			$error = ['errno' => $this->errno, 'error' => $this->error];
		}
		$t = microtime(true) - $t;
		$this->queryCache[] = ['query' => $query, 'time' => $t, 'rows' => $this->affected_rows, 'error' => $error];
		if (!$result) {
			$e = new Exception('MySQL query error: (' . $this->errno . ') ' . $this->error, $this->errno);
			$e->set('error', $this->error);
			$e->set('query', $query);
			$e->set('time', $t);
			$e->set('rows', $this->affected_rows);
			throw $e;
		}
		$mysqliresult = (is_bool($result) ? $result : new Mysqliresult($result));
		return $mysqliresult;
	}

}
