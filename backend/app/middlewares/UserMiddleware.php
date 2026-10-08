<?php
namespace App\Middlewares;
use App\Core\Responses;
class UserMiddleware {
  public static function handle(array $request, callable $next) {
    if (($request['auth']['user']['rol']??'')!=='user') (new Responses(403,'El administrador no tiene acceso a datos financieros',[]))->json();
    return $next($request);
  }
}
