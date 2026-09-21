<?php

// Copia este archivo como config.php (git-ignorado) y ajusta los valores para
// tu entorno local o el servidor donde se despliegue el proyecto.
//
// Sin config.php el sitio NO arranca: includes/bootstrap.php lo carga con
// require_once, así que falta el archivo y truena antes de pintar el login.

// ── Conexión principal ──────────────────────────────────────────────────────
// Es la base `catalogos`: de ahí salen la tabla `users` contra la que se
// autentica, las seis tablas de nómina (federal, homologados, regularizados,
// formalizados, formalizados2015, formalizados2016) y los catálogos de
// conceptos.
//
// OJO: aunque el proyecto se llame SSA Monitor, la base NO se llama así. Poner
// aquí 'monitor' es el error más fácil de cometer: el login falla diciendo que
// no existe la tabla `users` y no queda claro por qué.
//
// El sistema solo ejecuta SELECT contra esta base. Un usuario de solo lectura
// basta y es lo recomendable.
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'usuario_lectura');
define('DB_PASS', '');
define('DB_NAME', 'catalogos');

// ── Conexión a BPM ──────────────────────────────────────────────────────────
// Base del proveedor externo, para el avance de timbrado (Resumen, Productos,
// Detalle, Errores). También es de solo lectura.
define('DB_HOSTBPM', '127.0.0.1');
define('DB_USERBPM', 'bpm_readonly');
define('DB_PASSBPM', '');
define('DB_NAMEBPM', 'bpm');
