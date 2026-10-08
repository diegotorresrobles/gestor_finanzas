<?php 

namespace App\Core;

class Request {
  protected $data;
  public static function all() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
      return json_decode(
        file_get_contents('php://input', true),
        true
      ) ?? [];
    }
    return $_POST;
  }
  public function files($name) {
    return $_FILES[$name] ?? null;
  }
}