<?php
declare(strict_types=1);
namespace gimle;

class Spectacle
{
	use trick\Singelton;

	/**
	 * The id (filename) of this spectacle instance.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The data to be added to the spectacle file.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * The configuration on how to match the text when adding data.
	 *
	 * @var array
	 */
	private $match = [];

	/**
	 * The name of the tab on which to write data to.
	 *
	 * @var string
	 */
	private $tab = 'Spectacle';

	/**
	 * The location of where to store spectacle data.
	 *
	 * @var string
	 */
	private static $dir = TEMP_DIR . 'gimle/spectacle/';

	/**
	 * The lifetime of the cached spectacle data in seconds.
	 *
	 * @var int
	 */
	private static $lifeTime = 3600;

	/**
	 * Setup the spectacle instance.
	 */
	public function __construct ()
	{
		if (ENV_MODE & ENV_LIVE) {
			return;
		}
		if (!file_exists(self::$dir)) {
			mkdir(self::$dir, 0777, true);
		}
		else {
			foreach (new \DirectoryIterator(self::$dir) as $item) {
				$name = $item->getFilename();
				if (substr($name, 0, 1) === '.') {
					continue;
				}
				$age = time() - $item->getMTime();
				if ($age > self::$lifeTime) {
					unlink(self::$dir . $name);
				}
			}
		}
		$file = basename(tempnam(self::$dir, ''));
		header('X-Gimle-chrome-id: ' . $file);
		header('X-Gimle-spectacle-id: ' . $file);
		if ($base = Config::get('spectacle.base')) {
			header('X-Gimle-base-path: ' . $base);
		}
		else {
			header('X-Gimle-base-path: ' . BASE_PATH);
		}
		$this->id = $file;

		register_shutdown_function([$this, 'shutdown']);
	}

	/**
	 * Add contents to the end of the tab.
	 *
	 * @param mixed ...$data
	 * @return void
	 */
	public function push (...$data): void
	{
		$tab = $this->tab;
		$this->tab = 'Spectacle';
		$name = $tab;
		$tab = preg_replace('/[^a-zA-Z1-9]/', '_', mb_strtolower($tab));
		if ($tab === 'info') {
			return;
		}
		if (isset($this->data['tabs'][$tab])) {
			$this->data['tabs'][$tab]['content'] .= $this->datafy($data);
		}
		else {
			$this->data['tabs'][$tab] = ['title' => $name, 'content' => $this->datafy($data)];
		}
	}

	/**
	 * Add contents to the beginning of the tab.
	 *
	 * @param mixed ...$data
	 * @return void
	 */
	public function unshift (...$data): void
	{
		$tab = $this->tab;
		$this->tab = 'Spectacle';
		$name = $tab;
		$tab = preg_replace('/[^a-zA-Z1-9]/', '_', mb_strtolower($tab));
		if ($tab === 'info') {
			return;
		}
		if (isset($this->data['tabs'][$tab])) {
			$this->data['tabs'][$tab]['content'] = $this->datafy($data) . $this->data['tabs'][$tab]['content'];
		}
		else {
			$this->data['tabs'][$tab] = ['title' => $name, 'content' => $this->datafy($data)];
		}
	}

	/**
	 * Sets the text to match when pushing data to spectacle.
	 *
	 * @return self
	 */
	public function match (array $match = []): self
	{
		$this->match = $match;
		return $this;
	}

	/**
	 * Sets the current tab to pupulate data to.
	 *
	 * @param string $name The name of the tab.
	 * @return self
	 */
	public function tab (string $name): self
	{
		$this->tab = $name;
		return $this;
	}

