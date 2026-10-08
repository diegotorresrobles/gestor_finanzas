<?php
namespace App\Controllers;
use App\Services\AccountService;
use App\Core\Responses;
use App\Core\FinanceException;
use App\Core\Session;
class AccountController {
  public static function run(callable $action): void {
    try { Responses::ok($action())->json(); }
    catch (FinanceException $e) { (new Responses($e->getCode(),$e->getMessage(),['errors'=>$e->errors]))->json(); }
    catch (\Throwable $e) { (new Responses(500,'No se pudo completar la operación',[]))->json(); }
  }
  public static function get(array $r) { self::run(fn()=>AccountService::profile($r['auth']['user_id'])); }
  public static function password(array $r) { self::run(function() use($r) { AccountService::changePassword($r['auth']['user_id'],$r); Session::clear(); return ['message'=>'Contraseña actualizada. Inicia sesión de nuevo.']; }); }
  public static function email(array $r) { self::run(function() use($r) { AccountService::requestEmail($r['auth']['user_id'],$r); return ['message'=>'Revisa tu correo actual para autorizar el cambio.']; }); }
  public static function confirm(array $r) { self::run(function() use($r) { Session::origin(); return ['message'=>AccountService::confirmEmail($r['token']??null)]; }); }
}
