<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../../../includes/periodo.php';

requireModulo('productos', true);

use App\NominaRepository;
use App\Database;

header('Content-Type: application/json');

try {
    ['anio' => $anio, 'quincena' => $quincena] = periodoDeLaPeticion();

    $productos = [];

    foreach (NominaRepository::productosClaves($anio, $quincena) as $clave) {
        // Estado y unidad en una sola consulta ligera por producto.
        $rows = Database::get('bpm')->query(
            'SELECT estado, unidad, COUNT(*) AS total FROM detalle_nomina WHERE producto = ? GROUP BY estado, unidad',
            [$clave]
        );

        $estados = ['imp' => 0, 'act' => 0, 'can' => 0, 'inv' => 0];
        $unidades = [];

        foreach ($rows as $row) {
            $estado = strtolower(trim((string) $row['estado']));
            $unidad = trim((string) $row['unidad']);
            $cuantos = (int) $row['total'];

            if (isset($estados[$estado])) {
                $estados[$estado] += $cuantos;
            }

            $unidades[$unidad] = ($unidades[$unidad] ?? 0) + $cuantos;
        }

        // Los conceptos se leen de mayor a menor: el primero explica el grueso.
        arsort($unidades);

        $productos[] = [
            'codigo' => $clave,
            'nombre' => $clave,
            'counts' => $estados,
            'unidades' => $unidades,
            'total' => array_sum($estados),
        ];
    }

    echo json_encode([
        'productos' => $productos,
        'periodo' => ['anio' => $anio, 'quincena' => $quincena],
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
