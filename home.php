<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

/**
 * Puerta de entrada. Las pantallas viven cada una en su módulo bajo modules/,
 * así que aquí ya no hay interfaz: solo se manda al usuario al resumen.
 *
 * Se conserva la ruta porque hay enlaces viejos, marcadores y el logo del rail
 * que siguen apuntando a home.php.
 */
header('Location: modules/resumen/index.php');
exit;
