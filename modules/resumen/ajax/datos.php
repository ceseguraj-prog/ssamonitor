<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../../../includes/periodo.php';

requireModulo('resumen', true);

use App\NominaRepository;

header('Content-Type: application/json');

try {
    // periodoDeLaPeticion() también lo guarda en la sesión, así que al cambiar
    // de quincena aquí, el resto de las pantallas abre en la misma.
    ['anio' => $anio, 'quincena' => $quincena] = periodoDeLaPeticion();

    $trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $q = $quincena - $i;
        $a = $anio;
        if ($q < 1) {
            $q += 24;
            $a--;
        }

        $c = NominaRepository::estadoCounts($a, $q);

        // Los invisibles no se timbran nunca, así que no entran en la base: si
        // contaran, el avance jamás llegaría a 100%. Lo que queda pendiente son
        // los activos; un cancelado ya se timbró.
        $base = ($c['imp'] ?? 0) + ($c['act'] ?? 0) + ($c['can'] ?? 0);
        $timbrados = ($c['imp'] ?? 0) + ($c['can'] ?? 0);
        $pct = $base > 0 ? round(($timbrados / $base) * 100, 1) : 0;

        // 10,196 de 10,198 redondea a 100% con dos pendientes vivos. El 100% se
        // reserva para cuando de verdad no falta ninguno.
        if ($pct >= 100 && $timbrados < $base) {
            $pct = 99.9;
        }

        $trend[] = ['q' => $q, 'pct' => $pct];
    }

    echo json_encode([
        'quincena' => NominaRepository::estadoCounts($anio, $quincena),
        'anio' => NominaRepository::estadoCounts($anio),
        'trend' => $trend,
        'periodo' => ['anio' => $anio, 'quincena' => $quincena],
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
