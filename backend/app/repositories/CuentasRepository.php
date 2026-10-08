<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\Money;
use App\Core\Credit;
use App\Core\Events;
use App\Core\FinanceException;
use App\DTO\CuentasCreateDTO;
use PDO;

class CuentasRepository {
  public static function find(int $id, int $userId): array|false {
    $db = Database::connect();
    $stmt = $db->prepare('SELECT c.*, t.tipo AS tipo_cuenta FROM cuentas c JOIN tipo_cuenta t ON t.id = c.tipo_cuenta_id WHERE c.id = :id AND c.user_id = :user_id');
    $stmt->execute(['id' => $id, 'user_id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function update(int $id, CuentasCreateDTO $data, int $userId): bool {
    $db = Database::connect();
    $db->beginTransaction();
    try {
      $lock = $db->prepare('SELECT id, balance FROM cuentas WHERE id = :id AND user_id = :user_id FOR UPDATE');
      $lock->execute(['id' => $id, 'user_id' => $userId]);
      $account = $lock->fetch(PDO::FETCH_ASSOC);
      if (!$account) { $db->rollBack(); return false; }
      if ($data->expected_balance !== null && (Money::cents($data->expected_balance) === false || Money::cents($data->expected_balance) !== Money::cents($account['balance']))) {
        throw new FinanceException('El saldo cambió mientras editabas. Recarga la cuenta antes de guardar.', 409, ['balance' => 'El saldo cambió. Recarga la cuenta para consultar el saldo actual']);
      }
      $limit = Credit::limit($db, (int)$data->tipo_cuenta_id, $data->limite_credito, $data->balance);
      $stmt = $db->prepare('UPDATE cuentas SET limite_credito = :limite_credito, nombre = :nombre, tipo_cuenta_id = :tipo_cuenta_id, balance = :balance, color = :color WHERE id = :id AND user_id = :user_id');
      $stmt->execute(['limite_credito' => $limit, 'id' => $id, 'user_id' => $userId, 'nombre' => $data->nombre, 'tipo_cuenta_id' => $data->tipo_cuenta_id, 'balance' => Money::decimal(Money::cents($data->balance)), 'color' => strtoupper($data->color)]);
      Events::changed($db, $userId);
      $db->commit();
      return true;
    } catch (\Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
  }

  public static function getTipos(): array {
    $db = Database::connect();
    return $db->query('SELECT id, tipo FROM tipo_cuenta ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function delete(int $id, int $userId): bool {
    $db = Database::connect();
    $db->beginTransaction();
    try {
      $stmt = $db->prepare('DELETE FROM cuentas WHERE id = :id AND user_id = :user_id');
      $stmt->execute(['id' => $id, 'user_id' => $userId]);
      $deleted = $stmt->rowCount() > 0;
      if ($deleted) Events::changed($db, $userId);
      $db->commit();
      return $deleted;
    } catch (\Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
  }

  public static function getByUserId(int $userId): array {
    $db = Database::connect();
    $stmt = $db->prepare('SELECT id, user_id, tipo_cuenta_id, nombre, balance, limite_credito, color, created_at, updated_at FROM cuentas WHERE user_id = :user_id ORDER BY id DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function existTipo(int $tipoId): bool {
    $db = Database::connect();
    $stmt = $db->prepare('SELECT id FROM tipo_cuenta WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $tipoId]);
    return $stmt->fetchColumn() !== false;
  }

  public static function insert(CuentasCreateDTO $data, int $userId): array {
    $db = Database::connect();
    $account = [
      'limite_credito' => Credit::limit($db, (int)$data->tipo_cuenta_id, $data->limite_credito, $data->balance),
      'user_id' => $userId,
      'tipo_cuenta_id' => (int) $data->tipo_cuenta_id,
      'nombre' => $data->nombre,
      'balance' => Money::decimal(Money::cents($data->balance)),
      'color' => strtoupper($data->color),
    ];
    $db->beginTransaction();
    try {
      $stmt = $db->prepare('INSERT INTO cuentas (user_id, tipo_cuenta_id, nombre, balance, color, limite_credito) VALUES (:user_id, :tipo_cuenta_id, :nombre, :balance, :color, :limite_credito)');
      $stmt->execute($account);
      $id = (int)$db->lastInsertId();
      Events::changed($db, $userId);
      $db->commit();
      return ['id' => $id, ...$account];
    } catch (\Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
  }
}
