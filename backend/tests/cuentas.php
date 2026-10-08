<?php

// Exercise the real router, middleware, controller, service and repository without modifying MySQL.
namespace App\Core {
  // Cookie/session verification has separate MySQL integration coverage.
  class Session {
    public static function origin(): void {}
    public static function user(): array|false {
      $p=\App\Services\JwtService::validate($_COOKIE['dtr_access']??'');
      if (!$p || ($p['iss']??null)!==$_ENV['APP_DOMAIN'] || !isset($p['exp']) || $p['exp']<=time() || (int)($p['sub']??0)<=0) return false;
      return ['id'=>(int)$p['sub'],'rol'=>'user','session_id'=>str_repeat('a',64)];
    }
  }
  class Credit { public static function limit($db,int $type,mixed $limit,mixed $balance): ?string { return null; } }
  class Events { public static function changed($db,int $id): void {} }

  class Database {
    public static function connect() {
      return new class {
        public function beginTransaction() { return true; }
        public function commit() { return true; }
        public function rollBack() { return true; }
        public function inTransaction() { return true; }
        public function query($query) {
          if ($query !== 'SELECT id, tipo FROM tipo_cuenta ORDER BY id') throw new \RuntimeException('Unexpected catalog query.');
          return new class { public function fetchAll($mode) { return [['id' => 1, 'tipo' => 'Efectivo']]; } };
        }
        public function prepare($query) {
          return new class($query) {
            public function __construct(private string $query) {}
            public function execute($params) {
              if (str_contains($this->query, 'cuentas') && ($params['user_id'] ?? null) !== 7) {
                throw new \RuntimeException('Accounts must belong to the authenticated user.');
              }
              if (str_starts_with($this->query, 'SELECT') && str_contains($this->query, 'FROM cuentas') && !str_contains($this->query, 'WHERE user_id = :user_id')) {
                throw new \RuntimeException('Account queries must filter by user.');
              }
              if (($_SERVER['TEST_CASE'] ?? '') === 'database-error') throw new \PDOException('Private database information');
              if (str_starts_with($this->query, 'DELETE') && !str_contains($this->query, 'WHERE id = :id AND user_id = :user_id')) throw new \RuntimeException('Deletion must filter by owner.');
              return true;
            }
            public function rowCount() { return ($_SERVER['TEST_CASE'] ?? '') === 'delete-not-owned' ? 0 : 1; }
            public function fetchColumn() {
              return ($_SERVER['TEST_CASE'] ?? '') === 'unknown-type' ? false : 1;
            }
            public function fetchAll($mode) {
              return [['id' => 11, 'user_id' => 7, 'nombre' => 'Efectivo']];
            }
          };
        }
        public function lastInsertId() { return '11'; }
      };
    }
  }
}

namespace {
  require __DIR__ . '/../vendor/autoload.php';

  use App\Core\Router;
  use Firebase\JWT\JWT;

  $case = $argv[1] ?? null;
  if ($case !== null) {
    $_ENV['JWT_KEY'] = str_repeat('test-key-', 8);
    $_ENV['APP_DOMAIN'] = 'localhost';
    $_SERVER['TEST_CASE'] = $case;
    $_SERVER['REQUEST_METHOD'] = $case === 'get' ? 'GET' : 'POST';
    $_SERVER['REQUEST_URI'] = '/api/cuentas';
    $_POST = ['nombre' => ' Efectivo ', 'tipo_cuenta_id' => 1, 'color' => '#aabbcc', 'user_id' => 999, 'auth' => ['user_id' => 999]];
    if (str_starts_with($case, 'delete')) {
      $_SERVER['REQUEST_METHOD'] = 'DELETE';
      $_POST['id'] = $case === 'delete-invalid-id' ? [] : 11;
    }
    if ($case === 'tipos') {
      $_SERVER['REQUEST_METHOD'] = 'GET';
      $_SERVER['REQUEST_URI'] = '/api/cuentas/tipos';
    }
    $payload = ['iss' => 'localhost', 'sub' => '7', 'iat' => time() - 60, 'exp' => time() + 3600];
    if ($case === 'expired') $payload['exp'] = time() - 10;
    if ($case === 'invalid-user') $payload['sub'] = '0';
    if ($case === 'wrong-issuer') $payload['iss'] = 'other-domain';
    if ($case === 'no-expiration') unset($payload['exp']);
    $token = JWT::encode($payload, $_ENV['JWT_KEY'], 'HS256');
    $_COOKIE['dtr_access'] = $token;
    if ($case === 'missing-token') unset($_COOKIE['dtr_access']);
    if ($case === 'invalid-token') $_COOKIE['dtr_access'] = 'invalid';
    if ($case === 'wrong-signature') $_COOKIE['dtr_access'] = JWT::encode($payload, str_repeat('other-key', 8), 'HS256');
    if ($case === 'missing-fields') $_POST = [];
    if ($case === 'invalid-fields') $_POST = ['nombre' => [], 'tipo_cuenta_id' => true, 'balance' => '1.5', 'color' => []];
    if ($case === 'overflow') $_POST['balance'] = '10000000000';
    if ($case === 'null-balance') $_POST['balance'] = null;
    if ($case === 'negative-balance') $_POST['balance'] = -100;
    if ($case === 'long-name') $_POST['nombre'] = str_repeat('á', 256);
    register_shutdown_function(function () {
      fwrite(STDERR, (string) http_response_code());
    });
    $router = new Router();
    require __DIR__ . '/../app/routes/Web.php';
    $router->load();
    exit;
  }

  $cases = [
    'create' => 201, 'get' => 200, 'negative-balance' => 201,
    'missing-token' => 401, 'invalid-token' => 401, 'expired' => 401,
    'wrong-signature' => 401, 'invalid-user' => 401, 'wrong-issuer' => 401, 'no-expiration' => 401,
    'missing-fields' => 409, 'invalid-fields' => 409, 'unknown-type' => 409,
    'overflow' => 409, 'long-name' => 409, 'null-balance' => 409, 'database-error' => 500,
    'delete' => 200, 'delete-not-owned' => 404, 'delete-with-movements' => 200, 'delete-invalid-id' => 409, 'tipos' => 200,
  ];
  foreach ($cases as $name => $expected) {
    $process = proc_open([PHP_BINARY, __FILE__, $name], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]);
    $status = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $json = json_decode($output, true);
    if ($exit !== 0 || (int) $status !== $expected || !is_array($json) || $json['ok'] !== ($expected < 400)) {
      throw new \RuntimeException("Failed {$name}: {$status} {$output}");
    }
    if ($expected === 201 && ($json['data']['user_id'] !== 7 || $json['data']['nombre'] !== 'Efectivo' || $json['data']['color'] !== 'AABBCC' || $json['data']['balance'] !== ($name === 'negative-balance' ? '-100.00' : '0.00'))) {
      throw new \RuntimeException('Account data or ownership is incorrect.');
    }
    if ($expected >= 400 && str_contains($output, 'Private database information')) throw new \RuntimeException('Database errors must not leak details.');
  }
  echo count($cases) . " account endpoint checks passed.\n";
}
