<?php
// An isolated empty schema: no local users or financial records are read/copied.
require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/..')->load();
$connection = new PDO('mysql:host='.$_ENV['DB_HOST'].';charset=utf8mb4',$_ENV['DB_USER'],$_ENV['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$testDatabase = 'dtr_deploy_test_'.bin2hex(random_bytes(6));
if (!preg_match('/^dtr_deploy_test_[a-f0-9]{12}$/', $testDatabase)) throw new RuntimeException('Invalid fixture database name');
$created = false;
try {
  $connection->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $created = true;
  $_ENV['DB_NAME']=$testDatabase; $_ENV['APP_ENV']='production';
  require __DIR__.'/../../deploy/install-database.php';
  $db=App\Core\Database::connect();
  $expected=['users','cuentas','movimientos','categorias','tipo_cuenta','tipo_movimientos','auth_sessions','email_changes','user_revisions','ws_tickets','auth_attempts'];
  if (array_diff($expected,$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN))) throw new RuntimeException('Missing deployment tables');
  if ((int)$db->query('SELECT COUNT(id) FROM users')->fetchColumn()!==0) throw new RuntimeException('Production installer must not create development users');
  if ((int)$db->query("SELECT COUNT(id) FROM tipo_cuenta WHERE tipo='Bancaria'")->fetchColumn()!==1) throw new RuntimeException('Missing or repeated account catalog');
  if ((int)$db->query("SELECT COUNT(id) FROM tipo_movimientos WHERE tipo='Transferencia'")->fetchColumn()!==1) throw new RuntimeException('Missing or repeated movement catalog');
  $rules=$db->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='movimientos' AND REFERENCED_TABLE_NAME='cuentas'")->fetchAll(PDO::FETCH_COLUMN);
  if (count($rules)!==2 || array_diff($rules,['SET NULL'])) throw new RuntimeException('Incorrect account deletion rules');
  echo "5 empty deployment schema checks passed.\n";
} finally {
  if ($created) $connection->exec("DROP DATABASE `$testDatabase`");
}
