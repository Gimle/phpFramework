<?php
declare(strict_types=1);
namespace gimle;

use \gimle\router\Router;
use \gimle\canvas\Canvas;

/**
 * The local absolute location of the site.
 *
 * @var string
 */
define('gimle\\SITE_DIR', substr(__DIR__, 0, strrpos(__DIR__, DIRECTORY_SEPARATOR) + 1));

require SITE_DIR . 'module/gimle/init.php';

Canvas::title(null, 'template');
Canvas::title('Site name', 'sitename');
$router = Router::getInstance();

$router->setCanvas(BASE_PATH_KEY);

$router->bind('pc', '', function () use ($router) {
	return $router->setTemplate('welcome');
});

$router->dispatch();

return true;
