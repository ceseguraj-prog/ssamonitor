<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../SqlFixer.php';

requireModulo('sqlfix', true);

use App\Modules\SqlFix\SqlFixer;

header('Content-Type: application/json; charset=utf-8');

// Archivos de decenas de miles de líneas: el proceso es por streaming, así que
// la memoria no crece, pero el tiempo de CPU sí.
@set_time_limit(300);

/** Responde y termina. */
function responder(array $cuerpo, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido.'], 405);
}

// Si el envío rebasa post_max_size, PHP descarta todo y $_FILES llega vacío.
// Sin este caso especial el usuario solo vería "no se recibió archivo".
if ($_FILES === [] && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    responder([
        'error' => 'El archivo excede el límite del servidor (post_max_size = '
            . ini_get('post_max_size') . '). Súbelo en php.ini junto con upload_max_filesize.',
    ], 413);
}

$archivo = $_FILES['archivo'] ?? null;

if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $motivos = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
        UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió a medias.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal configurada.',
        UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo temporal.',
    ];
    $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    responder(['error' => $motivos[$codigo] ?? 'No se pudo recibir el archivo.'], 400);
}

if (!is_uploaded_file($archivo['tmp_name'])) {
    responder(['error' => 'Origen de archivo no válido.'], 400);
}

$nombreOriginal = (string) ($archivo['name'] ?? 'archivo.sql');
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($extension, ['sql', 'txt'], true)) {
    responder(['error' => 'Solo se aceptan archivos .sql o .txt.'], 400);
}

$almacen = __DIR__ . '/../storage';
if (!is_dir($almacen) && !@mkdir($almacen, 0775, true) && !is_dir($almacen)) {
    responder(['error' => 'No se pudo preparar la carpeta de trabajo.'], 500);
}

limpiarAntiguos($almacen);

$token = bin2hex(random_bytes(16));
$rutaSalida = $almacen . '/' . $token . '.sql';

try {
    $reporte = SqlFixer::procesar($archivo['tmp_name'], $rutaSalida);
} catch (\Throwable $e) {
    @unlink($rutaSalida);
    responder(['error' => 'No se pudo procesar el archivo: ' . $e->getMessage()], 500);
}

// El nombre sugerido de descarga conserva el original con sufijo.
$base = pathinfo($nombreOriginal, PATHINFO_FILENAME);
$nombreDescarga = ($base !== '' ? $base : 'archivo') . '_corregido.sql';

$_SESSION['sqlfix_archivos'][$token] = [
    'ruta'    => $rutaSalida,
    'nombre'  => $nombreDescarga,
    'creado'  => time(),
];

$reporte['token'] = $token;
$reporte['nombreOriginal'] = $nombreOriginal;
$reporte['nombreDescarga'] = $nombreDescarga;
$reporte['bytesSalida'] = (int) @filesize($rutaSalida);

responder($reporte);

/**
 * Borra resultados de más de una hora. Evita que la carpeta crezca sin
 * límite cuando alguien procesa varios archivos grandes seguidos.
 */
function limpiarAntiguos(string $almacen): void
{
    $limite = time() - 3600;

    foreach (glob($almacen . '/*.sql') ?: [] as $viejo) {
        if (@filemtime($viejo) < $limite) {
            @unlink($viejo);
        }
    }

    foreach ($_SESSION['sqlfix_archivos'] ?? [] as $token => $datos) {
        if (($datos['creado'] ?? 0) < $limite) {
            unset($_SESSION['sqlfix_archivos'][$token]);
        }
    }
}
