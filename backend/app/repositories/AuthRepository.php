<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\Responses;
use App\DTO\AuthLoginDTO;
use PDO;
use PDOException;

class AuthRepository {
  public static function findLogin($correo) : object {
    try {
      $db = Database::connect();
      $query = "SELECT id, correo, password FROM users WHERE correo = :correo";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'correo' => $correo,
      ]);

      $data = array_map(
        fn ($row) => AuthLoginDTO::fromArray($row),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
      );
      return array_shift($data) ?? AuthLoginDTO::fromArray([]);
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        $e->getMessage()
      )->json();
    }
    return Responses::empty();
  }
}