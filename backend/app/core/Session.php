<?php
namespace App\Core;
use App\Services\JwtService;
use PDO;
class Session {
  public const ACCESS='dtr_access';
  public const REFRESH='dtr_refresh';
  public static function origin(): void {
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    $expected=rtrim($_ENV['APP_URL']??'http://localhost:5173','/');
    if ($origin!==$expected) throw new FinanceException('Origen no autorizado',403);
  }
  private static function cookie(string $name, string $value, int $expires): void {
    setcookie($name,$value,['expires'=>$expires,'path'=>'/api','httponly'=>true,'samesite'=>'Strict','secure'=>($_ENV['APP_ENV']??'development')!=='development' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')]);
  }
  public static function access(array $user, string $sid): void {
    self::cookie(self::ACCESS,(new JwtService($user['id']))->generate($user['rol'],$sid),time()+900);
  }
  public static function create(array $user): void {
    $db=Database::connect(); $sid=bin2hex(random_bytes(32)); $secret=bin2hex(random_bytes(32));
    $db->exec('DELETE FROM auth_sessions WHERE expires_at<NOW()');
    $stmt=$db->prepare('INSERT INTO auth_sessions(id,user_id,refresh_hash,expires_at,last_seen) VALUES(:id,:user,:hash,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())');
    $stmt->execute(['id'=>$sid,'user'=>$user['id'],'hash'=>hash('sha256',$secret)]);
    self::access($user,$sid); self::cookie(self::REFRESH,"$sid.$secret",time()+2592000);
  }
  public static function user(): array|false {
    $payload=JwtService::validate($_COOKIE[self::ACCESS]??'');
    if (!$payload || ($payload['iss']??null)!==$_ENV['APP_DOMAIN'] || !isset($payload['sid'],$payload['sub'],$payload['exp']) || !is_numeric($payload['exp']) || $payload['exp']<=time() || !is_string($payload['sid']) || !preg_match('/^[a-f0-9]{64}$/',$payload['sid']) || filter_var($payload['sub'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])===false) return false;
    $db=Database::connect();
    $stmt=$db->prepare("SELECT u.id,u.nombre,u.apellido,u.correo,u.telefono,u.username,u.rol,s.id AS session_id FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.id=:sid AND u.id=:id AND u.confirmado='1' AND s.expires_at>NOW()");
    $stmt->execute(['sid'=>$payload['sid'],'id'=>$payload['sub']]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) { $stmt=$db->prepare('UPDATE auth_sessions SET last_seen=NOW() WHERE id=:id AND last_seen<DATE_SUB(NOW(),INTERVAL 60 SECOND)'); $stmt->execute(['id'=>$user['session_id']]); }
    return $user;
  }
  public static function refresh(): void {
    self::origin();
    $parts=explode('.',$_COOKIE[self::REFRESH]??'');
    if (count($parts)!==2 || !preg_match('/^[a-f0-9]{64}$/',$parts[0]) || !preg_match('/^[a-f0-9]{64}$/',$parts[1])) throw new FinanceException('La sesión expiró',401);
    [$sid,$secret]=$parts; $db=Database::connect(); $db->beginTransaction();
    try {
      $stmt=$db->prepare("SELECT s.*,u.rol,u.confirmado,(s.previous_until>=NOW()) AS previous_valid,UNIX_TIMESTAMP(s.expires_at) AS expires_timestamp FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.id=:id AND s.expires_at>NOW() FOR UPDATE");
      $stmt->execute(['id'=>$sid]); $row=$stmt->fetch(PDO::FETCH_ASSOC); $hash=hash('sha256',$secret);
      if (!$row || (int)$row['confirmado']!==1) throw new FinanceException('La sesión expiró',401);
      $current=hash_equals($row['refresh_hash'],$hash);
      $previous=$row['previous_hash'] && hash_equals($row['previous_hash'],$hash) && (bool)$row['previous_valid'];
      if (!$current && !$previous) throw new FinanceException('La sesión expiró',401);
      if ($current) {
        $new=bin2hex(random_bytes(32));
        $stmt=$db->prepare('UPDATE auth_sessions SET previous_hash=refresh_hash,previous_until=DATE_ADD(NOW(),INTERVAL 10 SECOND),refresh_hash=:hash,last_seen=NOW() WHERE id=:id');
        $stmt->execute(['hash'=>hash('sha256',$new),'id'=>$sid]);
      }
      $db->commit();
      if ($current) self::cookie(self::REFRESH,"$sid.$new",(int)$row['expires_timestamp']);
      self::access(['id'=>$row['user_id'],'rol'=>$row['rol']],$sid);
    } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  }
  public static function logout(): void {
    self::origin(); $sid=explode('.',$_COOKIE[self::REFRESH]??'')[0];
    $parts=explode('.',$_COOKIE[self::REFRESH]??'');
    if (count($parts)===2) {
      $stmt=Database::connect()->prepare('DELETE FROM auth_sessions WHERE id=:id AND (refresh_hash=:hash OR previous_hash=:previous)');
      $hash=hash('sha256',$parts[1]); $stmt->execute(['id'=>$sid,'hash'=>$hash,'previous'=>$hash]);
    }
    self::clear();
  }
  public static function clear(): void { self::cookie(self::ACCESS,'',time()-3600); self::cookie(self::REFRESH,'',time()-3600); }
}
