<?php
declare(strict_types=1);
namespace gimle\xml;

trait Queries
{
	/**
	 * Get the first child object.
	 *
	 * @param SimpleXmlElement $ref Reference node, or null if current is to be used.
	 * @return ?SimpleXmlElement
	 */
	public function firstChild (?SimpleXmlElement $ref = null): ?self
	{
		if ($ref === null) {
			return ($this->xpath('./*[1]')[0] ?? null);
		}

		return ($ref->xpath('./*[1]')[0] ?? null);
	}

	/**
	 * Get the last child object.
	 *
	 * @param SimpleXmlElement $ref Reference node, or null if current is to be used.
	 * @return ?SimpleXmlElement
	 */
	public function lastChild (?SimpleXmlElement $ref = null): ?self
	{
		if ($ref === null) {
			return ($this->xpath('./*[last()]')[0] ?? null);
		}

		return ($ref->xpath('./*[last()]')[0] ?? null);
	}

	/**
	 * Get the parent of the current node.
	 *
	 * @param int $level How many levels back up the parent tree? Default 1.
	 * @return ?SimpleXmlElement
	 */
	public function getParent (int $level = 1): ?self
	{
		$q = [];
		while ($level > 0) {
			$q[] = 'parent::*';
			$level--;
		}
		return ($this->xpath(implode('/', $q))[0] ?? null);
	}

	/**
	 * Get the following sibling of the current node.
	 *
	 * @return ?SimpleXmlElement
	 */
	public function getNext (): ?self
	{
		return ($this->xpath('following-sibling::*[1]')[0] ?? null);
	}

	/**
	 * Get the preceding sibling of the current node.
	 *
	 * @return ?SimpleXmlElement
	 */
	public function getPrevious (): ?self
	{
		return ($this->xpath('preceding-sibling::*[1]')[0] ?? null);
	}
}
