<?php
declare(strict_types=1);
namespace gimle\xml;

trait PrivateHelpers
{
	/**
	 * Convert DomElement to SimpleXmlElement
	 *
	 * @param DomElement $dom The DomDocument.
	 * @return ?SimpleXmlElement
	 */
	private function domToSxml (\DomNode $dom): ?self
	{
		$sxml = simplexml_import_dom($dom, get_class($this));
		if ($sxml !== false) {
			return $sxml;
		}
		return null;
	}

	/**
	 * Check if the reference is of type SimpleXmlElement.
	 *
	 * @param mixed $ref The reference to check.
	 * @return bool
	 */
	private function isSxml ($ref): bool
	{
		if ((get_class($ref)) || (is_subclass_of($ref, 'SimpleXmlElement'))) {
			return true;
		}
		return false;
	}

	/**
	 * Check if the reference is of type DomNode.
	 *
	 * @param mixed $ref The reference to check.
	 * @return bool
	 */
	private function isDom ($ref): bool
	{
		try {
			if ((get_class($ref)) && (is_subclass_of($ref, '\DomNode'))) {
				return true;
			}
		}
		catch (\Throwable $e) {
		}
		return false;
	}

	/**
	 * Check if the reference is an instance of self.
	 *
	 * @param mixed $ref The reference to check.
	 * @return bool
	 */
	private function isSelf ($ref): bool
	{
		$dom = dom_import_simplexml($this);
		$ref = dom_import_simplexml($ref);
		if ($ref->ownerDocument !== $dom->ownerDocument) {
			return false;
		}
		return true;
	}

	/**
	 * Require the reference to be an instance of self.
	 *
	 * @param mixed $ref The reference to check.
	 * @return void
	 */
	private function requireSelf ($ref): void
	{
		if (!isSelf($ref)) {
			throw new \DomException('The reference node does not come from the same document as the context node.', DOM_WRONG_DOCUMENT_ERR);
		}
	}

	/**
	 * Try to resolve any input to an array of SimpleXmlElement.
	 *
	 * @param mixed $refs
	 * @return array
	 */
	private function resolveReference ($refs = null): array
	{
		if ($refs === null) {
			return [$this];
		}
		if (is_array($refs)) {
			$return = [];
			foreach ($refs as $ref) {
				$return = array_merge($return, $this->resolveReference($ref));
			}
			return $return;
		}
		if (is_string($refs)) {
			return $this->xpath($refs);
		}
		if ($this->isSxml($refs)) {
			return [$refs];
		}
	}

	/**
	 * Try to resolve any input to a SimpleXmlElement.
	 *
	 * @param mixed $element
	 * @return self
	 */
	private function toSxml ($element): self
	{
		if (is_string($element)) {
			return new self($element);
		}
		if ($this->isSxml($element)) {
			return $element;
		}
		if ($this->isDom($element)) {
			return $this->domToSxml($element);
		}
	}

	/**
	 * Try to resolve any input to a DomNode.
	 *
	 * @param mixed $element
	 * @return DomNode
	 */
	private function toDom ($element, $allowDomInput = true): \DomNode
	{
		if (($allowDomInput === true) && ($this->isDom($element))) {
			return $element;
		}

		$dom = dom_import_simplexml($this);

		if (is_string($element)) {
			if (preg_match('/^\<\?([^\s]+)(\s)([^\?\>]+)\?\>/', $element, $matches)) {
				return $dom->ownerDocument->createProcessingInstruction($matches[1], $matches[3]);
			}
			try {
				$element = new SimpleXmlElement($element);
			}
			catch (\Exception $e) {
			}
		}
		else {
			$element = $this->toSxml($element);
		}

		if (!is_string($element)) {
			return $dom->ownerDocument->importNode(dom_import_simplexml($element), true);
		}

		$node = new \DomDocument();
		$node = $node->createTextNode($element);
		return $dom->ownerDocument->importNode($node, true);
	}

}
