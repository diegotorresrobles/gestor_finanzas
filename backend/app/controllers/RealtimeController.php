<?php
namespace App\Controllers;
use App\Core\Database;
class RealtimeController {
  public static function revision(array $r) { AccountController::run(function() use($r) {
    $stmt=Database::connect()->prepare('SELECT revision FROM user_revisions WHERE user_id=:id');
    $stmt->execute(['id'=>$r['auth']['user_id']]);
    return ['revision'=>(string)($stmt->fetchColumn() ?: 0)];
  }); }
  public static function ticket(array $r) { AccountController::run(function() use($r) {
    $db=Database::connect(); $db->exec('DELETE FROM ws_tickets WHERE expires_at<NOW()');
    $token=bin2hex(random_bytes(32)); $stmt=$db->prepare('INSERT INTO ws_tickets(token_hash,session_id,expires_at) VALUES(:hash,:sid,DATE_ADD(NOW(),INTERVAL 30 SECOND))');
    $stmt->execute(['hash'=>hash('sha256',$token),'sid'=>$r['auth']['user']['session_id']]);
    return ['ticket'=>$token,'url'=>$_ENV['WS_URL']??'ws://localhost:3002'];
  }); }
}
