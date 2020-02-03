<?php
declare(strict_types=1);
namespace gimle;

session_start();
if (isset($_SESSION['gimle']['user'])) {
	unset($_SESSION['gimle']['user']);
}

$urlPartsBase = parse_url(MAIN_BASE_PATH);
setcookie(
	session_name() . 'AutoSignin',
	'false',
	time() - (86400 * 400),
	$urlPartsBase['path'],
	'',
	true,
	true
);

header('Location: ' . BASE_PATH);

return true;
