<?php
namespace App\Middlewares;
use App\Core\Responses;
class AdminMiddleware {
  public static function handle(array $request, callable $next) {
    if (($request['auth']['user']['rol']??'')!=='admin' || ($request['auth']['user']['correo']??'')!=='admin@admin.com') (new Responses(403,'Acceso administrativo requerido',[]))->json();
    return $next($request);
  }
}
