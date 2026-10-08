"""Real HTTP/cookie/WebSocket checks using disposable generated fixtures only."""
import base64, hashlib, http.cookiejar, json, os, socket, struct, subprocess, urllib.request, urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
PHP=r'C:\php\php.exe'
checks=0
def check(value,label):
 global checks
 checks+=1
 if not value: raise AssertionError(label)
class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar(); self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
 def request(self,path,method='GET',body=None,origin='http://localhost:5173'):
  headers={'Origin':origin,'Content-Type':'application/json'}
  req=urllib.request.Request('http://localhost:3001'+path,data=json.dumps(body).encode() if body is not None else (b'' if method=='POST' else None),headers=headers,method=method)
  try: res=self.opener.open(req,timeout=15)
  except urllib.error.HTTPError as e: res=e
  raw=res.read()
  return res.status, json.loads(raw) if raw else {}
 def login(self,email): return self.request('/api/auth/login','POST',{'correo':email,'password':'Fixture-password-123'})
class WS:
 def __init__(self,ticket,origin='http://localhost:5173'):
  self.socket=socket.create_connection(('127.0.0.1',3002),timeout=5); self.socket.settimeout(5); self.buffer=b''
  key=base64.b64encode(os.urandom(16)).decode()
  self.socket.sendall(f'GET / HTTP/1.1\r\nHost: localhost:3002\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: {key}\r\nOrigin: {origin}\r\n\r\n'.encode())
  while b'\r\n\r\n' not in self.buffer:
   part=self.socket.recv(4096)
   if not part: raise ConnectionError('Handshake rejected')
   self.buffer+=part
  head,self.buffer=self.buffer.split(b'\r\n\r\n',1)
  check(b'101 Switching Protocols' in head,'WebSocket handshake')
  payload=json.dumps({'ticket':ticket}).encode(); mask=os.urandom(4)
  self.socket.sendall(bytes([0x81,0x80|len(payload)])+mask+bytes(c^mask[i%4] for i,c in enumerate(payload)))
 def read(self,n):
  while len(self.buffer)<n:
   part=self.socket.recv(4096)
   if not part: raise ConnectionError('Socket closed')
   self.buffer+=part
  value,self.buffer=self.buffer[:n],self.buffer[n:]; return value
 def message(self):
  while True:
   head=self.read(2); length=head[1]&127
   if length==126: length=struct.unpack('!H',self.read(2))[0]
   body=self.read(length)
   if head[0]&15==1: return json.loads(body)
 def close(self): self.socket.close()
def fixture(action,*args):
 result=subprocess.run([PHP,str(ROOT/'backend/tests/http_fixture.php'),action,*args],capture_output=True,text=True,check=True)
 return json.loads(result.stdout) if result.stdout else None
