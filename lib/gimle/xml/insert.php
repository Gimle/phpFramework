<?php
declare(strict_types=1);
namespace gimle\xml;

use function \gimle\sp;

trait Insert
{
	/**
	 * Adds a child element to the node.
	 *
	 * Same as the built in addChild() method, but prepends the child instead of appending it.
	 *
	 * Adds a child element to the node and returns a SimpleXMLElement of the child.
	 *
	 * @param string $name The name of the child element to add.
	 * @param string $value If specified, the value of the child element.
	 * @return SimpleXmlElement The child added to the XML node.
	 */
	public function prependChild (string $name, $value = ''): self
	{
		$dom = dom_import_simplexml($this);
		$new = $dom->ownerDocument->createElement($name, $value);
		$new = $dom->insertBefore($new, $dom->firstChild);

		return simplexml_import_dom($new, get_class($this));
	}

	/**
	 * Insert inside at the first position.
	 *
	 * self::BLOCK Duplicate leading space after insertion. If empty, create leading and trailing spaces. Typically for block level elements.
	 * self::INLINE Insert imidiately after leading spaces. If empty, do nothing. Typically for inline elements.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param int $mode self::BLOCK or self::INLINE.
	 * @param mixed $ref null = before self. string = xpath to prepend, SimpleXmlElement = reference to prepend.
	 * @return void
	 */
	public function insertFirst ($element, int $mode = self::BLOCK, $ref = null): void
	{
		assert(($mode >= self::BLOCK) && ($mode <= self::INLINE));

		$refs = $this->resolveReference($ref);
		$element = $this->toDom($element, false);

		$return = [];
		foreach ($refs as $ref) {
			$dom = dom_import_simplexml($ref);
			$insert = $dom->ownerDocument->importNode($element, true);

			$insertBefore = null;
			if ($dom->firstChild !== null) {
				preg_match('/^[\s]+/s', $dom->firstChild->nodeValue, $leadingWhitespace);
				$leadingWhitespace = ($leadingWhitespace[0] ?? null);
				$insertBefore = $leadingWhitespace;

				if ($mode === self::INLINE) {
					$dom->firstChild->nodeValue = ltrim($dom->firstChild->nodeValue);
				}
			}
			elseif ($mode === self::BLOCK) {
				$leadingWhitespace = '';
				if ($dom->parentNode->nodeValue !== null) {
					preg_match('/^[\s]+/s', $dom->parentNode->nodeValue, $leadingWhitespace);
					$leadingWhitespace = ($leadingWhitespace[0] ?? '');
					$pos = strpos($leadingWhitespace, "\n", 1);
					if ($pos !== false) {
						$leadingWhitespace = substr($leadingWhitespace, 0, $pos);
					}
				}
				$insertBefore = $leadingWhitespace . "\t";

				$node = new \DomDocument();
				$node = $node->createTextNode($leadingWhitespace);
				$insertAfter = $dom->ownerDocument->importNode($node, true);
				$dom->insertBefore($insertAfter, $dom->firstChild);
			}

			$dom->insertBefore($insert, $dom->firstChild);

			if ($insertBefore !== null) {
				$node = new \DomDocument();
				$node = $node->createTextNode($insertBefore);
				$insert = $dom->ownerDocument->importNode($node, true);
				$dom->insertBefore($insert, $dom->firstChild);
			}
		}
	}

