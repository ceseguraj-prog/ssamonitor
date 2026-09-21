<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('conceptos');

use App\ConceptosRepository;

/**
 * Esta pantalla no usa includes/periodo.php a propósito: el periodo compartido es
 * de quincena vigente y aquí lo normal es consultar un año entero hacia atrás. Un
 * selector propio evita que elegir 2019 aquí mueva el resumen de timbrado.
 */
$anios = ConceptosRepository::aniosDisponibles();
$anioPorDefecto = $anios[0] ?? (int) date('Y');

// Los años vienen sucios (hay 2124, 2030, 2224 por capturas erróneas), así que el
// primero de la lista descendente puede ser basura. Se arranca en el año plausible
// más alto, pero se listan todos: son datos reales que alguien puede necesitar ver.
foreach ($anios as $anio) {
    if ($anio <= (int) date('Y') + 1) {
        $anioPorDefecto = $anio;
        break;
    }
}

paginaInicio([
    'slug' => 'conceptos',
    'titulo' => 'Conceptos de pago',
    'kicker' => 'Nómina · catálogos',
    'h1' => 'Búsqueda de conceptos',
    'subtitulo' => 'Localiza una clave de concepto en las seis tablas de nómina',
    'fuente' => 'catalogos',
    'loader' => 'Buscando conceptos',
    'css' => ['css/conceptos.css?v=' . filemtime(__DIR__ . '/css/conceptos.css')],
]);
?>

<form id="filtros" class="card" style="padding:22px 24px;margin-bottom:20px">

    <div class="cnp-fila">
        <label class="cnp-campo">
            <span class="eyebrow">Códigos</span>
            <input id="codigos" class="field" placeholder="251, 257, 26700"
                   autocomplete="off" spellcheck="false" required>
        </label>

        <label class="cnp-campo cnp-campo--corto">
            <span class="eyebrow">Ejercicio</span>
            <select id="anio" class="field">
                <?php foreach ($anios as $anio): ?>
                    <option value="<?= $anio ?>" <?= $anio === $anioPorDefecto ? 'selected' : '' ?>><?= $anio ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="cnp-campo cnp-campo--corto">
            <span class="eyebrow">Quincena</span>
            <select id="quincena" class="field">
                <option value="">Todo el año</option>
                <?php for ($q = 1; $q <= 24; $q++): ?>
                    <option value="<?= $q ?>">Q<?= $q ?></option>
                <?php endfor; ?>
            </select>
        </label>

        <button type="submit" class="btn-primary" id="buscar">Buscar</button>

        <?php /* El catálogo es solo consulta de significados: no lee nómina ni
                 suma importes, así que lo puede abrir cualquiera que entre. */ ?>
        <button type="button" class="btn-ghost cnp-catalogo-btn" id="abrir-catalogo"
                title="Qué significa cada clave de concepto">Catálogo</button>
    </div>

    <div class="cnp-ayuda">
        Se busca por <strong>prefijo</strong>: <code>251</code> encuentra 25100, 251CG y cualquier
        subclave; <code>26700</code> encuentra solo esa. Separa varios con comas o espacios.
    </div>

    <details class="cnp-avanzado">
        <summary>Opciones avanzadas</summary>

        <div class="cnp-tablas">
            <?php foreach (ConceptosRepository::tablas() as $tabla): ?>
                <label class="cnp-check">
                    <input type="checkbox" name="tabla" value="<?= htmlspecialchars($tabla) ?>" checked>
                    <span><?= htmlspecialchars($tabla) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php /* La consulta original excluía siempre la UR 610. Se deja visible y
                 desmarcable en vez de escondido en el SQL, para que quien lea un
                 total sepa qué se dejó fuera. */ ?>
        <label class="cnp-check cnp-check--suelto">
            <input type="checkbox" id="excluir-ur" checked>
            <span>Excluir UR <?= ConceptosRepository::UR_EXCLUIDA ?></span>
        </label>
    </details>
</form>

<div id="aviso" class="cnp-aviso" hidden></div>

<div id="resultado" hidden>

    <div class="cnp-metricas">
        <div class="card cnp-tile">
            <div class="eyebrow">Coincidencias</div>
            <div class="figure" id="m-matches">0</div>
        </div>
        <div class="card cnp-tile">
            <div class="eyebrow">Registros</div>
            <div class="figure" id="m-filas">0</div>
        </div>
        <div class="card cnp-tile">
            <div class="eyebrow">Importe total</div>
            <div class="figure" id="m-importe">$0</div>
        </div>
        <div class="card cnp-tile">
            <div class="eyebrow">Claves distintas</div>
            <div class="figure" id="m-claves">0</div>
        </div>
    </div>

    <div class="card cnp-desglose">
        <div class="eyebrow">Por concepto</div>
        <div id="por-concepto" class="cnp-chips"></div>
    </div>

    <div class="card cnp-desglose">
        <div class="eyebrow">Por tabla</div>
        <div id="por-tabla" class="cnp-chips"></div>
    </div>

    <div class="cnp-barra">
        <input id="filtrar" class="field field--pill" placeholder="Filtrar por RFC, nombre o concepto…"
               autocomplete="off" spellcheck="false">
        <button type="button" class="btn-ghost" id="exportar">Descargar CSV</button>
        <div id="contador" class="cnp-contador">0 coincidencias</div>
    </div>

    <div class="tabla">
        <div class="tabla__head cnp-grid">
            <div>Tabla</div><div>QNA</div><div>Tipo</div><div>UR</div>
            <div>RFC</div><div>Nombre</div><div>Concepto</div><div>Slot</div>
            <div>AQ</div><div class="cnp-num">Importe</div>
        </div>
        <div id="tabla" class="cnp-cuerpo"></div>
    </div>

    <button type="button" class="btn-ghost cnp-mas" id="mas" hidden>Mostrar más</button>
</div>

<?php /* Catálogo de significados. Es un directorio, no un reporte: aquí no hay
         importes ni se consulta la nómina. Sale de las siete tablas de conceptos
         de `catalogos`, recompuestas en memoria (ver CatalogoConceptosRepository). */ ?>
<div class="cnp-modal" id="modal-catalogo" hidden>
    <div class="cnp-modal__fondo" data-cerrar></div>

    <div class="cnp-modal__hoja" role="dialog" aria-modal="true" aria-labelledby="cat-titulo">

        <div class="cnp-modal__cabeza">
            <div>
                <div class="eyebrow">Directorio</div>
                <h2 id="cat-titulo" class="cnp-modal__titulo">Catálogo de conceptos</h2>
                <div class="cnp-modal__sub" id="cat-resumen">Qué significa cada clave</div>
            </div>
            <button type="button" class="cnp-modal__cerrar" data-cerrar aria-label="Cerrar">&times;</button>
        </div>

        <div class="cnp-modal__barra">
            <input id="cat-buscar" class="field field--pill"
                   placeholder="Clave (264, 26430) o texto (fovissste, pensión)…"
                   autocomplete="off" spellcheck="false">
            <div id="cat-contador" class="cnp-contador"></div>
        </div>

        <div class="cnp-modal__cuerpo" id="cat-lista"></div>

        <div class="cnp-modal__pie">
            La clave sale de <strong>tipo + concepto + antecedente</strong>, que las tablas guardan
            por separado. Empieza en <code>1</code> si es percepción y en <code>2</code> si es
            deducción. Da clic en una clave para buscarla en la nómina.
        </div>
    </div>
</div>

<?php paginaFin(['js/conceptos.js?v=' . filemtime(__DIR__ . '/js/conceptos.js')]); ?>
