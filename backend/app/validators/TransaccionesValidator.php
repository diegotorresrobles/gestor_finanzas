<?php

namespace App\Validators;

use App\Core\Money;
use DateTimeImmutable;

class TransaccionesValidator extends Validator {
  public static function id(mixed $id): bool {
    return (is_int($id) || is_string($id)) && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) !== false;
  }

  public static function validate(object $data): bool {
    self::$errors = [];
    if (!in_array($data->tipo, ['gasto', 'ingreso', 'transferencia'], true)) self::$errors['tipo'] = 'Selecciona gasto, ingreso o transferencia';
    foreach (['cuenta_id', 'categoria_id'] as $field) {
      if (!self::id($data->$field)) self::$errors[$field] = 'Selecciona una opción válida';
    }
    if ($data->tipo === 'transferencia') {
      if (!self::id($data->cuenta_destino_id)) self::$errors['cuenta_destino_id'] = 'Selecciona la cuenta destino';
      elseif ((int) $data->cuenta_id === (int) $data->cuenta_destino_id) self::$errors['cuenta_destino_id'] = 'La cuenta destino debe ser distinta de la cuenta origen';
    } elseif ($data->cuenta_destino_id !== null && $data->cuenta_destino_id !== '') {
      self::$errors['cuenta_destino_id'] = 'Solo las transferencias tienen una cuenta destino';
    }
    $cents = Money::cents($data->monto);
    if ($cents === false || $cents <= 0) self::$errors['monto'] = 'El monto debe ser positivo y tener hasta dos decimales';
    if (!is_string($data->descripcion) || mb_strlen($data->descripcion) > 2000) self::$errors['descripcion'] = 'La descripción debe tener hasta 2000 caracteres';
    $date = is_string($data->fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data->fecha) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data->fecha) : false;
    if (!$date || $date->format('Y-m-d') !== $data->fecha || $data->fecha < '1000-01-01' || $data->fecha > '9999-12-31') self::$errors['fecha'] = 'Indica una fecha válida';
    return empty(self::$errors);
  }
}
