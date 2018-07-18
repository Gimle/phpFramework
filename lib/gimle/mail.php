<?php
declare(strict_types=1);
namespace gimle;

use \PHPMailer\PHPMailer\PHPMailer;

/**
 * This class requires PHPMailer to be installed as a submodule.
 *
 * mkdir vendor; cd vendor; git submodule add https://github.com/PHPMailer/PHPMailer.git
 */

System::autoloadRegister(SITE_DIR . 'vendor/PHPMailer/src/', ['stripRootNamespace' => 2]);


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
	private $smtpLog = null;

	/**
	 * Bound variables.
	 *
	 * @var ?array
	 */
	private $bindVars = null;

	/**
	 * Push to spectacle?
	 *
	 * @var boolean
	 */
	private $spectacle = false;

	/**
	 * Create a new PHPMailer object.
	 *
	 * @param string $key
	 * @return object
	 */
	public function __construct (?string $key)
	{
		parent::__construct(true);
		$config = Config::get('mail.gimle');
		if ($key !== null) {
			$config = array_merge_distinct($config, Config::get('mail.' . $key));
		}

		$this->CharSet = 'UTF-8';

		if (isset($config['host'])) {
			$this->Host = $config['host'];
		}
		if (isset($config['user'])) {
			$this->SMTPDebug = 2;
			$this->isSMTP();
			$this->Port = 587;
			$this->SMTPAuth = true;
			$this->Username = $config['user'];
			if (isset($config['pass'])) {
				$this->Password = $config['pass'];
			}
		}
		if (isset($config['secure'])) {
			$this->SMTPSecure = $config['secure'];
		}
		$this->isHTML(true);

		if (isset($config['address'])) {
			if (isset($config['name'])) {
				$this->setFrom($config['address'], $config['name']);
			}
			else {
				$this->setFrom($config['address']);
			}
		}

		if ((isset($config['spectacle'])) && ($config['spectacle'] === true)) {
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
	 * Send the email
	 *
	 * @return void
	 */
	public function send ()
	{
		foreach ($this->bindVars as $key => $value) {
			$this->Body = str_replace('%' . $key . '%', $value, $this->Body);
			$this->AltBody = str_replace('%' . $key . '%', $value, $this->AltBody);
		}
		if ($this->Subject === '') {
			$this->Subject = $this->getTitle();
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
	 * Get the smtp log.
	 *
	 * @return ?string
	 */
	public function getSmtpLog ()
	{
		return $this->smtpLog;
	}
}
