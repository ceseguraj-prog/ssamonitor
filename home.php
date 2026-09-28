<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/modulos.php';

requireLogin();

/**
 * Puerta de entrada. Las pantallas viven cada una en su módulo bajo modules/,
 * así que aquí ya no hay interfaz: solo se manda al usuario a su primer módulo.
 *
 * "Primero" es el primero del rail que el usuario puede ver, no el resumen fijo:
 * a quien solo tiene Terceros, mandarlo al resumen lo dejaba en un "No tienes
 * acceso" sin salida. Para casi todos sigue siendo el resumen, que es el de
 * menor 'orden'.
 *
 * Se conserva la ruta porque hay enlaces viejos, marcadores y el logo del rail
 * que siguen apuntando a home.php.
 */
$primero = array_values(modulosVisibles())[0] ?? null;

if ($primero !== null) {
    header('Location: ' . $primero['url']);
    exit;
}

// Sin ningún módulo: se le dice y se le deja cerrar sesión, en vez de rebotarlo
// a una pantalla prohibida.
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<meta charset="utf-8">
<title><?= htmlspecialchars(APP_NOMBRE) ?> — Sin módulos</title>
<p style="font-family:system-ui;padding:2rem">
    Tu usuario no tiene acceso a ningún módulo. Pide a quien administra los
    permisos que te asigne alguno.
    <br><br>
    <a href="logout.php">Cerrar sesión</a>
</p>
