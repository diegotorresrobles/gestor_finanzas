<?php
namespace App\Controllers;
use App\Core\Session;
use App\Core\Responses;
use App\Core\FinanceException;
use App\DTO\AuthLoginDTO;
use App\Services\AuthService;
class AuthController {
  public static function login(array $request) {
    try { (new AuthService())->login(AuthLoginDTO::fromArray($request))->json(); }
    catch (FinanceException $e) { (new Responses($e->getCode(),$e->getMessage(),['errors'=>$e->errors]))->json(); }
    catch (\Throwable $e) { (new Responses(500,'No se pudo iniciar sesión',[]))->json(); }
  }
  public static function me(array $r) { Responses::ok(['user'=>['id'=>$r['auth']['user_id'],'nombre'=>$r['auth']['user']['nombre'],'rol'=>$r['auth']['user']['rol']]])->json(); }
  public static function refresh(array $r) { AccountController::run(function() { Session::refresh(); return []; }); }
  public static function logout(array $r) { AccountController::run(function() { Session::logout(); return []; }); }
}
