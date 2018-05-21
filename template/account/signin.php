<?php
declare(strict_types=1);
namespace gimle;

use \gimle\canvas\Canvas;
use \gimle\user\User;

header('Content-type: text/html; charset=' . mb_internal_encoding());

session_start();

Canvas::title(_('Sign in', ['form' => 'action']), 'template');
$prefix = (Config::get('applinks') ? '#' : BASE_PATH);

if (User::isFirstSignin() === true) {
?>
<p><?=sprintf(_('Welcome to %s!'), Config::get('sitename'))?></p>
<p><a href="<?=$prefix?>process/signout"><?=_('Sign out')?></a></p>
<?php
	return true;
}

if (User::current() !== null) {
	if (User::signinPerformed()) {
?>
<p><?=_('You are now signed in.')?></p>
<?php
	}
	else {
?>
<p><?=_('You are signed in.')?></p>
<p><a href="<?=$prefix?>process/signout"><?=_('Sign out')?></a></p>
<?php
	}
	return true;
}

$signInResult = User::getSigninException();
if ($signInResult !== null) {
	$code = $signInResult->getCode();
	if (in_array($code, [User::USER_NOT_FOUND, User::INVALID_PASSWORD, User::USER_NOT_VALIDATED])) {
?>
<p><?=_('Invalid username or password.')?></p>
<?php
	}
	elseif ($code === User::INVALID_PAYLOAD) {
?>
<p><?=_('Timeout, please try again.')?></p>
<?php
	}
	else {
		d($signInResult);
?>
<p><?=_('An unknown error occured.')?></p>
<?php
	}
}

$token = User::generateSigninToken();

$config = __NAMESPACE__ . '\\MainConfig';
$oauth = call_user_func_array([$config, 'get'], ['user.auth.oauth']);
$hasOauth = ((is_array($oauth)) && (!empty($oauth)));
$local = call_user_func_array([$config, 'get'], ['user.auth.local']);
$hasLocal = ((is_array($local)) && (!empty($local)));

if ($hasOauth) {
?>
<form id="openIDSignin" action="<?=BASE_PATH?>process/signin" method="POST">
	<input type="hidden" name="token" value="<?=$token?>"></input>
<?php
	foreach ($oauth as $id => $data) {
?>
	<button name="oauth" value="<?=htmlspecialchars($id)?>"><?=sprintf(_('Sign in with %s'), mb_ucfirst($id))?></button>
<?php
	}
?>
</form>
<?php
}
if ($hasOauth && $hasLocal) {
?>
<p><?=_('or')?></p>
<?php
}
if ($hasLocal) {
	if (!$hasOauth) {
?>
<p><?=_('Sign in:')?></p>
<?php
	}
?>
<form id="localSignin" action="<?=BASE_PATH?>process/signin" method="POST">
	<input type="hidden" name="token" value="<?=$token?>"></input>
	<label>
		<span class="text"><?=_('Username')?></span>
		<input type="email" name="username" placeholder="<?=_('Email address')?>"/>
	</label>
	<label>
		<span class="text"><?=_('Password')?></span>
		<input type="password" name="password" placeholder="<?=_('Password')?>"/>
	</label>
	<button><?=_('Sign in', ['form' => 'action'])?></button>
</form>
<p>
	<a href="<?=$prefix?>account/passwordreset"><?=_('Forgot password')?></a>
</p>
<?php
}
if (!$hasOauth && !$hasLocal) {
?>
<p><?=_('No sign in options configured.')?></p>
<?php
}
return true;
