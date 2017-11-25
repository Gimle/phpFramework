<?php
declare(strict_types=1);
namespace gimle;

use gimle\rest\Fetch;
use gimle\xml\SimpleXmlElement;
use gimle\user\User;
use gimle\user\Ldap;

try {
	session_start();

	User::clearSigninException();

	if (isset($_SESSION['gimle']['user'])) {
		throw new Exception('User already signed in.', User::ALREADY_SIGNED_IN);
	}

	$config = User::getConfig();

	if (!isset($_SESSION['gimle']['awaitAuthFeedback'])) {
		if (!isset($_POST['token'])) {
			throw new Exception('Payload missing.', User::MISSING_PAYLOAD);
		}

		if (!User::validateSigninToken($_POST['token'])) {
			throw new Exception('Payload not valid.', User::INVALID_PAYLOAD);
		}

		if ((isset($_POST['username'])) && (isset($_POST['password']))) {
			$user = null;
			foreach ($config as $key => $value) {
				if ($key === 'ldap') {
					$ldap = Ldap::getInstance();
					$result = null;
					try {
						$result = $ldap->login($_POST['username'], $_POST['password']);
					}
					catch (Exception $e) {
					}
					if ($result !== null) {
						try {
							User::getUser($result['username'][0], 'remote');
						}
						catch (Exception $e) {
							User::create($result, 'ldap');
						}
						$user = User::login($result['username'][0], 'remote');
						$user['provider'] = $result;
					}
				}
			}
			if ($user === null) {
				$user = User::login($_POST['username'], 'local', $_POST['password']);
			}
			$_SESSION['gimle']['user'] = $user;
		}
		else {
			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
	}
	else {
		unset($_SESSION['gimle']['awaitAuthFeedback']);
	}
}
catch (Exception $e) {
	User::setSigninException($e);
}

User::deleteSigninToken();
return inc(SITE_DIR . 'module/' . MODULE_GIMLE . '/canvas/redirect.php');
