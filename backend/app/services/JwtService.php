<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService {
  public readonly string $key;
  public readonly string $id;

  public function __construct($id) {
    $this->key = $_ENV['JWT_KEY'];
    $this->id = $id ?? '';
  }

  public function generate(string $role = 'user', ?string $sid = null) {
    $payload = [
      "iss" => $_ENV['APP_DOMAIN'],
      "sub" => $this->id,
      "iat" => time(),
      "exp" => time() + 900,
      "rol" => $role,
      "sid" => $sid
      // "email" => "usuario@correo.com"
    ];

    try {
      return JWT::encode($payload, $this->key, 'HS256');
    } catch (Exception $e) {
      throw new \RuntimeException('No se pudo generar la sesión');
    }
  }

  public static function validate($jwt): array|false {
    try {
      $decode = JWT::decode($jwt, new Key($_ENV['JWT_KEY'], 'HS256'));
      $data = (array) $decode;
      return $data;
    } catch (Exception $e) {
      return false;
    }
  }
}
