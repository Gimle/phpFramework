<?php
declare(strict_types=1);
namespace gimle;

header('HTTP/1.0 500 Internal Server Error');

$headers = headers_list();

$e = false;
$params = inc_get_args();
if ((isset($params[0])) && (is_object($params[0])) && ($params[0] instanceof \Throwable)) {
	$e = $params[0];
}

foreach ($headers as $header) {
	$check = 'Content-type: application/json;';
	if (substr($header, 0, strlen($check)) === $check) {
		echo json_encode(['error' => 'Internal server error']);
		return true;
	}
}

?>
<h1>Internal Server Error</h1>
<?php

return true;
