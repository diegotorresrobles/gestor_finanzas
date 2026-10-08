<?php
// DOM Cloud injects DOMAIN, USERNAME, DATABASE and MYPASSWD into its deploy runner.
if (PHP_SAPI !== 'cli') exit(1);
function fail(string $message): never { fwrite(STDERR, $message."\n"); exit(1); }
if (PHP_VERSION_ID < 80300) fail('Se requiere PHP 8.3 o superior.');
foreach (['pdo_mysql', 'mbstring', 'openssl'] as $extension) if (!extension_loaded($extension)) fail('Falta la extensión '.$extension);
$path = __DIR__.'/../backend/.env';
if (is_file($path)) { chmod($path, 0600); echo "Se conserva la configuración existente.\n"; exit; }
$frontend = rtrim(getenv('FRONTEND_URL') ?: '', '/');
if (!preg_match('~^https://[a-z0-9.-]+$~i', $frontend) || str_contains($frontend, 'TU_SITIO')) fail('Configura FRONTEND_URL con el origen HTTPS real de Netlify.');
$domain = getenv('DOMAIN') ?: '';
if (!preg_match('/^[a-z0-9.-]+$/i', $domain)) fail('DOM Cloud no proporcionó un DOMAIN válido.');
$values = ['APP_ENV'=>'production', 'APP_NAME'=>'DTR Finanzas', 'APP_URL'=>$frontend, 'APP_DOMAIN'=>$domain,
  'APP_LOGO'=>'', 'JWT_KEY'=>bin2hex(random_bytes(32)), 'DB_HOST'=>'localhost',
  'DB_NAME'=>getenv('DATABASE') ?: '', 'DB_USER'=>getenv('USERNAME') ?: '', 'DB_PASS'=>getenv('MYPASSWD') ?: '',
  'MAIL_HOST'=>'', 'MAIL_PORT'=>'587', 'MAIL_USER'=>'', 'MAIL_PASS'=>'', 'MAIL_FROM'=>'', 'MAIL_ENCRYPTION'=>'tls',
  'WS_URL'=>'', 'WS_BIND'=>'127.0.0.1', 'WS_PORT'=>'33002'];
foreach (['DB_NAME','DB_USER','DB_PASS'] as $key) if ($values[$key] === '') fail('DOM Cloud no proporcionó '.$key.'. Habilita mysql antes de ejecutar.');
$contents = '';
foreach ($values as $key=>$value) {
  if (strpbrk($value, "\r\n\0") !== false) fail('Valor de configuración inválido: '.$key);
  $contents .= $key.'="'.str_replace(['\\','"','$'], ['\\\\','\\"','\\$'], $value)."\"\n";
}
require __DIR__.'/../backend/vendor/autoload.php';
Dotenv\Dotenv::parse($contents);
umask(0077);
$handle = fopen($path, 'x');
if (!$handle) fail('No se pudo crear .env; no se sobrescribió ningún archivo.');
if (fwrite($handle, $contents) !== strlen($contents)) { fclose($handle); fail('No se pudo completar .env. Revisa el archivo antes de continuar.'); }
fclose($handle);
echo "Configuración privada creada; JWT generado y dominio detectado automáticamente.\n";
