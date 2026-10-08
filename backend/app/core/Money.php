<?php

namespace App\Core;

class Money {
  public const MAX_CENTS = 999999999999;

  public static function cents(mixed $value): int|false {
    if (!is_int($value) && !is_string($value) && !is_float($value)) return false;
    if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/', (string) $value, $parts)) return false;
    $cents = (int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0');
    if ($cents > self::MAX_CENTS) return false;
    return $parts[1] === '-' ? -$cents : $cents;
  }

  public static function decimal(int $cents): string {
    $absolute = abs($cents);
    return ($cents < 0 ? '-' : '') . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
  }
}
