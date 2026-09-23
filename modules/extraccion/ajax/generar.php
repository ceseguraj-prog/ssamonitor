<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../../../includes/periodo.php';
require_once __DIR__ . '/../ExtraccionRepository.php';
require_once __DIR__ . '/../Reporte.php';

requireModulo('extraccion', true);

use App\ExtraccionRepository;
use App\NominaRepository;
use App\Reporte;

header('Content-Type: application/json; charset=utf-8');

/**
 * Arma el paquete de la quincena: los dos .txt de UUID, el resumen en Excel y
 * la nota en markdown.
 *
 * Nada de esto escribe en BPM. Las consultas son SELECT y lo que se produce son
 * archivos en storage/, que se borran solos a la hora.
 *
 * Leer los UUID de un par de miles de registros y armar el libro tarda segundos,
 * no milisegundos; con el límite de 30 s quedaría al filo en la primera corrida.
 */
@set_time_limit(300);

/** Responde y termina. */
function responder(array $cuerpo, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Qué se pidió ───────────────────────────────────────────────────────── */

['anio' => $anio, 'quincena' => $quincena] = periodoDeLaPeticion();

// Las claves llegan del navegador, así que se cruzan contra dos cosas: los
// productos que producto_nomina tiene para ese periodo, y la familia donde el
// grupo vale. Lo primero evita que una clave inventada mande a recorrer
// detalle_nomina para nada y que una de otra quincena meta registros ajenos;
// lo segundo es el filtro del IN de siempre, y va aquí y no solo en la
// pantalla porque esconder una casilla no es una restricción: un producto de
// eventuales no puede entrar a Regularizados ni pidiéndolo a mano.
$delPeriodo = array_map('strval', NominaRepository::productosClaves($anio, $quincena));
$elegidos = [];

foreach (ExtraccionRepository::GRUPOS as $id => $_grupo) {
    $crudo = (string) ($_GET[$id] ?? '');
    $claves = array_filter(array_map('trim', explode(',', $crudo)));
    $validas = array_intersect($claves, $delPeriodo);

    $elegidos[$id] = ExtraccionRepository::admitidos(array_values($validas), (string) $id);
}

if (!array_filter($elegidos)) {
    responder(['error' => 'Elige al menos un producto antes de generar.'], 400);
}

if (!class_exists('ZipArchive')) {
    responder([
        'error' => 'Falta la extensión zip de PHP, que es la que empaqueta los archivos. '
            . 'Habilita extension=zip en php.ini y reinicia el servidor.',
    ], 500);
}

$almacen = __DIR__ . '/../storage';

if (!is_dir($almacen) && !@mkdir($almacen, 0775, true) && !is_dir($almacen)) {
    responder(['error' => 'No se pudo preparar la carpeta de trabajo.'], 500);
}

limpiarAntiguos($almacen);

$inicio = microtime(true);
$nombrePeriodo = ExtraccionRepository::nombrePeriodo($anio, $quincena);
$token = bin2hex(random_bytes(16));
$temporales = [];

try {
    /* ── Consultas ──────────────────────────────────────────────────────── */

    $grupos = [];

    foreach (ExtraccionRepository::GRUPOS as $id => $meta) {
        $productos = $elegidos[$id];
        $desglose = ExtraccionRepository::desglose($id, $productos);

        $grupos[$id] = [
            'id'          => $id,
            'nombre'      => $meta['nombre'],
            'etiqueta'    => $meta['etiqueta'],
            'criterio'    => $meta['criterio'],
            'productos'   => $productos,
            'desglose'    => $desglose,
            'totales'     => ExtraccionRepository::totales($desglose),
            'retenciones' => ExtraccionRepository::retenciones($id, $productos),
        ];
    }

    /* ── Los .txt de extracción ─────────────────────────────────────────── */

    $archivos = [];

    foreach ($grupos as $id => $grupo) {
        if (!$grupo['productos']) {
            continue;
        }

        $nombre = 'UUID_' . ExtraccionRepository::GRUPOS[$id]['archivo'] . '_'
            . ExtraccionRepository::sufijo($anio, $quincena) . '.txt';

        $ruta = $almacen . '/' . $token . '_' . $id . '.tmp';
        $temporales[] = $ruta;

        $cuantos = escribirUuids($ruta, ExtraccionRepository::uuidsImpresos($id, $grupo['productos']));

        // El conteo del archivo y el de la columna IMP salen de dos consultas
        // distintas, así que compararlos es gratis y detecta un impreso sin
        // UUID, que es justo lo que haría que faltara un timbre en la entrega.
        $grupos[$id]['uuids'] = $cuantos;
        $grupos[$id]['uuidsFaltantes'] = $grupo['totales']['imp'] - $cuantos;

        $archivos[] = ['ruta' => $ruta, 'nombre' => $nombre, 'cuantos' => $cuantos];
    }

    /* ── El Excel y la nota ─────────────────────────────────────────────── */

    $markdown = Reporte::markdown($nombrePeriodo, $grupos);

    $rutaXlsx = $almacen . '/' . $token . '_libro.tmp';
    $rutaNota = $almacen . '/' . $token . '_nota.tmp';
    $temporales[] = $rutaXlsx;
    $temporales[] = $rutaNota;

    $rango = 'Quincena ' . $quincena . ' de ' . $anio . ' · ' . rangoPeriodo($anio, $quincena);

    Reporte::libro($rutaXlsx, $nombrePeriodo, $rango, $grupos);
    file_put_contents($rutaNota, $markdown);

    $archivos[] = ['ruta' => $rutaXlsx, 'nombre' => $nombrePeriodo . '.xlsx', 'cuantos' => null];
    $archivos[] = ['ruta' => $rutaNota, 'nombre' => $nombrePeriodo . '.md', 'cuantos' => null];

    /* ── El paquete ─────────────────────────────────────────────────────── */

    $rutaZip = $almacen . '/' . $token . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new \RuntimeException('No se pudo crear el paquete zip.');
    }

    // Con nombre explícito: dentro del zip los archivos llevan el nombre que
    // espera quien los consume, no el token.
    foreach ($archivos as $archivo) {
        $zip->addFile($archivo['ruta'], $archivo['nombre']);
    }

    $zip->close();
} catch (\Throwable $e) {
    foreach ($temporales as $ruta) {
        @unlink($ruta);
    }

    @unlink($almacen . '/' . $token . '.zip');

    responder(['error' => 'No se pudo generar el paquete: ' . $e->getMessage()], 500);
}

