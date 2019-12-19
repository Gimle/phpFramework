<?php
declare(strict_types=1);
namespace gimle;

use \gimle\canvas\Canvas;

header('HTTP/1.0 400 Bad Request');

Canvas::title(_('Bad Request'), 'template', -1);

$headers = headers_list();
foreach ($headers as $header) {
	$check = 'Content-type: application/json;';
	if (substr($header, 0, strlen($check)) === $check) {
		echo json_encode(_('Bad Request'));
		return true;
	}
}

?>
<h1><?=_('Bad Request')?></h1>
<?php

return true;
