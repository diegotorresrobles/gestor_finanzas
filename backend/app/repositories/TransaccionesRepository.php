<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\FinanceException;
use App\Core\Money;
use App\Core\Credit;
use App\Core\Events;
use App\DTO\TransaccionesDTO;
use PDO;
use Throwable;

class TransaccionesRepository {
  private const SELECT = 'SELECT m.*, LOWER(t.tipo) AS tipo, COALESCE(c.nombre,m.cuenta_nombre_historico,"Cuenta eliminada") AS cuenta_nombre, COALESCE(d.nombre,m.destino_nombre_historico,"Cuenta eliminada") AS cuenta_destino_nombre, cat.categoria FROM movimientos m LEFT JOIN cuentas c ON c.id = m.cuenta_id LEFT JOIN cuentas d ON d.id = m.cuenta_destino_id JOIN tipo_movimientos t ON t.id = m.tipo_movimineto_id JOIN categorias cat ON cat.id = m.categoria_id';
  private const OWNER = 'm.user_id = :user_id';

  public static function get(int $userId, ?int $accountId = null, ?int $limit = null): array {
    $db = Database::connect();
    $params = ['user_id' => $userId];
    $filter = '';
    if ($accountId !== null) {
      $filter = ' AND (m.cuenta_id = :account_id OR m.cuenta_destino_id = :destination_id)';
      $params += ['account_id' => $accountId, 'destination_id' => $accountId];
    }
    $stmt = $db->prepare(self::SELECT . ' WHERE ' . self::OWNER . $filter . ' ORDER BY m.fecha DESC, m.id DESC' . ($limit===null ? '' : ' LIMIT '.max(1,min(100,$limit))));
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function find(int $id, int $userId): array|false {
    $db = Database::connect();
    $stmt = $db->prepare(self::SELECT . ' WHERE ' . self::OWNER . ' AND m.id = :id');
    $stmt->execute(['id' => $id, 'user_id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public static function categorias(): array {
    return Database::connect()->query('SELECT id, categoria FROM categorias ORDER BY categoria')->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function delete(int $id, int $userId): void {
    $db=Database::connect(); $db->beginTransaction();
    try {
      $stmt=$db->prepare('SELECT * FROM movimientos WHERE id=:id AND user_id=:user_id FOR UPDATE');
      $stmt->execute(['id'=>$id,'user_id'=>$userId]); $old=$stmt->fetch(PDO::FETCH_ASSOC);
      if (!$old) throw new FinanceException('La transacción no existe',404);
      $stmt=$db->prepare('SELECT LOWER(tipo) FROM tipo_movimientos WHERE id=:id');
      $stmt->execute(['id'=>$old['tipo_movimineto_id']]); $old['tipo']=$stmt->fetchColumn();
      $effects=self::effects($old); ksort($effects,SORT_NUMERIC);
      $lock=$db->prepare('SELECT id,balance,limite_credito FROM cuentas WHERE id=:id AND user_id=:user_id FOR UPDATE');
      $update=$db->prepare('UPDATE cuentas SET balance=:balance WHERE id=:id AND user_id=:user_id');
      foreach ($effects as $accountId=>$effect) {
        $lock->execute(['id'=>$accountId,'user_id'=>$userId]); $account=$lock->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new FinanceException('La cuenta ya no está disponible. Intenta de nuevo.',409);
        $balance=Money::cents($account['balance'])-$effect;
        if (abs($balance)>Money::MAX_CENTS) throw new FinanceException('El saldo resultante excede el rango permitido',409);
        Credit::check($account,$balance);
        $update->execute(['id'=>$accountId,'user_id'=>$userId,'balance'=>Money::decimal($balance)]);
      }
      $stmt=$db->prepare('DELETE FROM movimientos WHERE id=:id AND user_id=:user_id');
      $stmt->execute(['id'=>$id,'user_id'=>$userId]);
      Events::changed($db,$userId); $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
  }

  private static function effects(array $transaction): array {
    $amount = Money::cents($transaction['monto']);
    if ($amount === false || $amount <= 0 || !in_array($transaction['tipo'], ['gasto', 'ingreso', 'transferencia'], true)) throw new FinanceException('La transacción registrada tiene datos inválidos');
    $effects = [];
    if ($transaction['cuenta_id'] !== null) $effects[(int) $transaction['cuenta_id']] = $transaction['tipo'] === 'ingreso' ? $amount : -$amount;
    if ($transaction['tipo'] === 'transferencia' && $transaction['cuenta_destino_id'] !== null) $effects[(int) $transaction['cuenta_destino_id']] = $amount;
    return $effects;
  }

  public static function save(TransaccionesDTO $data, int $userId, ?int $id = null): array {
    $db = Database::connect();
    $db->beginTransaction();
    try {
      $old = false;
      if ($id !== null) {
        // Lock the transaction before deriving the reversal, so concurrent edits cannot reuse stale data.
        $stmt = $db->prepare('SELECT * FROM movimientos WHERE id = :id AND user_id = :user_id FOR UPDATE');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) throw new FinanceException('La transacción no existe', 404);
        if ($data->expected_version !== null && (!\App\Validators\TransaccionesValidator::id($data->expected_version) || (int)$data->expected_version !== (int)$old['version'])) throw new FinanceException('La transacción cambió en otra sesión. Cancela la edición y consulta los datos actuales.',409);
        if ($old['cuenta_id'] === null || ($old['cuenta_destino_id'] === null && (int)$old['tipo_movimineto_id'] === (int)$db->query("SELECT id FROM tipo_movimientos WHERE LOWER(tipo)='transferencia' LIMIT 1")->fetchColumn())) throw new FinanceException('Una cuenta de esta transacción fue eliminada. Puedes consultar o eliminar el movimiento, pero no editarlo.',409);
        $type = $db->prepare('SELECT LOWER(tipo) FROM tipo_movimientos WHERE id = :id');
        $type->execute(['id' => $old['tipo_movimineto_id']]);
        $old['tipo'] = $type->fetchColumn();
      }
      $transaction = [
        'tipo' => $data->tipo, 'cuenta_id' => (int) $data->cuenta_id,
        'cuenta_destino_id' => $data->tipo === 'transferencia' ? (int) $data->cuenta_destino_id : null,
        'categoria_id' => (int) $data->categoria_id, 'monto' => Money::decimal(Money::cents($data->monto)),
        'descripcion' => $data->descripcion, 'fecha' => $data->fecha,
      ];
      $effects = self::effects($transaction);
      $accountIds = array_keys($effects);
      if ($old) {
        foreach (self::effects($old) as $accountId => $amount) {
          $effects[$accountId] = ($effects[$accountId] ?? 0) - $amount;
          $accountIds[] = $accountId;
        }
      }
      $accountIds = array_values(array_unique($accountIds));
      sort($accountIds, SORT_NUMERIC);
      $accounts = [];
      $lock = $db->prepare('SELECT id, nombre, balance, limite_credito FROM cuentas WHERE id = :id AND user_id = :user_id FOR UPDATE');
      foreach ($accountIds as $accountId) {
        $lock->execute(['id' => $accountId, 'user_id' => $userId]);
        $account = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new FinanceException('La cuenta no existe o no pertenece al usuario', 404, ['cuenta_id' => 'Todas las cuentas deben pertenecer a tu usuario']);
        $accounts[$accountId] = $account;
      }
      $catalog = $db->prepare('SELECT id FROM tipo_movimientos WHERE LOWER(tipo) = :tipo LIMIT 1');
      $catalog->execute(['tipo' => $data->tipo]);
      $typeId = $catalog->fetchColumn();
      $category = $db->prepare('SELECT id FROM categorias WHERE id = :id');
      $category->execute(['id' => $data->categoria_id]);
      if (!$typeId || !$category->fetchColumn()) throw new FinanceException('El tipo o la categoría no existe', 409, ['categoria_id' => 'Selecciona una categoría válida']);

      $updateBalance = $db->prepare('UPDATE cuentas SET balance = :balance WHERE id = :id AND user_id = :user_id');
      foreach ($effects as $accountId => $effect) {
        $balance = Money::cents($accounts[$accountId]['balance']);
        if ($balance === false || abs($balance + $effect) > Money::MAX_CENTS) throw new FinanceException('El saldo resultante excede el rango permitido', 409, ['monto' => 'El saldo resultante es demasiado grande']);
        Credit::check($accounts[$accountId], $balance + $effect);
        $updateBalance->execute(['id' => $accountId, 'user_id' => $userId, 'balance' => Money::decimal($balance + $effect)]);
      }
      $params = $transaction;
      unset($params['tipo']);
      $params['tipo_movimineto_id'] = (int) $typeId;
      $params['user_id'] = $userId;
      $params['cuenta_nombre_historico'] = $accounts[$transaction['cuenta_id']]['nombre'];
      $params['destino_nombre_historico'] = $transaction['cuenta_destino_id'] ? $accounts[$transaction['cuenta_destino_id']]['nombre'] : null;
      if ($id === null) {
        $stmt = $db->prepare('INSERT INTO movimientos (user_id, cuenta_nombre_historico, destino_nombre_historico, cuenta_id, cuenta_destino_id, categoria_id, tipo_movimineto_id, monto, descripcion, fecha) VALUES (:user_id, :cuenta_nombre_historico, :destino_nombre_historico, :cuenta_id, :cuenta_destino_id, :categoria_id, :tipo_movimineto_id, :monto, :descripcion, :fecha)');
        $stmt->execute($params);
        $id = (int) $db->lastInsertId();
      } else {
        $params['id'] = $id;
        $stmt = $db->prepare('UPDATE movimientos SET version=version+1, user_id = :user_id, cuenta_nombre_historico = :cuenta_nombre_historico, destino_nombre_historico = :destino_nombre_historico, cuenta_id = :cuenta_id, cuenta_destino_id = :cuenta_destino_id, categoria_id = :categoria_id, tipo_movimineto_id = :tipo_movimineto_id, monto = :monto, descripcion = :descripcion, fecha = :fecha WHERE id = :id');
        $stmt->execute($params);
      }
      Events::changed($db, $userId);
      $db->commit();
      return ['id' => $id, ...$transaction];
    } catch (Throwable $error) {
      if ($db->inTransaction()) $db->rollBack();
      throw $error;
    }
  }
}
