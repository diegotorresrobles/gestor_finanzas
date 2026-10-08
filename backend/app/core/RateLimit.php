<?php
namespace App\Core;
class RateLimit {
  public static function check(string $scope, int $max=12): void {
    $key=hash('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'cli'));
    $db=Database::connect();
    $db->exec('DELETE FROM auth_attempts WHERE window_start<DATE_SUB(NOW(),INTERVAL 1 DAY)');
    $stmt=$db->prepare('INSERT INTO auth_attempts(key_hash,attempts,window_start) VALUES(:key,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_start<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),window_start=IF(window_start<DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),window_start)');
    $stmt->execute(['key'=>$key]);
    $stmt=$db->prepare('SELECT attempts FROM auth_attempts WHERE key_hash=:key'); $stmt->execute(['key'=>$key]);
    if ((int)$stmt->fetchColumn()>$max) throw new FinanceException('Demasiados intentos. Intenta de nuevo en 15 minutos.',429);
  }
}
