# Auditoría técnica y de seguridad

Fecha: 2026-07-26

## Alcance

Revisión estática de controladores, modelos, rutas, autenticación, autorización,
sesiones, consultas SQL, vistas, cargas de archivos y configuración. También se
ejecutó lint sobre todos los archivos PHP, validación de Composer, conexión de
base de datos y pruebas HTTP locales de rutas públicas, privadas y sensibles.

## Corregido durante la revisión

- Regeneración del identificador de sesión al iniciar sesión y eliminación
  completa de la cookie al cerrar sesión.
- Cookies de sesión `HttpOnly`, `SameSite=Lax`, modo estricto y `Secure` bajo
  HTTPS.
- Cabeceras `nosniff`, `SAMEORIGIN`, política de referencia y permisos.
- Consultas preparadas nativas de MySQL (`ATTR_EMULATE_PREPARES=false`).
- Bloqueo temporal tras cinco intentos fallidos por documento e IP en 15
  minutos, sin revelar si el usuario existe.
- Eliminación de la contraseña inicial predecible `123456`; las nuevas claves
  requieren al menos ocho caracteres.
- Credenciales de base de datos separadas en un archivo local ignorado por Git;
  producción queda configurada mediante variables de entorno.
- SMTP seguro por defecto: validación TLS activa, sin certificados autofirmados
  y sin depuración.
- Límite de 10 MB, comprobación de carga real, firma XLSX y validación MIME en
  vouchers.
- Bloqueo web de `app`, `vendor`, `sql`, metadatos Git, archivos Composer y
  ejecución de scripts dentro de `uploads`.
- Codificación segura de datos JSON incrustados en JavaScript para evitar XSS
  almacenado.

## Pendientes prioritarios

### Críticos

1. Rotar inmediatamente la contraseña de MySQL que estaba versionada. Aunque ya
   no aparece en el archivo rastreado, puede existir en el historial de Git,
   copias o respaldos.
2. Habilitar HTTPS obligatorio en cualquier ambiente accesible por red. El sitio
   local probado usa HTTP; sin TLS las credenciales viajan sin cifrar.
3. Crear un usuario MySQL exclusivo con permisos mínimos. La aplicación todavía
   conecta localmente como `root`.

### Altos

1. Sustituir los métodos `ensureSchema()` ejecutados durante solicitudes web por
   migraciones versionadas. Actualmente numerosos modelos consultan o modifican
   el esquema al cargarse, lo que agrega latencia, bloqueos y permisos DDL
   innecesarios a la cuenta de producción.
2. Centralizar el manejo de errores. Varios controladores muestran
   `Throwable::getMessage()` al navegador y pueden revelar nombres de tablas,
   consultas o rutas internas. Registrar el detalle en servidor y devolver un
   mensaje genérico al usuario.
3. Añadir rate limiting persistente a formularios públicos de asistencia y
   captación/vouchers, además de límites por IP y campaña.
4. Incorporar recuperación/cambio obligatorio de contraseña y una política más
   fuerte para cuentas administrativas.

### Medios

1. Agregar migraciones e índices explícitos para búsquedas frecuentes, incluyendo
   unicidad de `(document_type, document_number)` si aún no existe en la base.
2. Definir retención y archivado de `security_logs`; registrar cada acceso de
   módulo hará crecer la tabla indefinidamente.
3. Paginar en SQL las colecciones grandes. Algunas pantallas cargan arreglos
   completos y luego usan `Pagination::paginateArray`, aumentando memoria y
   tiempo de respuesta.
4. Implementar pruebas automatizadas para login, CSRF, matriz de permisos,
   aislamiento administrador/trabajador, carga de archivos y operaciones de
   requerimientos.
5. Desactivar o minimizar las cabeceras de versión de Apache/PHP a nivel global
   del servidor (`ServerTokens Prod`, `ServerSignature Off`, `expose_php=Off`).

## Validaciones realizadas

- 170 archivos PHP: sintaxis correcta.
- `composer validate`: archivo válido (solo falta declarar licencia).
- Conexión PDO y consulta básica: correctas.
- `git diff --check`: sin errores de espacios.
- `/login`: 200 y cabeceras de seguridad presentes.
- `/admin` sin sesión: redirección 302 a `/login`.
- `/app/config/database.php`, `/composer.json` y `/uploads/`: 403.

No se pudo ejecutar `composer audit` porque requiere enviar metadatos del lock a
Packagist y esa salida de red no fue autorizada. La dependencia detectada en el
lock es PHPMailer 7.0.2.
