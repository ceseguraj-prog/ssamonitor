<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('conceptos', true);

use App\ConceptosRepository;

header('Content-Type: application/json');

/**
 * Una búsqueda normal de un año entero ronda 1 s (medido: 1.3 s para 251|257 en
 * 2026, seis tablas). Pero un prefijo de uso masivo recorre muchísimo más: 221
 * y 201 se van a ~8.5 s porque casan con cientos de miles de filas y terminan
 * topando en el límite de coincidencias. Con el tope por omisión de 30 s eso
 * queda al filo, así que se amplía para esta petición nada más.
 */
set_time_limit(180);

try {
    ['codigos' => $codigos, 'ignorados' => $ignorados] =
        ConceptosRepository::normalizarCodigos((string) ($_GET['codigos'] ?? ''));

    if ($codigos === []) {
        http_response_code(400);
        $detalle = $ignorados === []
            ? 'Escribe al menos un código de concepto (por ejemplo 251 o 26700).'
            : 'Ningún código es válido: ' . implode(', ', $ignorados)
                . '. Un código lleva de 3 a 5 caracteres (251, 26700, 267CG).';

        echo json_encode(['error' => $detalle]);
        exit;
    }

    $anio = (int) ($_GET['anio'] ?? 0);

    // El año es obligatorio y no hay opción de "todos": sin él la consulta pierde
    // el índice ANIO y pasa de leer ~1/21 de las tablas a leer los 12 GB completos.
    if ($anio <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta el ejercicio a consultar.']);
        exit;
    }

    $quincena = isset($_GET['quincena']) && $_GET['quincena'] !== ''
        ? max(1, min(24, (int) $_GET['quincena']))
        : null;

    // Llegan como lista separada por comas; ConceptosRepository las cruza contra su
    // whitelist, así que un nombre inventado simplemente no se consulta.
    $tablas = array_filter(explode(',', (string) ($_GET['tablas'] ?? '')));

    $excluirUr = ($_GET['excluirUr'] ?? '1') !== '0';

    $inicio = microtime(true);
    $resultado = ConceptosRepository::buscar($anio, $codigos, $quincena, $tablas, $excluirUr);
    $ms = (int) round((microtime(true) - $inicio) * 1000);

    echo json_encode([
        'filas' => $resultado['filas'],
        'porTabla' => $resultado['porTabla'],
        'porConcepto' => ConceptosRepository::resumenPorConcepto($resultado['filas']),
        'importe' => $resultado['importe'],
        'truncado' => $resultado['truncado'],
        'tope' => ConceptosRepository::TOPE,
        'codigos' => $codigos,
        'ignorados' => $ignorados,
        'ms' => $ms,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
