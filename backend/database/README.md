# Migración de transacciones

Desde la raíz del proyecto, ejecuta `php backend/database/migrate_transactions.php` en cada entorno que use esta versión. El script usa la conexión configurada en `backend/.env` y puede ejecutarse de nuevo sin duplicar los tipos.

La migración conserva los importes existentes, convierte `cuentas.balance` y `movimientos.monto` a `DECIMAL(12,2)`, agrega la cuenta destino opcional a los movimientos y registra los tipos **Bancaria** y **Transferencia**. Conserva el nombre existente de la columna `tipo_movimineto_id`.

Las nuevas transacciones usan montos positivos. Los ingresos suman al saldo; los gastos restan; las transferencias restan en origen y suman en destino. Las escrituras y las ediciones se realizan dentro de una transacción MySQL con bloqueo de las cuentas involucradas. La edición revierte el efecto anterior y aplica el nuevo. El backend calcula en centavos enteros y guarda cadenas decimales.

Pruebas: `php backend/tests/transacciones_integration.php` verifica la lógica sobre MySQL con usuarios y cuentas temporales; todas las filas y cambios de la prueba se revierten al terminar. Los contadores autoincrementales pueden avanzar. `php backend/tests/router_params.php` verifica las rutas parametrizadas sin conexión a la base de datos.

Después ejecuta `php backend/database/migrate_security.php`: agrega propietarios independientes al historial, referencias con `ON DELETE SET NULL`, límite de crédito, roles, sesiones de renovación y verificación de cambios de correo. La documentación de funcionamiento, configuración y WebSocket está en `README.md` en la raíz.
