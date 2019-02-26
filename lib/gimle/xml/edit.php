<?php
declare(strict_types=1);
namespace gimle\xml;

trait Edit
{
	/**
	 * Remove a given node.
	 *
	 * Can not remove root node.
	 *
	 * @param mixed $ref null = delete self. string = xpath to delete, SimpleXmlElement = reference to delete.
	 * @return array<SimpleXmlElement> Array with the removed elements.
	 */
	public function remove ($ref = null, ?int $mode = null): array
	{
		$refs = $this->resolveReference($ref);
		$return = [];
		foreach ($refs as $ref) {
			$dom = $this->toDom($ref);
			$this->removeWhitespaceHandler($dom, $mode);
			$res = $dom->parentNode->removeChild($dom);
			$return[] = $this->domToSxml($res);
		}
		return $return;
	}

	/**
	 * Replace a given node.
	 *
	 * Can not replace root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = replace self. string = xpath to replace, SimpleXmlElement = reference to replace.
	 * @return void
	 */
	public function replace ($element, $ref = null): array
	{
		$element = $this->toDom($element);

		$refs = $this->resolveReference($ref);
		$return = [];
		foreach ($refs as $ref) {
			$dom = dom_import_simplexml($ref);
			$dom->parentNode->replaceChild($element, $dom);
			$return[] = $ref;
		}
		return $return;
	}

	/**
	 * Rename a given element.
	 *
	 * Can not rename root element.
	 *
	 * @param string $name The new element name.
	 * @param mixed $ref null = rename self. string = xpath to rename, SimpleXmlElement = reference to rename.
	 * @return void
	 */
	public function rename (string $name, $ref = null): void
	{
		$refs = $this->resolveReference($ref);
		foreach ($refs as $ref) {
			$dom = dom_import_simplexml($this);
			$ref = dom_import_simplexml($ref);

			$newNode = $ref->ownerDocument->createElement($name);
			if ($ref->attributes->length) {
				foreach ($ref->attributes as $attribute) {
					$newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
				}
			}
			while ($ref->firstChild) {
				$newNode->appendChild($ref->firstChild);
			}
			$ref->parentNode->replaceChild($newNode, $ref);
		}
	}

	/**
	 * Handle whitespace around the elements.
	 *
	 * @param \DomNode $dom
	 * @param ?int $mode How to handle the whitespace.
	 * @return void
	 */
	private function removeWhitespaceHandler (\DomNode $dom, ?int $mode = null): void
	{
		if ($mode === null) {
			return;
		}
		$previous = $dom->previousSibling;
		$next = $dom->nextSibling;
		if ($mode & (self::LTRIM_OTHER | self::LTRIM_FIRST)) {
			if ($previous !== null) {
				if (get_class($previous) === 'DOMText') {
					if ($mode & self::LTRIM_OTHER) {
						if ($previous->previousSibling !== null) {
							$previous->nodeValue = rtrim($previous->nodeValue);
						}
					}
					if ($mode & self::LTRIM_FIRST) {
						if ($previous->previousSibling === null) {
							$previous->nodeValue = rtrim($previous->nodeValue);
						}
						else if ($next !== null) {
							$previous->nodeValue = rtrim($previous->nodeValue);
						}
					}
					if (($next !== null)  && (ltrim($next->nodeValue) !== '')) {
						if ($next->nodeValue !== ltrim($next->nodeValue)) {
							$next->nodeValue = ltrim($next->nodeValue);
						}
					}
				}
			}
		}
		if ($mode & (self::RTRIM_OTHER | self::RTRIM_LAST)) {
			if ($next !== null) {
				if (get_class($next) === 'DOMText') {
					if ($mode & self::RTRIM_OTHER) {
						if (($next->nextSibling !== null) || (ltrim($next->nodeValue) !== '')) {
							$next->nodeValue = ltrim($next->nodeValue);
						}
					}
					if ($mode & self::RTRIM_LAST) {
						if (($next->nextSibling === null) && (ltrim($next->nodeValue) === '')) {
							$next->nodeValue = ltrim($next->nodeValue);
						}
						else if ($previous !== null) {
							$next->nodeValue = ltrim($next->nodeValue);
						}
					}
					if (($previous !== null) && (rtrim($previous->nodeValue) !== '')) {
						if ($previous->nodeValue !== rtrim($previous->nodeValue)) {
							$previous->nodeValue = rtrim($previous->nodeValue);
						}
					}
				}
			}
		}
	}
}
