<?php
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/../backend')->load();
$password = stream_get_contents(STDIN, 73);
if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
  fwrite(STDERR, "Usa una contraseña de 12 a 72 bytes.\n"); exit(1);
}
try {
  $db = new PDO('mysql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'].';charset=utf8mb4', $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $stmt=$db->prepare('SELECT id FROM users WHERE correo=:email'); $stmt->execute(['email'=>'admin@admin.com']);
  if ($stmt->fetchColumn()) { fwrite(STDERR, "El administrador ya existe; no se modificó su contraseña ni rol.\n"); exit(1); }
  $stmt=$db->prepare("INSERT INTO users(nombre,apellido,correo,telefono,username,password,confirmado,rol) VALUES('DTR','Administración','admin@admin.com','','admin',:hash,'1','admin')");
  $stmt->execute(['hash'=>password_hash($password, PASSWORD_BCRYPT)]);
  unset($password);
  echo "Administrador creado. Configura SMTP en /admin antes de abrir el registro al público.\n";
} catch (Throwable $error) { fwrite(STDERR, "No se pudo crear el administrador. Revisa el esquema y la conexión.\n"); exit(1); }
