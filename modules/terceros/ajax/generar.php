<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('terceros', true);

use App\TercerosRepository;

header('Content-Type: application/json; charset=utf-8');

/**
 * Recorrer las seis tablas de un periodo tarda segundos, no milisegundos, y la
 * primera corrida del día va contra caché frío. Con el límite por omisión de 30 s
 * quedaría al filo, así que se amplía para esta petición nada más.
 */
@set_time_limit(300);

/** Responde y termina. */
function responder(array $cuerpo, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

$anio = (int) ($_GET['anio'] ?? 0);
$quincena = (int) ($_GET['quincena'] ?? 0);
$grupo = (string) ($_GET['grupo'] ?? 'Todos');

// Se valida antes de tocar la base: sin esto un año en blanco haría que la
// consulta recorriera las tablas completas para no encontrar nada.
if (!TercerosRepository::periodoValido($anio, $quincena)) {
    responder(['error' => 'Elige un ejercicio y una quincena válidos.'], 400);
}

// El grupo llega del cliente, así que se cruza contra el catálogo: cualquier
// cosa que no esté en él se trata como "todos" solo si es literalmente 'Todos'.
$grupos = TercerosRepository::grupos();

if ($grupo !== 'Todos' && !in_array($grupo, $grupos, true)) {
    responder(['error' => 'El grupo de terceros no existe.'], 400);
}

$elegidos = $grupo === 'Todos' ? $grupos : [$grupo];

if (!class_exists('ZipArchive')) {
    responder([
        'error' => 'Falta la extensión zip de PHP, que es la que empaqueta los dos CSV. '
            . 'Habilita extension=zip en php.ini y reinicia el servidor.',
    ], 500);
}

$almacen = __DIR__ . '/../storage';

if (!is_dir($almacen) && !@mkdir($almacen, 0775, true) && !is_dir($almacen)) {
    responder(['error' => 'No se pudo preparar la carpeta de trabajo.'], 500);
}

limpiarAntiguos($almacen);

$inicio = microtime(true);

try {
    $detalle = TercerosRepository::detalle($anio, $quincena, $elegidos);
} catch (\Throwable $e) {
    responder(['error' => 'No se pudo consultar la nómina: ' . $e->getMessage()], 500);
}

$resumen = TercerosRepository::resumen($detalle, $anio, $quincena);
$porGrupo = TercerosRepository::totalesPorGrupo($detalle);

$registros = 0;
$importe = 0.0;

foreach ($porGrupo as $fila) {
    $registros += $fila['registros'];
    $importe += $fila['importe'];
}

if ($registros === 0) {
    responder([
        'vacio' => true,
        'error' => "No hay descuentos a terceros en la quincena $quincena de $anio"
            . ($grupo === 'Todos' ? '.' : " para $grupo."),
    ]);
}

/* ── Archivos ───────────────────────────────────────────────────────────── */

$token = bin2hex(random_bytes(16));
$nombreDetalle = TercerosRepository::nombreDetalle($anio, $quincena, $grupo);
$nombreResumen = TercerosRepository::nombreResumen($anio, $quincena);

$rutaDetalle = $almacen . '/' . $token . '_detalle.tmp';
$rutaResumen = $almacen . '/' . $token . '_resumen.tmp';
$rutaZip = $almacen . '/' . $token . '.zip';

try {
    // El detalle sale grupo por grupo, en el orden del catálogo: es el mismo
    // orden de renglones que producía el reporte original.
    TercerosRepository::escribirCsv(
        $rutaDetalle,
        TercerosRepository::CABECERA_DETALLE,
        (static function () use ($detalle, $elegidos): \Generator {
            foreach ($elegidos as $clave) {
                foreach ($detalle[$clave] ?? [] as $fila) {
                    yield $fila;
                }
            }
        })()
    );

    TercerosRepository::escribirCsv($rutaResumen, TercerosRepository::CABECERA_RESUMEN, $resumen);

    $zip = new ZipArchive();

    if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('No se pudo crear el paquete zip.');
    }

    // addFile con nombre explícito: dentro del zip los archivos llevan el nombre
    // que espera quien los consume, no el token ni la carpeta.
    $zip->addFile($rutaDetalle, $nombreDetalle);
    $zip->addFile($rutaResumen, $nombreResumen);
    $zip->close();
} catch (\Throwable $e) {
    @unlink($rutaDetalle);
    @unlink($rutaResumen);
    @unlink($rutaZip);

    responder(['error' => 'No se pudieron generar los archivos: ' . $e->getMessage()], 500);
}

// Los CSV ya viven dentro del zip; en disco solo se conserva el paquete.
@unlink($rutaDetalle);
@unlink($rutaResumen);

$nombreZip = "TercerosN{$anio}Qna{$quincena}.zip";

$_SESSION['terceros_archivos'][$token] = [
    'ruta'   => $rutaZip,
    'nombre' => $nombreZip,
    'creado' => time(),
];

/* ── Respuesta ──────────────────────────────────────────────────────────── */

/*  El detalle no viaja al navegador: son ~20 mil renglones por quincena y lo que
    se lee en pantalla es el concentrado, que ronda los 1,700. El detalle completo
    va en el CSV, que es para lo que existe. */
responder([
    'resumen' => array_map(static fn (array $f): array => [
        'grupo' => (string) $f[2],
        'ur' => (string) $f[3],
        'rama' => (string) $f[4],
        'tipo' => (string) $f[5],
        'banco' => (string) $f[6],
        'concepto' => (string) $f[7],
        'importe' => (float) $f[8],
    ], $resumen),
    'porGrupo' => $porGrupo,
    'registros' => $registros,
    'importe' => $importe,
    'token' => $token,
    'nombreZip' => $nombreZip,
    'archivos' => [$nombreDetalle, $nombreResumen],
    'ms' => (int) round((microtime(true) - $inicio) * 1000),
]);

/**
 * Borra paquetes de más de una hora. Evita que la carpeta crezca sin límite
 * cuando se generan varias quincenas seguidas.
 */
function limpiarAntiguos(string $almacen): void
{
    $limite = time() - 3600;

    foreach (glob($almacen . '/*.{zip,tmp}', GLOB_BRACE) ?: [] as $viejo) {
        if (@filemtime($viejo) < $limite) {
            @unlink($viejo);
        }
    }

    foreach ($_SESSION['terceros_archivos'] ?? [] as $token => $datos) {
        if (($datos['creado'] ?? 0) < $limite) {
            unset($_SESSION['terceros_archivos'][$token]);
        }
    }
}
