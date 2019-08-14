<?php
declare(strict_types=1);
namespace gimle;

use \PHPMailer\PHPMailer\PHPMailer;

/**
 * This class requires PHPMailer to be installed as a submodule.
 *
 * mkdir vendor; cd vendor; git submodule add https://github.com/PHPMailer/PHPMailer.git
 */

System::autoloadRegister(SITE_DIR . 'vendor/PHPMailer/src/', ['stripRootNamespace' => 2], true);


/**
 * Mail utility class
 */
class Mail extends PHPMailer
{
	use \gimle\trick\Multiton;

	/**
	 * Smtp log.
	 *
	 * @var ?string
	 */
	public $smtpLog = null;

	/**
	 * Bound variables.
	 *
	 * @var array
	 */
	private $bindVars = [];

	/**
	 * Push to spectacle?
	 *
	 * @var boolean
	 */
	private $spectacle = false;

	/**
	 * Check if from address is set.
	 */
	private $fromIsSet = false;

	/**
	 * Check if mail is prepared.
	 */
	private $isPrepared = false;

	/**
	 * The config for this instance.
	 */
	private $config = null;

	/**
	 * Create a new PHPMailer object.
	 *
	 * @param string $key
	 * @return object
	 */
	public function __construct (?string $key)
	{
		parent::__construct(true);
		$this->config = Config::get('mail.gimle');
		if ($key !== null) {
			$this->config = array_merge_distinct($this->config, Config::get('mail.' . $key));
		}

		$this->CharSet = 'UTF-8';

		if (isset($this->config['host'])) {
			$this->Host = $this->config['host'];
		}
		if (isset($this->config['user'])) {
			$this->SMTPDebug = 2;
			$this->isSMTP();
			$this->Port = 587;
			$this->SMTPAuth = true;
			$this->Username = $this->config['user'];
			if (isset($this->config['pass'])) {
				$this->Password = $this->config['pass'];
			}
		}
		if (isset($this->config['secure'])) {
			$this->SMTPSecure = $this->config['secure'];
		}
		$this->isHTML(true);

		if ((isset($this->config['spectacle'])) && ($this->config['spectacle'] === true)) {
			$this->spectacle = true;
		}
	}

	/**
	 * Return the title from the given html mail template.
	 *
	 * @return string
	 */
	public function getTitle (): string
	{
		$dom = new \DomDocument();
		$dom->loadHTML($this->Body);
		$domXp = new \DomXpath($dom);
		$domTitle = $domXp->query('/html/head/title')->item(0);
		if (($domTitle !== null) && ($domTitle instanceof \DomElement)) {
			return $domTitle->nodeValue;
		}
		return '';
	}

	/**
	 * Bind a variable for the mail template.
	 *
	 * @var string $key The variable name.
	 * @var string $value The contents of the variable.
	 * @return void
	 */
	public function bind (string $key, string $value): void
	{
		$this->bindVars[$key] = $value;
	}

	/**
	 * Set the from address
	 *
	 * @param string $address
	 * @param string $name
	 * @param bool $auto
	 * @return mixed
	 */
	public function setFrom ($address, $name = '', $auto = true)
	{
		$this->fromIsSet = true;
		return parent::setFrom($address, $name, $auto);
	}

	/**
	 * Send the email
	 *
	 * @return void
	 */
	public function send ()
	{
		$this->prepare();

		if (isset($this->config['sender'])) {
			$this->config['sender']($this);
			return;
		}

		ob_start();
		parent::send();
		$this->smtpLog = ob_get_contents();
		ob_end_clean();

		if ($this->spectacle === true) {
			sp($this->Subject);
			sp($this->Body);
			sp($this->AltBody);
			sp($this->smtpLog);
		}
	}

	/**
	 * Prepare the email.
	 *
	 * @return void
	 */
	public function prepare (): void
	{
		if ($this->isPrepared === true) {
			return;
		}
		$this->isPrepared = true;

		foreach ($this->bindVars as $key => $value) {
			$this->Body = str_replace('%' . $key . '%', $value, $this->Body);
			$this->AltBody = str_replace('%' . $key . '%', $value, $this->AltBody);
		}
		if ($this->Subject === '') {
			$this->Subject = $this->getTitle();
		}

		if ($this->fromIsSet === false) {
			if (isset($this->config['address'])) {
				if (isset($this->config['name'])) {
					$this->setFrom($this->config['address'], $this->config['name']);
				}
				else {
					$this->setFrom($this->config['address']);
				}
			}
		}
	}
}
