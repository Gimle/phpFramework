<?php
declare(strict_types=1);
namespace gimle;

try {
	session_start();
	if ((isset($_POST['auto'])) && (!isset($_COOKIE[session_name() . 'Asi']))) {
		User::setCookie('Asi', 'true');
	}

	User::clearSigninException();

	if (isset($_SESSION['gimle']['user'])) {
		throw new Exception('User already signed in.', User::ALREADY_SIGNED_IN);
	}

	if (!isset($_SESSION['gimle']['awaitAuthFeedback'])) {
		if (!isset($_GET['oauth'])) {
			if (!isset($_POST['token'])) {
				throw new Exception('Payload missing.', User::MISSING_PAYLOAD);
			}
			if (!User::validateSigninToken($_POST['token'])) {
				throw new Exception('Payload not valid.', User::INVALID_PAYLOAD);
			}
		}

		if ((isset($_POST['email'])) && (isset($_POST['password']))) {
			$user = User::login($_POST['email'], $_POST['password']);
			if ($user->id === null) {
				throw new Exception('User not found.', User::USER_NOT_FOUND);
			}
			$_SESSION['gimle']['user'] = $user;
		}
		elseif (isset($_REQUEST['oauth'])) {
			try {
				call_user_func([__NAMESPACE__ . '\\User', 'redirect'  . ucfirst($_REQUEST['oauth'])]);
				$_SESSION['gimle']['awaitAuthFeedback'] = $_REQUEST['oauth'];
				die();
			}
			catch (\Throwable $t) {
				sp($t);
			}

			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
		else {
			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
	}
	else {
		$loginClass = $_SESSION['gimle']['awaitAuthFeedback'];
		unset($_SESSION['gimle']['awaitAuthFeedback']);

		try {
			$user = call_user_func([__NAMESPACE__ . '\\User', 'login'  . ucfirst($loginClass)]);
			if ($user->id === null) {
				throw new Exception('User not found.', User::USER_NOT_FOUND);
			}
			$_SESSION['gimle']['user'] = $user;
		}
		catch (\Throwable $t) {
			sp($_GET);
			sp($t);

			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
	}
}
catch (Exception $e) {
	$e->set('post', $_POST);
	User::setCookie('Asi', 'false', time() - (86400 * 400));
	User::setSigninException($e);
}

User::deleteSigninToken();
if (Config::get('user.reply') === 'json') {
	header('Content-type: application/json');
	if (isset($_SESSION['gimle']['signinException'])) {
		sp($_SESSION['gimle']['signinException']);
		echo json_encode(false);
		unset($_SESSION['gimle']['signinException']);
		return true;
	}
	echo json_encode(true);
	return true;
}

return inc(SITE_DIR . 'module/' . MODULE_GIMLE . '/canvas/redirect.php');
