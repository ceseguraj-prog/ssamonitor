<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('conceptos', true);

use App\CatalogoConceptosRepository;

header('Content-Type: application/json; charset=utf-8');

/**
 * Catálogo unificado: solo lee las siete tablas de conceptos y las junta en
 * memoria. Es rápido (~300 ms) porque entre todas suman ~1,700 filas, muy lejos
 * de las tablas de nómina que consulta buscar.php.
 *
 * Se entrega COMPLETO, sin tope ni filtro del servidor: son 426 claves, 101 KB
 * de JSON, y la pantalla filtra en el navegador. Recortarlo aquí fue un error
 * real: con el tope de 300 la última clave que viajaba era 246PC, así que buscar
 * 258 en el modal no encontraba nada aunque el concepto sí existiera.
 */
try {
    $catalogo = array_values(CatalogoConceptosRepository::todos());

    echo json_encode([
        'filas' => $catalogo,
        'total' => count($catalogo),
        'fuentes' => CatalogoConceptosRepository::resumenFuentes(),
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
