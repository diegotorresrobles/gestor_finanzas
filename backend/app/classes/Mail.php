<?php
namespace App\Classes;
use PHPMailer\PHPMailer\PHPMailer;
class Mail {
  public static function send(string $email,string $subject,string $body): bool {
    try {
      $mail=new PHPMailer(true); $mail->isSMTP();
      $mail->Host=$_ENV['MAIL_HOST']??''; $mail->Port=(int)($_ENV['MAIL_PORT']??587);
      $mail->SMTPAuth=!empty($_ENV['MAIL_USER']); $mail->Username=$_ENV['MAIL_USER']??''; $mail->Password=$_ENV['MAIL_PASS']??'';
      $mail->SMTPSecure=$_ENV['MAIL_ENCRYPTION']??'tls'; $mail->Timeout=10; $mail->CharSet='UTF-8';
      $mail->setFrom($_ENV['MAIL_FROM'],$_ENV['APP_NAME']??'DTR'); $mail->addAddress($email);
      $mail->Subject=$subject; $mail->Body=$body; $mail->isHTML(false); $mail->send(); return true;
    } catch (\Throwable $e) { return false; }
  }
  public static function verify($nombre,$correo,$token): bool {
    $link=rtrim($_ENV['APP_URL'],'/').'/verify-email/t/'.rawurlencode($token).'/e/'.rawurlencode($correo);
    return self::send($correo,'Verifica tu cuenta',"Hola $nombre. Confirma tu cuenta aquí: $link");
  }
}
