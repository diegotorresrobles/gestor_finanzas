<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
$db = App\Core\Database::connect();
function column(PDO $db, string $table, string $name, string $definition): bool {
  if ($db->query("SHOW COLUMNS FROM `$table` LIKE '$name'")->fetch()) return false;
  $db->exec("ALTER TABLE `$table` ADD `$name` $definition");
  return true;
}
column($db, 'users', 'rol', "ENUM('user','admin') NOT NULL DEFAULT 'user'");
if (column($db, 'cuentas', 'limite_credito', 'DECIMAL(12,2) NULL')) {
  // Existing debt remains valid; users can configure their actual contractual limit.
  $db->exec("UPDATE cuentas c JOIN tipo_cuenta t ON t.id=c.tipo_cuenta_id SET c.limite_credito=GREATEST(0,-c.balance) WHERE LOWER(t.tipo) LIKE '%credito%' OR LOWER(t.tipo) LIKE '%crédito%'");
}
column($db, 'movimientos', 'user_id', 'INT NULL');
column($db, 'movimientos', 'version', 'INT NOT NULL DEFAULT 1');
column($db, 'movimientos', 'cuenta_nombre_historico', 'VARCHAR(255) NULL');
column($db, 'movimientos', 'destino_nombre_historico', 'VARCHAR(255) NULL');
$db->exec('UPDATE movimientos m JOIN cuentas c ON c.id=m.cuenta_id LEFT JOIN cuentas d ON d.id=m.cuenta_destino_id SET m.user_id=COALESCE(m.user_id,c.user_id), m.cuenta_nombre_historico=COALESCE(m.cuenta_nombre_historico,c.nombre), m.destino_nombre_historico=COALESCE(m.destino_nombre_historico,d.nombre)');
if ((int)$db->query('SELECT COUNT(id) FROM movimientos WHERE user_id IS NULL')->fetchColumn()) throw new RuntimeException('Hay movimientos sin propietario. Revisa la integridad antes de migrar.');
$db->exec('ALTER TABLE movimientos MODIFY user_id INT NOT NULL');
foreach (['cuenta_id', 'cuenta_destino_id'] as $name) {
  $stmt=$db->prepare("SELECT k.CONSTRAINT_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='movimientos' AND k.COLUMN_NAME=:col AND k.REFERENCED_TABLE_NAME='cuentas'");
  $stmt->execute(['col'=>$name]); $fk=$stmt->fetch(PDO::FETCH_ASSOC);
  if ($fk && $fk['DELETE_RULE']==='SET NULL') continue;
  if ($fk) $db->exec('ALTER TABLE movimientos DROP FOREIGN KEY `'.str_replace('`','``',$fk['CONSTRAINT_NAME']).'`');
  $db->exec("ALTER TABLE movimientos MODIFY `$name` INT NULL");
  $db->exec("ALTER TABLE movimientos ADD CONSTRAINT `movimientos_{$name}_nullable_fk` FOREIGN KEY (`$name`) REFERENCES cuentas(id) ON DELETE SET NULL");
}
if (!$db->query("SHOW INDEX FROM movimientos WHERE Key_name='movimientos_owner_idx'")->fetch()) $db->exec('CREATE INDEX movimientos_owner_idx ON movimientos(user_id,fecha,id)');
if (!(int)$db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='movimientos' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'")->fetchColumn()) $db->exec('ALTER TABLE movimientos ADD CONSTRAINT movimientos_owner_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE');
if ((int)$db->query('SELECT COUNT(*) FROM (SELECT correo FROM users GROUP BY correo HAVING COUNT(id)>1) duplicate_emails')->fetchColumn()) throw new RuntimeException('Hay correos duplicados; resuélvelos antes de habilitar cambios de correo.');
if (!$db->query("SHOW INDEX FROM users WHERE Key_name='users_correo_unique'")->fetch()) $db->exec('CREATE UNIQUE INDEX users_correo_unique ON users(correo)');
$db->exec("CREATE TABLE IF NOT EXISTS auth_sessions (id CHAR(64) PRIMARY KEY,user_id INT NOT NULL,refresh_hash CHAR(64) NOT NULL,previous_hash CHAR(64) NULL,previous_until DATETIME NULL,expires_at DATETIME NOT NULL,last_seen DATETIME NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,INDEX(user_id,last_seen))");
$db->exec("CREATE TABLE IF NOT EXISTS email_changes (user_id INT PRIMARY KEY,new_email VARCHAR(255) NOT NULL,stage ENUM('current','new') NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,expires_at DATETIME NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)");
$db->exec('CREATE TABLE IF NOT EXISTS user_revisions (user_id INT PRIMARY KEY,revision BIGINT NOT NULL DEFAULT 0,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)');
$db->exec('CREATE TABLE IF NOT EXISTS ws_tickets (token_hash CHAR(64) PRIMARY KEY,session_id CHAR(64) NOT NULL,expires_at DATETIME NOT NULL,FOREIGN KEY(session_id) REFERENCES auth_sessions(id) ON DELETE CASCADE)');
$db->exec('CREATE TABLE IF NOT EXISTS auth_attempts (key_hash CHAR(64) PRIMARY KEY,attempts INT NOT NULL,window_start DATETIME NOT NULL)');
$development=($_ENV['APP_ENV']??'development')==='development';
if ($development) {
  $stmt=$db->prepare('SELECT id,rol FROM users WHERE correo=:email'); $stmt->execute(['email'=>'admin@admin.com']); $admin=$stmt->fetch(PDO::FETCH_ASSOC);
  if (!$admin) {
    $stmt=$db->prepare("INSERT INTO users(nombre,apellido,correo,telefono,username,password,confirmado,rol) VALUES('DTR','Administración','admin@admin.com','','admin',:hash,'1','admin')");
    $stmt->execute(['hash'=>password_hash('admin',PASSWORD_DEFAULT)]);
  } elseif ($admin['rol']!=='admin') throw new RuntimeException('El correo de desarrollo ya pertenece a un usuario; no se modificó su rol.');
}
echo "Security, ownership, credit limits and development administrator ready.\n";
