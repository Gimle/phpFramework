<?php
declare(strict_types=1);
namespace gimle;

use \gimle\user\User;

session_start();

$prefix = (Config::get('applinks') ? '#' : BASE_PATH);

if (User::current() !== null) {
?>
<p><?=_('You are signed in.')?></p>
<p><a href="<?=$prefix?>process/signout"><?=_('Sign out')?></a></p>
<?php
	return true;
}
$token = User::generateSigninToken();

$config = __NAMESPACE__ . '\\MainConfig';
$oauth = call_user_func_array([$config, 'get'], ['user.auth.oauth']);
$hasOauth = ((is_array($oauth)) && (!empty($oauth)));
$local = call_user_func_array([$config, 'get'], ['user.auth.local']);
$hasLocal = ((is_array($local)) && (!empty($local)));

if ($hasLocal) {
?>

<h1><?=_('Create a new account')?></h1>
<div id="gimle-createaccountoptions">
	<form id="gimle-createaccountform" class="gimle-form" action="<?=BASE_PATH?>account/json/create" method="POST">
		<div>
			<label>
				<span><?=_('First name')?></span>
				<input name="first_name" type="text" placeholder="<?=_('First name')?>"></input>
			</label>
			<label>
				<span><?=_('Last name')?></span>
				<input name="last_name" type="text" placeholder="<?=_('Last name')?>"></input>
			</label>
		</div>
		<div class="gimle-inputmatcher">
			<p class="gimle-matchermessage gimle-emailmismatch"><?=_('Email does not match.')?></p>
			<label>
				<span><?=_('Email address')?></span>
				<input name="username" type="email" placeholder="<?=_('Email address')?>" required></input>
			</label>
			<label>
				<span><?=_('Email address (repeat)')?></span>
				<input name="username2" type="email" placeholder="<?=_('Email address (repeat)')?>" required></input>
			</label>
		</div>
		<div>
			<label>
				<input name="generate_password" value="true" type="checkbox" id="gimle-generateRandomPassword"/>
				<span><?=_('Generate a random password.')?></span>
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

		<button name="action" value="local" class="content"><?=_('Create account, and send me an activation email')?></button>
		<p>
			<?=sprintf(_('You can at any time use the %s to create a new password.'), sprintf('<a href="' . $prefix . 'account/passwordreset">%s</a>', _('password reset')))?>
		</p>
	</form>
<?php
}
if ($hasOauth && $hasLocal) {
?>
	<p>
		<?=_('We also support these sign in providers.', ['quantity' => count($oauth)])?>
	</p>
<?php
}
if ($hasOauth) {
?>
	<form class="gimle-form" action="<?=BASE_PATH?>process/signin" method="POST">
		<input type="hidden" name="token" value="<?=$token?>"></input>
		<div>
<?php
foreach ($oauth as $id => $data) {
?>
			<button name="oauth" value="<?=htmlspecialchars($id)?>" class="signInProviderButton"><?=sprintf(_('Sign in with %s'), mb_ucfirst($id))?></button>
<?php
}
?>
		</div>
	</form>
</div>

<?php
}
?>
<style>
	.gimle-form .gimle-matchermessage:not(.gimle-mismatch),
	.gimle-form.gimle-generatepass .gimle-typepass {
		display: none;
	}
</style>
<script>
	var $uname1 = document.querySelector('#gimle-createaccountform input[name="username"]');
	var $uname2 = document.querySelector('#gimle-createaccountform input[name="username2"]');
	var $pass1 = document.querySelector('#gimle-createaccountform input[name="password"]');
	var $pass2 = document.querySelector('#gimle-createaccountform input[name="password2"]');

	var checkUsername = function () {
		if ($uname1.value !== $uname2.value) {
			document.querySelector('#gimle-createaccountform .gimle-emailmismatch').classList.add('gimle-mismatch');
			return false;
		}
		document.querySelector('#gimle-createaccountform .gimle-emailmismatch').classList.remove('gimle-mismatch');
		return true;
	};
	var checkPassword = function () {
		if ($pass1.value !== $pass2.value) {
			document.querySelector('#gimle-createaccountform .gimle-passmismatch').classList.add('gimle-mismatch');
			return false;
		}
		document.querySelector('#gimle-createaccountform .gimle-passmismatch').classList.remove('gimle-mismatch');
		return true;
	};

	$uname2.addEventListener('blur', function (e) {
		$uname1.addEventListener('blur', function (e) {
			checkUsername();
		});
		checkUsername();
	});
	$pass2.addEventListener('blur', function (e) {
		$pass1.addEventListener('blur', function (e) {
			checkPassword();
		});
		checkPassword();
	});
	document.getElementById('gimle-generateRandomPassword').addEventListener('change', function (e) {
		if (this.checked === true) {
			document.getElementById('gimle-createaccountform').classList.add('gimle-generatepass');
			$pass1.required = false;
			$pass2.required = false;
		}
		else {
			document.getElementById('gimle-createaccountform').classList.remove('gimle-generatepass');
			$pass1.required = true;
			$pass2.required = true;
			checkPassword();
		}
	});


	var success = () => {
		document.getElementById('gimle-createaccountoptions').innerHTML = `<?=_('An activation email has been sent. Check your email.')?>`;
	};
	var error = () => {
		document.getElementById('gimle-createaccountoptions').innerHTML = `<?=_('An unknown error occured.')?>`;
	};

	document.getElementById('gimle-createaccountform').addEventListener('submit', function (e) {
		e.preventDefault();

		if (checkUsername() === false) {
			return false;
		}

		if (document.getElementById('gimle-generateRandomPassword').checked === false) {
			if (checkPassword() === false) {
				return false;
			}
		}

		var data = new FormData(this);
		var request = new XMLHttpRequest();
		request.open(this.getAttribute('method'), this.getAttribute('action'), true);

		document.getElementById('gimle-createaccountoptions').innerHTML = '<?=_('Loading…')?>';

		request.onload = () => {
			if ((request.status >= 200) && (request.status < 400)) {
				try {
					var json = JSON.parse(request.responseText);
					if (json.error !== undefined) {
						throw json.error;
					}
					if (json !== 'account_created') {
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