// El contenido ya vive dentro del zip; en disco solo se conserva el paquete.
foreach ($temporales as $ruta) {
    @unlink($ruta);
}

$nombreZip = $nombrePeriodo . '.zip';

$_SESSION['extraccion_archivos'][$token] = [
    'ruta'   => $rutaZip,
    'nombre' => $nombreZip,
    'creado' => time(),
];

/* ── Respuesta ──────────────────────────────────────────────────────────── */

responder([
    'periodo'   => ['anio' => $anio, 'quincena' => $quincena],
    'nombre'    => $nombrePeriodo,
    'grupos'    => array_values($grupos),
    'markdown'  => $markdown,
    'token'     => $token,
    'nombreZip' => $nombreZip,
    'archivos'  => array_map(static fn (array $a): array => [
        'nombre'  => $a['nombre'],
        'cuantos' => $a['cuantos'],
    ], $archivos),
    'ms' => (int) round((microtime(true) - $inicio) * 1000),
]);

/* ── Piezas ─────────────────────────────────────────────────────────────── */

/**
 * Escribe los UUID, uno por línea, conforme van llegando de la base.
 *
 * Salto de línea de Windows: el archivo se abre y se revisa en el Bloc de notas
 * antes de mandarlo, y con \n solo se vería todo en un renglón.
 *
 * @param \Generator<int,string> $uuids
 * @return int cuántos se escribieron
 */
function escribirUuids(string $ruta, \Generator $uuids): int
{
    $fh = fopen($ruta, 'wb');

    if ($fh === false) {
        throw new \RuntimeException('No se pudo abrir el archivo de UUID.');
    }

    $cuantos = 0;

    try {
        foreach ($uuids as $uuid) {
            fwrite($fh, $uuid . "\r\n");
            $cuantos++;
        }
    } finally {
        fclose($fh);
    }

    return $cuantos;
}

/**
 * Borra paquetes de más de una hora, para que la carpeta no crezca sin límite
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

    foreach ($_SESSION['extraccion_archivos'] ?? [] as $token => $datos) {
        if (($datos['creado'] ?? 0) < $limite) {
            unset($_SESSION['extraccion_archivos'][$token]);
        }
    }
}
