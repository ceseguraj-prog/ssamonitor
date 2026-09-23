<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../../../includes/periodo.php';
require_once __DIR__ . '/../ExtraccionRepository.php';

requireModulo('extraccion', true);

use App\ExtraccionRepository;

header('Content-Type: application/json; charset=utf-8');

/**
 * Qué productos del periodo tocan Regularizados y cuáles IMSS Bienestar.
 *
 * Es el paso que se hacía a ojo antes de escribir el IN de la consulta. Aquí
 * solo se propone: los productos llegan marcados, pero quien genera decide.
 */
try {
    ['anio' => $anio, 'quincena' => $quincena] = periodoDeLaPeticion();

    $inventario = ExtraccionRepository::inventario($anio, $quincena);
    $grupos = [];

    foreach (ExtraccionRepository::GRUPOS as $id => $grupo) {
        $productos = $inventario[$id]['productos'] ?? [];

        $grupos[] = [
            'id'          => $id,
            'nombre'      => $grupo['nombre'],
            'etiqueta'    => $grupo['etiqueta'],
            'criterio'    => $grupo['criterio'],
            'prefijos'    => $grupo['prefijos'],
            'productos'   => $productos,
            'descartados' => $inventario[$id]['descartados'] ?? [],
            'totales'     => ExtraccionRepository::totales($productos),
        ];
    }

    echo json_encode([
        'periodo' => ['anio' => $anio, 'quincena' => $quincena],
        'nombre'  => ExtraccionRepository::nombrePeriodo($anio, $quincena),
        'grupos'  => $grupos,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
