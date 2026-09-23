<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/periodo.php';
require_once __DIR__ . '/../../includes/pagina.php';
require_once __DIR__ . '/ExtraccionRepository.php';

requireModulo('extraccion');

use App\NominaRepository;

/**
 * Cierre de Regularizados y Eventuales IMSS.
 *
 * La pantalla se lee de arriba abajo, en el mismo orden del trámite: primero
 * qué productos entran (que es el paso que antes se hacía a ojo), luego lo que
 * salió, y al final lo que uno se lleva — los .txt, el Excel y la nota.
 *
 * Usa el periodo compartido de includes/periodo.php: esto lee BPM igual que
 * Resumen y Productos, así que elegir la quincena aquí y saltar allá tiene que
 * dejarte en la misma.
 */
$periodo = periodoDeLaPeticion();

/**
 * Los archivos se arman en storage/ antes de empaquetarlos. La carpeta viene en
 * el repositorio, así que existe siempre; lo que un despliegue nuevo no suele
 * traer es el permiso de escritura para el usuario del servidor web. Se avisa al
 * cargar y no hasta que alguien pulsa Generar, que es cuando ya esperó a que se
 * consultara la quincena entera para nada. El endpoint lo comprueba otra vez.
 */
$almacenEscribible = is_dir(__DIR__ . '/storage') && is_writable(__DIR__ . '/storage');

paginaInicio([
    'slug' => 'extraccion',
    'titulo' => 'Extracción de timbres',
    'kicker' => 'Nómina · cierre de quincena',
    'h1' => 'Regularizados y Eventuales IMSS',
    'subtitulo' => 'Los UUID para extracción, el resumen en Excel y la nota de la quincena',
    'fuente' => 'bpm',
    'loader' => 'Consultando la quincena',
    'css' => ['css/extraccion.css?v=' . filemtime(__DIR__ . '/css/extraccion.css')],
]);

selectorPeriodo($periodo, NominaRepository::aniosDisponibles());
?>

<div class="ext-ayuda card">
    Los productos de la quincena se leen de <code>producto_nomina</code> y se
    proponen marcados los que tienen filas del grupo: <strong>Regularizados</strong>
    son las de <code>unidad = 'REG'</code> <em>en los productos de base</em>
    (<code>PRD*</code>) e <strong>IMSS</strong> las de <code>clavep LIKE '%IMSS%'</code>.
    Los productos de eventuales también traen filas <code>REG</code>, pero esos son
    regularizados eventuales y no entran: se listan aparte, apagados, para que se
    vea que se descartan adrede. Revisa las casillas antes de generar. Todo es de
    solo lectura; no se escribe nada en la base.
</div>

<?php if (!$almacenEscribible): ?>
    <div class="ext-aviso ext-aviso--error">
        La carpeta <code>modules/extraccion/storage</code> no acepta escritura, y ahí es
        donde se arman los archivos antes de empaquetarlos. Mientras siga así, generar
        va a fallar: el servidor web necesita permiso sobre ella.
    </div>
<?php endif; ?>

<div id="aviso" class="ext-aviso" hidden></div>

<div id="grupos" class="ext-grupos"></div>

<div class="ext-barra">
    <button type="button" class="btn-primary" id="generar" disabled>Generar paquete</button>
    <span id="seleccion" class="ext-contador"></span>
</div>

<div id="resultado" hidden>

    <div class="ext-metricas" id="metricas"></div>

    <div class="ext-salidas">

        <div class="card ext-descargas">
            <div class="eyebrow">Archivos</div>
            <div id="archivos" class="ext-archivos"></div>
            <a class="btn-ghost ext-zip" id="descargar-zip" download>Descargar todo en zip</a>
        </div>

        <div class="card ext-nota">
            <div class="ext-nota__cabeza">
                <div class="eyebrow">Nota de la quincena</div>
                <button type="button" class="btn-ghost ext-copiar" id="copiar">Copiar markdown</button>
            </div>
            <pre id="markdown" class="ext-markdown"></pre>
        </div>
    </div>

    <div class="card ext-detalle">
        <div class="eyebrow">Desglose por producto</div>
        <div class="tabla ext-tabla">
            <div class="tabla__head ext-grid">
                <div>Unidad</div><div>Producto</div>
                <div class="ext-num">ACT</div><div class="ext-num">CAN</div>
                <div class="ext-num">INV</div><div class="ext-num">IMP</div>
                <div class="ext-num">Total</div>
            </div>
            <div id="detalle"></div>
        </div>
    </div>
</div>

<?php paginaFin(['js/extraccion.js?v=' . filemtime(__DIR__ . '/js/extraccion.js')]); ?>
