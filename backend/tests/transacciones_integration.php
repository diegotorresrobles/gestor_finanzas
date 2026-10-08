<?php

// Integration test against configured MySQL. Fixtures and all changes roll back at the end.
namespace App\Core {
  class TestPDO extends \PDO {
    private int $depth = 0;
    public function beginTransaction(): bool {
      if ($this->depth === 0) parent::beginTransaction();
      else $this->exec('SAVEPOINT finance_test_' . ($this->depth + 1));
      $this->depth++;
      return true;
    }
    public function commit(): bool {
      if ($this->depth === 1) parent::commit();
      else $this->exec('RELEASE SAVEPOINT finance_test_' . $this->depth);
      $this->depth--;
      return true;
    }
    public function rollBack(): bool {
      if ($this->depth === 1) parent::rollBack();
      else $this->exec('ROLLBACK TO SAVEPOINT finance_test_' . $this->depth);
      $this->depth--;
      return true;
    }
  }
  class Database {
    public static TestPDO $pdo;
    public static function connect() { return self::$pdo; }
  }
}

namespace {
  require __DIR__ . '/../vendor/autoload.php';
  \Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
  use App\Core\Database;
  use App\Core\Money;
  use App\Core\TestPDO;
  use App\DTO\CuentasCreateDTO;
  use App\DTO\TransaccionesDTO;
  use App\Repositories\CuentasRepository;
  use App\Services\CuentasService;
  use App\Services\TransaccionesService;

