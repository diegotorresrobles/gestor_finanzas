<?php
namespace App\Services;
use App\Core\Database;
use App\Core\FinanceException;
use App\Core\RateLimit;
use App\Classes\Mail;
use PDO;
class AccountService {
  public static function profile(int $id): array {
    $stmt=Database::connect()->prepare('SELECT nombre,apellido,correo,telefono,username,rol FROM users WHERE id=:id');
    $stmt->execute(['id'=>$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
  }
  private static function password(PDO $db, int $id, mixed $password): array {
    $stmt=$db->prepare('SELECT id,nombre,correo,password,rol FROM users WHERE id=:id FOR UPDATE'); $stmt->execute(['id'=>$id]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_string($password) || !$user || !password_verify($password,$user['password'])) throw new FinanceException('La contraseña actual es incorrecta',409,['current_password'=>'Verifica tu contraseña actual']);
    return $user;
  }
  public static function changePassword(int $id, array $data): void {
    RateLimit::check('password:'.$id);
    $new=$data['password']??null;
    if (!is_string($new) || strlen($new)<12 || strlen($new)>72) throw new FinanceException('La nueva contraseña debe tener entre 12 y 72 caracteres',409,['password'=>'Usa de 12 a 72 caracteres']);
    if ($new!==($data['password_confirmation']??null)) throw new FinanceException('Las contraseñas no coinciden',409);
    $db=Database::connect(); $db->beginTransaction();
    try {
      $user=self::password($db,$id,$data['current_password']??null);
      if (password_verify($new,$user['password'])) throw new FinanceException('Elige una contraseña diferente',409);
      $stmt=$db->prepare('UPDATE users SET password=:hash WHERE id=:id'); $stmt->execute(['hash'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$id]);
      $stmt=$db->prepare('DELETE FROM auth_sessions WHERE user_id=:id'); $stmt->execute(['id'=>$id]);
      $stmt=$db->prepare('DELETE FROM email_changes WHERE user_id=:id'); $stmt->execute(['id'=>$id]);
      if (!Mail::send($user['correo'],'Cambio de contraseña','La contraseña de tu cuenta DTR ha sido cambiada. Se cerraron todas las sesiones. Si no solicitaste este cambio, contacta con soporte.')) throw new FinanceException('No se pudo enviar el aviso. La contraseña no se modificó.',503);
      $db->commit();
    } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  }
  private static function available(PDO $db, string $email): void {
    $stmt=$db->prepare('SELECT id FROM users WHERE correo=:email LIMIT 1'); $stmt->execute(['email'=>$email]);
    if ($stmt->fetchColumn()) throw new FinanceException('No se puede utilizar ese correo',409,['correo'=>'El correo no está disponible']);
  }
  private static function link(string $token): string { return rtrim($_ENV['APP_URL'],'/').'/account/email/confirm#'.rawurlencode($token); }
  public static function requestEmail(int $id, array $data): void {
    RateLimit::check('email-change:'.$id,5);
    $email=$data['correo']??null;
    if (!is_string($email) || strlen($email)>255 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new FinanceException('Correo inválido',409);
    $email=mb_strtolower(trim($email)); $db=Database::connect(); $db->beginTransaction();
    try {
      $user=self::password($db,$id,$data['current_password']??null);
      if ($user['rol']==='admin') throw new FinanceException('El correo del administrador de desarrollo es fijo',409);
      self::available($db,$email); $token=bin2hex(random_bytes(32));
      $stmt=$db->prepare("INSERT INTO email_changes(user_id,new_email,stage,token_hash,expires_at) VALUES(:id,:email,'current',:hash,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE new_email=VALUES(new_email),stage='current',token_hash=VALUES(token_hash),expires_at=VALUES(expires_at)");
      $stmt->execute(['id'=>$id,'email'=>$email,'hash'=>hash('sha256',$token)]);
      if (!Mail::send($user['correo'],'Autoriza el cambio de correo','Se solicitó cambiar el correo de tu cuenta. Autoriza el cambio aquí: '.self::link($token).' . Este enlace vence en 30 minutos. Si no lo solicitaste, ignora este mensaje.')) throw new FinanceException('No se pudo enviar el correo de aprobación. Intenta de nuevo.',503);
      $db->commit();
    } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  }
  public static function confirmEmail(mixed $token): string {
    RateLimit::check('email-confirm',30);
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/',$token)) throw new FinanceException('El enlace es inválido',409);
    $db=Database::connect(); $db->beginTransaction();
    try {
      $stmt=$db->prepare('SELECT * FROM email_changes WHERE token_hash=:hash AND expires_at>NOW() FOR UPDATE');
      $stmt->execute(['hash'=>hash('sha256',$token)]); $change=$stmt->fetch(PDO::FETCH_ASSOC);
      if (!$change) throw new FinanceException('El enlace expiró o ya fue utilizado',409);
      self::available($db,$change['new_email']);
      if ($change['stage']==='current') {
        $new=bin2hex(random_bytes(32));
        $stmt=$db->prepare("UPDATE email_changes SET stage='new',token_hash=:hash,expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE) WHERE user_id=:id");
        $stmt->execute(['hash'=>hash('sha256',$new),'id'=>$change['user_id']]);
        if (!Mail::send($change['new_email'],'Verifica tu nuevo correo','Confirma tu nuevo correo aquí: '.self::link($new).' . Tu correo actual continuará vigente hasta completar esta verificación. El enlace vence en 30 minutos.')) throw new FinanceException('No se pudo enviar la verificación del nuevo correo',503);
        $message='Aprobación recibida. Revisa el nuevo correo para completar la verificación.';
      } else {
        $stmt=$db->prepare('UPDATE users SET correo=:email WHERE id=:id'); $stmt->execute(['email'=>$change['new_email'],'id'=>$change['user_id']]);
        $stmt=$db->prepare('DELETE FROM email_changes WHERE user_id=:id'); $stmt->execute(['id'=>$change['user_id']]);
        $stmt=$db->prepare('DELETE FROM auth_sessions WHERE user_id=:id'); $stmt->execute(['id'=>$change['user_id']]);
        $message='Nuevo correo verificado. Inicia sesión con el nuevo correo.';
      }
      $db->commit(); return $message;
    } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  }
}
