<?php

namespace App\DTO;

class UsersVerifyDTO {
    public function __construct(
    public readonly string $correo,
    public readonly string $token,
    public readonly int $confirmado
  ) {}

  public static function fromArray($array): UsersVerifyDTO {
    return new self(
      correo: $array['correo'] ?? '',
      token: $array['token']  ?? '',
      confirmado: $array['confirmado']  ?? 0,
    );
  }
}