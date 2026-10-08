<?php

namespace App\DTO;

class CuentasCreateDTO {
  public function __construct(
    public readonly mixed $nombre,
    public readonly mixed $tipo_cuenta_id,
    public readonly mixed $balance,
    public readonly mixed $color,
    public readonly mixed $expected_balance = null,
    public readonly mixed $limite_credito = null
  ) {}

  public static function fromArray(array $data): self {
    $color = $data['color'] ?? '';
    if (is_string($color)) {
      $color = trim($color);
      if (str_starts_with($color, '#')) $color = substr($color, 1);
    }
    return new self(
      nombre: is_string($data['nombre'] ?? null) ? trim($data['nombre']) : ($data['nombre'] ?? ''),
      tipo_cuenta_id: $data['tipo_cuenta_id'] ?? '',
      balance: array_key_exists('balance', $data) ? $data['balance'] : 0,
      color: $color,
      expected_balance: $data['expected_balance'] ?? null,
      limite_credito: $data['limite_credito'] ?? null
    );
  }
}
