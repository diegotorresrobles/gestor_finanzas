<?php

namespace App\DTO;

class UsersAllDTO {
  public function __construct(
    public readonly int $id,
    public readonly string $nombre,
    public readonly string $apellido,
    public readonly string $correo,
    public readonly string $telefono,
    public readonly string $username,
    public readonly string $password,
    public readonly int $confirmado,
    public readonly string $token,
    public readonly string $created_at,
    public readonly string $updated_at,
    public readonly string $rol = 'user'
  ) {}

  public static function fromArray($array): UsersAllDTO {
    return new self(
      id: $array['id'] ?? null,
      nombre: $array['nombre'] ?? '',
      apellido: $array['apellido']  ?? '',
      correo: $array['correo']  ?? '',
      telefono: $array['telefono']  ?? '',
      username: $array['username']  ?? '',
      password: $array['password']  ?? '',
      confirmado: (int) ($array['confirmado'] ?? 0),
      token: $array['token']  ?? '',
      created_at: $array['created_at']  ?? '',
      updated_at: $array['updated_at']  ?? '',
      rol: $array['rol'] ?? 'user',
    );
  }
}
