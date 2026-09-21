<?php

declare(strict_types=1);

require_once __DIR__ . '/../Classes/NominaRepository.php';

use App\NominaRepository;

/**
 * El periodo (año + quincena) que comparten Resumen, Productos y Detalle.
 *
 * Al ser tres páginas distintas, el periodo tiene que sobrevivir al salto de
 * una a otra: si eliges Q17 en Resumen y entras a Productos, esperas seguir en
 * Q17. Se guarda en la sesión, así los enlaces del rail quedan limpios y no hay
 * que arrastrar parámetros por toda la interfaz.
 *
 * Orden de resolución:
 *   1. lo que venga en la petición (el usuario acaba de cambiarlo),
 *   2. lo último que eligió en esta sesión,
 *   3. la última quincena con timbres en la base — no la del calendario, que
 *      suele estar en curso y todavía vacía.
 *
 * @return array{anio:int,quincena:int}
 */
function periodoActual(?int $anio = null, ?int $quincena = null): array
{
    if ($anio === null || $quincena === null) {
        $guardado = $_SESSION['periodo'] ?? null;

        if (is_array($guardado) && isset($guardado['anio'], $guardado['quincena'])) {
            $anio ??= (int) $guardado['anio'];
            $quincena ??= (int) $guardado['quincena'];
        }
    }

    if ($anio === null || $quincena === null) {
        $vigente = NominaRepository::periodoVigente();
        $anio ??= (int) $vigente['anio'];
        $quincena ??= (int) $vigente['quincena'];
    }

    $periodo = ['anio' => $anio, 'quincena' => max(1, min(24, $quincena))];
    $_SESSION['periodo'] = $periodo;

    return $periodo;
}

/**
 * El mismo periodo, pero leyendo lo que haya llegado por GET. Lo usan tanto las
 * páginas como sus endpoints.
 *
 * @return array{anio:int,quincena:int}
 */
function periodoDeLaPeticion(): array
{
    $anio = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int) $_GET['anio'] : null;
    $quincena = isset($_GET['quincena']) && $_GET['quincena'] !== '' ? (int) $_GET['quincena'] : null;

    return periodoActual($anio, $quincena);
}

/** Rango de fechas de una quincena, para mostrarlo junto al selector. */
function rangoPeriodo(int $anio, int $quincena): string
{
    $mes = (int) ceil($quincena / 2);
    $impar = $quincena % 2 === 1;
    $ultimo = (int) date('t', (int) mktime(0, 0, 0, $mes, 1, $anio));

    return sprintf('%04d-%02d-%02d al %04d-%02d-%02d',
        $anio, $mes, $impar ? 1 : 16,
        $anio, $mes, $impar ? 15 : $ultimo);
}
