<?php
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/../backend')->load();
try {
  $db = new PDO('mysql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'].';charset=utf8mb4', $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  if (!$tables) {
    $sql = file_get_contents(__DIR__.'/schema.sql');
    foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $db->exec($statement);
    echo "Esquema inicial creado sin importar información de usuarios.\n";
  } elseif (!empty(array_diff(['users','cuentas','movimientos','tipo_cuenta','tipo_movimientos','categorias'], $tables))) {
    throw new RuntimeException('Esquema incompleto');
  }
  require __DIR__.'/../backend/database/migrate_transactions.php';
  require __DIR__.'/../backend/database/migrate_security.php';
} catch (Throwable $error) {
  fwrite(STDERR, "No se pudo preparar la base de datos. Revisa conexión y esquema; las migraciones pueden haberse aplicado parcialmente. No se muestran credenciales.\n");
  exit(1);
}
