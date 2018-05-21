<?php
declare(strict_types=1);
namespace gimle;

use \gimle\user\User;
use \gimle\sql\Mysql;

session_start();

$prefix = (Config::get('applinks') ? '#' : BASE_PATH);

if (User::current() !== null) {
?>
<p><?=_('You are signed in.')?></p>
<p><a href="<?=$prefix?>process/signout"><?=_('Sign out')?></a></p>
<?php
	return true;
}

$verified = false;
$token = '';
if ((isset($_SERVER['QUERY_STRING'])) && (strlen($_SERVER['QUERY_STRING']) > 2)) {
	$token = $_SERVER['QUERY_STRING'];
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
?>
<h1><?=_('Set new password')?></h1>
<?php
if ($verified === false) {
?>
<p><?=_('Could not set new password.')?></p>
<ul>
	<li><?=_('The reset link might be expired.')?> <?=sprintf('<a href="' . $prefix . 'account/passwordreset">%s</a>.', _('request new password reset email'))?></li>
	<li><?=sprintf(_('Did you end up here looking for the %s page?'), sprintf('<a href="' . $prefix . 'account/signin">%s</a>', _('sign in', ['form' => 'page'])))?></li>
	<li><?=_('The verification token might be incorrect, check your email again.')?></li>
</ul>
<?php
	return true;
}
?>
<form id="gimle-newpasswordform" class="gimle-form" action="<?=$prefix?>account/json/verifyreset" method="POST">
	<input type="hidden" name="token" value="<?=htmlspecialchars($token)?>"/>
	<label>
		<input name="generate_password" value="true" type="checkbox" id="gimle-generateRandomPassword"/>
		<span><?=_('Generate a random password.')?></span>
	</label>
	<div class="gimle-generate">
		<label>
			<input name="target" value="email" type="radio" checked/>
			<span><?=_('Send me the new password in an email.')?></span>
		</label>
		<label>
			<input name="target" value="show" type="radio"/>
			<span><?=_('Show the new password after it is set.')?></span>
		</label>
	</div>
	<div class="gimle-inputmatcher gimle-typepass">
		<p class="gimle-matchermessage gimle-passmismatch"><?=_('Password does not match.')?></p>
		<label>
			<span><?=_('Password')?></span>
			<input name="password" type="password" placeholder="<?=_('Password')?>" required></input>
		</label>
		<label>
			<span><?=_('Password (repeat)')?></span>
			<input name="password2" type="password" placeholder="<?=_('Password (repeat)')?>" required></input>
		</label>
	</div>

	<button name="action" value="local" class="content"><?=_('Set new password')?></button>
</form>
<style>
	.gimle-form .gimle-matchermessage:not(.gimle-mismatch),
	.gimle-form.gimle-generatepass .gimle-typepass,
	.gimle-form:not(.gimle-generatepass) .gimle-generate {
		display: none;
	}
</style>
<script>
	var $pass1 = document.querySelector('#gimle-newpasswordform input[name="password"]');
	var $pass2 = document.querySelector('#gimle-newpasswordform input[name="password2"]');

	var checkPassword = function () {
		if ($pass1.value !== $pass2.value) {
			document.querySelector('#gimle-newpasswordform .gimle-passmismatch').classList.add('gimle-mismatch');
			return false;
		}
		document.querySelector('#gimle-newpasswordform .gimle-passmismatch').classList.remove('gimle-mismatch');
		return true;
	};

	$pass2.addEventListener('blur', function (e) {
		$pass1.addEventListener('blur', function (e) {
			checkPassword();
		});
		checkPassword();
	});
	document.getElementById('gimle-generateRandomPassword').addEventListener('change', function (e) {
		if (this.checked === true) {
			document.getElementById('gimle-newpasswordform').classList.add('gimle-generatepass');
			$pass1.required = false;
			$pass2.required = false;
		}
		else {
			document.getElementById('gimle-newpasswordform').classList.remove('gimle-generatepass');
			$pass1.required = true;
			$pass2.required = true;
			checkPassword();
		}
	});

	var success = (json) => {
		if (json === 'email') {
			document.getElementById('gimle-newpasswordform').innerHTML = `<?=_('An email with the password has been sent. Check your email.')?>`;
		}
		else if (json.newpass !== undefined) {
			document.getElementById('gimle-newpasswordform').innerHTML = `<?=sprintf(_('Your new password had been set to: %s'), '<code class="selectable">${json.newpass}</code>')?>
				<br/><?=_('Make sure to copy the password from above before you leave this page.')?>
				<br/>
				<br/><?=sprintf(_('You can now %s.'), sprintf('<a href="#account/signin">%s</a>', _('sign in')))?>
			`;
		}
		else {
			document.getElementById('gimle-newpasswordform').innerHTML = `<?=sprintf(_('Your new password had been set. You can now %s.'), sprintf('<a href="' . $prefix . 'account/signin">%s</a>', _('sign in')))?>`;
		}
	};
	var error = () => {
		document.getElementById('gimle-newpasswordform').innerHTML = `<?=_('An unknown error occured.')?>`;
	};

	document.getElementById('gimle-newpasswordform').addEventListener('submit', function (e) {
		e.preventDefault();

		if (document.getElementById('gimle-generateRandomPassword').checked === false) {
			if (checkPassword() === false) {
				return false;
			}
		}

		var data = new FormData(this);
		var request = new XMLHttpRequest();
		request.open(this.getAttribute('method'), this.getAttribute('action'), true);

		document.getElementById('gimle-newpasswordform').innerHTML = '<?=_('Loading…')?>';

		request.onload = () => {
			if ((request.status >= 200) && (request.status < 400)) {
				try {
					var json = JSON.parse(request.responseText);
					if (json.error !== undefined) {
						throw json.error;
					}
					success(json);
				}
				catch (e) {
					error();
				}
			}
			else {
				error();
			}
		};
		request.onerror = () => {
			error();
		};
		request.send(data);
	});
</script>
<?php

return true;

