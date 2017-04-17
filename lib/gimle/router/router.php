<?php
declare(strict_types=1);
namespace gimle\router;

use const gimle\SITE_DIR;
use const gimle\BASE_PATH_KEY;
use const gimle\GIMLE5;
use const gimle\ENV_MODE;
use const gimle\ENV_LIVE;

use gimle\canvas\Canvas;
use gimle\System;
use gimle\canvas\Exception as CanvasException;
use gimle\template\Exception as TemplateException;

class Router
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

	public function __construct ()
	{
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

		if ((ENV_MODE | ENV_LIVE) !== ENV_LIVE) {
			$this->bind('*', '__gimle/:id', function () {
				$this->setCanvas('js');
				$this->setTemplate('spectacle');
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
	public function setTemplate (string $name): void
	{
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
		assert(($requestMethod >= self::R_GET) && ($requestMethod <= ((self::R_PURGE * 2) - 1)));
		if (!is_array($conditions)) {
			$requestMethod = $conditions;
			$conditions = [];
		}
		$path = $this->bindToRegex($path, $conditions);

		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {

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
	public function getCanvas (): string
	{
		if ($this->canvas === null) {
			throw new Exception('No canvas set.', self::E_CANVAS_NOT_SET);
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
	function page ($part = null)
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
		$routeFound = false;
		$methodMatch = false;

		$recuriveCanvasHolder = $this->canvas;

		foreach ($this->routes as $path => $index) {
			$route = end($index);

			// Check if the current page matches the route.
			if (preg_match($path, $this->urlString, $matches)) {
				$routeFound = true;

				foreach ($matches as $key => $url) {
					if (!is_int($key)) {
						$this->url[$key] = $url;
					}
				}

				if ($this->requestMethod & $route['requestMethod']) {
					$route['callback']();
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

		if ($routeFound === false) {
			throw new Exception('Route not found.', self::E_ROUTE_NOT_FOUND);
		}
		if ($methodMatch === false) {
			throw new Exception('Method not found.', self::E_METHOD_NOT_FOUND);
		}

		if ($this->parseCanvas === true) {

			if ($this->template !== null) {
				$found = false;
				if (is_readable(SITE_DIR . 'template/' . $this->template . '.php')) {
					$this->template = SITE_DIR . 'template/' . $this->template . '.php';
					$found = true;
				}
				else {
					foreach (System::getModules() as $module) {
						if (is_readable(SITE_DIR . 'module/' . $module . '/template/' . $this->template . '.php')) {
							$this->template = SITE_DIR . 'module/' . $module . '/template/' . $this->template . '.php';
							$found = true;
						}
					}
				}
				if ($found === false) {
					throw new Exception('Template "' . $this->template . '" not found.', self::E_TEMPLATE_NOT_FOUND);
				}

				ob_start();
				$templateResult = include $this->template;
				$content = ob_get_contents();
				ob_end_clean();
			}
		}

		$found = false;
		if (is_readable(SITE_DIR . 'canvas/' . $this->canvas . '.php')) {
			$this->canvas = SITE_DIR . 'canvas/' . $this->canvas . '.php';
			$found = true;
		}
		else {
			foreach (System::getModules() as $module) {
				if (is_readable(SITE_DIR . 'module/' . $module . '/canvas/' . $this->canvas . '.php')) {
					$this->canvas = SITE_DIR . 'module/' . $module . '/canvas/' . $this->canvas . '.php';
					$found = true;
				}
			}
		}
		if ($found === false) {
			throw new Exception('Canvas "' . $this->canvas . '" not found.', self::E_CANVAS_NOT_FOUND);
		}

		if ($this->parseCanvas === true) {
			$canvasResult = Canvas::_set($this->canvas);
			if ((is_array($canvasResult)) && (count($canvasResult) === 2) && (is_string($canvasResult[0])) && (is_int($canvasResult[1]))) {
				throw new CanvasException(...$canvasResult);
			}
			if ($canvasResult === true) {
				if ($this->template !== null) {

					if ((is_array($templateResult)) && (count($templateResult) === 2) && (is_string($templateResult[0])) && (is_int($templateResult[1]))) {
						throw new TemplateException(...$templateResult);
					}
					if ($templateResult === false) {
						if (count($this->routes[$path]) > 1) {
							$this->canvas = $recuriveCanvasHolder;
							array_pop($this->routes[$path]);
							$this->dispatch();
							return;
						}
						else {
							throw new Exception('Routes exhausted.', self::E_ROUTES_EXHAUSTED);
						}
					}
					if ($templateResult !== true) {
						throw new Exception('Invalid template return value.', self::E_TEMPLATE_RETURN);
					}

					echo $content;
				}
			}
			else {
				throw new Exception('Invalid canvas return value.', self::E_CANVAS_RETURN);
			}
			Canvas::_create();
			return;
		}
		include $this->canvas;
	}
}
