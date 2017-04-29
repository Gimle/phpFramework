<?php
declare(strict_types=1);
namespace gimle\xml;

/**
 * Extend the basic SimpleXmlElement with some additional functionality.
 */
class SimpleXmlElement extends \SimpleXmlElement
{
	use Edit;
	use Formatters;
	use Helpers;
	use Insert;
	use PrivateHelpers;
	use ProcessingInstructions;
	use Queries;
	use Validation;

	/**
	 * Trim off left whitespace, except first.
	 */
	public const LTRIM_OTHER = 1;

	/**
	 * Trim off right whitespace, except first.
	 */
	public const RTRIM_OTHER = 2;

	/**
	 * Trim off first left whitespace.
	 */
	public const LTRIM_FIRST = 4;

	/**
	 * Trim off first right whitespace.
	 */
	public const RTRIM_LAST = 8;

	/**
	 * Trim off both first and last whitespace.
	 */
	public const TRIM_BOTH = 12;

	/**
	 * Normalize spaces.
	 */
	public const NORMALIZE_SPACE = 16;

	/**
	 * Treat element as block level element.
	 */
	public const BLOCK = 1;

	/**
	 * Treat element as inline level element.
	 */
	public const INLINE = 2;

}
