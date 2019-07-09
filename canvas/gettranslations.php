<?php
declare(strict_types=1);
namespace gimle;

header('Content-type: application/json');

$i18n = i18n::getInstance();

echo json_encode($i18n->getJson());

return true;
