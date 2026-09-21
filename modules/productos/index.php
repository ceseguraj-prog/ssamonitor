<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/periodo.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('productos');

use App\NominaRepository;

$periodo = periodoDeLaPeticion();

paginaInicio([
    'slug' => 'productos',
    'titulo' => 'Productos del periodo',
    'kicker' => 'Comprobantes de nómina',
    'h1' => 'Productos del periodo',
    'subtitulo' => 'Peso y estado de cada producto que integra la quincena',
    'fuente' => 'bpm',
    'loader' => 'Cargando productos',
]);

selectorPeriodo($periodo, NominaRepository::aniosDisponibles());
?>

<div id="grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:18px"></div>

<?php paginaFin(['js/productos.js?v=' . filemtime(__DIR__ . '/js/productos.js')]); ?>
