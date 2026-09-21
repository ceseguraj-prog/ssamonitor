<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

use App\EmpleadosRepository;

header('Content-Type: application/json');

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 200);
$length = $length > 0 ? min($length, 200) : 200;

$columnas = EmpleadosRepository::columnas();

$ordenColumna = (int) ($_GET['order'][0]['column'] ?? 0);
$ordenDireccion = (string) ($_GET['order'][0]['dir'] ?? 'asc');

$filtros = [];
foreach ($_GET['columns'] ?? [] as $indice => $columna) {
    $valor = trim((string) ($columna['search']['value'] ?? ''));
    if ($valor !== '' && isset($columnas[$indice])) {
        $filtros[$columnas[$indice]] = $valor;
    }
}

try {
    $total = EmpleadosRepository::contarRegistros([]);
    $filtrado = EmpleadosRepository::contarRegistros($filtros);
    $rows = EmpleadosRepository::registros($filtros, $ordenColumna, $ordenDireccion, $length, $start);

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtrado,
        'data' => array_map('array_values', $rows),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'No se pudo consultar BPM: ' . $e->getMessage(),
    ]);
}
