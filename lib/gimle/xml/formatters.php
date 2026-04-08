<?php
declare(strict_types=1);
namespace gimle\xml;

use function \gimle\ent2utf8;
use function \gimle\normalize_space;
use function \gimle\mb_trim;

trait Formatters
{
	/**
	 * Get a pretty text version if the xml.
	 *
	 * Note: All processing instructions is moved to the top of the document.
	 *
	 * @return string
	 */
	public function pretty (): string
	{
		$dom = dom_import_simplexml($this);
		$that = $dom;

		$dom = $dom->ownerDocument;
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$dom->loadXml($dom->saveXml($that));

		$res = $dom->saveXml($dom->documentElement);

		$res = preg_replace('/^  |\G  /m', "\t", $res);

		$prepend = '';
		$xpath = new \DomXpath($dom);
		foreach ($xpath->query('//processing-instruction()') as $pi) {
			$prepend .= '<?' . $pi->nodeName . ' ' . $pi->nodeValue . '?>' . "\n";
		}

		return $prepend . $res;
	}

	/**
	 * Get a html compatible representation of the xml.
	 *
	 * @return string
	 */
	public function asHtml (): string
	{
		$dom = dom_import_simplexml($this);

		$prepend = '';
		$xpath = new \DomXpath($dom->ownerDocument);
		foreach ($xpath->query('//processing-instruction()') as $pi) {
			$prepend .= '<?' . $pi->nodeName . ' ' . $pi->nodeValue . '?>' . "\n";
		}

		$res = new \DomDocument('1.0');
		$res->formatOutput = true;
		$dom = $res->importNode($dom, true);
		$dom = $res->appendChild($dom);
		$res = $res->saveXml($res, LIBXML_NOEMPTYTAG);

		$res = preg_replace('/^  |\G  /m', "\t", $res);
		$res = preg_replace('/<\?xml[^\n]+\n/', '', $res);
		$res = preg_replace('/(<(img|col[^group]|br|hr|input)(.*))\>\<\/(img|col[^group]|br|hr|input)\>/', '$1/>', $res);

		return $prepend . $res;
	}

	/**
	 * Extract normalized text content from a node.
	 *
	 * @param array $allow Allowed tags.
	 * @param array $remove Elements to remove.
	 * @return string The resulting string.
	 */
	public function textContent (array $allow = [], array $remove = []): string
	{
		if (!empty($remove)) {
			$sxml = new SimpleXmlElement($this->asXml());
			array_walk($remove, function (&$value) {
				$value = '//' . $value;
			});

			$sxml->remove(implode('|', $remove));
			$xml = $sxml->asXml();
		}
		else {
			$xml = $this->asXml();
		}
		$xml = preg_replace('/^.+\n/', '', $xml);

		if (!empty($allow)) {
			$regex = '/(<(?!((|\/)(' . implode('|', $allow) . '(?!t))))[^>]+>)/';
		}
		else {
			$regex = '/(<[^>]+>)/';
		}

		return mb_trim(normalize_space(preg_replace($regex, ' ', ent2utf8($xml))));
	}

	/**
	 * Get the inner xml of one or more nodes.
	 *
	 * @param mixed $ref null = self. string = xpath, SimpleXmlElement = reference.
	 * @return array The nodes inner xml.
	 */
	public function innerXml ($ref = null, ?int $mode = null): array
	{
		$refs = $this->resolveReference($ref);
		$result = [];
		foreach ($refs as $ref) {
			$tag = $ref->getName();
			$res = preg_replace('/<'. $tag .'(?:[^>]*)>(.*)<\/'. $tag .'>/Ums', '$1', $ref->asXml());
			if ($mode & self::NORMALIZE_SPACE) {
				$res = \gimle\normalize_space($res);
			}
			if ($mode & self::LTRIM_FIRST) {
				$res = ltrim($res);
			}
			if ($mode & self::RTRIM_LAST) {
				$res = rtrim($res);
			}
			$result[] = $res;
		}
		return $result;
	}
}
