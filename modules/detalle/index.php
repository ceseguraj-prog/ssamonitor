<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/periodo.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('detalle');

use App\NominaRepository;

$periodo = periodoDeLaPeticion();

paginaInicio([
    'slug' => 'detalle',
    'titulo' => 'Detalle de registros',
    'kicker' => 'Comprobantes de nómina',
    'h1' => 'Detalle de registros',
    'subtitulo' => 'Auditoría fila por fila de los registros timbrados',
    'fuente' => 'bpm',
    'loader' => 'Cargando registros',
]);

selectorPeriodo($periodo, NominaRepository::aniosDisponibles());
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <input id="buscar" class="field field--pill" placeholder="Buscar RFC…" style="min-width:230px" autocomplete="off" spellcheck="false">
    <?php /* Las opciones las llena el JS con las claves de la quincena: cambian
             de un periodo a otro, no pueden estar fijas aquí. */ ?>
    <select id="filtro-producto" class="field field--pill" style="cursor:pointer">
        <option value="todos">Todos los productos</option>
    </select>
    <select id="filtro-estado" class="field field--pill" style="cursor:pointer">
        <option value="todos">Todos los estados</option>
        <option value="IMP">Impreso</option>
        <option value="ACT">Activo (pendiente)</option>
        <option value="CAN">Cancelado</option>
        <option value="INV">Invisible</option>
    </select>
    <div id="contador" style="margin-left:auto;font-size:12.5px;color:var(--ink-faint)">0 registros</div>
</div>

<div class="tabla">
    <div class="tabla__head" style="display:grid;grid-template-columns:0.9fr 0.9fr 1.4fr 1fr 1fr 1fr 1fr">
        <div>Producto</div><div>Estado</div><div>RFC</div><div>Total 1</div><div>Total 2</div><div>Inicio</div><div>Fin</div>
    </div>
    <div id="tabla" style="max-height:540px;overflow-y:auto"></div>
</div>

<?php paginaFin(['js/detalle.js?v=' . filemtime(__DIR__ . '/js/detalle.js')]); ?>
