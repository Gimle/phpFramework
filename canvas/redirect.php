<?php
declare(strict_types=1);
namespace gimle;

$params = inc_get_args();

?>
<!doctype html>
<html>
	<head>
		<meta charset="<?=mb_internal_encoding()?>">
		<title>Redirect</title>
		<script>
<?php
if (isset($params[0])) {
?>
			history.pushState({}, '', '<?=str_replace("'", "\\'", $params[0])?>');
			window.location.reload();
<?php
}
else {
?>
			if (window.history.length > 0) {
				window.history.go(-1);
			}
			else {
				window.location.href = '<?=BASE_PATH?>';
			}
<?php
}
?>
		</script>
	</head>
	<body>
	</body>
</html>
<?php
return true;
