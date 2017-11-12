<?php
declare(strict_types=1);
namespace gimle;

$params = inc_get_args();

$returnUrl = BASE_PATH;
if (isset($params[0])) {
	$returnUrl = str_replace("'", "\\'", $params[0]);
}

?>
<!doctype html>
<html>
	<head>
		<meta charset="<?=mb_internal_encoding()?>">
		<title>Redirect</title>
		<script>
			if (window.history.length > 0) {
				window.history.go(-1);
			}
			else {
				window.location.href = '<?=$returnUrl?>';
			}
		</script>
	</head>
	<body>
	</body>
</html>
<?php
return true;
