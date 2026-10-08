<?php
require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/..')->load();
$db=App\Core\Database::connect();
$action=$argv[1]??'';
if ($action==='create') {
  $email='dtr-http-'.bin2hex(random_bytes(16)).'@example.test';
  $stmt=$db->prepare("INSERT INTO users(nombre,correo,telefono,username,password,confirmado) VALUES('HTTP fixture',:email,'','dtr-http-fixture',:hash,'1')");
  $stmt->execute(['email'=>$email,'hash'=>password_hash('Fixture-password-123',PASSWORD_DEFAULT)]);
  echo json_encode(['email'=>$email,'id'=>(int)$db->lastInsertId()]);
} elseif ($action==='cleanup') {
  $email=$argv[2]??'';
  if (!preg_match('/^dtr-http-[a-f0-9]{32}@example\.test$/',$email)) exit(1);
  $db->beginTransaction();
  try {
    $stmt=$db->prepare("SELECT id FROM users WHERE correo=:email AND username='dtr-http-fixture' FOR UPDATE"); $stmt->execute(['email'=>$email]); $id=$stmt->fetchColumn();
    if ($id) {
      foreach (['movimientos','cuentas','users'] as $table) {
        $field=$table==='users'?'id':'user_id'; $stmt=$db->prepare("DELETE FROM $table WHERE $field=:id"); $stmt->execute(['id'=>$id]);
      }
    }
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
