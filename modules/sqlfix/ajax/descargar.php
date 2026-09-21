<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('sqlfix');

$token = (string) ($_GET['token'] ?? '');

// El token solo se acepta si esta sesión lo generó: así la ruta del archivo
// nunca viene del navegador y no hay forma de pedir otra cosa del disco.
$datos = $_SESSION['sqlfix_archivos'][$token] ?? null;

if (!is_array($datos) || !is_file($datos['ruta'])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>No disponible</title>'
        . '<p style="font-family:system-ui;padding:2rem">El resultado ya no está disponible. '
        . 'Vuelve a procesar el archivo.</p>';
    exit;
}

$ruta = (string) $datos['ruta'];
$nombre = (string) $datos['nombre'];

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombre) . '"');
header('Content-Length: ' . (string) filesize($ruta));
header('X-Content-Type-Options: nosniff');

// Sin buffer intermedio: el archivo puede pesar varios MB.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($ruta);
