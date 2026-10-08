<?php

namespace App\Controllers;

use App\DTO\CuentasCreateDTO;
use App\Services\CuentasService;

class CuentasController {
  public static function find(array $request) { (new CuentasService())->find($request['params']['id'], $request['auth']['user_id'])->json(); }
  public static function update(array $request) { (new CuentasService())->update($request['params']['id'], CuentasCreateDTO::fromArray($request), $request['auth']['user_id'])->json(); }

  public static function get(array $request) {
    (new CuentasService())->get($request['auth']['user_id'])->json();
  }

  public static function create(array $request) {
    $dto = CuentasCreateDTO::fromArray($request);
    (new CuentasService())->create($dto, $request['auth']['user_id'])->json();
  }

  public static function tipos(array $request) {
    (new CuentasService())->tipos()->json();
  }

  public static function delete(array $request) {
    (new CuentasService())->delete($request['id'] ?? null, $request['auth']['user_id'])->json();
  }
}
