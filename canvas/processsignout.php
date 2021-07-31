<?php
declare(strict_types=1);
namespace gimle;

session_start();
if (isset($_SESSION['gimle']['user'])) {
	unset($_SESSION['gimle']['user']);
}

if (method_exists(__NAMESPACE__ . '\\User', 'postSignOut')) {
	User::postSignOut();
}

User::setCookie('Asi', 'false', time() - (86400 * 400));

header('Location: ' . BASE_PATH);

return true;
