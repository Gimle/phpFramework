<?php
declare(strict_types=1);
namespace gimle\router;

use const \gimle\SITE_DIR;
use const \gimle\BASE_PATH_KEY;
use const \gimle\ENV_MODE;
use const \gimle\ENV_LIVE;
use const \gimle\ENV_CLI;
use const \gimle\FILTER_VALIDATE_DIRNAME;
use const \gimle\MAIN_SITE_DIR;
use const \gimle\SITE_ID;

use \gimle\canvas\Canvas;
use \gimle\Spectacle;

use function \gimle\filter_var;
use function \gimle\get_mimetype;
use function \gimle\inc;
use function \gimle\sp;
use function \gimle\d;

class RouterBase extends PathResolver
{
	use \gimle\trick\Singelton;

	/*
	Constants for route types.
	*/
	public const R_GET = 1;
	public const R_POST = 2;
	public const R_PUT = 4;
	public const R_PATCH = 8;
	public const R_DELETE = 16;
	public const R_COPY = 32;
	public const R_HEAD = 64;
	public const R_OPTIONS = 128;
	public const R_LINK = 256;
	public const R_UNLINK = 512;
	public const R_PURGE = 1024;
	public const R_ANY = 2047;

	/*
	Constants for errors.
	*/
	public const E_ROUTE_NOT_FOUND = 1;
	public const E_METHOD_NOT_FOUND = 2;
	public const E_CANVAS_NOT_FOUND = 3;
	public const E_TEMPLATE_NOT_FOUND = 4;
	public const E_CANVAS_RETURN = 5;
	public const E_TEMPLATE_RETURN = 6;
	public const E_ROUTES_EXHAUSTED = 7;
	public const E_CANVAS_NOT_SET = 8;
	public const E_UNKNOWN = 9;

	public $preRender = null;

	/**
	 * The request method for this request.
	 *
	 * @var int
	 */
	private $requestMethod;

	/**
	 * The currently selected canvas to serve.
	 *
	 * @var string
	 */
	private $canvas = null;

	/**
	 * Should the canvas be parsed, or served directly.
	 *
	 * @var bool
	 */
	private $parseCanvas = true;

	/**
	 * The currently selected template to serve.
	 *
	 * @var ?string
	 */
	private $template = null;
	private $templateargs = [];

	/**
	 * The defined routes.
	 *
	 * @var array
	 */
	private $routes = [];

	/**
	 * Information about the current url.
	 *
	 * @var array
	 */
	private $url = [];

	/**
	 * The current url.
	 *
	 * @var string
	 */
	private $urlString = '';

	/**
	 * Custom fallbacks.
	 *
	 * @var array
	 */
	private $fallback = [];

	/**
	 * If a route fails, store the fail here, for a potential exception to use it later.
	 *
	 * @var array
	 */
	private $tried = [];

	/**
	 * Holder for variables that should not be affected by includes.
	 *
	 * @var array
	 */
	private $vars = [];

	/**
	 * Holder for potentional error.
	 *
	 * @var ?\Throwable
	 */
	public $e = null;

	public function __construct ()
	{
		if (ENV_MODE & ENV_CLI) {
			return;
		}
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				$this->requestMethod = self::R_GET;
				break;
			case 'POST':
				$this->requestMethod = self::R_POST;
				break;
			case 'PUT':
				$this->requestMethod = self::R_PUT;
				break;
			case 'PATCH':
				$this->requestMethod = self::R_PATCH;
				break;
			case 'DELETE':
				$this->requestMethod = self::R_DELETE;
				break;
			case 'COPY':
				$this->requestMethod = self::R_COPY;
				break;
			case 'HEAD':
				$this->requestMethod = self::R_HEAD;
				break;
			case 'OPTIONS':
				$this->requestMethod = self::R_OPTIONS;
				break;
			case 'LINK':
				$this->requestMethod = self::R_LINK;
				break;
			case 'UNLINK':
				$this->requestMethod = self::R_UNLINK;
				break;
			case 'PURGE':
				$this->requestMethod = self::R_PURGE;
				break;
			default:
				$this->requestMethod = 0;
		}

		if (isset($_SERVER['PATH_INFO'])) {
			$this->urlString = trim($_SERVER['PATH_INFO'], '/');
			if ($this->urlString !== '') {
				$this->url = explode('/', $this->urlString);
			}
		}

		if (current($this->url) === '__spectacle') {
			if (isset($this->url[1])) {
				header('Content-type: application/json');
				header('Access-Control-Allow-Origin: *');
				$res = Spectacle::read($this->url[1]);
				if ($res === false) {
					echo json_encode(false);
				}
				else {
					echo trim($res);
				}
				die();
			}
		}

