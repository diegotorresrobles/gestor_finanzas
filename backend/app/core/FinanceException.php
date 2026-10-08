<?php

namespace App\Core;

use RuntimeException;

class FinanceException extends RuntimeException {
  public function __construct(string $message, int $status = 409, public readonly array $errors = []) {
    parent::__construct($message, $status);
  }
}
