<?php

namespace App\Core;

use App\Middlewares\Middleware;

class Router
{
  protected array $routes = [];

  public function get(
    string $path,
    array $handler,
    array $middlewares = []
  ) {
    $this->routes['GET'][$path] = [
      'handler' => $handler,
      'middlewares' => $middlewares
    ];
  }

  public function post(
    string $path,
    array $handler,
    array $middlewares = []
  ) {
    $this->routes['POST'][$path] = [
      'handler' => $handler,
      'middlewares' => $middlewares
    ];
  }

  public function delete(string $path, array $handler, array $middlewares = []) {
    $this->routes['DELETE'][$path] = [
      'handler' => $handler,
      'middlewares' => $middlewares,
    ];
  }

  public function put(string $path, array $handler, array $middlewares = []) {
    $this->routes['PUT'][$path] = ['handler' => $handler, 'middlewares' => $middlewares];
  }

  public function load()
  {
      $httpMethod = $_SERVER['REQUEST_METHOD'];
      $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

      $request = Request::all();

      $route = $this->routes[$httpMethod][$uri] ?? null;
      $params = [];
      if (!$route) {
        $segments = explode('/', trim($uri, '/'));
        foreach ($this->routes[$httpMethod] ?? [] as $path => $candidate) {
          $pattern = explode('/', trim($path, '/'));
          if (count($pattern) !== count($segments)) continue;
          $matches = [];
          foreach ($pattern as $index => $segment) {
            if (preg_match('/^\{([a-z_]+)\}$/', $segment, $name)) $matches[$name[1]] = $segments[$index];
            elseif ($segment !== $segments[$index]) continue 2;
          }
          $route = $candidate;
          $params = $matches;
          break;
        }
      }
      $request['params'] = $params;

      if (!$route) {
          header('HTTP/1.1 404 Not Found');
          exit;
      }

      [$controller, $controllerMethod] = $route['handler'];

      if (!method_exists($controller, $controllerMethod)) {
          header('HTTP/1.1 500 Internal Server Error');
          exit;
      }

      $middlewares = $route['middlewares'];

      $controllerHandler = function ($request) use (
          $controller,
          $controllerMethod
      ) {
          return $controller::$controllerMethod($request);
      };

      $pipeline = new Middleware();

      return $pipeline->handle(
          $controllerHandler,
          $request,
          $middlewares
      );
  }
}
