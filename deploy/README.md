# Versión preliminar: DOM Cloud + Netlify

El backend detecta el dominio asignado por DOM Cloud mediante `DOMAIN`, por ejemplo `nombre-app.mnz.dom.my.id`. El frontend consume `/api` a través de Netlify: las cookies HttpOnly quedan asociadas al origen de Netlify y conservan `Secure` y `SameSite=Strict`. No configures `VITE_API_URL` con el dominio remoto para este despliegue. El proxy está basado en las [reescrituras oficiales de Netlify](https://docs.netlify.com/manage/routing/redirects/rewrites-proxies/).

## 1. Preparar el código y reservar el sitio Netlify

Sube el proyecto completo a un repositorio Git, incluyendo `deploy/`, `backend/composer.lock`, `frontend/package-lock.json` y `netlify.toml`. `.gitignore` excluye credenciales, dependencias y archivos compilados. También puedes cargar el código por SFTP en `~/public_html` si aún no tienes repositorio; omite `source` en ese caso. No subas la base local ni ningún archivo `.env`.

Crea el sitio en Netlify y elige su nombre para obtener el origen definitivo `https://TU_SITIO.netlify.app`. Puedes reservarlo desde el panel o con la CLI oficial (`npm install -g netlify-cli`, `netlify login`, `netlify sites:create`). Sigue el inicio de sesión interactivo, sin colocar tokens en los scripts. Usa el mismo origen definitivo en el backend; los previews de Netlify tienen otro origen y no están autorizados.

## 2. Backend: copiar y pegar YAML en DOM Cloud

Abre `deploy/domcloud.yaml`, cambia `source` por la URL real del repositorio y `FRONTEND_URL` por el sitio real de Netlify. Pégalo en **Setup → Deploy** del sitio vacío de DOM Cloud. La opción `source` reemplaza el contenido de `public_html`: usa esta receta solo para la primera instalación, como indica la [documentación de DOM Cloud](https://domcloud.co/docs/deployment/deploy).

La receta configura PHP 8.3, MariaDB, HTTPS y el directorio público `backend/public`. Composer instala el backend. Se genera un JWT aleatorio y un `.env` privado usando las credenciales de MariaDB proporcionadas por DOM Cloud, sin mostrarlas. Se crea el esquema vacío y se ejecutan las migraciones. En instalaciones existentes se conserva `.env`; se detiene ante un esquema incompleto. Las migraciones SQL no son atómicas: ante un fallo revisa el esquema antes de repetirlas.

Después, abre SSH desde DOM Cloud y ejecuta:

```bash
cd ~/public_html
bash deploy/create-admin.sh
```

Elige y confirma una contraseña de 12 a 72 bytes en el prompt oculto. Se crea `admin@admin.com` con contraseña hasheada y rol admin; no se sobrescriben cuentas existentes. No se utiliza la contraseña de desarrollo `admin` en este entorno.

Comprueba `https://DOMINIO_ASIGNADO/api/config`: debe responder JSON y el nombre de la aplicación. La raíz del backend no sirve React; React se publica en Netlify.

## 3. Frontend: compilar y publicar desde PowerShell

Desde la raíz del proyecto, copia este bloque. Introduce el dominio HTTPS que DOM Cloud acaba de asignar, por ejemplo `https://nombre-app.mnz.dom.my.id`:

```powershell
$backendOrigin = Read-Host 'URL HTTPS asignada por DOM Cloud'
node deploy/prepare-netlify.mjs $backendOrigin
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar el proxy' }
Push-Location frontend
try {
    # En una instalación nueva, ejecutar npm ci primero (Node 24 recomendado).
    $env:VITE_API_URL = ''
    node node_modules/vite/bin/vite.js build
    if ($LASTEXITCODE -ne 0) { throw 'Falló la compilación' }
} finally { Pop-Location }
netlify login
if ($LASTEXITCODE -ne 0) { throw 'Falló el inicio de sesión' }
netlify link
if ($LASTEXITCODE -ne 0) { throw 'Falló la selección del sitio' }
$publishDirectory = (Resolve-Path frontend/dist).Path
netlify deploy --prod --dir $publishDirectory --no-build
if ($LASTEXITCODE -ne 0) { throw 'Falló la publicación' }
```

Selecciona el sitio reservado en el paso 1. Si no tienes la CLI, puedes subir **el contenido de `frontend/dist`** mediante la publicación manual de Netlify. El `_redirects` generado ya incluye el proxy y el fallback de React, por lo que se pueden abrir `/cuentas`, `/transacciones/ID` y `/account` directamente.

Para despliegue automático desde Git, conecta el repositorio a ese mismo sitio y agrega en Netlify **DOMCLOUD_BACKEND_URL** con el origen HTTPS asignado por DOM Cloud, disponible durante el build. `netlify.toml` configura base `frontend`, publicación `dist`, Node 24 y generación del proxy antes del build. Solo esta URL pública debe ir a Netlify; nunca JWT, contraseñas de base de datos ni SMTP. Si cambia el dominio del backend, actualiza esta variable y vuelve a compilar/publicar.

## 4. Correo y comprobación de la instalación

Inicia sesión en el frontend como `admin@admin.com` y entra a `/admin`. Configura SMTP real (host, usuario, contraseña, remitente y puerto 587/TLS o 465/SSL, según proveedor). El servidor necesita permitir la conexión saliente al proveedor. Las claves se guardan únicamente en el `.env` del backend. No abras el registro a usuarios hasta comprobar entrega de verificación, aviso de cambio de contraseña y las dos etapas de cambio de correo con cuentas de prueba propias.

Comprueba inicio y cierre de sesión, recarga directa de las rutas de React, creación y eliminación de una cuenta de prueba y cambios entre dos sesiones. En DevTools, las cookies `dtr_access`/`dtr_refresh` deben ser HttpOnly, Secure, Strict, con path `/api`; las peticiones deben ir al dominio de Netlify bajo `/api`. Si hay 403, revisa que `APP_URL` sea exactamente el origen del sitio, sin ruta ni slash final. Si hay 404 o HTML en `/api`, revisa el proxy y el dominio. No habilites la caché de respuestas privadas: la API ya envía `Cache-Control: no-store`.

## 5. Actualización entre sesiones y WebSocket opcional

La instalación inicial deja `WS_URL` vacío. Las sesiones visibles consultan una revisión privada cada 10 segundos y recargan su información cuando cambia; no se envían datos financieros en la revisión. WebSocket se mantiene disponible en desarrollo y se usa cuando se configura una URL válida. Ante fallo de conexión también se utiliza consulta periódica.

Para WebSocket persistente, DOM Cloud documenta el servicio systemd de usuario con la función `docker` habilitada en planes Kit/Pro; el alojamiento restringe los procesos persistentes sin ese soporte ([PHP daemon](https://domcloud.co/docs/deployment/php), [medidas de seguridad](https://domcloud.co/docs/intro/security)). No uses `nohup` ni tareas cron para eludir esas restricciones.

En un plan compatible, habilita `docker` desde DOM Cloud, comprueba que el puerto local `33002` esté disponible y ejecuta por SSH:

```bash
cd ~/public_html
bash deploy/install-websocket.sh
```

Agrega el bloque de `deploy/websocket.nginx.conf` al servidor HTTPS de Nginx desde el panel de DOM Cloud y aplica su validación/recarga. Conserva la configuración existente de PHP. El proxy convierte `/ws` a `/`, que es la ruta aceptada por el worker. Los encabezados Upgrade/Connection siguen la [configuración oficial de Nginx](https://nginx.org/en/docs/http/websocket.html). Si el panel no permite estas directivas, solicita el proxy WebSocket al soporte y continúa con la consulta periódica.

Configura `WS_URL=wss://DOMINIO_ASIGNADO/ws` en `/admin`. `WS_BIND=127.0.0.1` y `WS_PORT=33002` están ya en el `.env` inicial; si usas otro puerto, cambia `.env` y proxy juntos, luego `systemctl --user restart dtr-websocket`. El navegador abre directamente WSS al backend; no usa el proxy HTTP de Netlify para WebSocket. Verifica respuesta 101 y actualizaciones entre dos sesiones. Esta configuración remota requiere validarse en el plan y servidor contratados.

## Actualizaciones posteriores

No vuelvas a ejecutar `source` sobre el sitio instalado. Actualiza el código por Git o SFTP conservando `backend/.env`, ejecuta Composer y `php deploy/install-database.php`, y vuelve a compilar/publicar Netlify. Si cambias la configuración utilizada por el worker, reinicia `dtr-websocket`. Conserva respaldos privados antes de migraciones; nunca los pongas en `backend/public` ni los compartas como parte del despliegue.

Los scripts preparan el despliegue; no crean ni publican sitios en tus cuentas automáticamente. La entrega de SMTP, la sesión detrás del proxy y el servicio WebSocket se deben comprobar en los servidores reales.
