<?php
declare(strict_types=1);
namespace gimle;

use \gimle\canvas\Canvas;

header('HTTP/1.0 404 Page not found');

Canvas::title(_('Page not found'), 'template', -1);

$headers = headers_list();
foreach ($headers as $header) {
	$check = 'Content-type: application/json;';
	if (substr($header, 0, strlen($check)) === $check) {
		echo json_encode(_('Page not found'));
		return true;
	}
}

?>
<h1><?=_('Page not found')?></h1>
<?php

return true;