fixtures=[]; sockets=[]
try:
 owner=fixture('create'); fixtures.append(owner)
 foreign=fixture('create'); fixtures.append(foreign)
 a,b,c=Client(),Client(),Client()
 check(a.login(owner['email'])[0]==200 and b.login(owner['email'])[0]==200 and c.login(foreign['email'])[0]==200,'Two independent sessions login')
 check(all(cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite')=='Strict' for cookie in a.jar),'Cookies are HttpOnly and SameSite Strict')
 check(a.request('/api/auth/me')[1]['data']['user']['rol']=='user','Authenticated session')
 check(Client().request('/api/realtime/revision')[0]==401,'Polling revision requires authentication')
 owner_revision=b.request('/api/realtime/revision')[1]['data']['revision']
 foreign_revision=c.request('/api/realtime/revision')[1]['data']['revision']
 a.jar.clear('localhost.local','/api','dtr_access')
 check(a.request('/api/auth/me')[0]==401 and a.request('/api/auth/refresh','POST')[0]==200 and a.request('/api/auth/me')[0]==200,'Expired access renews through refresh cookie')
 check(a.request('/api/admin/metrics')[0]==403 and a.request('/api/admin/settings')[0]==403,'Users cannot access administration')
 check(a.request('/api/users')[0]==404,'No user listing endpoint')
 check(a.request('/api/cuentas','POST',{'nombre':'Rejected','tipo_cuenta_id':1,'balance':'0','color':'1D4ED8'},origin='http://untrusted.example')[0]==403,'Cross-origin mutations are blocked')
 account=a.request('/api/cuentas','POST',{'nombre':'HTTP cash','tipo_cuenta_id':1,'balance':'100','color':'1D4ED8'})[1]['data']
 check(c.request('/api/cuentas/'+str(account['id']))[0]==404 and c.request('/api/cuentas')[1]['data']==[],'Financial data stays private')
 category=a.request('/api/transacciones/categorias')[1]['data'][0]['id']
 ticket=b.request('/api/realtime/ticket','POST')[1]['data']['ticket']; ws=WS(ticket); sockets.append(ws)
 check(ws.message()['type']=='ready' and ws.message()['type']=='changed','Authenticated WebSocket ready')
 foreign_ws=WS(c.request('/api/realtime/ticket','POST')[1]['data']['ticket']); sockets.append(foreign_ws)
 check(foreign_ws.message()['type']=='ready' and foreign_ws.message()['type']=='changed','Other user authenticates only its own channel')
 replay=WS(ticket); sockets.append(replay)
 try: replay.message(); check(False,'Reused ticket must fail')
 except ConnectionError: check(True,'One-use WebSocket ticket')
 movement=a.request('/api/transacciones','POST',{'tipo':'gasto','cuenta_id':account['id'],'categoria_id':category,'monto':'10','fecha':'2026-10-08'})[1]['data']
 revision=b.request('/api/realtime/revision')[1]['data']
 check(set(revision)=={'revision'} and int(revision['revision'])>int(owner_revision),'Polling sees committed changes without financial information')
 check(c.request('/api/realtime/revision')[1]['data']['revision']==foreign_revision,'Polling revisions are isolated by user')
 check(ws.message()['type']=='changed','Second session receives committed financial update through WebSocket')
 foreign_ws.socket.settimeout(1.5)
 try: foreign_ws.message(); check(False,'Other user must not receive the financial notification')
 except socket.timeout: check(True,'WebSocket notifications are isolated by user')
 check(b.request('/api/cuentas/'+str(account['id']))[1]['data']['balance']=='90.00','Second session reads new balance')
 check(c.request('/api/transacciones/'+str(movement['id']))[0]==404 and c.request('/api/transacciones/'+str(movement['id']),'DELETE')[0]==404,'Foreign movement read and delete denied')
 check(a.request('/api/cuentas','DELETE',{'id':account['id']})[0]==200,'Used account can be deleted')
 preserved=b.request('/api/transacciones/'+str(movement['id']))[1]['data']
 check(preserved['cuenta_id'] is None and preserved['cuenta_nombre']=='HTTP cash','History survives account deletion')
 check(a.request('/api/transacciones/'+str(movement['id']),'DELETE')[0]==200,'Orphan movement can be deleted')
 check(a.request('/api/auth/logout','POST')[0]==200 and a.request('/api/auth/refresh','POST')[0]==401,'Logout revokes session and clears cookies')
 admin=Client()
 status,payload=admin.request('/api/auth/login','POST',{'correo':'admin@admin.com','password':'admin'})
 check(status==200 and payload['data']['user']['rol']=='admin' and 'token' not in payload['data'],'Development admin uses cookie login without exposing tokens')
 metrics=admin.request('/api/admin/metrics')[1]['data']
 check(set(metrics)=={'users','active_users','active_window_minutes'} and isinstance(metrics['users'],int),'Administration exposes only aggregate ID counts')
 settings=admin.request('/api/admin/settings')[1]['data']
 check(all(isinstance(settings[key],dict) and set(settings[key])=={'configured'} for key in ['JWT_KEY','DB_PASS','MAIL_PASS']),'Administrative secrets are masked')
 check(admin.request('/api/cuentas')[0]==403 and admin.request('/api/transacciones')[0]==403,'Administrator cannot access financial endpoints')
 check(admin.request('/api/admin/settings','PUT',{'settings':{'JWT_KEY':'weak'}})[0]==409,'Weak JWT signing keys are rejected without saving')
 admin.request('/api/auth/logout','POST')
 print(f'{checks} HTTP/cookie/WebSocket checks passed; generated fixtures cleaned up.')
finally:
 for ws in sockets: ws.close()
 for item in fixtures: fixture('cleanup',item['email'])
