<?php

namespace App\Validators;

use App\Core\Money;

class CuentasValidator extends Validator {
  public static function create(object $data): bool {
    self::make($data, [
      'nombre' => ['required'],
      'tipo_cuenta_id' => ['required'],
      'balance' => ['required'],
      'color' => ['required'],
    ]);

    if (!isset(self::$errors['nombre']) && (!is_string($data->nombre) || mb_strlen($data->nombre) > 255)) {
      self::$errors['nombre'] = 'El nombre debe ser texto de hasta 255 caracteres';
    }
    if (!isset(self::$errors['tipo_cuenta_id']) && (!is_int($data->tipo_cuenta_id) && !is_string($data->tipo_cuenta_id) || filter_var($data->tipo_cuenta_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false)) {
      self::$errors['tipo_cuenta_id'] = 'El tipo de cuenta debe ser un ID entero positivo';
    }
    if (!isset(self::$errors['balance']) && Money::cents($data->balance) === false) {
      self::$errors['balance'] = 'El balance debe tener hasta dos decimales y estar dentro del rango permitido';
    }
    if (!isset(self::$errors['color']) && (!is_string($data->color) || !preg_match('/^[a-f0-9]{6}$/i', $data->color))) {
      self::$errors['color'] = 'El color debe ser hexadecimal de seis caracteres';
    }

    return empty(self::$errors);
  }
}
