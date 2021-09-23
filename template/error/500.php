<?php
declare(strict_types=1);
namespace gimle;

use \gimle\canvas\Canvas;

header('HTTP/1.0 500 Internal Server Error');

Canvas::title(_('Internal Server Error'), 'template', -1);

$e = inc_get_args();

$headers = headers_list();
foreach ($headers as $header) {
	$check = 'Content-type: application/json;';
	if (substr($header, 0, strlen($check)) === $check) {
		echo json_encode(_('Internal Server Error'));
		return true;
	}
}

?>
<h1><?=_('Internal Server Error')?></h1>
<?php

return true;
