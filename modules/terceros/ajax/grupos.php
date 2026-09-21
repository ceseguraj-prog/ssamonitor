<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('terceros', true);

use App\TercerosRepository;

header('Content-Type: application/json; charset=utf-8');

/** Responde y termina. */
function responder(array $cuerpo, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Consultar el reporte lo puede hacer cualquiera; tocar el catálogo no. Se
 * comprueba en el servidor y no solo escondiendo el botón: ocultar un botón no
 * es una restricción.
 */
$admin = (array) (modulosRegistrados()['terceros']['admin'] ?? []);

if ($admin !== [] && !usuarioEsAlguno(...$admin)) {
    responder(['error' => 'No tienes permiso para cambiar el catálogo de grupos.'], 403);
}

$accion = (string) ($_POST['accion'] ?? $_GET['accion'] ?? 'listar');

/* ── Listar ─────────────────────────────────────────────────────────────── */

if ($accion === 'listar') {
    $grupos = [];

    foreach (TercerosRepository::catalogo(true) as $nombre => $prefijos) {
        $nombre = (string) $nombre;
        $fabrica = TercerosRepository::prefijosDeFabrica($nombre);

        $grupos[] = [
            'nombre' => $nombre,
            'prefijos' => $prefijos,
            'deFabrica' => $fabrica !== null,
            // Un grupo de fábrica al que le cambiaron los prefijos: conviene
            // que se note, porque ya no coincide con lo que dice el código.
            'modificado' => $fabrica !== null && $fabrica !== $prefijos,
            'prefijosFabrica' => $fabrica,
        ];
    }

    responder(['grupos' => $grupos]);
}

/* ── Probar un prefijo antes de darlo de alta ───────────────────────────── */

if ($accion === 'probar') {
    $anio = (int) ($_POST['anio'] ?? 0);
    $quincena = (int) ($_POST['quincena'] ?? 0);

    if (!TercerosRepository::periodoValido($anio, $quincena)) {
        responder(['error' => 'Elige un ejercicio y una quincena válidos.'], 400);
    }

    @set_time_limit(300);

    try {
        $resultado = TercerosRepository::probarPrefijo((string) ($_POST['prefijo'] ?? ''), $anio, $quincena);
    } catch (\Throwable $e) {
        responder(['error' => $e->getMessage()], 400);
    }

    $prefijo = TercerosRepository::normalizarPrefijo((string) $_POST['prefijo']);

    responder($resultado + [
        'prefijo' => $prefijo,
        'grupoActual' => TercerosRepository::grupoDelPrefijo($prefijo),
        'anio' => $anio,
        'quincena' => $quincena,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido.'], 405);
}

/* ── Guardar ────────────────────────────────────────────────────────────── */

if ($accion === 'guardar') {
    $prefijos = $_POST['prefijos'] ?? '';

    // Se acepta tanto "246, 250" como un arreglo del formulario.
    if (is_string($prefijos)) {
        $prefijos = preg_split('/[^0-9A-Za-z]+/', $prefijos) ?: [];
    }

    try {
        $grupo = TercerosRepository::guardarGrupo((string) ($_POST['nombre'] ?? ''), (array) $prefijos);
    } catch (\Throwable $e) {
        responder(['error' => $e->getMessage()], 400);
    }

    responder(['ok' => true, 'grupo' => $grupo]);
}

/* ── Eliminar ───────────────────────────────────────────────────────────── */

if ($accion === 'eliminar') {
    try {
        TercerosRepository::eliminarGrupo((string) ($_POST['nombre'] ?? ''));
    } catch (\Throwable $e) {
        responder(['error' => $e->getMessage()], 400);
    }

    responder(['ok' => true]);
}

responder(['error' => 'Acción desconocida.'], 400);
