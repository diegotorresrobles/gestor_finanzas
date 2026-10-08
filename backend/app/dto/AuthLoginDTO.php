<?php

namespace App\DTO;

class AuthLoginDTO {
  public function __construct(
    public readonly mixed $id,
    public readonly string $correo,
    public readonly string $password,
  ) {}

  public static function fromArray($array): AuthLoginDTO {
    return new self(
      id: $array['id']  ?? '',
      correo: $array['correo']  ?? '',
      password: $array['password']  ?? ''
    );
  }
}