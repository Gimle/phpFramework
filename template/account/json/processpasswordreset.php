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

if (!isset($_POST['username'])) {
	echo json_encode(['error' => 2]);
	return true;
}
if (!filter_var($_POST['username'], FILTER_VALIDATE_EMAIL)) {
	echo json_encode(['error' => 4]);
	return true;
}

$db = Mysql::getInstance('gimle');
$mail = Mail::getInstance('account');
$mail->bind('sitename', Config::get('sitename'));

$exists = User::exists($_POST['username'], 'local');
if ($exists === false) {
	$mail->addAddress($_POST['username']);

	$mail->Body = file_get_contents(Router::getTemplatePath('mail/noaccount/%s.noaccount.html', $lang, 'en'));
	$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/noaccount/%s.noaccount.txt', $lang, 'en'));
	$mail->bind('url', BASE_PATH . $postfix . 'account/create');
	$mail->send();
	sleep(1);

	echo json_encode(true);
	return true;
}

$userInfo = User::getUser($_POST['username'], 'local');
$mail->addAddress($_POST['username'], $userInfo['full_name']);

if ($userInfo['disabled'] !== null) {
	$mail->Body = file_get_contents(Router::getTemplatePath('mail/accountdisabled/%s.accountdisabled.html', $lang, 'en'));
	$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/accountdisabled/%s.accountdisabled.txt', $lang, 'en'));
	$mail->send();

	sleep(1);

	echo json_encode(true);
	return true;
}

foreach ($userInfo['local'] as $local) {
	if ($local['id'] === $_POST['username']) {
		if ($local['verification'] !== null) {
			$mail->Body = file_get_contents(Router::getTemplatePath('mail/accountcreated/%s.accountcreated.html', $lang, 'en'));
			$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/accountcreated/%s.accountcreated.txt', $lang, 'en'));
			$mail->bind('url', BASE_PATH . $postfix . 'account/verify?' . $local['verification']);
			$mail->bind('htmlPass', '');
			$mail->bind('txtPass', '');
			$mail->send();

			sleep(1);

			echo json_encode(true);
			return true;
		}
		break;
	}
}

$code = sha1(openssl_random_pseudo_bytes(16));
$query = sprintf("UPDATE `account_auth_local` SET `reset_code` = '%s', `reset_datetime` = NOW() WHERE `id` = '%s';",
	$db->real_escape_string($code),
	$db->real_escape_string($_POST['username'])
);
$db->query($query);

$mail->Body = file_get_contents(Router::getTemplatePath('mail/passwordreset/%s.passwordreset.html', $lang, 'en'));
$mail->AltBody = file_get_contents(Router::getTemplatePath('mail/passwordreset/%s.passwordreset.txt', $lang, 'en'));
$mail->bind('url', BASE_PATH . $postfix . 'account/verifyreset?' . $code);
$mail->send();

echo json_encode(true);

return true;
