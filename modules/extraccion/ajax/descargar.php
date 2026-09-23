<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('extraccion');

/**
 * Entrega el paquete de la quincena, o uno solo de los archivos que trae
 * dentro: casi siempre lo que se quiere es un .txt suelto para mandarlo a
 * extracción, no el zip entero.
 */

/** Corta con una página de error legible. */
function noDisponible(string $motivo): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>No disponible</title>'
        . '<p style="font-family:system-ui;padding:2rem">' . htmlspecialchars($motivo) . '</p>';
    exit;
}

$token = (string) ($_GET['token'] ?? '');

// El token solo se acepta si esta sesión lo generó: así la ruta del archivo
// nunca viene del navegador y no hay forma de pedir otra cosa del disco.
$datos = $_SESSION['extraccion_archivos'][$token] ?? null;

if (!is_array($datos) || !is_file($datos['ruta'])) {
    noDisponible('El paquete ya no está disponible. Vuelve a generarlo.');
}

$ruta = (string) $datos['ruta'];

/* ── Un archivo suelto ──────────────────────────────────────────────────── */

$archivo = (string) ($_GET['archivo'] ?? '');

if ($archivo !== '') {
    // El nombre se busca dentro del zip, no en el disco: lo peor que puede
    // pasar con un nombre inventado es que no exista esa entrada.
    $zip = new ZipArchive();

    if ($zip->open($ruta) !== true) {
        noDisponible('No se pudo abrir el paquete.');
    }

    $contenido = $zip->getFromName($archivo);
    $zip->close();

    if ($contenido === false) {
        noDisponible('Ese archivo no está en el paquete.');
    }

    $tipos = [
        'txt'  => 'text/plain; charset=utf-8',
        'md'   => 'text/markdown; charset=utf-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

    header('Content-Type: ' . ($tipos[$extension] ?? 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($archivo)) . '"');
    header('Content-Length: ' . (string) strlen($contenido));
    header('X-Content-Type-Options: nosniff');

    echo $contenido;
    exit;
}

/* ── El paquete completo ────────────────────────────────────────────────── */

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $datos['nombre']) . '"');
header('Content-Length: ' . (string) filesize($ruta));
header('X-Content-Type-Options: nosniff');

while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($ruta);
