<?php
declare(strict_types=1);
namespace gimle;

try {
	session_start();
	if ((isset($_POST['auto'])) && (!isset($_COOKIE[session_name() . 'Asi']))) {
		User::setCookie('Asi', 'true');
	}

	if (isset($_REQUEST['principal'])) {
		$_SESSION['gimle']['signingoto'] = $_REQUEST['principal'];
	}

	User::clearSigninException();

	if (isset($_SESSION['gimle']['user'])) {
		throw new Exception('User already signed in.', User::ALREADY_SIGNED_IN);
	}

	if (!isset($_SESSION['gimle']['awaitAuthFeedback'])) {
		if (!isset($_GET['remote'])) {
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
		elseif (isset($_REQUEST['remote'])) {
			try {
				call_user_func([__NAMESPACE__ . '\\User', 'redirect'  . ucfirst($_REQUEST['remote'])]);
				$_SESSION['gimle']['awaitAuthFeedback'] = $_REQUEST['remote'];
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
			$user = call_user_func([__NAMESPACE__ . '\\User', 'remote'  . ucfirst($loginClass)]);
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
	if ($e->getCode() !== User::ALREADY_SIGNED_IN) {
		User::setCookie('Asi', 'false', time() - (86400 * 400));
	}
	User::setSigninException($e);
}

User::deleteSigninToken();

$target = BASE_PATH;
if (isset($_SESSION['gimle']['signingoto'])) {
	if (Config::exists('user.principal.' . $_SESSION['gimle']['signingoto'])) {
		$target .= Config::get('user.principal.' . $_SESSION['gimle']['signingoto']);
	}
	unset($_SESSION['gimle']['signingoto']);
}

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

header('Location: ' . $target);

/*
Was this needed by some oauth providers?
If so, make it optional.
It will show white screen blink which is not needed if not strictly required by the site.
?>
<!doctype html>
<html>
	<head>
		<meta charset="<?=mb_internal_encoding()?>">
		<title>Redirect</title>
		<script>
			window.location.href = '<?=str_replace("'", "\\'", $target)?>';
		</script>
	</head>
	<body></body>
</html>
<?php
*/

return true;
