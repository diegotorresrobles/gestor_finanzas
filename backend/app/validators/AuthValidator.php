<?php

namespace App\Validators;

class AuthValidator extends Validator {
  public static function login(object $data) {
    return self::make($data, [
      'correo' => [
        'required',
        'email'
      ],
      'password' => [
        'required'
      ]
    ]);
  }
}