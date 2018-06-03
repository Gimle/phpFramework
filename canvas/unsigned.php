<?php
declare(strict_types=1);
namespace gimle;

header('Content-type: text/html; charset=' . mb_internal_encoding());
?>
<!doctype html>
<html lang="%lang%">
	<head>
		<meta charset="<?=mb_internal_encoding()?>">
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<title>%title%</title>
		<meta name="description" content="%description%">
		<meta name="viewport" content="width=device-width, initial-scale=1">
	</head>
	<body>
		%content%
	</body>
</html>
<?php
return true;
