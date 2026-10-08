<?php
// Only generated fixtures are read. All database changes roll back; no real email is sent.
namespace App\Core {
  class SecurityPDO extends \PDO {
    private int $depth=0;
    public function beginTransaction(): bool { if ($this->depth===0) parent::beginTransaction(); else $this->exec('SAVEPOINT security_'.$this->depth); $this->depth++; return true; }
    public function commit(): bool { $this->depth--; if ($this->depth===0) parent::commit(); else $this->exec('RELEASE SAVEPOINT security_'.$this->depth); return true; }
    public function rollBack(): bool { $this->depth--; if ($this->depth===0) parent::rollBack(); else $this->exec('ROLLBACK TO SAVEPOINT security_'.$this->depth); return true; }
  }
  class Database { public static SecurityPDO $db; public static function connect() { return self::$db; } }
}
namespace App\Classes {
  class Mail {
    public static array $messages=[]; public static bool $fail=false;
    public static function send(string $email,string $subject,string $body): bool { if (self::$fail) return false; self::$messages[]=['email'=>$email,'body'=>$body]; return true; }
  }
}
namespace {
  require __DIR__.'/../vendor/autoload.php'; \Dotenv\Dotenv::createImmutable(__DIR__.'/..')->load();
  use App\Core\Database; use App\Core\SecurityPDO; use App\Core\Session; use App\Core\FinanceException;
  use App\Services\AccountService; use App\Services\JwtService; use App\Classes\Mail;
  $checks=0;
  function check(bool $value,string $label): void { global $checks; $checks++; if (!$value) throw new \RuntimeException($label); }
  function denied(callable $action,int $status): bool { try { $action(); return false; } catch (FinanceException $e) { return $e->getCode()===$status; } }
  function token(): string { $body=Mail::$messages[array_key_last(Mail::$messages)]['body']; preg_match('/#([a-f0-9]{64})/',$body,$match); return $match[1]; }
  Database::$db=new SecurityPDO('mysql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'].';charset=utf8mb4',$_ENV['DB_USER'],$_ENV['DB_PASS'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
  $db=Database::$db; $db->beginTransaction(); $_SERVER['HTTP_ORIGIN']=rtrim($_ENV['APP_URL'],'/'); $_SERVER['REMOTE_ADDR']='security-fixture-'.bin2hex(random_bytes(8));
  try {
    $email='security-'.bin2hex(random_bytes(12)).'@example.test'; $newEmail='changed-'.bin2hex(random_bytes(12)).'@example.test'; $password='Initial-password-123';
    $stmt=$db->prepare("INSERT INTO users(nombre,apellido,correo,telefono,username,password,confirmado) VALUES('Fixture','Security',:email,'','',:hash,'1')");
    $stmt->execute(['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT)]); $id=(int)$db->lastInsertId();
    $profile=AccountService::profile($id); check($profile['correo']===$email && $profile['rol']==='user' && !isset($profile['password'],$profile['token']),'Profile must contain no secrets.');
    $sid=bin2hex(random_bytes(32)); $secret=bin2hex(random_bytes(32));
    $stmt=$db->prepare('INSERT INTO auth_sessions(id,user_id,refresh_hash,expires_at,last_seen) VALUES(:sid,:id,:hash,DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())');
    $stmt->execute(['sid'=>$sid,'id'=>$id,'hash'=>hash('sha256',$secret)]);
    $_COOKIE[Session::ACCESS]=(new JwtService($id))->generate('admin',$sid);
    check(Session::user()['rol']==='user','JWT role cannot escalate database role.');
    $_COOKIE[Session::ACCESS]=(new JwtService($id+100000))->generate('user',$sid);
    check(Session::user()===false,'Session and JWT subject must match.');
    $_COOKIE[Session::ACCESS]='invalid'; check(Session::user()===false,'Malformed access cookie is denied.');
    $_COOKIE[Session::REFRESH]="$sid.$secret"; Session::refresh();
    $stmt=$db->prepare('SELECT refresh_hash,previous_hash FROM auth_sessions WHERE id=:id'); $stmt->execute(['id'=>$sid]); $row=$stmt->fetch(\PDO::FETCH_ASSOC);
    check($row['refresh_hash']!==hash('sha256',$secret) && $row['previous_hash']===hash('sha256',$secret),'Refresh token rotates and stores hashes only.');
    Session::refresh(); check(true,'Concurrent refresh has a brief grace period.');
    $_COOKIE[Session::REFRESH]=$sid.'.'.str_repeat('0',64); check(denied(fn()=>Session::refresh(),401),'Forged refresh token is denied.');
    $_SERVER['HTTP_ORIGIN']='http://untrusted.example'; check(denied(fn()=>Session::refresh(),403),'Cross-origin refresh is denied.'); $_SERVER['HTTP_ORIGIN']=rtrim($_ENV['APP_URL'],'/');
    check(denied(fn()=>AccountService::changePassword($id,['current_password'=>'wrong','password'=>'Different-password-123','password_confirmation'=>'Different-password-123']),409),'Password must be verified against database.');
    Mail::$fail=true;
    check(denied(fn()=>AccountService::changePassword($id,['current_password'=>$password,'password'=>'Different-password-123','password_confirmation'=>'Different-password-123']),503),'Notification failure prevents changing password.'); Mail::$fail=false;
    $stmt=$db->prepare('SELECT password FROM users WHERE id=:id'); $stmt->execute(['id'=>$id]); check(password_verify($password,$stmt->fetchColumn()),'Failed change preserves password.');
    AccountService::requestEmail($id,['correo'=>$newEmail,'current_password'=>$password]); $first=token();
    check(Mail::$messages[array_key_last(Mail::$messages)]['email']===$email && AccountService::profile($id)['correo']===$email,'Approval goes to current address; email remains unchanged.');
    $stmt=$db->prepare('SELECT token_hash FROM email_changes WHERE user_id=:id'); $stmt->execute(['id'=>$id]); check($stmt->fetchColumn()===hash('sha256',$first),'Approval tokens are stored only as hashes.');
    AccountService::confirmEmail($first); $second=token();
    check($first!==$second && Mail::$messages[array_key_last(Mail::$messages)]['email']===$newEmail && AccountService::profile($id)['correo']===$email,'New email verification is required after current email approval.');
    check(denied(fn()=>AccountService::confirmEmail($first),409),'Approval token is single-use.');
    AccountService::confirmEmail($second);
    check(AccountService::profile($id)['correo']===$newEmail,'Email changes only after both confirmations.');
    check(denied(fn()=>AccountService::confirmEmail($second),409),'Verification token is single-use.');
    $stmt=$db->prepare('SELECT COUNT(id) FROM auth_sessions WHERE user_id=:id'); $stmt->execute(['id'=>$id]); check((int)$stmt->fetchColumn()===0,'Email change revokes all sessions.');
    AccountService::changePassword($id,['current_password'=>$password,'password'=>'Different-password-123','password_confirmation'=>'Different-password-123']);
    $stmt=$db->prepare('SELECT password FROM users WHERE id=:id'); $stmt->execute(['id'=>$id]); $hash=$stmt->fetchColumn();
    check($hash!=='Different-password-123' && password_verify('Different-password-123',$hash),'New password is stored only as a hash.');
    check(Mail::$messages[array_key_last(Mail::$messages)]['email']===$newEmail,'Password notification uses the current verified address.');
    $occupied='occupied-'.bin2hex(random_bytes(12)).'@example.test';
    $stmt=$db->prepare("INSERT INTO users(nombre,correo,telefono,username,password,confirmado) VALUES('Other fixture',:email,'','',:hash,'1')"); $stmt->execute(['email'=>$occupied,'hash'=>password_hash('Other-password-123',PASSWORD_DEFAULT)]);
    check(denied(fn()=>AccountService::requestEmail($id,['correo'=>$occupied,'current_password'=>'Different-password-123']),409),'Registered emails cannot be claimed.');
    $candidate='candidate-'.bin2hex(random_bytes(12)).'@example.test'; AccountService::requestEmail($id,['correo'=>$candidate,'current_password'=>'Different-password-123']); AccountService::confirmEmail(token()); $pending=token();
    $stmt->execute(['email'=>$candidate,'hash'=>password_hash('Other-password-123',PASSWORD_DEFAULT)]);
    check(denied(fn()=>AccountService::confirmEmail($pending),409) && AccountService::profile($id)['correo']===$newEmail,'Availability is checked again at final verification.');
    $db->prepare('UPDATE email_changes SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE user_id=:id')->execute(['id'=>$id]);
    check(denied(fn()=>AccountService::confirmEmail($pending),409),'Expired email confirmation is denied.');
    echo "$checks security integration checks passed; fixtures rolled back; no emails sent.\n";
  } finally { $db->rollBack(); }
}
