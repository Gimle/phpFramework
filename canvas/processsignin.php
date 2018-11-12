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
			foreach ($config['auth'] as $key => $value) {
				if (($value !== false) && ($key === 'ldap')) {
					$ldap = Ldap::getInstance();
					$result = null;
					try {
						$result = $ldap->login($_POST['username'], $_POST['password']);
					}
					catch (Exception $e) {
					}
					if ($result !== null) {
						if (!User::exists($result['username'][0], 'ldap')) {
							// @todo: Check if autocreate is enabled.
							User::create($result, 'ldap');
						}
						$user = User::login($result['username'][0], 'ldap');
						$user['provider'] = $result;
					}
				}
				if (($value !== false) && ($key === 'pam')) {
					$result = pam_auth($_POST['username'], $_POST['password']);
					if ($result === true) {
						if (!User::exists($_POST['username'], 'pam')) {
							// @todo: Check if autocreate is enabled, and set correct input for create.
							User::create([], 'pam');
						}
						$user = User::login($_POST['username'], 'pam');
						$user['pam'] = $_POST['username'];
					}
				}
				if (($value !== false) && ($key === 'custom')) {
					$result = $value['function']($_POST['username'], $_POST['password']);
					if ($result !== null) {
						if (!User::exists($_POST['username'], 'custom')) {
							// @todo: Check if autocreate is enabled, and set correct input for create.
							User::create([], 'custom');
						}
						$user = User::login($_POST['username'], 'custom');
						$user['provider'] = $result;
					}
				}
			}
			if ($user === null) {
				if ((isset($config['auth']['local'])) && ($config['auth']['local'] !== false) && ($config['auth']['local'] !== null)) {
					$user = User::login($_POST['username'], 'local', $_POST['password']);
				}
				else {
					throw new Exception('User not found.', User::USER_NOT_FOUND);
				}
			}
			$_SESSION['gimle']['user'] = $user;
		}
		elseif (isset($_POST['oauth'])) {
			foreach ($config['auth']['oauth'] as $key => $value) {
				if ($key === $_POST['oauth']) {
					$state = sha1(openssl_random_pseudo_bytes(1024));
					$_SESSION['gimle']['awaitAuthFeedback'] = $key;
					$_SESSION['gimle']['authState'] = $state;

					$get = [];
					$get['client_id'] = $value['clientId'];
					$get['response_type'] = 'code';
					$get['scope'] = $value['scope'];
					$get['redirect_uri'] = THIS_PATH;
					$get['state'] = $state;

					header('Location: ' . $value['authurl'] . '?' . http_build_query($get));
					return true;
				}
			}
			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
		else {
			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
	}
	else {
		$key = $_SESSION['gimle']['awaitAuthFeedback'];
		unset($_SESSION['gimle']['awaitAuthFeedback']);
		if (!isset($_GET['code'])) {
			throw new Exception('Oauth error.', User::OAUTH_ERROR);
		}
		if ((!isset($_GET['state'])) || ($_SESSION['gimle']['authState'] !== $_GET['state'])) {
			throw new Exception('Oauth state error.', User::STATE_ERROR);
		}

		$fetch = new Fetch();
		$fetch->connectionTimeout(2);
		$fetch->resultTimeout(3);

		$url = $config['auth']['oauth'][$key]['tokenurl'];

		$fetch->post('code', $_GET['code']);
		$fetch->post('client_id', $config['auth']['oauth'][$key]['clientId']);
		$fetch->post('client_secret', $config['auth']['oauth'][$key]['clientSecret']);
		$fetch->post('redirect_uri', THIS_PATH);
		$fetch->post('grant_type', 'authorization_code');

		$res = $fetch->query($url);

		if ($res['error'] === 0) {
			$res = json_decode($res['reply'], true);
		}
		if (!isset($res['access_token'])) {
			throw new Exception('Oauth reject signin.', User::OAUTH_REJECT);
		}

		/*
		ok, now induvidual callback, google and facebook hardcoded here.
		@todo remove this hardcoding.
		*/

		if ($key === 'google') {

			$fetch = new Fetch();
			$fetch->header('Authorization', 'Bearer ' . $res['access_token']);
			$url = 'https://www.googleapis.com/plus/v1/people/me/openIdConnect';
			$res = $fetch->query($url);
			if ($res['error'] !== 0) {
				throw new Exception('Google plus connect error.', User::OTHER_ERROR);
			}
			$res = json_decode($res['reply'], true);

			if (!is_array($res)) {
				throw new Exception('Google plus reply not valid.', User::OTHER_ERROR);
			}
			if (!isset($res['sub'])) {
				throw new Exception('Google plus sub missing.', User::OTHER_ERROR);
			}

			if (!User::exists($res['sub'], 'oauth.google')) {
				// @todo: Check if autocreate is enabled.
				User::create($res, 'oauth.google');
			}
			$user = User::login($res['sub'], 'oauth.google');
		}
		elseif ($key === 'facebook') {

			$fetch = new Fetch();
			$fetch->connectionTimeout(2);
			$fetch->resultTimeout(3);

			$url = 'https://graph.facebook.com/v2.5/me?fields=id,email,first_name,last_name&access_token=' . $res['access_token'];
			$res = $fetch->query($url);
			if ($res['error'] !== 0) {
				throw new Exception('Facebook connect error.', User::OTHER_ERROR);
			}
			$res = json_decode($res['reply'], true);

			if (!is_array($res)) {
				throw new Exception('Facebook reply not valid.', User::OTHER_ERROR);
			}
			if (!isset($res['id'])) {
				throw new Exception('Facebook id missing.', User::OTHER_ERROR);
			}

			if (!User::exists($res['id'], 'oauth.facebook')) {
				// @todo: Check if autocreate is enabled.
				User::create($res, 'oauth.facebook');
			}
			$user = User::login($res['id'], 'oauth.facebook');
		}
		else {
			throw new Exception('Unknown signin operation.', User::UNKNOWN_OPERATION);
		}
		$_SESSION['gimle']['user'] = $user;
	}
}
catch (Exception $e) {
	$e->set('post', $_POST);
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
