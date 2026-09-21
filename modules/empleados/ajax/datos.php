<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('empleados', true);

use App\EmpleadosIndex;
use App\Database;

header('Content-Type: application/json');

try {
    switch ($_GET['accion'] ?? 'buscar') {
        case 'buscar':
            // La búsqueda se resuelve contra el snapshot local (EmpleadosIndex)
            // y no contra BPM: en la tabla empleados no hay índice por nombre y
            // un LIKE por tecleo obligaba a un escaneo completo de la tabla.
            $limite = (int) ($_GET['limite'] ?? EmpleadosIndex::MAX_RESULTADOS);
            $limite = max(1, min($limite, EmpleadosIndex::MAX_RESULTADOS));

            echo json_encode(EmpleadosIndex::buscar(trim((string) ($_GET['search'] ?? '')), $limite));
            break;

        case 'pagos':
            $rfc = trim((string) ($_GET['rfc'] ?? ''));

            if ($rfc === '') {
                echo json_encode(['rows' => []]);
                break;
            }

            echo json_encode([
                'rows' => Database::get('bpm')->query(
                    'SELECT producto AS codigo, estado, total1 AS percepciones, total2 AS deducciones,'
                    . ' fechai AS inicio, fechaf AS fin FROM detalle_nomina WHERE rfc = ? ORDER BY fechai DESC LIMIT 50',
                    [$rfc]
                ),
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no reconocida.']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
