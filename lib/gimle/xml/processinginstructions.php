<?php
declare(strict_types=1);
namespace gimle\xml;

trait ProcessingInstructions
{
	/**
	 * Get processing instructions from the document with a matching name.
	 *
	 * To get root level processing instructions, you need to get sibling processing instructions.
	 * To get document level processing instructions, pass a reference to not get sibling processing instructions.
	 *
	 * @param ?string $name Only get processing instructions with the given name. Default = null.
	 * @param ?bool $searchChildren Do a recursive search for all processing instructions in all children of the reference. Default = false.
	 * @param ?mixed $ref null = Siblings of self. string = Children of xpath, SimpleXmlElement = Children of reference.
	 * @return array
	 */
	public function getPi (string $name = null, bool $searchChildren = false, $ref = null): array
	{
		$pis = $this->getDomPi($name, $searchChildren, $ref);
		$return = [];
		foreach ($pis as $pi) {
			$return[$pi->nodeName][] = $pi->nodeValue;
		}
		return $return;
	}

	/**
	 * Remove processing instructions from the document with a matching name.
	 *
	 * To remove root level processing instructions, you need to remove sibling processing instructions.
	 * To remove document level processing instructions, pass a reference to not remove sibling processing instructions.
	 *
	 * @param ?string $name Only remove processing instructions with the given name. Default = null.
	 * @param ?bool $searchChildren Do a recursive search for all processing instructions in all children of the reference. Default = false.
	 * @param ?mixed $ref null = Siblings of self. string = Children of xpath, SimpleXmlElement = Children of reference.
	 * @return array
	 */
	public function removePi (string $name = null, bool $searchChildren = false, $ref = null): array
	{
		$pis = $this->getDomPi($name, $searchChildren, $ref);
		$return = [];
		foreach ($pis as $pi) {
			$return[$pi->nodeName][] = $pi->nodeValue;
			$pi->parentNode->removeChild($pi);
		}
		return $return;
	}

	/**
	 * Get processing instructions from the document with a matching name.
	 *
	 * To get root level processing instructions, you need to get sibling processing instructions.
	 * To get document level processing instructions, pass a reference to not get sibling processing instructions.
	 *
	 * @param ?string $name Only get processing instructions with the given name. Default = null.
	 * @param ?bool $searchChildren Do a recursive search for all processing instructions in all children of the reference. Default = false.
	 * @param ?mixed $ref null = Siblings of self. string = Children of xpath, SimpleXmlElement = Children of reference.
	 * @return array
	 */
	private function getDomPi (string $name = null, bool $searchChildren = false, $ref = null): array
	{
		$injectXpath = '';
		if ($ref === null) {
			$injectXpath = '../';
		}
		$refs = $this->resolveReference($ref);

		if ($name !== null) {
			$name = '"' . $name . '"';
		}
		else {
			$name = '';
		}

		$return = [];
		foreach ($refs as $ref) {
			$dom = dom_import_simplexml($ref);
			$xpath = new \DomXpath($dom->ownerDocument);

			$prefix = $injectXpath . './';
			if ($searchChildren === true) {
				$prefix .= '/';
			}

			foreach ($xpath->query($prefix . 'processing-instruction(' . $name . ')', $dom) as $pi) {
				$return[] = $pi;
			}
		}

		return $return;
	}
}
