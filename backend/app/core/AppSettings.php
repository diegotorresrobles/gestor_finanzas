<?php
namespace App\Core;
class AppSettings {
  public const KEYS=['APP_NAME','APP_URL','APP_DOMAIN','APP_LOGO','JWT_KEY','DB_HOST','DB_NAME','DB_USER','DB_PASS','MAIL_HOST','MAIL_PORT','MAIL_USER','MAIL_PASS','MAIL_FROM','MAIL_ENCRYPTION','WS_URL'];
  public const SECRETS=['JWT_KEY','DB_PASS','MAIL_PASS'];
  public static function get(): array {
    $result=[];
    foreach (self::KEYS as $key) $result[$key]=in_array($key,self::SECRETS,true) ? ['configured'=>!empty($_ENV[$key])] : ($_ENV[$key]??'');
    return $result;
  }
  public static function save(array $values): void {
    if (!$values || array_diff(array_keys($values),self::KEYS)) throw new FinanceException('Configuración inválida',409);
    foreach ($values as $key=>$value) {
      if (!is_string($value) || strlen($value)>2048 || preg_match('/[\r\n\x00]/',$value)) throw new FinanceException('Valor inválido para '.$key,409);
      if (in_array($key,self::SECRETS,true) && $value==='') { unset($values[$key]); continue; }
      if ($key==='JWT_KEY' && strlen($value)<32) throw new FinanceException('JWT_SECRET debe tener al menos 32 caracteres',409);
      if (in_array($key,['APP_URL','WS_URL'],true) && !($key==='WS_URL' && $value==='') && !preg_match($key==='WS_URL' ? '~^wss?://[^\s]+$~' : '~^https?://[^\s]+$~',$value)) throw new FinanceException('URL inválida',409);
      if ($key==='APP_LOGO' && $value!=='' && !preg_match('~^https?://[^\s]+$|^/[a-zA-Z0-9/_.-]+$~',$value)) throw new FinanceException('El logo debe ser una URL http(s) o una ruta local',409);
      if ($key==='MAIL_PORT' && (filter_var($value,FILTER_VALIDATE_INT)===false || (int)$value<1 || (int)$value>65535)) throw new FinanceException('Puerto SMTP inválido',409);
      if ($key==='MAIL_FROM' && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new FinanceException('Remitente inválido',409);
      if ($key==='MAIL_ENCRYPTION' && !in_array($value,['','tls','ssl'],true)) throw new FinanceException('Usa tls o ssl',409);
      if (in_array($key,['APP_NAME','APP_URL','APP_DOMAIN','DB_HOST','DB_NAME','DB_USER','MAIL_HOST'],true) && trim($value)==='') throw new FinanceException('Falta '.$key,409);
    }
    if (array_intersect(array_keys($values),['DB_HOST','DB_NAME','DB_USER','DB_PASS'])) {
      $candidate=array_replace($_ENV,$values);
      try { $test=new \PDO('mysql:host='.$candidate['DB_HOST'].';dbname='.$candidate['DB_NAME'].';charset=utf8mb4',$candidate['DB_USER'],$candidate['DB_PASS'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_TIMEOUT=>5]); $test->query('SELECT 1'); }
      catch (\Throwable $e) { throw new FinanceException('No se pudo conectar con la configuración de base de datos indicada. No se guardaron cambios.',409); }
    }
    $path=dirname(__DIR__,2).'/.env'; $lock=fopen($path.'.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new FinanceException('No se pudo bloquear la configuración',503);
    try {
      $content=file_get_contents($path);
      foreach ($values as $key=>$value) {
        $line=$key.'="'.str_replace(['\\','"','$'],['\\\\','\\"','\\$'],$value).'"';
        $pattern='/^'.preg_quote($key,'/').'\s*=.*$/m';
        $content=preg_match($pattern,$content) ? preg_replace_callback($pattern,fn()=>$line,$content) : rtrim($content)."\n".$line."\n";
      }
      try { \Dotenv\Dotenv::parse($content); } catch (\Throwable $e) { throw new FinanceException('La configuración tiene un formato inválido. No se guardaron cambios.',409); }
      $tmp=$path.'.tmp.'.bin2hex(random_bytes(6));
      $previousMask=umask(0077);
      try { $written=file_put_contents($tmp,$content); } finally { umask($previousMask); }
      if ($written===false || !rename($tmp,$path)) throw new FinanceException('No se pudo guardar la configuración',503);
      if (isset($values['JWT_KEY'])) Database::connect()->exec('DELETE FROM auth_sessions');
    } finally { flock($lock,LOCK_UN); fclose($lock); }
  }
}
