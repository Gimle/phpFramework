<?php
declare(strict_types=1);
namespace gimle;

?>
<h1>Welcome to your new site!</h1>

<p>As long as this page shows you are good to go. The tests below cover support for extended functionality.

<p>Checking system:</p>
<ul>
	<li>zend.assertions: <?=(ini_get('zend.assertions') !== '-1' ? '<span style="color: #0c0;">On</span>' : '<span style="color: #c00;">Off</span>')?></li>
	<li>date.timezone: <?=(ini_get('date.timezone') !== '' ? ini_get('date.timezone') : '<span style="color: #c00;">Not set</span>')?>
</ul>

<p>Checking extensions:</p>
<ul>
	<li>SimpleXmlElement: <?=(class_exists('SimpleXmlElement') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
	<li>ZipArchive: <?=(class_exists('ZipArchive') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
	<li>Mariadb / MySql: <?=(function_exists('mysqli_connect') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
	<li>Mongo: <?=(class_exists('MongoDB\Driver\Manager') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
	<li>Sqlite3: <?=(class_exists('Sqlite3') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
	<li>GD: <?=(function_exists('gd_info') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
	<li>Curl: <?=(function_exists('curl_init') === true ? '<span style="color: #0c0;">Found</span>' : '<span style="color: #c00;">Not found</span>')?></li>
</ul>

<?php

return true;