  $checks = 0;
  function check(bool $passed, string $message): void {
    global $checks;
    $checks++;
    if (!$passed) throw new \RuntimeException($message);
  }
  Database::$pdo = new TestPDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4', $_ENV['DB_USER'], $_ENV['DB_PASS'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
  $db = Database::$pdo;
  $db->beginTransaction();
  try {
    $users = [];
    $stmt = $db->prepare("INSERT INTO users (nombre, apellido, correo, telefono, username, password, confirmado) VALUES ('Test', 'Finance', :email, '', '', :password, '1')");
    foreach ([1, 2] as $index) {
      $stmt->execute(['email' => 'finance-test-' . bin2hex(random_bytes(12)) . '@example.test', 'password' => password_hash('test-only', PASSWORD_BCRYPT)]);
      $users[] = (int) $db->lastInsertId();
    }
    $types = $db->query('SELECT id, tipo FROM tipo_cuenta')->fetchAll(\PDO::FETCH_KEY_PAIR);
    $bankType = (int) array_search('Bancaria', $types, true);
    $creditType = (int) array_search('Tarjeta de Credito', $types, true);
    check($bankType > 0, 'Bancaria must be in the catalog.');
    $category = (int) $db->query('SELECT id FROM categorias ORDER BY id LIMIT 1')->fetchColumn();
    $accountService = new CuentasService();
    $service = new TransaccionesService();
    $createAccount = function (string $name, string $balance, int $type, int $owner) use ($accountService): int {
      $response = $accountService->create(CuentasCreateDTO::fromArray(['nombre' => $name, 'tipo_cuenta_id' => $type, 'balance' => $balance, 'limite_credito' => '1000.00', 'color' => '1D4ED8']), $owner);
      check($response->code === 201, 'Account creation failed.');
      return $response->data['id'];
    };
    $bank = $createAccount('Test bank', '1000.00', $bankType, $users[0]);
    $credit = $createAccount('Test credit', '0.00', $creditType, $users[0]);
    $cash = $createAccount('Test cash', '50.00', 1, $users[0]);
    $foreign = $createAccount('Other user', '70.00', $bankType, $users[1]);
    $balance = function (int $id) use ($db): string {
      $stmt = $db->prepare('SELECT balance FROM cuentas WHERE id = :id'); $stmt->execute(['id' => $id]); return $stmt->fetchColumn();
    };
    $dto = function (string $type, int $source, string $amount, ?int $destination = null) use ($category): TransaccionesDTO {
      return TransaccionesDTO::fromArray(['tipo' => $type, 'cuenta_id' => $source, 'cuenta_destino_id' => $destination, 'categoria_id' => $category, 'monto' => $amount, 'fecha' => '2026-10-08', 'descripcion' => 'Integration fixture']);
    };
    $expense = $service->save($dto('gasto', $credit, '25.75'), $users[0]);
    check($expense->code === 201 && $balance($credit) === '-25.75', 'Credit expenses must create negative debt.');
    $income = $service->save($dto('ingreso', $credit, '5.25'), $users[0]);
    check($income->code === 201 && $balance($credit) === '-20.50', 'Income must reduce credit debt.');
    $transfer = $service->save($dto('transferencia', $bank, '100.00', $credit), $users[0]);
    check($transfer->code === 201 && $balance($bank) === '900.00' && $balance($credit) === '79.50', 'A payment must debit source and credit destination.');
    check(Money::cents($balance($bank)) + Money::cents($balance($credit)) + Money::cents($balance($cash)) === 102950, 'Transfer must preserve the total balance.');

    $edited = $service->save($dto('gasto', $bank, '30.25'), $users[0], $expense->data['id']);
    check($edited->code === 200 && $balance($bank) === '869.75' && $balance($credit) === '105.25', 'Editing must reverse the old account effect.');
    $edited = $service->save($dto('transferencia', $cash, '20.10', $bank), $users[0], $transfer->data['id']);
    check($edited->code === 200 && $balance($bank) === '989.85' && $balance($credit) === '5.25' && $balance($cash) === '29.90', 'Editing a transfer must reverse both old sides and apply both new sides.');
    $service->save($dto('gasto', $bank, '30.25'), $users[0], $expense->data['id']);
    check($balance($bank) === '989.85', 'Repeated edits must not double count.');
    $edited = $service->save($dto('transferencia', $credit, '5.25', $cash), $users[0], $income->data['id']);
    check($edited->code === 200 && $balance($credit) === '-5.25' && $balance($cash) === '35.15', 'Changing income to transfer must reverse the income.');

    $before = [$balance($bank), $balance($credit), $balance($cash), $balance($foreign)];
    check($service->save($dto('transferencia', $bank, '10.00', $foreign), $users[0])->code === 404, 'Cross-user transfers must be rejected.');
    check($service->save($dto('gasto', $foreign, '10.00'), $users[1], $expense->data['id'])->code === 404, 'Other users must not edit a transaction.');
    check($before === [$balance($bank), $balance($credit), $balance($cash), $balance($foreign)], 'Denied operations must leave every balance unchanged.');
    check($service->find($expense->data['id'], $users[1])->code === 404 && $service->get($users[1])->data === [], 'Other users must not read transactions.');
    check($service->save($dto('transferencia', $bank, '1.00', $bank), $users[0])->code === 409, 'Transfers to the same account must be rejected.');
    foreach (['0', '-1', '1.001', '10000000000', [], true] as $amount) {
      $invalid = TransaccionesDTO::fromArray(['tipo' => 'gasto', 'cuenta_id' => $bank, 'categoria_id' => $category, 'monto' => $amount, 'fecha' => '2026-10-08']);
      check($service->save($invalid, $users[0])->code === 409, 'Invalid money input must be rejected.');
    }
    $invalid = TransaccionesDTO::fromArray(['tipo' => 'gasto', 'cuenta_id' => $bank, 'categoria_id' => $category, 'monto' => '1.00', 'fecha' => '2026-02-30']);
    check($service->save($invalid, $users[0])->code === 409, 'Invalid calendar dates must be rejected.');
    foreach (["2026-10-08\0", '0001-01-01', [], '2026-1-1'] as $date) {
      $invalid = TransaccionesDTO::fromArray(['tipo' => 'gasto', 'cuenta_id' => $bank, 'categoria_id' => $category, 'monto' => '1.00', 'fecha' => $date]);
      check($service->save($invalid, $users[0])->code === 409, 'Malformed dates must be rejected without throwing.');
    }
    $invalid = TransaccionesDTO::fromArray(['tipo' => 'gasto', 'cuenta_id' => $bank, 'categoria_id' => 2147483647, 'monto' => '1.00', 'fecha' => '2026-10-08']);
    check($service->save($invalid, $users[0])->code === 409 && $balance($bank) === $before[0], 'Invalid categories must not change balances.');

    $update = CuentasCreateDTO::fromArray(['nombre' => 'Updated bank', 'tipo_cuenta_id' => $bankType, 'balance' => '989.85', 'color' => '#abcdef', 'expected_balance' => '989.85']);
    check($accountService->update($bank, $update, $users[0])->code === 200, 'Account details must be editable.');
    $detail = $accountService->find($bank, $users[0]);
    check($detail->data['nombre'] === 'Updated bank' && $detail->data['color'] === 'ABCDEF' && count($detail->data['transacciones']) === 2, 'Account details must include incoming and outgoing transactions.');
    check($accountService->find($bank, $users[1])->code === 404 && $accountService->update($bank, $update, $users[1])->code === 404, 'Account details and edits must be owner scoped.');
    $stale = CuentasCreateDTO::fromArray(['nombre' => 'Stale', 'tipo_cuenta_id' => $bankType, 'balance' => '1.00', 'color' => 'ABCDEF', 'expected_balance' => '1000.00']);
    check($accountService->update($bank, $stale, $users[0])->code === 409 && $balance($bank) === '989.85', 'A stale account edit must not overwrite a transaction balance.');
    check($accountService->delete($bank, $users[1])->code === 404, 'Other users cannot delete accounts.');

    $maximum = CuentasCreateDTO::fromArray(['nombre' => 'Max', 'tipo_cuenta_id' => 1, 'balance' => '9999999999.99', 'color' => 'ABCDEF']);
    $accountService->update($cash, $maximum, $users[0]);
    check($service->save($dto('transferencia', $bank, '1.00', $cash), $users[0])->code === 409, 'Balance overflow must be rejected.');
    check($balance($bank) === '989.85' && $balance($cash) === '9999999999.99', 'Partial balance updates must roll back on overflow.');
    check(count($service->get($users[0])->data) === 3, 'Rejected transactions must not leave extra records.');

    check($service->delete($expense->data['id'],$users[1])->code===404,'Other users cannot delete movements.');
    check($service->delete($expense->data['id'],$users[0])->code===200 && $balance($bank)==='1020.10','Deleting expense must restore balance.');
    check($service->delete($expense->data['id'],$users[0])->code===404 && $balance($bank)==='1020.10','Repeated delete cannot double count.');
    // Remove the temporary maximum balance before testing reversal.
    $db->prepare('UPDATE cuentas SET balance=:balance WHERE id=:id')->execute(['balance'=>'35.15','id'=>$cash]);
    check($service->delete($income->data['id'],$users[0])->code===200 && $balance($credit)==='0.00' && $balance($cash)==='29.90','Transfer deletion reverses both balances.');
    $simpleIncome=$service->save($dto('ingreso',$bank,'10.00'),$users[0]);
    check($service->delete($simpleIncome->data['id'],$users[0])->code===200 && $balance($bank)==='1020.10','Income deletion subtracts its amount.');
    $over=$service->save($dto('gasto',$credit,'1000.01'),$users[0]);
    check($over->code===409 && $balance($credit)==='0.00','Credit limit rejects expense atomically.');
    $exact=$service->save($dto('gasto',$credit,'1000.00'),$users[0]);
    check($exact->code===201 && $balance($credit)==='-1000.00','Exact credit limit is allowed.');
    $payment=$service->save($dto('ingreso',$credit,'10.00'),$users[0]);
    $smallExpense=$service->save($dto('gasto',$credit,'10.00'),$users[0]);
    check($service->delete($payment->data['id'],$users[0])->code===409 && $balance($credit)==='-1000.00','Income reversal cannot exceed credit limit.');
    check($service->save($dto('transferencia',$credit,'0.01',$bank),$users[0])->code===409 && $balance($bank)==='1020.10','Transfer from credit enforces limit and rolls back destination.');
    $low=CuentasCreateDTO::fromArray(['nombre'=>'Credit','tipo_cuenta_id'=>$creditType,'balance'=>'-1000.00','limite_credito'=>'999.99','color'=>'ABCDEF']);
    check($accountService->update($credit,$low,$users[0])->code===409,'Cannot lower credit limit below debt.');
    $version=$service->find($transfer->data['id'],$users[0])->data['version'];
    $edit=TransaccionesDTO::fromArray(['tipo'=>'transferencia','cuenta_id'=>$cash,'cuenta_destino_id'=>$bank,'categoria_id'=>$category,'monto'=>'20.10','fecha'=>'2026-10-08','expected_version'=>$version]);
    check($service->save($edit,$users[0],$transfer->data['id'])->code===200 && $service->save($edit,$users[0],$transfer->data['id'])->code===409,'Stale edits from a second session are rejected.');
    check($accountService->delete($cash,$users[0])->code===200,'Account deletion preserves movements.');
    $orphan=$service->find($transfer->data['id'],$users[0]);
    check($orphan->code===200 && $orphan->data['cuenta_id']===null && $orphan->data['cuenta_nombre']==='Max','Deleted account reference becomes NULL and name survives.');
    check($service->find($transfer->data['id'],$users[1])->code===404,'Orphan transaction remains private to its owner.');
    check($service->save($dto('ingreso',$bank,'1.00'),$users[0],$transfer->data['id'])->code===409,'Orphan movements cannot be financially reassigned.');
    check($service->delete($transfer->data['id'],$users[0])->code===200 && $balance($bank)==='1000.00','Deleting orphan transfer reverses only surviving account.');
    echo $checks . " transaction integration checks passed; fixtures rolled back.\n";
  } finally { $db->rollBack(); }
}
