<?php

require __DIR__ . '/../vendor/autoload.php';

class RouteTestController {
  public static function find(array $request) { return ['action' => 'find', 'request' => $request]; }
  public static function update(array $request) { return ['action' => 'update', 'request' => $request]; }
  public static function catalog(array $request) { return ['action' => 'catalog', 'request' => $request]; }
}
$router = new App\Core\Router();
$router->get('/api/cuentas/{id}', [RouteTestController::class, 'find']);
$router->get('/api/cuentas/tipos', [RouteTestController::class, 'catalog']);
$router->put('/api/cuentas/{id}', [RouteTestController::class, 'update']);
$router->get('/api/transacciones/{id}', [RouteTestController::class, 'find']);
$router->put('/api/transacciones/{id}', [RouteTestController::class, 'update']);
foreach ([['GET', '/api/cuentas/tipos', 'catalog', null], ['GET', '/api/cuentas/42', 'find', '42'], ['PUT', '/api/cuentas/42', 'update', '42'], ['GET', '/api/transacciones/73?test=1', 'find', '73'], ['PUT', '/api/transacciones/73', 'update', '73']] as [$method, $uri, $action, $id]) {
  $_SERVER['REQUEST_METHOD'] = $method;
  $_SERVER['REQUEST_URI'] = $uri;
  $_POST = ['params' => ['id' => '999']];
  $result = $router->load();
  if ($result['action'] !== $action || ($result['request']['params']['id'] ?? null) !== $id) throw new RuntimeException('Route matching or parameter precedence failed.');
}
echo "5 parameterized route checks passed.\n";
