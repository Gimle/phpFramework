#!/usr/bin/env php
<?php
declare(strict_types=1);
namespace gimle;

use \gimle\xml\SimpleXmlElement;

/**
 * The local absolute location of the site.
 *
 * @var string
 */
define('gimle\\SITE_DIR', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);

require dirname(__DIR__) . '/init.php';

if (!is_readable(TEMP_DIR . 'gimle/spectacle/')) {
	return;
}

$specname = null;
if (!isset($argv[1])) {
	$newest = 0;
	foreach (new \Directoryiterator(TEMP_DIR . 'gimle/spectacle/') as $fifo) {
		$fname = $fifo->getFilename();
		if (str_starts_with($fname, '.')) {
			continue;
		}
		$ctime = $fifo->getCTime();
		if ($newest < $ctime) {
			$specname = $fname;
			$newest = $ctime;
		}
	}
	if ($specname === null) {
		return;
	}
}
else {
	$specname = $argv[1];
}

$spectacle = file_get_contents(TEMP_DIR . 'gimle/spectacle/' . $specname);
$html = json_decode($spectacle, true)['tabs']['spectacle']['content'];
try {
	$dom = new \DomDocument();
	$dom->loadHtml($html);
	$sxml = simplexml_import_dom($dom, '\\gimle\\xml\\SimpleXMLElement');
	$first = true;
	foreach ($sxml->xpath('//p') as $p) {
		if ($first) {
			$p->insertAfter("<fix>\n</fix>");
		}
		else {
			$p->insertAfter("<fix></fix>");
		}
		$first = false;
	}
	foreach ($sxml->xpath('//span') as $span) {
		try {
			if (str_contains((string) $span['style'], 'color: DarkBlue;')) {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'array'));
			}
			elseif ((string) $span['style'] === 'color: green;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'string'));
			}
			elseif ((string) $span['style'] === 'color: red;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'int'));
			}
			elseif ((string) $span['style'] === 'color: gray;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'gray'));
			}
			elseif ((string) $span['style'] === 'color: lightgray;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'lightgray'));
			}
			elseif ((string) $span['style'] === 'color: blue;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'array'));
			}
			elseif ((string) $span['style'] === 'color: dodgerblue;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'float'));
			}
			elseif ((string) $span['style'] === 'color: darkorange;') {
				$span[0] = str_replace("\033[", "\\033*replaceme*[", colorize((string) $span, 'recursion'));
			}
			else {
				d((string) $span['style']);
			}
		}
		catch (\Throwable $t) {
			d('Colouring failed');
		}
	}

	echo str_replace('\\033*replaceme*[', "\033[", html_entity_decode(strip_tags($sxml->asXml())));
}
catch (\Throwable $t) {
	echo html_entity_decode(strip_tags($html));
}

return true;
