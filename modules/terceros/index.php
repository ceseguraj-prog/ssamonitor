<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('terceros');

use App\TercerosRepository;

/**
 * Esta pantalla no usa includes/periodo.php, por lo mismo que Conceptos: el
 * periodo compartido lo alimenta BPM y aquí los años salen de `catalogos`. Si
 * este selector escribiera en la sesión, elegir aquí un año que BPM no tiene
 * dejaría el selector de Resumen y Productos marcando un periodo inexistente.
 */
$anios = TercerosRepository::aniosDisponibles();
$anioPorDefecto = $anios[0] ?? (int) date('Y');

// La quincena que se está cerrando es casi siempre la anterior a la del
// calendario: la vigente todavía se está capturando.
$quincenaActual = (int) date('n') * 2 - ((int) date('j') <= 15 ? 1 : 0);
$quincenaPorDefecto = max(1, min(24, $quincenaActual - 1));

// Consultar el reporte lo puede hacer cualquiera; redefinir qué conceptos entran
// en cada grupo mueve las cifras del cierre, así que el panel solo se le pinta a
// quien esté en 'admin'. El endpoint lo vuelve a comprobar: esconder un botón no
// es una restricción.
$admin = (array) (modulosRegistrados()['terceros']['admin'] ?? []);
$puedeAdministrar = $admin === [] || usuarioEsAlguno(...$admin);
$configEscribible = is_dir(__DIR__ . '/../../config') && is_writable(__DIR__ . '/../../config');

paginaInicio([
    'slug' => 'terceros',
    'titulo' => 'Descuentos a terceros',
    'kicker' => 'Nómina · cierre de quincena',
    'h1' => 'Descuentos a terceros',
    'subtitulo' => 'Resumen por grupo y concepto, con el detalle y el concentrado en CSV',
    'fuente' => 'catalogos',
    'loader' => 'Recorriendo las tablas de nómina',
    'css' => ['css/terceros.css?v=' . filemtime(__DIR__ . '/css/terceros.css')],
]);
?>

<form id="filtros" class="card" style="padding:22px 24px;margin-bottom:20px">

    <div class="ter-fila">
        <label class="ter-campo ter-campo--corto">
            <span class="eyebrow">Ejercicio</span>
            <select id="anio" class="field">
                <?php foreach ($anios as $anio): ?>
                    <option value="<?= $anio ?>" <?= $anio === $anioPorDefecto ? 'selected' : '' ?>><?= $anio ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="ter-campo ter-campo--corto">
            <span class="eyebrow">Quincena</span>
            <select id="quincena" class="field">
                <?php for ($q = 1; $q <= 24; $q++): ?>
                    <option value="<?= $q ?>" <?= $q === $quincenaPorDefecto ? 'selected' : '' ?>>Q<?= $q ?></option>
                <?php endfor; ?>
            </select>
        </label>

        <label class="ter-campo">
            <span class="eyebrow">Grupo de terceros</span>
            <select id="grupo" class="field">
                <option value="Todos" selected>Todos los grupos</option>
                <?php foreach (TercerosRepository::grupos() as $grupo): ?>
                    <option value="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars(str_replace('_', ' ', $grupo)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="btn-primary" id="generar">Generar</button>

        <?php if ($puedeAdministrar): ?>
            <button type="button" class="btn-ghost ter-admin" id="abrir-grupos"
                    title="Dar de alta grupos y conceptos sin tocar código">Grupos…</button>
        <?php endif; ?>
    </div>

    <div class="ter-ayuda">
        Se recorren las seis tablas de nómina del periodo y se excluye siempre la
        UR <?= TercerosRepository::UR_EXCLUIDA ?>. Los grupos se arman por los tres primeros
        dígitos del concepto: <code>264</code> y <code>265</code> son FOVISSSTE, <code>258</code>
        cuotas sindicales. La tabla de abajo es el concentrado; el detalle
        registro por registro va en el CSV.
    </div>
</form>

<div id="aviso" class="ter-aviso" hidden></div>

