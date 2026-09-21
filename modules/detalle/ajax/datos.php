<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../../../includes/periodo.php';

requireModulo('detalle', true);

use App\NominaRepository;

header('Content-Type: application/json');

try {
    ['anio' => $anio, 'quincena' => $quincena] = periodoDeLaPeticion();

    $busqueda = trim((string) ($_GET['search'] ?? ''));
    $filtroProducto = trim((string) ($_GET['filtroProducto'] ?? 'todos'));
    $filtroEstado = trim((string) ($_GET['filtroEstado'] ?? 'todos'));

    $filtros = [];
    if ($busqueda !== '') {
        $filtros['rfc'] = $busqueda;
    }
    if ($filtroProducto !== 'todos') {
        $filtros['producto'] = $filtroProducto;
    }
    if ($filtroEstado !== 'todos') {
        $filtros['estado'] = $filtroEstado;
    }

    $claves = NominaRepository::productosClaves($anio, $quincena);
    sort($claves);

    $tope = 500;

    // El tope protege a la base, pero ordenado por producto se lleva entero el
    // primer producto alfabético y los demás nunca asoman. Se manda el total
    // real para poder decirlo en pantalla.
    echo json_encode([
        'rows' => NominaRepository::registros($claves, $filtros, 0, 'asc', $tope, 0),
        'total' => NominaRepository::contarRegistros($claves, $filtros),
        'tope' => $tope,
        'productos' => $claves,
        'periodo' => ['anio' => $anio, 'quincena' => $quincena],
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
