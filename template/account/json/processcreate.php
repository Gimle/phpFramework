<?php
declare(strict_types=1);
namespace gimle;

use \gimle\sql\Mysql;
use \gimle\user\User;
use \gimle\router\Router;

session_start();

if (isset($_SESSION['gimle']['user'])) {
	echo json_encode(['error' => 1]);
	return true;
}

$db = Mysql::getInstance('gimle');
$postfix = (Config::get('applinks') ? '#' : '');
$lang = (i18n::getInstance())->getLanguage();


if ((!isset($_POST['first_name'])) ||
	(!isset($_POST['last_name'])) ||
	(!isset($_POST['password'])) ||
	(!isset($_POST['password2'])) ||
	(!isset($_POST['username'])) ||
	(!isset($_POST['username2']))
) {
	echo json_encode(['error' => 2]);
	return true;
}

if ($_POST['username'] === '') {
	echo json_encode(['error' => 3]);
	return true;
}

if (!filter_var($_POST['username'], FILTER_VALIDATE_EMAIL)) {
	echo json_encode(['error' => 4]);
	return true;
}

$data = [
	'first_name' => $_POST['first_name'],
	'last_name' => $_POST['last_name'],
	'username' => $_POST['username'],
	'password' => $_POST['password']
];

if ($data['first_name'] === '') {
	$data['first_name'] = null;
}
if ($data['last_name'] === '') {
	$data['last_name'] = null;
}

$mail = Mail::getInstance('account');
if (($data['first_name'] !== null) || ($data['last_name'] !== null)) {
	$name = '';
	if ($data['first_name'] !== null) {
		$name .= $data['first_name'];
	}
	if (($data['first_name'] !== null) && ($data['last_name'] !== null)) {
		$name .= ' ';
	}
	if ($data['last_name'] !== null) {
		$name .= $data['last_name'];
	}
	$mail->addAddress($data['username'], $name);
}
else {
	$mail->addAddress($data['username']);
}
$mail->bind('sitename', Config::get('sitename'));

$exists = User::exists($data['username'], 'local');
if ($exists === true) {
	$userInfo = User::getUser($data['username'], 'local');

	$mail->Body = file_get_contents(Router::getTemplatePath('mail/accounttaken/%s.accounttaken.html', $lang, 'en'));
	$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/accounttaken/%s.accounttaken.txt', $lang, 'en'));
	$mail->bind('signin', BASE_PATH . $postfix . 'account/signin');
	$mail->bind('reset', BASE_PATH . $postfix . 'account/passwordreset');
	$mail->send();
	sleep(1);

	echo json_encode('account_created');
	return true;
}

$generatePassword = false;
if (($data['password'] === '') || ((isset($_POST['generate_password'])) && ($_POST['generate_password'] === 'true'))) {
	$generatePassword = true;
	$data['password'] = User::generatePassword();
}

User::create($data, 'local');

$newUser = User::getUser($data['username'], 'local');

try {
	$mail->Body = file_get_contents(Router::getTemplatePath('mail/accountcreated/%s.accountcreated.html', $lang, 'en'));
	$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/accountcreated/%s.accountcreated.txt', $lang, 'en'));
	$mail->bind('url', BASE_PATH . $postfix . 'account/verify?' . current($newUser['local'])['verification']);
	if ($generatePassword === true) {
		$text = sprintf(_('Your password is: %s'), htmlspecialchars($data['password']));
		$mail->bind('htmlPass', '<code>' . $text . '</code>');
		$text = sprintf(_('Your password is: %s'), $data['password']);
		$mail->bind('textPass', "\n{$text}\n");
	}
	else {
		$mail->bind('htmlPass', '');
		$mail->bind('textPass', '');
	}
	$mail->send();
}
catch (\Exception $e) {
	sp($mail->ErrorInfo);
	sp($e);
}

echo json_encode('account_created');

return true;
