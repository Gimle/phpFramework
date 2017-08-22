<?php
declare(strict_types=1);
namespace gimle;

class Form
{
	use \gimle\trick\Multiton;

	/**
	 * The id of the form.
	 *
	 * @var ?string
	 */
	private $id = null;

	/**
	 * The created forms.
	 *
	 * @var array
	 */
	private static $created = [];

	/**
	 * Create a new form, or retrieve a created one.
	 *
	 * @param ?string $retrieve null to create a new, or a valid form id to retrieve one.
	 * @return ?string The form id if a new was created.
	 */
	public function __construct (?string $retrieve = null)
	{
		if ($retrieve === null) {
			$form = $this->createNewFormInternal();
			if ($form !== null) {
				$this->id = $form['id'];
			}
			return null;
		}

		$this->id = $retrieve;
	}

	/**
	 * Retrieve the current form id.
	 *
	 * @return ?string The form id.
	 */
	public function getId (): ?string
	{
		return $this->id;
	}

	/**
	 * Validate that the form exists.
	 *
	 * @return bool Do the form exist?
	 */
	public function validate (): bool
	{
		if (isset($_SESSION['gimle']['module']['form'][$this->id])) {
			return true;
		}
		return false;
	}

	/**
	 * Set a property on the form.
	 *
	 * @param string $name The property name.
	 * @param mixed $value The property value.
	 * @return bool Do the form exist?
	 */
	public function setProperty (string $name, $value): bool
	{
		if (!isset($_SESSION['gimle']['module']['form'][$this->id])) {
			return false;
		}
		$_SESSION['gimle']['module']['form'][$this->id]['prop'][$name] = $value;
		return true;
	}

	/**
	 * Retrieve a property.
	 *
	 * @param string $name The name of the property.
	 * @return ?mixed The value of the property, or null if not found.
	 */
	public function getProperty (string $name)
	{
		if (isset($_SESSION['gimle']['module']['form'][$this->id]['prop'][$name])) {
			return $_SESSION['gimle']['module']['form'][$this->id]['prop'][$name];
		}
		return null;
	}

	/**
	 * Set the return url.
	 *
	 * @param string $url The return url.
	 * @return bool Do the form exist?
	 */
	public function setReturnUrl (string $url): bool
	{
		if (!isset($_SESSION['gimle']['module']['form'][$this->id])) {
			return false;
		}
		$_SESSION['gimle']['module']['form'][$this->id]['returnUrl'] = $url;
		return true;
	}

	/**
	 * Retrieve the return url.
	 *
	 * @return ?string The return url if set, or null.
	 */
	public function getReturnUrl (): ?string
	{
		if (!isset($_SESSION['gimle']['module']['form'][$this->id])) {
			return null;
		}
		return $_SESSION['gimle']['module']['form'][$this->id]['returnUrl'];
	}

	/**
	 * Internal method to create a new form.
	 *
	 * @return ?array The new form, or null if more than 10 forms exist.
	 */
	private function createNewFormInternal (): ?array
	{
		if (isset($_SESSION['gimle']['module']['form'])) {
			if (count($_SESSION['gimle']['module']['form']) > 10) {
				return null;
			}
		}

		$id = bin2hex(openssl_random_pseudo_bytes(12));

		if (!isset($_SESSION['gimle']['module']['form'][$id])) {
			self::$created[] = $id;
			$new = [
				'id' => $id,
				'returnUrl' => THIS_PATH,
			];
			$_SESSION['gimle']['module']['form'][$id] = $new;
			return $new;
		}

		return $this->createNewFormInternal();
	}

	/**
	 * Clean up all forms that was not created.
	 *
	 * @return void
	 */
	public function __destruct ()
	{
		if (isset($_SESSION['gimle']['module']['form'])) {
			foreach ($_SESSION['gimle']['module']['form'] as $id => $form) {
				if (!in_array($id, self::$created)) {
					unset($_SESSION['gimle']['module']['form'][$id]);
				}
			}
		}
	}
}