		if (file_exists(MAIN_SITE_DIR . 'config/manifest.php')) {
			$this->bind('*', 'manifest.json', function () {
				$this->setCanvas('manifest');
				$this->setTemplate('manifest');
			});
		}
	}

	/**
	 * Set the canvas to use.
	 *
	 * @param string $name The name of the canvas.
	 * @param bool $parse Should the canvas be parsed.
	 * @return void
	 */
	public function setCanvas (string $name, bool $parse = true): void
	{
		$this->canvas = $name;
		$this->parseCanvas = $parse;
	}

	/**
	 * Set the template to use.
	 *
	 * @param string $name The name of the canvas.
	 * @return void
	 */
	public function setTemplate (string $name, ...$args): void
	{
		$this->templateargs = $args;
		$this->template = $name;
	}

	/**
	 * Bind a route by specifying a regular expression for that route.
	 *
	 * @param string $basePathKey The base path key this route is valid for.
	 * @param string $path The path this route is triggered for.
	 * @param ?callable $callback A callback for this route.
	 * @param int $requestMethod The request method this route is valid for.
	 * @return void
	 */
	public function bindByRegex (string $basePathKey, string $path, ?callable $callback, int $requestMethod = self::R_GET | self::R_HEAD): void
	{
		if (ENV_MODE & ENV_CLI) {
			return;
		}
		assert(($requestMethod >= self::R_GET) && ($requestMethod <= ((self::R_PURGE * 2) - 1)));
		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {

			$this->routes[$path][] = [
				'callback' => $callback,
				'requestMethod' => $requestMethod,
			];
		}
	}

	/**
	 * Bind a route by specifying a url structure for that route.
	 *
	 * @param string $basePathKey The base path key this route is valid for.
	 * @param string $path The path this route is triggered for.
	 * @param ?callable $callback A callback for this route.
	 * @param array $conditions Conditions for the route (optional).
	 * @param int $requestMethod The request method this route is valid for.
	 * @return void
	 */
	public function bind (string $basePathKey, string $path, ?callable $callback = null, $conditions = [], $requestMethod = self::R_GET | self::R_HEAD): void
	{
		if (ENV_MODE & ENV_CLI) {
			return;
		}
		assert(($requestMethod >= self::R_GET) && ($requestMethod <= ((self::R_PURGE * 2) - 1)));
		if (!is_array($conditions)) {
			$requestMethod = $conditions;
			$conditions = [];
		}
		$path = $this->bindToRegex($path, $conditions);

		$basePathKey = explode('|', $basePathKey);
		if ((in_array('*', $basePathKey)) || (in_array(BASE_PATH_KEY, $basePathKey))) {

			$this->routes[$path][] = [
				'callback' => $callback,
				'requestMethod' => $requestMethod,
			];
		}
	}

	/**
	 * Convert a path representation for a route to a regular expression.
	 *
	 * @param string $path The path.
	 * @param array $conditions Conditions for the path.
	 * @return string The regular expression.
	 */
	public function bindToRegex (string $path, array $conditions = []): string
	{
		return '#^' . preg_replace_callback('#:([\w]+)\+?#', function ($match) use ($conditions) {
			if (isset($conditions[$match[1]])) {
				return '(?P<' . $match[1] . '>' . $conditions[$match[1]] . ')';
			}
			if (substr($match[0], -1) === '+') {
				return '(?P<' . $match[1] . '>.+)';
			}
			return '(?P<' . $match[1] . '>[^/]+)';
		}, str_replace(')', ')?', (string) $path)) . '$#u';
	}

	/**
	 * Retrieve the currently set canvas.
	 *
	 * @return string
	 */
	public function getCanvas ($skipCheck = false): ?string
	{
		if (($skipCheck === false) && ($this->canvas === null)) {
			try {
				$this->except(self::E_CANVAS_NOT_SET);
			}
			catch (\Throwable $e) {
				$this->catch($e);
			}
		}
		return $this->canvas;
	}

	/**
	 * Get information about the current url.
	 *
	 * @param mixed $part The part you want returned. (Optional).
	 * @return mixed If no $part passed in, and array of available parts is returned.
	 *               If a valid part is part is passed in, that part is returned as a string.
	 *               If part was not found, null will be returned.
	 */
	public function page ($part = null)
	{
		if ($part !== null) {
			if (isset($this->url[$part])) {
				return $this->url[$part];
			}
			return null;
		}
		return $this->url;
	}

	/**
	 * Dispatch the router.
	 *
	 * @return void
	 */
	public function dispatch (): void
	{
		try {
			$routeFound = false;
			$methodMatch = false;

			foreach ($this->routes as $path => $index) {

				// Check if the current page matches the route.
				if (preg_match($path, $this->urlString, $matches)) {
					$routeFound = true;

					foreach ($matches as $key => $url) {
						if (!is_int($key)) {
							$this->url[$key] = $url;
						}
					}

					$route = end($index);
					if ($this->requestMethod & $route['requestMethod']) {
						if ($route['callback']() === false) {
							$routeFound = false;
							continue;
						}
						$methodMatch = true;
						break;
					}
					elseif (count($this->routes[$path]) > 1) {
						array_pop($this->routes[$path]);
						$this->dispatch();
						return;
					}
				}
			}

			if (is_callable($this->preRender)) {
				($this->preRender)();
			}

			if ($routeFound === false) {
				$this->except(self::E_ROUTES_EXHAUSTED);
			}
			if ($methodMatch === false) {
				$this->except(self::E_METHOD_NOT_FOUND);
			}

			$this->vars['path'] = $path;
			if ($this->parseCanvas === true) {

				if ($this->template !== null) {
					$resolved = self::getTemplatePath($this->template);
					if ($resolved === null) {
						$this->except(self::E_TEMPLATE_NOT_FOUND);
					}
					$this->template = $resolved;

					ob_start();
					if (!empty($this->templateargs)) {
						$templateResult = inc($this->template, ...$this->templateargs);
					}
					else {
						$templateResult = include $this->template;
					}
					$content = ob_get_contents();
					ob_end_clean();
				}
			}

			$recuriveCanvasHolder = $this->canvas;
			$this->canvas = self::getCanvasPath($this->canvas);
			if ($this->canvas === null) {
				$this->except(self::E_CANVAS_NOT_FOUND);
			}

			$path = $this->vars['path'];

			$resultHandler = function ($template, $result) use ($path, $recuriveCanvasHolder) {
				if (count($this->routes) > 0) {
					$ctype = null;
					$headers = headers_list();
					foreach ($headers as $header) {
						if (substr($header, 0, 14) === 'Content-type: ') {
							$ctype = substr($header, 14);
							$pos = strpos($ctype, ';');
							if ($pos !== false) {
								$ctype = substr($ctype, 0, $pos);
							}
						}
					}
					$this->tried[] = [
						'route' => $path,
						'content-type' => $ctype,
						'canvas' => $this->canvas,
						'template' => $template,
						'returnValue' => $result
					];
					$this->canvas = $recuriveCanvasHolder;
					array_pop($this->routes[$path]);
					unset($this->routes[$path]);
					$this->dispatch();
					return;
				}
				else {
					$this->except(self::E_ROUTES_EXHAUSTED);
				}
			};

			if ($this->parseCanvas === true) {
				$canvasResult = Canvas::_set($this->canvas);
				if ($canvasResult === true) {
					if ($this->template !== null) {
						if ($templateResult !== true) {
							$resultHandler($this->template, $templateResult);
						}
						echo $content;
					}
				}
				else {
					$this->except(self::E_CANVAS_RETURN, [
						'returnValue' => $canvasResult
					]);
				}
				Canvas::_create();
				return;
			}
			ob_start();
			$canvasResult = include $this->canvas;
			$content = ob_get_contents();
			ob_end_clean();
			if ($canvasResult !== true) {
				$resultHandler(null, $canvasResult);
			}
			echo $content;
		}
		catch (\Throwable $e) {
			$this->catch($e);
		}
	}

	/**
	 * If a template returns an error code, define a special fallback for it.
	 *
	 * @var int $code The return value to match.
	 * @var callable $callback The callback to run.
	 *
	 * @return void
	 */
	public function fallback (callable|int $code, ?callable $callback = null): void
	{
		if (is_int($code)) {
			$this->fallback[$code] = $callback;
		}
		else {
			$this->fallback['*'] = $code;
		}
	}

	protected function catch ($e)
	{
		$this->e = $e;

		$contentType = null;
		foreach (headers_list() as $header) {
			if (substr($header, 0, 14) === 'Content-type: ') {
				$pos = strpos($header, ';');
				if ($pos !== false) {
					$contentType = substr($header, 14, $pos - 14);
				}
				else {
					$contentType = substr($header, 14);
				}
				break;
			}
		}
		if ($contentType === null) {
			$contentType = 'text/html';
		}

		$error = 500;
		if (method_exists($e, 'get')) {
			$tried = $e->get('tried');
			if ($tried !== null) {
				foreach ($tried as $trial) {
					$returnValue = $trial['returnValue'];
					if ((is_array($returnValue)) && (isset($returnValue['status']))) {
						$returnValue = $returnValue['status'];
					}
					if ((isset($this->fallback[$returnValue])) || (isset($this->fallback['*']))) {
						if (isset($this->fallback[$returnValue])) {
							$result = $this->fallback[$returnValue]($contentType, $trial);
						}
						else {
							$result = $this->fallback['*']($contentType, $trial, $returnValue);
						}
						if ($result === true) {
							sp($e);
							return true;
						}
					}
				}
			}

			if (($e->getCode() === self::E_ROUTES_EXHAUSTED) || ($e->getCode() === self::E_ROUTE_NOT_FOUND)) {
				$url = $e->get('url');
				if ((filter_var($url, FILTER_VALIDATE_DIRNAME)) && (substr($url, 0, 7) === 'module/') && (strpos($url, '../') === false)) {
					$url = substr($url, 7);
					$pos = strpos($url, '/');
					if ($pos !== false) {
						$module = substr($url, 0, $pos);
						$url = ltrim(substr($url, $pos), '/');

						$path = null;
						if (($module === 'local') && ($url !== false) && (is_readable(MAIN_SITE_DIR . SITE_ID . '/' . '/public/' . $url))) {
							$path = MAIN_SITE_DIR . SITE_ID . '/' . '/public/';
						}
						elseif (($url !== false) && (is_readable(SITE_DIR . 'module/' . $module . '/public/' . $url))) {
							$path = SITE_DIR . 'module/' . $module . '/public/';
						}
						if ($path !== null) {
							$mime = get_mimetype($path . $url);
							/*
							Sometimes the system reports wrong mime type.
							When serving files for a website from a public folder,
							we need this little probability override to not cause problems with common mime types.
							*/
							if (substr($url, -3, 3) === '.js') {
								$mime['mime'] = 'application/javascript';
							}
							elseif (substr($url, -4, 4) === '.css') {
								$mime['mime'] = 'text/css';
							}
							elseif ($mime['mime'] === 'image/svg') {
								$mime['mime'] = 'image/svg+xml';
							}
							header('Content-Type: ' . $mime['mime']);
							readfile($path . ltrim($url, '/'));
							return true;
						}
					}
				}

				$error = 404;
			}
		}
		sp($e);

		if ((isset($this->fallback[$error])) || (isset($this->fallback['*']))) {
			while (ob_get_length() !== false) {
				ob_end_clean();
			}
			ob_start();
			if (isset($this->fallback[$error])) {
				$result = $this->fallback[$error]($contentType, []);
			}
			else {
				$result = $this->fallback['*']($contentType, [], $error);
			}
			if ($result === true) {
				Canvas::_create();
				return true;
			}
		}

		if ($contentType === 'text/html') {
			Canvas::_override(self::getCanvasPath('html'));
		}
		else {
			Canvas::_override(self::getCanvasPath('json'));
		}
		inc(self::getTemplatePath('error/' . $error), $e);
		Canvas::_create();
	}

	/**
	 * The router encountered an error, and should throw an exception.
	 *
	 * @throws Exception
	 * @param int $type
	 * @param array $params
	 * @return void
	 */
	private function except (int $type, array $params = []): void
	{
		if (($type === self::E_ROUTES_EXHAUSTED) && (empty($this->tried))) {
			$type = self::E_ROUTE_NOT_FOUND;
		}
		switch ($type) {
			case self::E_ROUTE_NOT_FOUND:
				$e = new Exception('Route not found.', $type);
				break;
			case self::E_METHOD_NOT_FOUND:
				$e = new Exception('Method not found.', $type);
				break;
			case self::E_CANVAS_NOT_FOUND:
				$e = new Exception('Canvas not found.', $type);
				break;
			case self::E_TEMPLATE_NOT_FOUND:
				$e = new Exception('Template not found.', $type);
				$e->set('template', $this->template);
				break;
			case self::E_CANVAS_RETURN:
				$e = new Exception('Invalid canvas return value.', $type);
				break;
			case self::E_TEMPLATE_RETURN:
				$e = new Exception('Invalid template return value.', $type);
				break;
			case self::E_ROUTES_EXHAUSTED:
				$e = new Exception('Routes exhausted.', $type);
				break;
			case self::E_CANVAS_NOT_SET:
				$e = new Exception('No canvas set.', $type);
				break;
			default:
				$e = new Exception('Unknown.', self::E_UNKNOWN);
		}
		$e->set('url', $this->urlString);
		$e->set('page', \gimle\page());
		$headers = headers_list();
		$ctype = null;
		foreach ($headers as $header) {
			if (substr($header, 0, 14) === 'Content-type: ') {
				$ctype = substr($header, 14);
				$pos = strpos($ctype, ';');
				if ($pos !== false) {
					$ctype = substr($ctype, 0, $pos);
				}
			}
		}
		$e->set('content-type', $ctype);
		$e->set('GET', $_GET);
		$e->set('requestMethod', $_SERVER['REQUEST_METHOD']);
		$e->set('tried', $this->tried);
		$e->set('canvas', $this->canvas);
		$e->set('template', $this->template);
		foreach ($params as $key => $value) {
			$e->set($key, $value);
		}
		throw $e;
	}
}
