<?php

// Copia este archivo como config.php (git-ignorado) y ajusta los valores
// para tu entorno local o el servidor donde se despliegue el proyecto.

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'monitor');

// Conexión de solo lectura a BPM para el dashboard de nómina.
// El usuario de BPM solo tiene permisos SELECT.
define('DB_HOSTBPM', '127.0.0.1');
define('DB_USERBPM', 'bpm_readonly');
define('DB_PASSBPM', '');
define('DB_NAMEBPM', 'bpm');
