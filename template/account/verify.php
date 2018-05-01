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
	$query = sprintf("SELECT `account_id` FROM `account_auth_local` WHERE `verification` = '%s';",
		$db->real_escape_string($token)
	);
	$result = $db->query($query);
	$row = $result->get_assoc($query);
	if ($row !== false) {
		$query = sprintf("UPDATE `account_auth_local` SET `verification` = null WHERE `verification` = '%s';",
			$db->real_escape_string($token)
		);
		$result = $db->query($query);
		$verified = true;
	}
}
?>
<h1><?=_('Verify account')?></h1>
<?php
if ($verified === false) {
?>
<p><?=_('Could not verify the account.')?></p>
<ul>
	<li><?=_('Your account might already be verified.')?> <?=sprintf('<a href="' . $prefix . 'account/signin">%s</a>', _('sign in', ['form' => 'action']))?></li>
	<li><?=_('The verification token might be incorrect, check your email again.')?></li>
</ul>
<?php
}
else {
?>
<p><?=sprintf(_('Your account is now verified, you may now %s.'), sprintf('<a href="' . $prefix . 'account/signin">%s</a>', _('sign in')))?></p>
<?php
}
return true;
