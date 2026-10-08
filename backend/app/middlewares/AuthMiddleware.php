<?php
namespace App\Middlewares;
use App\Core\Responses;
use App\Core\Session;
use App\Core\FinanceException;
class AuthMiddleware {
  public static function handle(array $request, callable $next) {
    if (!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)) {
      try { Session::origin(); } catch (FinanceException $e) { (new Responses(403,$e->getMessage(),[]))->json(); }
    }
    $user=Session::user();
    if (!$user) Responses::unauthorized(['errors'=>['token'=>'La sesión es inválida o ha expirado']])->json();
    $request['auth']=['user_id'=>(int)$user['id'],'user'=>$user];
    return $next($request);
  }
}
