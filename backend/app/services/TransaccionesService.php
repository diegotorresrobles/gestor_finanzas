<?php

namespace App\Services;

use App\Core\FinanceException;
use App\Core\Responses;
use App\DTO\TransaccionesDTO;
use App\Repositories\TransaccionesRepository;
use App\Validators\TransaccionesValidator;
use PDOException;

class TransaccionesService {
  public function delete(mixed $id, int $userId): Responses {
    if (!TransaccionesValidator::id($id)) return Responses::debt(['errors'=>['id'=>'ID inválido']]);
    try { TransaccionesRepository::delete((int)$id,$userId); return Responses::ok(['id'=>(int)$id]); }
    catch (FinanceException $error) { return new Responses($error->getCode(),$error->getMessage(),['errors'=>$error->errors]); }
    catch (PDOException $error) { return Responses::database(500,'No se pudo eliminar el movimiento',[]); }
  }
  public function get(int $userId, ?int $limit = null): Responses {
    try { return Responses::ok(TransaccionesRepository::get($userId,null,$limit)); }
    catch (PDOException $error) { return Responses::database(500, 'Error al consultar las transacciones', []); }
  }

  public function find(mixed $id, int $userId): Responses {
    if (!TransaccionesValidator::id($id)) return Responses::debt(['errors' => ['id' => 'ID inválido']]);
    try {
      $transaction = TransaccionesRepository::find((int) $id, $userId);
      return $transaction ? Responses::ok($transaction) : new Responses(404, 'La transacción no existe', []);
    } catch (PDOException $error) { return Responses::database(500, 'Error al consultar la transacción', []); }
  }

  public function categorias(): Responses {
    try { return Responses::ok(TransaccionesRepository::categorias()); }
    catch (PDOException $error) { return Responses::database(500, 'Error al consultar las categorías', []); }
  }

  public function save(TransaccionesDTO $data, int $userId, mixed $id = null): Responses {
    if ($id !== null && !TransaccionesValidator::id($id)) return Responses::debt(['errors' => ['id' => 'ID inválido']]);
    if (!TransaccionesValidator::validate($data)) return Responses::debt(['errors' => TransaccionesValidator::getErrors()]);
    try {
      $transaction = TransaccionesRepository::save($data, $userId, $id === null ? null : (int) $id);
      return new Responses($id === null ? 201 : 200, $id === null ? 'Transacción creada correctamente' : 'Transacción actualizada correctamente', $transaction);
    } catch (FinanceException $error) {
      return new Responses($error->getCode(), $error->getMessage(), ['errors' => $error->errors]);
    } catch (PDOException $error) { return Responses::database(500, 'Error al guardar la transacción', []); }
  }
}
