<?php
namespace App\Controllers;
use App\Core\Database;
use App\Core\AppSettings;
class AdminController {
  public static function metrics(array $r) { AccountController::run(function() {
    $db=Database::connect();
    // Administrative queries return counts of IDs only, never user rows or financial data.
    return ['users'=>(int)$db->query("SELECT COUNT(id) FROM users WHERE rol='user'")->fetchColumn(),'active_users'=>(int)$db->query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN auth_sessions s ON s.user_id=u.id WHERE u.rol='user' AND s.expires_at>NOW() AND s.last_seen>DATE_SUB(NOW(),INTERVAL 15 MINUTE)")->fetchColumn(),'active_window_minutes'=>15];
  }); }
  public static function settings(array $r) { AccountController::run(fn()=>AppSettings::get()); }
  public static function save(array $r) { AccountController::run(function() use($r) { AppSettings::save($r['settings']??[]); return ['message'=>'Configuración guardada. Los servicios WS necesitan reiniciarse si cambias su configuración.']; }); }
  public static function publicConfig(array $r) { AccountController::run(fn()=>['name'=>$_ENV['APP_NAME']??'Gestor de finanzas','logo'=>$_ENV['APP_LOGO']??'','ws_url'=>$_ENV['WS_URL']??'ws://localhost:3002']); }
}
