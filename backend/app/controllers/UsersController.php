<?php

namespace App\Controllers;

use App\Core\Responses;
use App\DTO\UsersRegisterDTO;
use App\DTO\UsersVerifyDTO;
use App\Services\JwtService;
use App\Services\UsersService;

class UsersController {
  public static function register(array $request) {
    $dto = UsersRegisterDTO::fromArray($request);
    
    $service = new UsersService();
    $service = $service->register($dto);

    (new Responses(
      $service->code,
      $service->message,
      $service->data
    ))->json();
  }

  public static function verify(array $request) {
    $dto = UsersVerifyDTO::fromArray($request);

    $service = new UsersService();
    $service = $service->verify($dto);

    (new Responses(
      $service->code,
      $service->message,
      $service->data
    ))->json();
  }

  public static function get(array $request) {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? null;

    if (!$token || JwtService::validate($token)) {
      Responses::unauthorized(['errors' => ['token' => 'No autorizado']])->json();
    }

    $d = JwtService::validate($token);

    (new Responses(
      200,
      '',
      $d
    ))->json();
  }
}