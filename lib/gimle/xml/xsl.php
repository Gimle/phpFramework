<?php
declare(strict_types=1);
namespace gimle\xml;

use \gimle\Exception;

use function \gimle\exec;
use function \gimle\uniquename;
use function \gimle\clear_dir;
use function \gimle\sp;

use const \gimle\TEMP_DIR;

class Xsl
{
	protected static $xslTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:strip-space elements="*"/>
<xsl:output method="html" indent="yes"/>

%s

</xsl:stylesheet>
';

	protected static $xslTemplate2 = '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:strip-space elements="*"/>

%s

</xsl:stylesheet>
';

	protected $variables = [];
	protected $stylesheets = [];

	public function render ($document, $engine = 'standard'): string
	{
		$stylesheet = $this->loadAndMakeStylesheet($engine);
		$document = $this->toXml($document);

		if ($engine === 'standard') {
			$xsl = new \XSLTProcessor();
			$xsl->registerPHPFunctions();

			$xsldoc = new \DOMDocument();
			$xsldoc->loadXML($stylesheet);
			$xsl->importStyleSheet($xsldoc);

			$xmldoc = new \DOMDocument();
			$xmldoc->loadXML($document);

			return $xsl->transformToXML($xmldoc);
		}
		elseif (($engine === 'saxon') || ($engine === 'saxonb')) {
			$saxonDir = TEMP_DIR . uniquename(TEMP_DIR, 'saxon_') . '/';
			mkdir($saxonDir);
			$xslfile = $saxonDir . 'stylesheet.xsl';
			$sourcefile = $saxonDir . 'source.xml';
			$resultfile = $saxonDir . 'result.xml';
			file_put_contents($xslfile, $stylesheet);
			file_put_contents($sourcefile, $document);

			$result = exec($engine . '-xslt -o ' . $resultfile . ' ' . $sourcefile . ' ' . $xslfile);

			$return = file_get_contents($resultfile);
			clear_dir($saxonDir, true);

			return $return;
		}
	}

	public function variable (string $name, string $value): void
	{
		$this->variables[$name] = $value;
	}

	public function stylesheet (string $filename): void
	{
		$this->stylesheets[] = $filename;
	}

	private function toXml ($document)
	{
		if (is_string($document)) {
			return $document;
		}
		elseif ((get_class($document)) || (is_subclass_of($document, 'SimpleXmlElement'))) {
			return $document->asXml();
		}
	}

	private function loadAndMakeStylesheet ($engine)
	{
		$stylesheets = '';
		foreach ($this->stylesheets as $stylesheet) {
			$stylesheets .= '<xsl:include href="' . str_replace(['å', ' '], [urlencode('å'), '%20'], $stylesheet) . '"/>' . "\n";
		}

		if ($engine !== 'saxonb') {
			$template = self::$xslTemplate;
		}
		else {
			$template = self::$xslTemplate2;
		}

		foreach ($this->variables as $name => $value) {
			$template = sprintf($template, '<xsl:variable name="' . $name . '">
	<xsl:value-of select="\'' . $value . '\'"/>
</xsl:variable>

%s');
		}

		return sprintf($template, $stylesheets);
	}
}
