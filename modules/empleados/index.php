<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('empleados');

paginaInicio([
    'slug' => 'empleados',
    'titulo' => 'Empleados',
    'kicker' => 'Comprobantes de nómina',
    'h1' => 'Empleados',
    'subtitulo' => 'Selecciona un RFC para ver sus timbres',
    'fuente' => 'bpm',
    'loader' => 'Cargando empleados',
]);
?>

<div id="vista-lista">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap">
        <input id="buscar" class="field field--pill" placeholder="Buscar por nombre, apellidos, RFC o CURP…"
               autocomplete="off" spellcheck="false" style="flex:1;min-width:280px;max-width:430px">
        <div id="contador" style="font-size:12.5px;color:var(--ink-faint)"></div>
    </div>
    <div id="lista" class="tabla"></div>
</div>

<div id="vista-detalle" hidden>
    <div id="volver" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px;color:var(--wine);font-weight:600;font-size:13px;margin-bottom:18px">← Volver a empleados</div>

    <div class="hero" style="padding:26px 30px;margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div id="det-iniciales" style="width:52px;height:52px;border-radius:50%;background:rgba(255,238,240,0.16);color:var(--hero-ink);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:16px;flex:0 0 auto;position:relative"></div>
        <div style="position:relative;flex:1;min-width:200px">
            <div id="det-nombre" class="figure" style="font-size:21px;color:var(--hero-ink)"></div>
            <div id="det-claves" style="font-size:12.5px;color:var(--hero-ink-soft);margin-top:6px;letter-spacing:0.3px"></div>
        </div>
        <div style="position:relative;text-align:right">
            <div id="det-timbres" class="figure" style="font-size:26px;color:var(--hero-ink)">0</div>
            <div class="eyebrow" style="color:var(--hero-ink-faint);margin-top:4px">Timbres</div>
        </div>
    </div>

    <div class="tabla">
        <div class="tabla__head" style="display:grid;grid-template-columns:0.4fr 1.5fr 0.9fr 0.9fr 0.9fr 0.9fr 0.9fr 0.9fr 0.8fr">
            <div>#</div><div>Clave de pago</div><div>CLUES</div><div>Código</div><div>Inicio</div><div>Fin</div><div>Percepc.</div><div>Deduc.</div><div>Estado</div>
        </div>
        <div id="det-tabla" style="max-height:440px;overflow-y:auto"></div>
    </div>
</div>

<?php paginaFin(['js/empleados.js?v=' . filemtime(__DIR__ . '/js/empleados.js')]); ?>
