<?php
declare(strict_types=1);
namespace gimle;

session_start();
if (isset($_SESSION['gimle']['user'])) {
	unset($_SESSION['gimle']['user']);
}
if (isset($_SESSION['gimle'])) {
	foreach ($_SESSION['gimle'] as $key => $value) {
		if (str_starts_with($key, 'user-')) {
			unset($_SESSION['gimle'][$key]);
		}
	}
}

if (method_exists(__NAMESPACE__ . '\\User', 'postSignOut')) {
	User::postSignOut();
}

User::setCookie('Asi', 'false', time() - (86400 * 400));

header('Location: ' . BASE_PATH);

return true;
