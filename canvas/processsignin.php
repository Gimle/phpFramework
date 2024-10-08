<?php
declare(strict_types=1);
namespace gimle;

try {
	session_start();

	if (isset($_REQUEST['principal'])) {
		$_SESSION['gimle']['activeprincipal'] = $_REQUEST['principal'];
	}

	User::clearActionException();

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
			$asi = false;
			if (isset($_POST['auto'])) {
				$asi = true;
			}
			$user = User::login($_POST['email'], $_POST['password'], $asi);
			if ($user->id === null) {
				if ($user instanceof \gimle\user\storage\Xml) {
					$userxml = \gimle\xml\SimpleXmlElement::open(STORAGE_DIR . 'users.xml');
					if (current($userxml->xpath('/users/user')) === false) {
						$user = new User();
						$user->email = $_POST['email'];
						$user->firstName = substr($_POST['email'], 0, strpos($_POST['email'], '@'));
						$user->lastName = substr($_POST['email'], strpos($_POST['email'], '@') + 1);
						$user->addLocalAuth($_POST['email'], $_POST['password']);
						$user->groups = [2 => 'root'];
						$res = $user->save();
						if ($res === null) {
							throw new Exception('Could not create user.', User::OTHER_ERROR);
						}
					}
				}
				if ($user->id === null) {
					throw new Exception('User not found.', User::USER_NOT_FOUND);
				}
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
	$e->set('type', 'signin');
	if ($e->getCode() !== User::ALREADY_SIGNED_IN) {
		User::setCookie('Asi', 'false', time() - (86400 * 400));
	}
	User::setActionException($e);
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

$target = BASE_PATH;

if (isset($_SESSION['gimle']['retrunto'])) {
	$target = $_SESSION['gimle']['retrunto'];
	unset($_SESSION['gimle']['retrunto']);
}

if (isset($_SESSION['gimle']['activeprincipal'])) {
	if (Config::exists('user.principal.' . $_SESSION['gimle']['activeprincipal'])) {
		$target = BASE_PATH;
		$principal = Config::get('user.principal.' . $_SESSION['gimle']['activeprincipal']);
		if (!isset($_SESSION['gimle']['user'])) {
			if (isset($principal['fail'])) {
				$target .= $principal['fail'];
			}
			elseif (isset($principal['path'])) {
				$target .= $principal['path'];
			}
		}
		elseif (isset($principal['path'])) {
			$target .= $principal['path'];
		}
	}
	unset($_SESSION['gimle']['activeprincipal']);
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
