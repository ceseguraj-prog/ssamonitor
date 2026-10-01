<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';
require_once __DIR__ . '/QnaRepository.php';

requireModulo('qna');

use App\QnaRepository;

/**
 * Reporte QNA: el reporte quincenal de nómina ordinaria, armado de la base.
 *
 * No usa includes/periodo.php: ese periodo es el de BPM (la última quincena con
 * timbres), y aquí se lee `catalogos`, que se carga por su lado. Abre en la
 * última quincena que `federal` ya tiene.
 */
$anios = QnaRepository::aniosDisponibles();
$anio = $anios[0] ?? (int) date('Y');
$quincena = QnaRepository::ultimaQuincena($anio) ?? 1;

paginaInicio([
    'slug' => 'qna',
    'titulo' => 'Reporte QNA',
    'kicker' => 'Nómina · reporte quincenal',
    'h1' => 'Reporte QNA',
    'subtitulo' => 'La nómina ordinaria de la quincena en un Excel, directo de las seis tablas',
    'fuente' => 'catalogos',
    'loader' => 'Armando el reporte',
    'css' => ['css/qna.css?v=' . filemtime(__DIR__ . '/css/qna.css')],
]);
?>

<form id="filtros" class="card qna-filtros">
    <div class="qna-fila">
        <label class="qna-campo">
            <span class="eyebrow">Ejercicio</span>
            <select id="anio" class="field">
                <?php foreach ($anios as $a): ?>
                    <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="qna-campo">
            <span class="eyebrow">Quincena</span>
            <select id="quincena" class="field">
                <?php for ($q = 1; $q <= 24; $q++): ?>
                    <option value="<?= $q ?>" <?= $q === $quincena ? 'selected' : '' ?>>Q<?= $q ?></option>
                <?php endfor; ?>
            </select>
        </label>

        <button type="submit" class="btn-primary">Generar reporte</button>
    </div>

    <?php /* Por omisión el reporte sale idéntico al que salía de los .txt, con
             sus defectos: es lo que se entrega y contra lo que se compara. La
             casilla los corrige, para cuadrar importes. */ ?>
    <label class="qna-check">
        <input type="checkbox" id="corregido" value="<?= QnaRepository::MODO_CORREGIDO ?>">
        <span>Corregir lo que traían mal los .txt: sin renglones repetidos, con las personas cuyo
            CLUES no está en <code>indeteccr</code> y con la Ñ en el nombre</span>
    </label>

    <div class="qna-ayuda">
        Lee la quincena de <code>federal</code>, <code>formalizados</code>, <code>formalizados2015</code>,
        <code>formalizados2016</code>, <code>homologados</code> y <code>regularizados</code>: solo nómina
        ordinaria (<code>TIPO 11</code>) y sin la UR <code>610</code>. La UR 416 sale como 411.
        La descripción del CR viene de <code>indeteccr</code> (por CLUES) y la del puesto de
        <code>cat_puesto</code>. Sin la casilla, el Excel sale igual que el que se armaba con los .txt.
        Todo es de solo lectura.
    </div>
</form>

<div id="aviso" class="qna-aviso" hidden></div>

<div id="resultado" hidden>

    <div class="qna-metricas" id="metricas"></div>

    <div class="card qna-descarga">
        <div>
            <div class="eyebrow">Archivo</div>
            <div class="qna-archivo" id="archivo"></div>
            <div class="qna-pie" id="pie"></div>
        </div>
        <button type="button" class="btn-primary" id="descargar">Descargar Excel</button>
    </div>

    <div id="avisos"></div>

    <div class="tabla qna-tabla">
        <div class="tabla__head qna-grid" id="cabeza"></div>
        <div id="por-tabla"></div>
    </div>
</div>

<?php paginaFin(['js/qna.js?v=' . filemtime(__DIR__ . '/js/qna.js')]); ?>
