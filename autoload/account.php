<?php
declare(strict_types=1);
namespace gimle;

use gimle\rest\Fetch;
use gimle\xml\SimpleXmlElement;

/**
 * Rertieve gravatar information about an email address.
 *
 * @param string $email
 * @return ?array
 */
function gravatar (string $email): ?array
{
	$fetch = new Fetch();
	$fetch->connectionTimeout(2);
	$fetch->resultTimeout(3);

	$md5 = md5($email);

	$url = sprintf('https://www.gravatar.com/%s.xml', $md5);

	$res = $fetch->query($url);
	if (($res['error'] === 0) && (is_string($res['reply']))) {
		$xml = $res['reply'];
		$sxml = new SimpleXmlElement($xml);
		if ($sxml !== false) {
			$thumbnailUrl = current($sxml->xpath('/response/entry/thumbnailUrl'));
			if ($thumbnailUrl !== false) {
				$thumbnailUrl = (string) $thumbnailUrl;
				if ($thumbnailUrl !== '') {
					$name = [];

					$displayName = current($sxml->xpath('/response/entry/displayName'));
					if ($displayName !== false) {
						$displayName = (string) $displayName;
						if ($displayName !== '') {
							$name['screen'] = $displayName;
						}
					}
					$givenName = current($sxml->xpath('/response/entry/name/givenName'));
					if ($givenName !== false) {
						$givenName = (string) $givenName;
						if ($givenName !== '') {
							$name['first'] = $givenName;
						}
					}
					$familyName = current($sxml->xpath('/response/entry/name/familyName'));
					if ($familyName !== false) {
						$familyName = (string) $familyName;
						if ($familyName !== '') {
							$name['last'] = $familyName;
						}
					}

					return ['picture' => $thumbnailUrl, 'name' => $name];
				}
			}
		}
	}
	return null;
}

return true;