	/**
	 * Converts an array of values into readable text.
	 *
	 * @param array<mixed> Data to stringify.
	 * @return string
	 */
	private function datafy (array $data): string
	{
		$match = $this->match;

		if (!isset($match['match'])) {
			$match['match'] = '/([a-zA-Z\\\\]+|)(Spectacle::getInstance\(\)(.*?)(->(push|unshift)\((.*)))/';
		}
		if (!isset($match['steps'])) {
			$match['steps'] = 1;
		}
		if (!isset($match['index'])) {
			$match['index'] = 4;
		}
		if (!isset($match['fallback'])) {
			$match['fallback'] = false;
		}

		$return = [];
		$backtrace = debug_backtrace();
		$backtrace = $backtrace[$match['steps']];
		$file = $backtrace['file'];
		$line = $backtrace['line'];

		if (substr($backtrace['file'], -13) == 'eval()\'d code') {
			$title = 'eval()';
		}
		else {
			$con = explode("\n", file_get_contents($backtrace['file']));
			$callee = $con[$backtrace['line'] - 1];
			preg_match_all($match['match'], $callee, $matches);
			if ((!empty($matches)) && (!empty($matches[$match['index']]))) {
				$i = 0;
				$title = '';
				foreach (str_split($matches[$match['index']][0], 1) as $value) {
					if ($value === '(') {
						$i++;
					}
					if (($i === 0) && ($value === ',')) {
						break;
					}
					if ($value === ')') {
						$i--;
					}
					if (($i === 0) && ($value === ')')) {
						$title .= $value;
						break;
					}
					$title .= $value;
				}
			}
			elseif ($match['fallback'] === false) {
				$title = trim($con[$backtrace['line'] - 1]);
			}
			else {
				$title = $match['fallback'];
			}
		}
		$this->match = [];

		$return[] = '<p><span style="font-family: monospace; color: DarkBlue;">' . $title . '</span> in <span style="color: DarkBlue;">' . $file . '</span> on line <span style="color: DarkBlue;">' . $line . '</span></p>';

		foreach ($data as $index => $item) {
			$return[] = d($item, ['return' => true, 'title' => 'param' . ($index + 1)]);
		}
		return implode('', $return);
	}

	/**
	 * Get final data to send to spectacle before script end.
	 *
	 * @return void
	 */
	public function shutdown (): void
	{
		$this->data['time'] = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
		$this->data['info']['mysql'] = false;

		if (Config::exists('mysql')) {
			$dbs = sql\Mysql::getInstances();
			if (!empty($dbs)) {
				$this->data['tabs']['mysql'] = ['title' => 'Mysql', 'content' => ''];
				$this->data['info']['mysql'] = ['connections' => 0, 'count' => 0, 'time' => 0, 'duplicates' => false];
				foreach ($dbs as $name) {
					$this->data['info']['mysql']['connections']++;
					$db = sql\Mysql::getInstance($name);
					$this->data['tabs']['mysql']['content'] .= $db->explain();
					$stats = $db->explainJson();
					$this->data['info']['mysql']['count'] += count($stats['queries']);
					$this->data['info']['mysql']['time'] += $stats['time'];
					if (($stats['duplicates'] === true) && ($this->data['info']['mysql']['duplicates'] === false)) {
						$this->data['info']['mysql']['duplicates'] = true;
						$this->data['tabs']['mysql']['content'] = '<p style="color: deeppink;">You have duplicate queries!</p>' . $this->data['tabs']['mysql']['content'];
					}
				}
			}
		}
	}

	/**
	 * Clean up old spectacle files
	 *
	 * @param ?int $ttl Hov long to keep spectacle files.
	 * @return void.
	 */
	public static function clean (int $ttl = 600): void
	{
		if (ENV_MODE & ENV_LIVE) {
			return;
		}
		if (file_exists(self::$dir)) {
			foreach (new \DirectoryIterator(self::$dir) as $fileInfo) {
				$filename = $fileInfo->getFilename();
				if ((substr($filename, 0, 1) === '.') || (!$fileInfo->isFile())) {
					continue;
				}

				if (($ttl === null) || ($ttl < time() - $fileInfo->getMTime())) {
					unlink(self::$dir . $filename);
				}
			}
		}
	}

	/**
	 * Retrieve a spectacle file.
	 *
	 * @param string $id The name of the file to load.
	 * @return ?string The contents of the file if found.
	 */
	public static function read (string $id): ?string
	{
		if (ENV_MODE & ENV_LIVE) {
			return null;
		}
		if (file_exists(self::$dir . $id)) {
			return file_get_contents(self::$dir . $id);
		}
		return null;
	}

	/**
	 * Write the data to disk so it can be loaded from a spectacle viewer.
	 */
	public function __destruct ()
	{
		if (ENV_MODE & ENV_LIVE) {
			return;
		}
		file_put_contents(self::$dir . $this->id, json_encode($this->data) . "\n");
		chmod(self::$dir . $this->id, 0664);
	}
}
