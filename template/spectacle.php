<?php
declare(strict_types=1);
namespace gimle;

if (ENV_MODE & ENV_LIVE) {
	return false;
}

$res = Spectacle::read(page('id'));
if ($res === false) {
	echo json_encode(false);
}
echo trim($res);

return true;
