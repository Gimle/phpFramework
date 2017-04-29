<?php
declare(strict_types=1);
namespace gimle\xml;

trait Validation
{
	/**
	 * Validates a document based on a schema.
	 *
	 * @param string $filename The path to the schema.
	 * @return bool
	 */
	public function schemaValidate (string $filename): bool
	{
		$dom = dom_import_simplexml($this);
		return $dom->ownerDocument->schemaValidate($filename);
	}
}
