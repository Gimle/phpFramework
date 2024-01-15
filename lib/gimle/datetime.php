<?php
declare(strict_types=1);
namespace gimle;

class DateTime extends \DateTime
{
	public $formats = 'https://unicode-org.github.io/icu/userguide/format_parse/datetime/';

	public function format (string $format): string
	{
		$fmt = new \IntlDateFormatter(setlocale(LC_TIME, 0));
		$fmt->setPattern($format);
		return $fmt->format($this);
	}
}
