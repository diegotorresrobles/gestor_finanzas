<?php

namespace App\Core;

class Responses {
  public static array $successCodes = [200, 201, 204];
  public function __construct(
    public readonly int $code,
    public readonly string $message,
    public readonly mixed $data
  ) {}

  public function json() {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($this->code);
    $ok = in_array($this->code, self::$successCodes) ?? false;
    echo json_encode([
      'ok' => $ok,
      'message' => $this->message,
      'data' => $this->data,
    ]);
    exit;
  }
  public static function database($code, $message, $data) {
    return new self(
      $code,
      $message,
      $data
    );
  }
  public function services($code, $message, $data) {
    return new self(
      $code,
      $message,
      $data
    );
  }
  public static function created(mixed $data) {
    return new self(
      200,
      'Registro creado correctamente',
      $data
    );
  }
  public static function ok(mixed $data) {
    return new self(
      200,
      'Todo bien',
      $data
    );
  }
  public static function updated(mixed $data) {
    return new self(
      200,
      'Registro actualizado correctamente',
      $data
    );
  }
  public static function debt(mixed $data) {
    return new self(
      409,
      'Faltan datos',
      $data
    );
  }
  public static function conflict(mixed $data) {
    return new self(
      409,
      'Conflicto en los datos',
      $data
    );
  }
  public static function unauthorized(mixed $data) {
    return new self(
      401,
      'No estas autorizado',
      $data
    );
  }
  public static function empty() {
    return new self(
      0,
      '0',
      []
    );
  }
}