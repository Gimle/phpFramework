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
?>

<h1><?=_('Request an email to reset password')?></h1>

<form id="gimle-signinform" action="<?=BASE_PATH?>account/json/passwordreset" method="POST">
	<input type="hidden" name="token" value="<?=$token?>"></input>
	<label>
		<span><?=_('E-Mail')?></span>
		<input name="username" type="email" placeholder="<?=_('Email address')?>" required></input>
	</label>

	<button name="action" value="local" class="content"><?=_('Request a password reset email')?></button>

	<script>
		var success = (json) => {
			document.getElementById('gimle-signinform').innerHTML = `<?=_('The requst has been sent, please check your email.')?>`;
		};
		var error = () => {
			document.getElementById('gimle-signinform').innerHTML = `<?=_('An unknown error occured.')?>`;
		};
		document.getElementById('gimle-signinform').addEventListener('submit', function (e) {
			e.preventDefault();
			var data = new FormData(this);
			var request = new XMLHttpRequest();
			request.open(this.getAttribute('method'), this.getAttribute('action'), true);

			document.getElementById('gimle-signinform').innerHTML = `<?=_('Loading…')?>`;

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
</form>
<p><a href="<?=$prefix?>account/signin"><?=_('Sign in', ['form' => 'action'])?></a></p>

<?php
return true;
