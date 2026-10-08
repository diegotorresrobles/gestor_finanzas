<?php

// Run in an isolated process: php backend/tests/account_verification.php
// Replace only the database connection; exercise the real repositories and services.
namespace App\Core {
  class RateLimit { public static function check(string $scope,int $max=12): void {} }
  class Session {
    public static bool $created=false;
    public static function origin(): void {}
    public static function create(array $user): void { self::$created=true; }
  }
  class Database {
    public static array|false $user = false;
    public static array $queries = [];

    public static function connect() {
      return new class {
        public function prepare($query) {
          Database::$queries[] = $query;
          return new class {
            public function execute($params) {
              return true;
            }

            public function fetch($mode) {
              return Database::$user;
            }
          };
        }
      };
    }
  }
}

namespace {
  require __DIR__ . '/../vendor/autoload.php';

  use App\Core\Database;
  use App\DTO\AuthLoginDTO;
  use App\DTO\UsersRegisterDTO;
  use App\DTO\UsersVerifyDTO;
  use App\Repositories\UsersRespository;
  use App\Services\AuthService;
  use App\Services\JwtService;
  use App\Services\UsersService;

  function check(bool $passed, string $message): void {
    if (!$passed) {
      throw new \RuntimeException($message);
    }
  }

  function fixture(array|false $user): void {
    Database::$user = $user;
    Database::$queries = [];
  }

  $_ENV['JWT_KEY'] = str_repeat('test-key-', 8);
  $_ENV['APP_DOMAIN'] = 'localhost';
  $user = [
    'id' => 1, 'nombre' => 'Prueba', 'apellido' => 'Cuenta',
    'correo' => 'test@example.com', 'password' => password_hash('correct-password', PASSWORD_BCRYPT),
    'confirmado' => '0', 'token' => str_repeat('a', 64),
  ];
  $credentials = AuthLoginDTO::fromArray([
    'correo' => $user['correo'], 'password' => 'correct-password',
  ]);

  fixture(false);
  check(UsersRespository::existByCorreo($user['correo']) === false, 'Missing account must return false.');
  check(count(Database::$queries) === 1, 'Lookup must use one query.');

  fixture($user);
  $record = UsersRespository::existByCorreo($user['correo']);
  check($record->confirmado === 0 && $record->token === $user['token'], 'Lookup must return verification fields.');
  check(str_contains(Database::$queries[0], 'confirmado, token'), 'Query must select verification fields.');

  foreach ([false, $user, array_replace($user, ['confirmado' => '1'])] as $account) {
    fixture($account);
    $response = (new AuthService())->login($credentials);
    check(count(Database::$queries) === 1, 'Login must use one query.');
    if ($account === false) {
      check($response->code === 409 && !isset($response->data['token']), 'Missing account must not log in.');
    } elseif ($account['confirmado'] === '0') {
      check($response->code === 401 && isset($response->data['errors']['correo']) && !isset($response->data['token']), 'Unverified account must not receive a JWT.');
    } else {
      check($response->code === 200 && App\Core\Session::$created && $response->data['user']['id'] === 1 && !isset($response->data['token']), 'Verified account must create a cookie session without exposing JWT.');
    }
  }

  fixture(array_replace($user, ['confirmado' => '1']));
  $response = (new AuthService())->login(AuthLoginDTO::fromArray([
    'correo' => $user['correo'], 'password' => 'wrong-password',
  ]));
  check($response->code === 409 && !isset($response->data['token']), 'Wrong password must not log in.');

  foreach ([false, array_replace($user, ['confirmado' => '1']), $user] as $account) {
    fixture($account);
    $response = (new UsersService())->verify(UsersVerifyDTO::fromArray([
      'correo' => $user['correo'], 'token' => $user['token'],
    ]));
    $canVerify = $account !== false && $account['confirmado'] === '0';
    check($response->code === ($canVerify ? 200 : 409), 'Verification must handle absent, confirmed and pending accounts.');
    check(count(Database::$queries) === ($canVerify ? 2 : 1), 'Verification must use one lookup and only update when needed.');
  }

  fixture($user);
  $response = (new UsersService())->verify(UsersVerifyDTO::fromArray([
    'correo' => $user['correo'], 'token' => str_repeat('b', 64),
  ]));
  check($response->code === 409 && count(Database::$queries) === 1, 'Invalid token must not update the account.');

  fixture($user);
  $response = (new UsersService())->register(UsersRegisterDTO::fromArray($user));
  check($response->code === 409 && count(Database::$queries) === 1, 'Existing account must still block duplicate registration.');

  echo "Account verification checks passed.\n";
}
