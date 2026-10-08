<?php

namespace App\DTO;

class UsersRegisterDTO {
  public function __construct(
    public readonly string $nombre,
    public readonly string $apellido,
    public readonly string $correo,
    public readonly string $telefono,
    public readonly string $username,
    public readonly string $password,
    public readonly string $passwordConfirm
  ) {}

  public static function fromArray($array): UsersRegisterDTO {
    return new self(
      nombre: $array['nombre'] ?? '',
      apellido: $array['apellido']  ?? '',
      correo: $array['correo']  ?? '',
      telefono: $array['telefono']  ?? '',
      username: $array['username']  ?? '',
      password: $array['password']  ?? '',
      passwordConfirm: $array['passwordConfirm']  ?? ''
    );
  }
  
}