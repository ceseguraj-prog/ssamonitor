<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../../../Classes/Xlsx.php';
require_once __DIR__ . '/../QnaRepository.php';

requireModulo('qna', true);

use App\QnaRepository;
use App\Xlsx;

header('Content-Type: application/json; charset=utf-8');

/**
 * Arma el reporte de la quincena y lo devuelve junto con su resumen.
 *
 * El .xlsx viaja dentro de la respuesta, en base64, y no por un segundo
 * endpoint con token como en Extracción: así no hace falta carpeta con permiso
 * de escritura en el servidor, y lo que se ve en pantalla y lo que se descarga
 * salen de la misma consulta. Son ~1.2 MB de libro; en base64, 1.6 MB.
 *
 * Medido en la q16 de 2026: ~3 s de consulta en las seis tablas con la caché
 * fría. El límite de 30 s por omisión basta, pero una base cargada lo acerca.
 */
@set_time_limit(180);

function responder(array $cuerpo, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

$anio = (int) ($_GET['anio'] ?? 0);
$quincena = (int) ($_GET['quincena'] ?? 0);
$modo = QnaRepository::modo((string) ($_GET['modo'] ?? ''));

// Sin año la consulta pierde el índice ANIO y recorre las seis tablas enteras.
if ($anio < 2000 || $quincena < 1 || $quincena > 24) {
    responder(['error' => 'Elige el ejercicio y la quincena.'], 400);
}

if (!class_exists('ZipArchive')) {
    responder([
        'error' => 'Falta la extensión zip de PHP, que es la que escribe el .xlsx. '
            . 'Habilita extension=zip en php.ini y reinicia el servidor.',
    ], 500);
}

$inicio = microtime(true);
$temporal = null;

try {
    $reporte = QnaRepository::generar($anio, $quincena, $modo);

    if (!$reporte['filas']) {
        responder(['error' => "No hay nómina ordinaria de la quincena $quincena de $anio en las seis tablas."], 404);
    }

    // Con la misma pinta que el reporte que se entregaba: hoja «Sheet1», sin
    // estilos ni anchos, con el filtro de Excel puesto en el encabezado.
    $xlsx = new Xlsx();
    $xlsx->hoja('Sheet1', [], array_merge([QnaRepository::COLUMNAS], $reporte['filas']), true);

    $temporal = tempnam(sys_get_temp_dir(), 'qna');

    if ($temporal === false) {
        throw new \RuntimeException('No se pudo crear el archivo temporal.');
    }

    $xlsx->guardar($temporal);
    $contenido = (string) file_get_contents($temporal);
} catch (\Throwable $e) {
    responder(['error' => 'No se pudo generar el reporte: ' . $e->getMessage()], 500);
} finally {
    if ($temporal !== null && $temporal !== false) {
        @unlink($temporal);
    }
}

$porTabla = [];
foreach ($reporte['porTabla'] as $tabla => $datos) {
    $porTabla[] = ['tabla' => $tabla] + $datos;
}

responder([
    'anio' => $anio,
    'quincena' => $quincena,
    'archivo' => QnaRepository::nombreArchivo($anio, $quincena),
    'xlsx' => base64_encode($contenido),
    'modo' => $reporte['modo'],
    'renglones' => count($reporte['filas']),
    'pagos' => $reporte['pagos'],
    'duplicados' => $reporte['duplicados'],
    'omitidos' => $reporte['omitidos'],
    'importes' => array_keys(QnaRepository::IMPORTES),
    'totales' => $reporte['totales'],
    'porTabla' => $porTabla,
    'sinDescripcionCr' => $reporte['sinDescripcionCr'],
    'sinDescripcionPuesto' => $reporte['sinDescripcionPuesto'],
    'clavesFuera' => $reporte['clavesFuera'],
    'ms' => (int) round((microtime(true) - $inicio) * 1000),
]);
