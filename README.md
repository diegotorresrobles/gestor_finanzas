# Gestor de finanzas DTR

Aplicación React + Tailwind con una API PHP/MySQL. Su objetivo funcional es ayudar a cada persona a administrar sus cuentas, saldos y movimientos preservando la privacidad de su información.

## Ética DTR

Los datos personales y financieros pertenecen a cada usuario. La aplicación no ofrece al administrador listados de personas, acceso a cuentas ajenas ni exportaciones de información privada. Las métricas administrativas se calculan únicamente con conteos de IDs. Los endpoints financieros validan el propietario en el servidor y excluyen el rol administrador.

## Desarrollo

La configuración privada reside en `backend/.env`, fuera del directorio público y excluida de Git. Después de instalar las dependencias existentes, ejecuta desde la raíz:

```powershell
php backend/database/migrate_transactions.php
php backend/database/migrate_security.php
php -S localhost:3001 -t backend/public
```

Para una versión preliminar en DOM Cloud y Netlify, consulta [`deploy/README.md`](deploy/README.md); incluye la receta YAML, comandos copiables y configuración del dominio dinámico.

En otra terminal inicia el worker WebSocket:

```powershell
php backend/bin/websocket.php
```

Y desde `frontend`:

```powershell
node node_modules/vite/bin/vite.js --host localhost
```

La migración de seguridad es idempotente y ya se aplicó al entorno local. Agrega `users.rol` con valor predeterminado `user`. En `APP_ENV=development` crea el administrador `admin@admin.com` con la contraseña `admin` hasheada, sin sobrescribir usuarios existentes. Su panel está en `/admin`; el inicio de sesión normal determina la ruta según el rol. Estas credenciales son exclusivamente para desarrollo. En producción configura `APP_ENV=production`, HTTPS/WSS y cambia la contraseña del administrador antes de publicar.

## Comportamiento

- Eliminar una cuenta conserva sus transacciones y nombres históricos mediante `ON DELETE SET NULL` en las referencias a cuentas. El movimiento conserva `user_id` para seguir siendo privado. No modifica los saldos de otras cuentas. El historial desvinculado admite consulta y eliminación, pero no edición financiera.
- Eliminar un movimiento revierte sus efectos sobre las cuentas existentes, dentro de una transacción MySQL. También revierte ambos lados de una transferencia. Se rechaza cualquier reversión que exceda el límite de crédito o el rango monetario.
- `cuentas.limite_credito` representa la deuda máxima de una tarjeta: `balance >= -limite_credito`. La migración inicializa las tarjetas existentes con su deuda actual, o cero si no deben. Configura el límite contractual real en la edición de cada tarjeta para permitir nuevos cargos. Los saldos positivos indican saldo a favor.
- `/` muestra las cuentas en una fila horizontal desplazable y las últimas cinco transacciones, ordenadas por fecha e ID descendentes. `/cuentas`, `/cuentas/:id`, `/transacciones` y `/transacciones/:id` mantienen las tablas y la edición. Los formularios de edición detectan cambios de saldo o versión realizados en otra sesión.
- `/account` muestra exclusivamente el perfil propio. El cambio de contraseña consulta y verifica el hash actual en la base de datos. Envía un aviso por SMTP y revoca todas las sesiones y solicitudes de cambio de correo. Si falla el envío, la operación se revierte.
- Para cambiar de correo se verifica la contraseña actual, se pide aprobación al correo vigente y después verificación al nuevo. Solo entonces se modifica `users.correo`, comprobando nuevamente su disponibilidad. Un índice único resuelve solicitudes concurrentes. Los enlaces usan tokens de un solo uso, con 30 minutos de vigencia, almacenados únicamente como hashes. El token viaja en el fragmento del enlace, se retira de la dirección al abrirlo y se confirma mediante POST.
- La sesión usa cookies HttpOnly y SameSite Strict. El JWT de acceso dura 15 minutos; la renovación dura hasta 30 días y rota su secreto. El frontend renueva el acceso tras un 401, sin guardar tokens ni contraseñas en almacenamiento persistente. Logout revoca la sesión en el servidor. Se valida el origen de las escrituras para proteger las solicitudes con cookies.
- WebSocket autentica cada conexión con un ticket efímero de un solo uso, ligado a la sesión. Envía únicamente avisos de cambio al propietario, sin información financiera. React consulta sus endpoints autorizados y reconecta con espera progresiva. El worker comprueba la revocación de sesiones y cambios de revisión cada segundo.
- `/admin` muestra usuarios registrados y usuarios con solicitudes autenticadas en los últimos 15 minutos. Permite configurar nombre, URL, host/emisor JWT, logo por URL/ruta, WebSocket, SMTP y base de datos. Los secretos existentes se muestran solo como «configurado»; los campos vacíos los conservan. `JWT_SECRET` corresponde a la variable existente `JWT_KEY`. Cambiarla revoca todas las sesiones. La configuración se guarda de forma atómica y se comprueba la conexión antes de cambiar datos de MySQL. La base de datos de destino debe tener las migraciones aplicadas.

## Correo y servicios

El correo usa `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` y `MAIL_ENCRYPTION` (`tls` o `ssl`). Se trasladaron las credenciales SMTP existentes desde el código a `.env`. El entorno actual utiliza el sandbox de desarrollo existente; para entregar correos a los usuarios, configura un proveedor SMTP de entrega real en `/admin`.

`APP_URL` debe ser el origen del frontend, por ejemplo `http://localhost:5173`. `WS_URL` es la dirección que usa el navegador, inicialmente `ws://localhost:3002`; `WS_BIND` y `WS_PORT` controlan el listener, inicialmente `127.0.0.1:3002`. En producción el worker debe ejecutarse bajo un supervisor y detrás de un proxy TLS. Reinícialo al modificar la configuración de conexión, base de datos u origen. PHP lee `.env` en cada solicitud nueva.

## Verificación

```powershell
php backend/tests/account_verification.php
php backend/tests/cuentas.php
php backend/tests/router_params.php
php backend/tests/transacciones_integration.php
php backend/tests/security_integration.php
python backend/tests/http_security.py
```

Las pruebas de integración SQL revierten sus fixtures al terminar. La prueba HTTP/WebSocket requiere los servicios locales y genera dos usuarios desechables, que elimina al finalizar; nunca consulta información financiera de usuarios existentes ni envía correos reales. El fixture visual `/tests/cuentas-ui.html` del servidor Vite intercepta la API en memoria y no forma parte de la compilación de producción. Usa `?role=admin` para revisar el panel y `?theme=light` para revisar el tema claro.
