<?php

namespace App\Services;

use App\Core\Responses;
use App\Core\FinanceException;
use App\DTO\CuentasCreateDTO;
use App\Repositories\CuentasRepository;
use App\Repositories\TransaccionesRepository;
use App\Validators\TransaccionesValidator;
use App\Validators\CuentasValidator;
use PDOException;

class CuentasService {
  public function find(mixed $id, int $userId): Responses {
    if (!TransaccionesValidator::id($id)) return Responses::debt(['errors' => ['id' => 'ID inválido']]);
    try {
      $account = CuentasRepository::find((int) $id, $userId);
      if (!$account) return new Responses(404, 'La cuenta no existe', []);
      $account['transacciones'] = TransaccionesRepository::get($userId, (int) $id);
      return Responses::ok($account);
    } catch (PDOException $error) { return Responses::database(500, 'Error al consultar la cuenta', []); }
  }

  public function update(mixed $id, CuentasCreateDTO $data, int $userId): Responses {
    if (!TransaccionesValidator::id($id)) return Responses::debt(['errors' => ['id' => 'ID inválido']]);
    if (!CuentasValidator::create($data)) return Responses::debt(['errors' => CuentasValidator::getErrors()]);
    try {
      if (!CuentasRepository::existTipo((int) $data->tipo_cuenta_id)) return Responses::conflict(['errors' => ['tipo_cuenta_id' => 'El tipo de cuenta no existe']]);
      if (!CuentasRepository::update((int) $id, $data, $userId)) return new Responses(404, 'La cuenta no existe', []);
      return Responses::updated(['id' => (int) $id]);
    } catch (FinanceException $error) { return new Responses($error->getCode(), $error->getMessage(), ['errors' => $error->errors]); }
    catch (PDOException $error) { return Responses::database(500, 'Error al actualizar la cuenta', []); }
  }

  public function tipos(): Responses {
    try {
      return Responses::ok(CuentasRepository::getTipos());
    } catch (PDOException $e) {
      return Responses::database(500, 'Error al consultar los tipos de cuenta', []);
    }
  }

  public function delete(mixed $id, int $userId): Responses {
    if ((!is_int($id) && !is_string($id)) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
      return Responses::debt(['errors' => ['id' => 'El ID de la cuenta debe ser un entero positivo']]);
    }
    try {
      if (!CuentasRepository::delete((int) $id, $userId)) {
        return new Responses(404, 'La cuenta no existe', []);
      }
      return Responses::ok(['id' => (int) $id]);
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? null) === 1451) {
        return Responses::conflict(['errors' => ['cuenta' => 'No puedes eliminar una cuenta con movimientos registrados']]);
      }
      return Responses::database(500, 'Error al eliminar la cuenta', []);
    }
  }

  public function get(int $userId): Responses {
    try {
      return Responses::ok(CuentasRepository::getByUserId($userId));
    } catch (PDOException $e) {
      return Responses::database(500, 'Error al consultar las cuentas', []);
    }
  }

  public function create(CuentasCreateDTO $data, int $userId): Responses {
    if (!CuentasValidator::create($data)) {
      return Responses::debt(['errors' => CuentasValidator::getErrors()]);
    }

    try {
      if (!CuentasRepository::existTipo((int) $data->tipo_cuenta_id)) {
        return Responses::conflict(['errors' => ['tipo_cuenta_id' => 'El tipo de cuenta no existe']]);
      }
      return new Responses(201, 'Cuenta creada correctamente', CuentasRepository::insert($data, $userId));
    } catch (FinanceException $error) { return new Responses($error->getCode(), $error->getMessage(), ['errors' => $error->errors]); }
    catch (PDOException $e) {
      return Responses::database(500, 'Error al crear la cuenta', []);
    }
  }
}