	/**
	 * Insert inside at the last position.
	 *
	 * self::BLOCK Duplicate leading space after insertion. If empty, create leading and trailing spaces. Typically for block level elements.
	 * self::INLINE Insert imidiately after leading spaces. If empty, do nothing. Typically for inline elements.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param int $mode self::BLOCK or self::INLINE.
	 * @param mixed $ref null = before self. string = xpath to apppend, SimpleXmlElement = reference to apppend.
	 * @return void
	 */
	public function insertLast ($element, int $mode = self::BLOCK, $ref = null): void
	{
		assert(($mode >= self::BLOCK) && ($mode <= self::INLINE));

		$refs = $this->resolveReference($ref);
		$element = $this->toDom($element, false);

		$return = [];
		foreach ($refs as $ref) {
			$dom = dom_import_simplexml($ref);
			$insert = $dom->ownerDocument->importNode($element, true);

			$insertAfter = null;
			if ($dom->lastChild !== null) {
				preg_match('/[\s]+$/s', $dom->lastChild->nodeValue, $trailingWhitespace);
				$trailingWhitespace = ($trailingWhitespace[0] ?? null);
				$insertAfter = $trailingWhitespace;

				if ($mode === self::INLINE) {
					$dom->lastChild->nodeValue = rtrim($dom->lastChild->nodeValue);
				}
				elseif ($insertAfter !== null) {
					$node = new \DomDocument();
					$node = $node->createTextNode("\t");
					$insertA = $dom->ownerDocument->importNode($node, true);
					$dom->appendChild($insertA);
				}
			}
			elseif ($mode !== self::INLINE) {
				$leadingWhitespace = '';
				if ($dom->parentNode->nodeValue !== null) {
					preg_match('/^[\s]+/s', $dom->parentNode->nodeValue, $leadingWhitespace);
					$leadingWhitespace = ($leadingWhitespace[0] ?? '');
					$pos = strpos($leadingWhitespace, "\n", 1);
					if ($pos !== false) {
						$leadingWhitespace = substr($leadingWhitespace, 0, $pos);
					}
					$insertAfter = $leadingWhitespace;
				}

				$node = new \DomDocument();
				$node = $node->createTextNode($leadingWhitespace . "\t");
				$insertA = $dom->ownerDocument->importNode($node, true);
				$dom->insertBefore($insertA, $dom->firstChild);
			}

			$dom->appendChild($insert);

			if ($insertAfter !== null) {
				$node = new \DomDocument();
				$node = $node->createTextNode($insertAfter);
				$insert = $dom->ownerDocument->importNode($node, true);
				$dom->appendChild($insert);
			}
		}
	}

	/**
	 * Insert before a given node.
	 *
	 * Can not insert before root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = before self. string = xpath to prepend, SimpleXmlElement = reference to prepend.
	 * @return void
	 */
	public function insertBefore ($element, $ref = null): void
	{
		$refs = $this->resolveReference($ref);
		$element = $this->toDom($element, false);

		$return = [];
		foreach ($refs as $ref) {
			$dom = dom_import_simplexml($ref);

			$insert = $dom->ownerDocument->importNode($element, true);
			$new = $dom->parentNode->insertBefore($insert, $dom);

			if ($new->previousSibling) {
				preg_match('/^[\s]+/s', $new->previousSibling->nodeValue, $leadingWhitespace);
				$leadingWhitespace = ($leadingWhitespace[0] ?? null);
				if (($leadingWhitespace !== null) && (strpos($leadingWhitespace, "\n") !== false)) {
					$node = new \DomDocument();
					$node = $node->createTextNode($leadingWhitespace);
					$insertBefore = $dom->ownerDocument->importNode($node, true);
					$dom->parentNode->insertBefore($insertBefore, $new->nextSibling);
				}
			}

		}
	}

	/**
	 * Insert after a given node.
	 *
	 * Can not insert after root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = after self. string = xpath to append, SimpleXmlElement = reference to append.
	 * @return void
	 */
	public function insertAfter ($element, $ref = null): void
	{
		$refs = $this->resolveReference($ref);
		$element = $this->toDom($element, false);

		$return = [];
		foreach ($refs as $ref) {
			$dom = $this->toDom($ref);

			$insert = $dom->ownerDocument->importNode($element, true);
			$new = $dom->parentNode->insertBefore($insert, $dom->nextSibling);

			if ($new->previousSibling->previousSibling) {
				preg_match('/^[\s]+/s', $new->previousSibling->previousSibling->nodeValue, $leadingWhitespace);
				$leadingWhitespace = ($leadingWhitespace[0] ?? null);
				if (($leadingWhitespace !== null) && (strpos($leadingWhitespace, "\n") !== false)) {
					$node = new \DomDocument();
					$node = $node->createTextNode($leadingWhitespace);
					$insertBefore = $dom->ownerDocument->importNode($node, true);
					$dom->parentNode->insertBefore($insertBefore, $new);
				}
			}
		}
	}
}
