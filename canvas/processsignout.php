<?php
declare(strict_types=1);
namespace gimle;

session_start();
if (isset($_SESSION['gimle']['user'])) {
	unset($_SESSION['gimle']['user']);
}

header('Location: ' . BASE_PATH);

return true;
