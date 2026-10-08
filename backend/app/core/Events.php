<?php
namespace App\Core;
use PDO;
class Events {
  public static function changed(PDO $db, int $userId): void {
    $stmt=$db->prepare('INSERT INTO user_revisions(user_id,revision) VALUES(:id,1) ON DUPLICATE KEY UPDATE revision=revision+1');
    $stmt->execute(['id'=>$userId]);
  }
}
