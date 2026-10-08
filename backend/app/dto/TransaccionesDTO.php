<?php

namespace App\DTO;

class TransaccionesDTO {
  public function __construct(
    public readonly mixed $tipo,
    public readonly mixed $cuenta_id,
    public readonly mixed $cuenta_destino_id,
    public readonly mixed $categoria_id,
    public readonly mixed $monto,
    public readonly mixed $descripcion,
    public readonly mixed $fecha,
    public readonly mixed $expected_version = null
  ) {}

  public static function fromArray(array $data): self {
    return new self(
      $data['tipo'] ?? '', $data['cuenta_id'] ?? '', $data['cuenta_destino_id'] ?? null,
      $data['categoria_id'] ?? '', $data['monto'] ?? '',
      is_string($data['descripcion'] ?? '') ? trim($data['descripcion'] ?? '') : $data['descripcion'],
      $data['fecha'] ?? '', $data['expected_version'] ?? null
    );
  }
}
