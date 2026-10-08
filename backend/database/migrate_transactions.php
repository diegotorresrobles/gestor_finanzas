<?php

// Run once per environment: php backend/database/migrate_transactions.php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
$db = App\Core\Database::connect();

foreach (['cuentas' => 'balance', 'movimientos' => 'monto'] as $table => $column) {
  $type = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'")->fetch(PDO::FETCH_ASSOC)['Type'];
  if ($type !== 'decimal(12,2)') {
    $default = $table === 'cuentas' ? ' DEFAULT 0.00' : '';
    $db->exec("ALTER TABLE {$table} MODIFY {$column} DECIMAL(12,2) NOT NULL{$default}");
  }
}
if (!$db->query("SHOW COLUMNS FROM movimientos LIKE 'cuenta_destino_id'")->fetch()) {
  $db->exec('ALTER TABLE movimientos ADD cuenta_destino_id INT NULL AFTER cuenta_id');
}
$constraint = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'cuenta_destino_id' AND REFERENCED_TABLE_NAME = 'cuentas'")->fetchColumn();
if (!$constraint) {
  $db->exec('ALTER TABLE movimientos ADD CONSTRAINT movimientos_cuenta_destino_fk FOREIGN KEY (cuenta_destino_id) REFERENCES cuentas(id)');
}

$db->beginTransaction();
try {
  foreach (['tipo_cuenta' => 'Bancaria', 'tipo_movimientos' => 'Transferencia'] as $table => $name) {
    $stmt = $db->prepare("INSERT INTO {$table} (tipo) SELECT :tipo WHERE NOT EXISTS (SELECT 1 FROM {$table} WHERE tipo = :existing)");
    $stmt->execute(['tipo' => $name, 'existing' => $name]);
  }
  $db->commit();
} catch (Throwable $error) {
  $db->rollBack();
  throw $error;
}
echo "Transaction schema and catalogs ready.\n";
