<?php 

namespace App\Validators;

class Validator {
  public static $errors = [];
  public static function make(object $data, array $rules) {
    self::$errors = [];
    foreach ($rules as $field => $fRules) {
      $value = $data->$field ?? null;

      foreach ($fRules as $rule) {
        $params = explode(':', $rule);
        $method = $params[0];
        $argument = $params[1] ?? null;
        if (!method_exists(self::class, $method)) continue;
        if ($method === 'equal') {
          $field = [$field, $argument];
          $argument = $data->{$argument} ?? null;
        }
        
        $passed = self::$method(
          $field,
          $value,
          $argument
        );
        if (!$passed && $method === 'required') {
          break;
        }
      }
    }
    return empty(self::$errors);
  }
  protected static function required(string $field, mixed $value, mixed $argument = null) {
    if ($value === null || $value === '') {
      self::$errors[$field] = "El campo {$field} es requerido";
      return false;
    }
    return true;
  }
  protected static function equal(array $field, mixed $value, mixed $argument = null) {
    if ($value !== $argument) {
      self::$errors[$field[0]][] = "El campo {$field[0]} no coincide con {$field[1]}";
    }
  }
  protected static function email(string $field, mixed $value, mixed $argument) {
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
      self::$errors[$field] = "Formato de email incorrecto";
    }
  }
  public static function size(string $field, mixed $value, mixed $argument) {
    if (strlen($value) !== (int)$argument) {
      self::$errors[$field] = "El {$field} debe tener {$argument} caracteres";
    }
  }
  public static function getErrors() {
    return self::$errors;
  }
}