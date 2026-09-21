<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/periodo.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('resumen');

use App\NominaRepository;

$periodo = periodoDeLaPeticion();

paginaInicio([
    'slug' => 'resumen',
    'titulo' => 'Resumen de timbrado',
    'kicker' => 'Comprobantes de nómina',
    'h1' => 'Resumen de timbrado',
    'subtitulo' => 'Avance de la quincena y acumulado del año',
    'fuente' => 'bpm',
    'loader' => 'Cargando resumen',
]);

selectorPeriodo($periodo, NominaRepository::aniosDisponibles());
?>

<div id="contenido">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(440px,1fr));gap:20px;align-items:stretch">

        <!-- HERO: la quincena -->
        <div class="hero rise-in" style="padding:34px 38px">
            <div class="hero__sheen"></div>
            <div style="display:flex;align-items:center;gap:34px;position:relative;flex-wrap:wrap">
                <div class="ring-in" style="--i:1;width:196px;height:196px;position:relative;flex:0 0 auto">
                    <svg id="q-ring" width="196" height="196" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
                        <circle cx="50" cy="50" r="41" fill="none" stroke="rgba(255,238,240,0.13)" stroke-width="9"/>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                        <div class="figure" style="font-size:42px;color:var(--hero-ink);letter-spacing:-1px"><span id="q-pct">0</span><span style="font-size:22px">%</span></div>
                        <div class="eyebrow" style="color:var(--hero-ink-soft);margin-top:8px">Timbrado</div>
                    </div>
                </div>
                <div style="flex:1;min-width:240px">
                    <div id="q-title" class="eyebrow" style="letter-spacing:1.3px;color:var(--hero-ink-faint)">Quincena</div>
                    <div class="figure" style="font-size:26px;color:var(--hero-ink);margin:8px 0 20px;font-weight:400">
                        <span id="q-total">0</span>
                        <span style="font-size:14px;font-family:var(--font-ui);color:var(--hero-ink-soft)">timbrables en el periodo</span>
                    </div>
                    <div id="q-legend" style="display:flex;flex-direction:column;gap:11px"></div>
                </div>
            </div>
        </div>

        <!-- Acumulado del año: deliberadamente más discreto -->
        <div class="card rise-in" style="--i:1;padding:30px 32px;display:flex;flex-direction:column;gap:20px">
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px">
                <div id="y-title" class="eyebrow" style="letter-spacing:1.3px">Acumulado</div>
                <div style="font-size:12px;color:var(--ink-faint)">24 quincenas</div>
            </div>
            <div style="display:flex;align-items:center;gap:26px;flex-wrap:wrap">
                <div class="ring-in" style="--i:2;width:116px;height:116px;position:relative;flex:0 0 auto">
                    <svg id="y-ring" width="116" height="116" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="var(--track)" stroke-width="8"/>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
                        <div class="figure" style="font-size:23px"><span id="y-pct">0</span><span style="font-size:13px">%</span></div>
                    </div>
                </div>
                <div id="y-legend" style="display:flex;flex-direction:column;gap:10px;flex:1;min-width:150px"></div>
            </div>
        </div>
    </div>

    <!-- KPI -->
    <div id="tiles" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-top:20px"></div>

    <!-- Tendencia -->
    <div class="card rise-in" style="--i:4;padding:26px 30px 22px;margin-top:20px">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;gap:10px">
            <div class="eyebrow" style="letter-spacing:1.3px">Tendencia de timbrado</div>
            <div style="font-size:12px;color:var(--ink-faint)">últimas 6 quincenas</div>
        </div>
        <div id="trend" style="display:flex;align-items:flex-end;gap:22px;height:150px"></div>
    </div>

    <!-- Accesos -->
    <div class="rise-in" style="--i:5;display:flex;gap:16px;margin-top:20px;flex-wrap:wrap">
        <a class="card lift" href="../productos/index.php" style="flex:1;min-width:230px;border-radius:var(--r-tile);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;text-decoration:none;color:inherit">
            <div>
                <div style="font-size:14.5px;font-weight:600">Productos de la quincena</div>
                <div style="font-size:12px;color:var(--ink-muted);margin-top:3px">Desglose por producto del periodo</div>
            </div>
            <span style="color:var(--wine);font-size:17px">→</span>
        </a>
        <a class="card lift" href="../detalle/index.php" style="flex:1;min-width:230px;border-radius:var(--r-tile);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;text-decoration:none;color:inherit">
            <div>
                <div style="font-size:14.5px;font-weight:600">Detalle de registros</div>
                <div style="font-size:12px;color:var(--ink-muted);margin-top:3px">Auditoría fila por fila</div>
            </div>
            <span style="color:var(--wine);font-size:17px">→</span>
        </a>
    </div>
</div>

<div id="error" class="emp-vacio" hidden></div>

<?php paginaFin(['js/resumen.js?v=' . filemtime(__DIR__ . '/js/resumen.js')]); ?>
