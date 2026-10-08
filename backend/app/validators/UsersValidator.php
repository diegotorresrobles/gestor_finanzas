<?php

namespace App\Validators;

class UsersValidator extends Validator {
  public static function register(object $data) {
    return self::make($data, [
      'nombre' => [
        'required'
      ],
      'apellido' => [
        'required'
      ],
      'correo' => [
        'required',
        'email'
      ],
      'password' => [
        'required',
      ]
    ]);
  }
  public static function verify(object $data) {
    return self::make($data, [
      'correo' => [
        'required',
        'email'
      ],
      'token' => [
        'required',
        'size:64'
      ]
    ]);
  }
}