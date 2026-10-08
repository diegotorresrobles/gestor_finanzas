<?php

use App\Controllers\AuthController;
use App\Controllers\UsersController;
use App\Controllers\CuentasController;
use App\Controllers\TransaccionesController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\UserMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\RealtimeController;

// No public user listing: only /api/account exposes the authenticated user's own profile.
$router->post('/api/users', [UsersController::class, 'register']);
$router->post('/api/users/verify', [UsersController::class, 'verify']);

$router->post('/api/auth/login', [AuthController::class, 'login']);

$router->get('/api/cuentas', [CuentasController::class, 'get'], [AuthMiddleware::class, UserMiddleware::class]);
$router->post('/api/cuentas', [CuentasController::class, 'create'], [AuthMiddleware::class, UserMiddleware::class]);
$router->delete('/api/cuentas', [CuentasController::class, 'delete'], [AuthMiddleware::class, UserMiddleware::class]);
$router->get('/api/cuentas/tipos', [CuentasController::class, 'tipos'], [AuthMiddleware::class, UserMiddleware::class]);
$router->get('/api/cuentas/{id}', [CuentasController::class, 'find'], [AuthMiddleware::class, UserMiddleware::class]);
$router->put('/api/cuentas/{id}', [CuentasController::class, 'update'], [AuthMiddleware::class, UserMiddleware::class]);

$router->get('/api/transacciones', [TransaccionesController::class, 'get'], [AuthMiddleware::class, UserMiddleware::class]);
$router->post('/api/transacciones', [TransaccionesController::class, 'create'], [AuthMiddleware::class, UserMiddleware::class]);
$router->get('/api/transacciones/categorias', [TransaccionesController::class, 'categorias'], [AuthMiddleware::class, UserMiddleware::class]);
$router->get('/api/transacciones/recientes', [TransaccionesController::class, 'recent'], [AuthMiddleware::class, UserMiddleware::class]);
$router->get('/api/transacciones/{id}', [TransaccionesController::class, 'find'], [AuthMiddleware::class, UserMiddleware::class]);
$router->put('/api/transacciones/{id}', [TransaccionesController::class, 'update'], [AuthMiddleware::class, UserMiddleware::class]);

$router->delete('/api/transacciones/{id}', [TransaccionesController::class,'delete'], [AuthMiddleware::class,UserMiddleware::class]);
$router->get('/api/auth/me',[AuthController::class,'me'],[AuthMiddleware::class]);
$router->post('/api/auth/refresh',[AuthController::class,'refresh']);
$router->post('/api/auth/logout',[AuthController::class,'logout']);
$router->get('/api/config',[AdminController::class,'publicConfig']);
$router->get('/api/account',[AccountController::class,'get'],[AuthMiddleware::class]);
$router->post('/api/account/password',[AccountController::class,'password'],[AuthMiddleware::class]);
$router->post('/api/account/email',[AccountController::class,'email'],[AuthMiddleware::class]);
$router->post('/api/account/email/confirm',[AccountController::class,'confirm']);
$router->get('/api/admin/metrics',[AdminController::class,'metrics'],[AuthMiddleware::class,AdminMiddleware::class]);
$router->get('/api/admin/settings',[AdminController::class,'settings'],[AuthMiddleware::class,AdminMiddleware::class]);
$router->put('/api/admin/settings',[AdminController::class,'save'],[AuthMiddleware::class,AdminMiddleware::class]);
$router->post('/api/realtime/ticket',[RealtimeController::class,'ticket'],[AuthMiddleware::class]);
$router->get('/api/realtime/revision',[RealtimeController::class,'revision'],[AuthMiddleware::class]);
