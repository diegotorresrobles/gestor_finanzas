<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\Responses;
use App\DTO\UsersAllDTO;
use App\DTO\UsersVerifyDTO;
use PDO;
use PDOException;
class UsersRespository {
  public static function insert($data, $hash, $token) {
    try {
      $db = Database::connect();
      $query = "INSERT INTO users (nombre, apellido, correo, telefono, username, password, token) VALUES (:nombre, :apellido, :correo, :telefono, :username, :password, :token)";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'nombre' => $data->nombre,
        'apellido' => $data->apellido,
        'correo' => $data->correo,
        'telefono' => $data->telefono ?? '',
        'username' => $data->username ?? '',
        'password' => $hash,
        'token' => $token
      ]);

      return $r;
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        []
      )->json();
    }
  }
  public static function updateVerifyEmail($data, $confirmado) {
    try {
      $db = Database::connect();
      $query = "UPDATE users SET confirmado = :confirmado, token = :token WHERE correo = :correo AND confirmado = '0'";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'confirmado' => $confirmado,
        'token' => null,
        'correo' => $data->correo
      ]);
      return $r;
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        []
      )->json();
    }
  }
  public static function findByCorreo($correo) : array {
    try {
      $db = Database::connect();
      $query = "SELECT id, nombre, apellido, correo, telefono, username, password, confirmado, created_at, updated_at FROM users WHERE correo = :correo";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'correo' => $correo,
      ]);

      return array_map(
        fn ($row) => UsersAllDTO::fromArray($row),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
      );
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        []
      )->json();
    }
    return [];
  }
  public static function findVerify($correo) : array {
    try {
      $db = Database::connect();
      $query = "SELECT id, correo, confirmado, token FROM users WHERE correo = :correo";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'correo' => $correo,
      ]);
      return array_map(
        fn ($row) => UsersVerifyDTO::fromArray($row),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
      );
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        []
      )->json();
    }
    return [];
  }
  public static function existByCorreo($correo) : UsersAllDTO|false {
    try {
      $db = Database::connect();
      $query = "SELECT id, nombre, apellido, correo, telefono, username, password, confirmado, token, rol, created_at, updated_at FROM users WHERE correo = :correo LIMIT 1";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'correo' => $correo,
      ]);

      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      return $user === false ? false : UsersAllDTO::fromArray($user);
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        []
      )->json();
    }
    return false;
  }
  public static function existByToken($token) : bool {
    try {
      $db = Database::connect();
      $query = "SELECT id, token FROM users WHERE token = :token";
      $stmt = $db->prepare($query);
      $r = $stmt->execute([
        'token' => $token,
      ]);

      return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
      Responses::database(
        500,
        'Error en la base de datos',
        []
      )->json();
    }
    return false;
  }
}
