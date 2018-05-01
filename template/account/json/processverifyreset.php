<?php
declare(strict_types=1);
namespace gimle;

use \gimle\sql\Mysql;
use \gimle\user\User;
use \gimle\router\Router;

session_start();

$postfix = (Config::get('applinks') ? '#' : '');
$lang = (i18n::getInstance())->getLanguage();

if (User::current() !== null) {
	echo json_encode(['error' => 1]);
	return true;
}

$db = Mysql::getInstance('gimle');

$verified = false;
$token = '';
if ((isset($_POST['token'])) && (strlen($_POST['token']) > 2)) {
	$token = $_POST['token'];
}
if ($token !== '') {
	$db = Mysql::getInstance('gimle');
	$query = sprintf("SELECT `account_id` FROM `account_auth_local` WHERE `reset_code` = '%s';",
		$db->real_escape_string($token)
	);
	$result = $db->query($query);
	$row = $result->get_assoc($query);
	if ($row !== false) {
		$verified = true;
	}
}
if ($verified === false) {
	throw new Exception('Invalid token.');
}

$password = null;

// c1915f548e7b53cadc469b123b16a713290b92f2
// 2018-04-15 19:29:16

if ((isset($_POST['generate_password'])) && ($_POST['generate_password'] === 'true')) {
	$password = User::generatePassword();

	if ($_POST['target'] === 'email') {
		$mail = Mail::getInstance('account');
		$mail->bind('sitename', Config::get('sitename'));

		$userInfo = User::getUser($row['account_id']);
		if ($userInfo['email'] === false) {
			throw new Exception('No user email.');
		}
		$mail->addAddress($userInfo['email'], $userInfo['full_name']);

		$mail->Body = file_get_contents(Router::getTemplatePath('mail/newpassword/%s.newpassword.html', $lang, 'en'));
		$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/newpassword/%s.newpassword.txt', $lang, 'en'));
		$mail->bind('password', '<code>' . $password . '</code>');
		$mail->bind('url', BASE_PATH . $postfix . 'account/signin');
		$mail->send();

		if (User::setNewPassword($token, $password) === true) {
			echo json_encode('email');
			return true;
		}
		throw new Exception('Could not set password.');
	}
	if ($_POST['target'] === 'show') {
		if (User::setNewPassword($token, $password) === true) {
			echo json_encode(['newpass' => $password]);
			return true;
		}
		throw new Exception('Could not set password.');
	}

	echo json_encode(false);
	return true;
}

if ($_POST['password'] !== '') {
	$password = $_POST['password'];
	if (User::setNewPassword($token, $password) === true) {
		echo json_encode(true);
		return true;
	}
	throw new Exception('Could not set password.');
}

echo json_encode(false);
return true;
