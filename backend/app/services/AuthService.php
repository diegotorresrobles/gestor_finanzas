<?php

namespace App\Services;

use App\Core\Responses;
use App\Core\Session;
use App\Core\RateLimit;
use App\DTO\AuthLoginDTO;
use App\Repositories\UsersRespository;
use App\Validators\AuthValidator;

class AuthService {
  public function login(AuthLoginDTO $data) : Responses {
    Session::origin();
    RateLimit::check('login-ip',60);
    RateLimit::check('login-account:'.hash('sha256',mb_strtolower(trim($data->correo))));
    $isValidate = AuthValidator::login($data);
    if (!$isValidate) {
      return Responses::debt(['errors' => AuthValidator::getErrors()]);
    }

    $user = UsersRespository::existByCorreo($data->correo);
    if ($user === false || !password_verify($data->password, $user->password)) {
      return Responses::conflict(['errors' => ['correo' => 'El correo o la contaseña son incorrectos']]);
    }

    if ($user->confirmado !== 1) {
      return Responses::unauthorized(['errors' => ['correo' => 'Debes verificar tu correo antes de iniciar sesión']]);
    }

    Session::create(['id'=>$user->id,'rol'=>$user->rol]);
    return Responses::ok(['user'=>['id'=>$user->id,'nombre'=>$user->nombre,'rol'=>$user->rol]]);
  }
}
