<?php
declare(strict_types=1);
namespace gimle;

/**
 * Internatiolization.
 */
class i18n
{
	use trick\Singelton;

	/**
	 * A local holder for the configuration.
	 *
	 * @var ?array
	 */
	private $config = null;

	/**
	 * The current active language.
	 *
	 * @var ?string
	 */
	private $language = null;

	/**
	 * A holder for the translation objects.
	 *
	 * @var mixed
	 */
	private $objects = [];

	/**
	 * Set up the class.
	 */
	private function __construct ()
	{
		$this->config = Config::get('i18n');
		if ((!isset($this->config['lang'])) || (!is_array($this->config['lang'])) || (empty($this->config['lang']))) {
			throw new Exception('No language configured.');
		}
	}

	/**
	 * Sets up the current i18n instance.
	 *
	 * @return void
	 */
	private function setup (): void
	{
		if (isset($this->config['lang'][$this->language]['lc'])) {
			setlocale(LC_TIME, $this->config['lang'][$this->language]['lc']);
			setlocale(LC_COLLATE, $this->config['lang'][$this->language]['lc']);
			setlocale(LC_CTYPE, $this->config['lang'][$this->language]['lc']);
		}

		if ((isset($this->config['lang'][$this->language]['objects'])) && (is_array($this->config['lang'][$this->language]['objects'])) && (!empty($this->config['lang'][$this->language]['objects']))) {
			foreach ($this->config['lang'][$this->language]['objects'] as $object => $params) {
				$this->objects[$this->language][] = call_user_func_array([__NAMESPACE__ . '\\i18n\\' . mb_ucfirst($object), 'getInstance'], [$this->language, $params]);
			}
		}
	}

	/**
	 * Set the language.
	 *
	 * @param ?string $language The language
	 * @return void
	 */
	public function setLanguage (?string $language = null): void
	{
		if ($language === null) {
			$available = [];
			$synonyms = [];
			foreach ($this->config['lang'] as $code => $data) {
				$available[] = $code;
				if (isset($data['synonyms'])) {
					foreach ($data['synonyms'] as $synonym) {
						$available[] = $synonym;
						$synonyms[$synonym] = $code;
					}
				}
			}
			$this->language = get_preferred_language($available);
			if ($this->language === null) {
				if ((isset($this->config['default'])) && (isset($this->config['lang'][$this->config['default']]))) {
					$this->language = $this->config['default'];
				}
				else {
					$this->language = current(array_keys($this->config['lang']));
				}
			}
			else if ((!empty($synonyms)) && (isset($synonyms[$this->language]))) {
				$this->language = $synonyms[$this->language];
			}
		}
		else {
			if (isset($this->config['lang'][$language])) {
				$this->language = $language;
			}
			elseif ((isset($this->config['default'])) && (isset($this->config['lang'][$this->config['default']]))) {
				$this->language = $this->config['default'];
			}
			else {
				$this->language = current(array_keys($this->config['lang']));
			}
		}

		$this->setup();
	}

	/**
	 * Gets the currently selected language.
	 *
	 * @return string The language
	 */
	public function getLanguage (): string
	{
		if ($this->language === null) {
			$this->setLanguage();
		}
		return $this->language;
	}

	/**
	 * Translate a message.
	 *
	 * @param mixed $message The message to be translated.
	 * @return mixed The translated message
	 */
	public function _ (...$message)
	{
		if ($this->language === null) {
			$this->setLanguage();
		}

		if ((isset($this->objects[$this->language])) && (!empty($this->objects[$this->language]))) {
			foreach ($this->objects[$this->language] as $object) {
				$result = $object->_(...$message);
				if ($result !== null) {
					return $result;
				}
			}
		}
		return current($message);
	}

	public function getJson ()
	{
		$result = [
			'string' => [],
		];
		if ((isset($this->objects[$this->language])) && (!empty($this->objects[$this->language]))) {
			foreach ($this->objects[$this->language] as $object) {
				$result = array_merge($result, $object->getJson());
			}
		}
		return $result;
	}
}
