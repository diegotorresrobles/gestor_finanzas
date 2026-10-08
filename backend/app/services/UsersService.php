<?php

namespace App\Services;

use App\Classes\Mail;
use App\Core\Responses;
use App\DTO\UsersRegisterDTO;
use App\Repositories\UsersRespository;
use App\Validators\UsersValidator;

class UsersService {
  public function register(UsersRegisterDTO $data) : Responses {
    $isValidate = UsersValidator::register($data);
    if (!$isValidate) {
      return Responses::debt(['errors' => UsersValidator::getErrors()]);
    }
    $isExist = UsersRespository::existByCorreo($data->correo);
    if ($isExist) {
      return Responses::conflict(['errors' => ['correo' => 'Ya existe una cuenta con este correo']]);
    }
    $hash = password_hash($data->password, PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(32));
    $repository = UsersRespository::insert(
      $data,
      $hash,
      $token
    );
    if ($repository) {
      Mail::verify("{$data->nombre} {$data->apellido}", $data->correo, $token);
    }
    return Responses::created($repository);
  }
  
  public function verify(object $data) : Responses {
    $isValidate = UsersValidator::verify($data);
    if (!$isValidate) {
      return Responses::debt(['errors' => UsersValidator::getErrors()]);
    }
    $user = UsersRespository::existByCorreo($data->correo);
    if ($user === false) {
      return Responses::conflict(['errors' => ['correo' => 'El correo no esta registrado']]);
    }
    if ((int)$user->confirmado) {
      return Responses::conflict(['errors' => ['correo' => 'El correo ya esta confirmado']]);
    }
    if ($user->token !== $data->token) {
      return Responses::conflict(['errors' => ['token' => 'El token es invalido']]);
    }
    $repository = UsersRespository::updateVerifyEmail($data, 1);
    return Responses::updated($repository);
  }
}
