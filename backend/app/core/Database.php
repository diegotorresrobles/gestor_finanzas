<?php

namespace App\Core;

use PDO;
use PDOException;

class Database {
  public static function connect() {
    try {
      $dns = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4";
      $user = $_ENV['DB_USER'];
      $pass = $_ENV['DB_PASS'];
      $pdo = new PDO($dns, $user, $pass);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    } catch (PDOException $e) {
      Responses::database(
        404, 
        'Error en la conexion a la base de datos', 
        []
      )->json();
    }
  }
}