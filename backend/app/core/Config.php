<?php
use Dotenv\Dotenv;
require_once __DIR__.'/../../vendor/autoload.php';
Dotenv::createImmutable(__DIR__.'/../../')->load();
$origin=rtrim($_ENV['APP_URL']??'http://localhost:5173','/');
if (($_SERVER['HTTP_ORIGIN']??'')===$origin) {
  header('Access-Control-Allow-Origin: '.$origin);
  header('Access-Control-Allow-Credentials: true');
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD','OPTIONS'],true) && ($_SERVER['HTTP_ORIGIN']??'')!==$origin) {
  http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>'Origen no autorizado','data'=>[]]); exit;
}
if (($_SERVER['REQUEST_METHOD']??'')==='OPTIONS') { http_response_code(204); exit; }
