<?php

use App\Core\Router;

require_once __DIR__ . '/../app/core/Config.php';

$router = new Router();

require_once __DIR__ . '/../app/routes/Web.php';

try { $router->load(); }
catch (Throwable $error) { App\Core\Responses::database(500,'No se pudo completar la solicitud',[])->json(); }
