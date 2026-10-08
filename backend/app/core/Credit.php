<?php
namespace App\Core;
use PDO;
class Credit {
  public static function limit(PDO $db, int $type, mixed $limit, mixed $balance): ?string {
    $stmt=$db->prepare('SELECT tipo FROM tipo_cuenta WHERE id=:id'); $stmt->execute(['id'=>$type]);
    $name=mb_strtolower((string)$stmt->fetchColumn());
    if (!str_contains($name,'credito') && !str_contains($name,'crédito')) return null;
    $cents=Money::cents($limit);
    if ($cents===false || $cents<0) throw new FinanceException('Indica un límite de crédito válido',409,['limite_credito'=>'El límite debe ser positivo o cero, con hasta dos decimales']);
    self::check(['limite_credito'=>Money::decimal($cents)],Money::cents($balance));
    return Money::decimal($cents);
  }
  public static function check(array $account, int $balance): void {
    if ($account['limite_credito']!==null && $balance < -Money::cents($account['limite_credito'])) throw new FinanceException('La operación excede el límite de crédito',409,['monto'=>'El saldo resultante excede el límite de crédito','balance'=>'La deuda no puede superar el límite de crédito']);
  }
}
