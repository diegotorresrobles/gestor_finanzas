<?php

namespace App\Controllers;

use App\DTO\TransaccionesDTO;
use App\Services\TransaccionesService;

class TransaccionesController {
  public static function recent(array $request) { (new TransaccionesService())->get($request['auth']['user_id'],5)->json(); }
  public static function delete(array $request) { (new TransaccionesService())->delete($request['params']['id'], $request['auth']['user_id'])->json(); }
  public static function get(array $request) { (new TransaccionesService())->get($request['auth']['user_id'])->json(); }
  public static function find(array $request) { (new TransaccionesService())->find($request['params']['id'], $request['auth']['user_id'])->json(); }
  public static function categorias(array $request) { (new TransaccionesService())->categorias()->json(); }
  public static function create(array $request) { (new TransaccionesService())->save(TransaccionesDTO::fromArray($request), $request['auth']['user_id'])->json(); }
  public static function update(array $request) { (new TransaccionesService())->save(TransaccionesDTO::fromArray($request), $request['auth']['user_id'], $request['params']['id'])->json(); }
}