<div id="resultado" hidden>

    <div class="ter-metricas">
        <div class="card ter-tile">
            <div class="eyebrow">Registros</div>
            <div class="figure" id="m-registros">0</div>
        </div>
        <div class="card ter-tile">
            <div class="eyebrow">Importe total</div>
            <div class="figure" id="m-importe">$0</div>
        </div>
        <div class="card ter-tile">
            <div class="eyebrow">Grupos con datos</div>
            <div class="figure" id="m-grupos">0</div>
        </div>
        <div class="card ter-tile">
            <div class="eyebrow">Renglones del concentrado</div>
            <div class="figure" id="m-renglones">0</div>
        </div>
    </div>

    <div class="card ter-desglose">
        <div class="eyebrow">Por grupo</div>
        <div id="por-grupo" class="ter-chips"></div>
    </div>

    <div class="ter-barra">
        <input id="filtrar" class="field field--pill" placeholder="Filtrar por grupo, UR, tipo, banco o concepto…"
               autocomplete="off" spellcheck="false">
        <a class="btn-ghost" id="descargar" download>Descargar CSV</a>
        <div id="contador" class="ter-contador">0 renglones</div>
    </div>

    <div class="tabla">
        <div class="tabla__head ter-grid">
            <div>Grupo</div><div>UR</div><div>Rama</div><div>Tipo</div>
            <div>Banco</div><div>Concepto</div><div class="ter-num">Importe</div>
        </div>
        <div id="tabla" class="ter-cuerpo"></div>
    </div>

    <button type="button" class="btn-ghost ter-mas" id="mas" hidden>Mostrar más</button>
</div>

<?php if ($puedeAdministrar): ?>
<?php /* Panel del catálogo. Vive en la misma página y no en otra pantalla
         porque lo normal es abrirlo, dar de alta un concepto y volver a generar
         en el acto; mandar a otra vista rompería ese ida y vuelta. */ ?>
<div class="ter-panel" id="panel-grupos" hidden>
    <div class="ter-panel__fondo" data-cerrar></div>

    <div class="ter-panel__hoja" role="dialog" aria-modal="true" aria-labelledby="panel-titulo">

        <div class="ter-panel__cabeza">
            <div>
                <div class="eyebrow">Catálogo</div>
                <h2 id="panel-titulo" class="ter-panel__titulo">Grupos de terceros</h2>
            </div>
            <button type="button" class="ter-panel__cerrar" data-cerrar aria-label="Cerrar">&times;</button>
        </div>

        <?php if (!$configEscribible): ?>
            <div class="ter-aviso ter-aviso--error">
                La carpeta <code>config/</code> no acepta escritura, así que no se puede guardar.
                El servidor web necesita permiso sobre ella.
            </div>
        <?php endif; ?>

        <div class="ter-panel__cuerpo">

            <div class="ter-panel__lista" id="lista-grupos"></div>

            <form class="ter-panel__form" id="form-grupo">
                <div class="eyebrow">Dar de alta o cambiar</div>

                <label class="ter-campo">
                    <span class="eyebrow">Nombre del grupo</span>
                    <input id="g-nombre" class="field" placeholder="FEGAC_221"
                           autocomplete="off" spellcheck="false" required
                           <?= $configEscribible ? '' : 'disabled' ?>>
                </label>

                <label class="ter-campo">
                    <span class="eyebrow">Prefijos de concepto</span>
                    <input id="g-prefijos" class="field" placeholder="255, 256, 264, 265"
                           autocomplete="off" spellcheck="false" required
                           <?= $configEscribible ? '' : 'disabled' ?>>
                </label>

                <div class="ter-panel__ayuda">
                    Un prefijo son los <strong>tres primeros caracteres</strong> del concepto:
                    <code>264</code> agrupa 26403, 26430 y cualquier subclave. Si el nombre ya
                    existe, se reemplazan sus prefijos; si es nuevo, el grupo se agrega
                    <strong>al final</strong>, para que los renglones de los CSV que ya existían
                    no se muevan.
                </div>

                <div class="ter-panel__botones">
                    <button type="button" class="btn-ghost" id="g-probar"
                            title="Consulta cuánto mueve ese prefijo en el periodo elegido arriba"
                            <?= $configEscribible ? '' : 'disabled' ?>>Consultar prefijos</button>
                    <button type="submit" class="btn-primary"
                            <?= $configEscribible ? '' : 'disabled' ?>>Guardar grupo</button>
                </div>

                <div id="g-aviso" class="ter-aviso" hidden></div>
                <div id="g-prueba" class="ter-prueba" hidden></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php paginaFin(['js/terceros.js?v=' . filemtime(__DIR__ . '/js/terceros.js')]); ?>
