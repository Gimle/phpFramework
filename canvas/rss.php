<?php
declare(strict_types=1);
namespace gimle;

header('Content-type: application/rss+xml; charset=' . mb_internal_encoding());
?>%content%<?php
return true;
