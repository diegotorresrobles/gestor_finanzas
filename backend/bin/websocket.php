<?php
// Development WebSocket worker. In production, expose it through a TLS reverse proxy.
require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/..')->load();
use App\Core\Database;
$db=Database::connect();
$server=stream_socket_server('tcp://'.($_ENV['WS_BIND']??'127.0.0.1').':'.($_ENV['WS_PORT']??'3002'),$errno,$error);
if (!$server) { fwrite(STDERR,"Cannot start WebSocket listener.\n"); exit(1); }
stream_set_blocking($server,false); $clients=[]; $tick=0;
function frame(string $body,int $opcode=1): string {
  $len=strlen($body); return chr(0x80|$opcode).($len<126 ? chr($len) : chr(126).pack('n',$len)).$body;
}
function drop(array &$clients,int $id): void { fclose($clients[$id]['socket']); unset($clients[$id]); }
echo "DTR WebSocket worker ready.\n";
while (true) {
  $read=[$server]; $write=[];
  foreach ($clients as $client) { $read[]=$client['socket']; if ($client['output']!=='') $write[]=$client['socket']; }
  $except=null; if (@stream_select($read,$write,$except,0,250000)===false) continue;
  foreach ($write as $socket) {
    $id=(int)$socket; $sent=@fwrite($socket,$clients[$id]['output']);
    if ($sent===false) { drop($clients,$id); continue; }
    $clients[$id]['output']=substr($clients[$id]['output'],$sent);
  }
  foreach ($read as $socket) {
    if ($socket===$server) {
      $new=@stream_socket_accept($server,0);
      if ($new) {
        if (count($clients)>=200) { fclose($new); continue; }
        stream_set_blocking($new,false); $clients[(int)$new]=['socket'=>$new,'phase'=>'http','buffer'=>'','output'=>'','start'=>time(),'seen'=>time(),'ping'=>time(),'session'=>null,'revision'=>null];
      }
      continue;
    }
    $id=(int)$socket; $chunk=@fread($socket,8192);
    if ($chunk===false || ($chunk==='' && feof($socket))) { drop($clients,$id); continue; }
    $clients[$id]['buffer'].=$chunk;
    if (strlen($clients[$id]['buffer'])>16384) { drop($clients,$id); continue; }
    $c=&$clients[$id];
    if ($c['phase']==='http') {
      $end=strpos($c['buffer'],"\r\n\r\n"); if ($end===false) continue;
      $headers=substr($c['buffer'],0,$end); $c['buffer']=substr($c['buffer'],$end+4);
      preg_match('/^Origin:\s*(.+)$/mi',$headers,$origin); preg_match('/^Sec-WebSocket-Key:\s*(.+)$/mi',$headers,$key);
      if (($origin[1]??'')=== '' || trim($origin[1])!==rtrim($_ENV['APP_URL'],'/') || !preg_match('~^GET /(?: HTTP/1\.1)~',$headers) || !preg_match('/^Upgrade:\s*websocket\s*$/mi',$headers) || !preg_match('/^Sec-WebSocket-Version:\s*13\s*$/mi',$headers) || strlen(base64_decode(trim($key[1]??''),true)?:'')!==16) { drop($clients,$id); continue; }
      $accept=base64_encode(sha1(trim($key[1]).'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
      $c['output'].="HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $accept\r\n\r\n"; $c['phase']='auth';
    }
    while (isset($clients[$id]) && strlen($c['buffer'])>=2) {
      $b=$c['buffer']; $first=ord($b[0]); $second=ord($b[1]); $opcode=$first&15; $len=$second&127; $offset=2;
      if (!($first&128) || ($first&112) || !($second&128) || $len===127 || !in_array($opcode,[1,8,9,10],true)) { drop($clients,$id); break; }
      if ($len===126) { if (strlen($b)<4) break; $len=unpack('n',substr($b,2,2))[1]; $offset=4; }
      if ($len>4096 || ($opcode>=8 && $len>125)) { drop($clients,$id); break; }
      if (strlen($b)<$offset+4+$len) break;
      $mask=substr($b,$offset,4); $body=substr($b,$offset+4,$len); $c['buffer']=substr($b,$offset+4+$len);
      for ($i=0;$i<$len;$i++) $body[$i]=$body[$i]^$mask[$i%4];
      $c['seen']=time();
      if ($opcode===8) { drop($clients,$id); break; }
      if ($opcode===9) { $c['output'].=frame($body,10); continue; }
      if ($opcode===10) continue;
      if ($c['phase']!=='auth') { drop($clients,$id); break; }
      $message=json_decode($body,true); $ticket=$message['ticket']??'';
      if (!is_string($ticket) || !preg_match('/^[a-f0-9]{64}$/',$ticket)) { drop($clients,$id); break; }
      try {
        $db->beginTransaction();
        $stmt=$db->prepare('SELECT s.id,s.user_id FROM ws_tickets t JOIN auth_sessions s ON s.id=t.session_id WHERE t.token_hash=:hash AND t.expires_at>NOW() AND s.expires_at>NOW() FOR UPDATE');
        $stmt->execute(['hash'=>hash('sha256',$ticket)]); $session=$stmt->fetch(PDO::FETCH_ASSOC);
        $stmt=$db->prepare('DELETE FROM ws_tickets WHERE token_hash=:hash'); $stmt->execute(['hash'=>hash('sha256',$ticket)]); $db->commit();
        if (!$session) { drop($clients,$id); break; }
        $c['session']=$session; $c['phase']='ready'; $c['output'].=frame('{"type":"ready"}');
      } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); drop($clients,$id); break; }
    }
    unset($c);
  }
  if ($tick!==time()) {
    $tick=time();
    foreach (array_keys($clients) as $id) {
      $c=&$clients[$id];
      if (($c['phase']!=='ready' && time()-$c['start']>30) || time()-$c['seen']>60 || strlen($c['output'])>32768) { drop($clients,$id); continue; }
      if ($c['phase']!=='ready') continue;
      try {
        $stmt=$db->prepare('SELECT s.user_id,u.rol,COALESCE(r.revision,0) AS revision FROM auth_sessions s JOIN users u ON u.id=s.user_id LEFT JOIN user_revisions r ON r.user_id=s.user_id WHERE s.id=:id AND s.expires_at>NOW()');
        $stmt->execute(['id'=>$c['session']['id']]); $state=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$state) { drop($clients,$id); continue; }
        if ($c['revision']!==$state['revision']) { $c['output'].=frame('{"type":"changed"}'); $c['revision']=$state['revision']; }
        if ($state['rol']==='admin' && $tick%30===0) $c['output'].=frame('{"type":"changed"}');
        if ($tick-$c['ping']>=20) { $c['output'].=frame('',9); $c['ping']=$tick; }
      } catch (Throwable $e) { drop($clients,$id); }
      unset($c);
    }
  }
}
