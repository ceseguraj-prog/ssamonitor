<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('tg7');

/** Versión de un asset por su fecha de modificación, igual que en los demás módulos. */
function assetTg7(string $ruta): string
{
    $absoluta = __DIR__ . '/' . $ruta;

    return $ruta . '?v=' . (is_file($absoluta) ? filemtime($absoluta) : '1');
}

/**
 * Íconos de los pasos. Trazos de 24x24 sin relleno, como los del rail y los de
 * includes/fuentes.php: heredan color y grosor del contenedor.
 */
const ICONOS_TG7 = [
    // Hoja con renglones: la nómina que llega de la pagaduría.
    'nomina' => '<path d="M6 3.4h7.2L18.4 8.6v12H6z"/><path d="M13 3.6V9h5.2"/>'
        . '<path d="M8.8 12.6h6.6"/><path d="M8.8 16.2h4.2"/>',
    // Hoja con palomita: la orden de descuento, que autoriza el préstamo.
    'ordenes' => '<path d="M6 3.4h7.2L18.4 8.6v12H6z"/><path d="M13 3.6V9h5.2"/>'
        . '<path d="M8.6 14.9l2 2 3.6-4.3"/>',
    // Etiqueta: el encabezado es la ficha que identifica al archivo entero.
    'encabezado' => '<path d="M3.8 10.4V5a1.2 1.2 0 0 1 1.2-1.2h5.4l9.2 9.2a1.6 1.6 0 0 1 0 2.3'
        . 'l-4.8 4.8a1.6 1.6 0 0 1-2.3 0z"/><circle cx="7.7" cy="7.7" r="1.25"/>',
    // Palomita en círculo: el resultado del cruce.
    'resultado' => '<circle cx="12" cy="12" r="8.4"/><path d="M8.4 12.2l2.6 2.6 4.7-5.4"/>',
    // Hoja rota: los trabajadores a los que les falta la orden.
    'faltante' => '<path d="M6 3.4h7.2L18.4 8.6v12H6z"/><path d="M13 3.6V9h5.2"/>'
        . '<path d="M12.2 12v3.4"/><path d="M12.2 18v.1"/>',
    // Triángulo de aviso: calidad del dato en el origen.
    'senal' => '<path d="M12 3.8 21 19.6H3z"/><path d="M12 10v4"/><path d="M12 16.6v.1"/>',
    // Lista con lupa: los hallazgos de la validación.
    'hallazgo' => '<path d="M4.4 6h9.6"/><path d="M4.4 10.4h6.4"/><path d="M4.4 14.8h4.8"/>'
        . '<circle cx="16.4" cy="14.6" r="3.6"/><path d="M19.1 17.3 21.4 19.6"/>',
    // Círculo con diagonal: lo que no se emite.
    'rechazo' => '<circle cx="12" cy="12" r="8.4"/><path d="M6.6 6.6 17.4 17.4"/>',
    // Engrane simplificado: incidencias de lectura, cosas del proceso.
    'incidencia' => '<circle cx="12" cy="12" r="3.2"/>'
        . '<path d="M12 3.4v2.4"/><path d="M12 18.2v2.4"/><path d="M3.4 12h2.4"/><path d="M18.2 12h2.4"/>'
        . '<path d="M6 6l1.7 1.7"/><path d="M16.3 16.3 18 18"/><path d="M18 6l-1.7 1.7"/><path d="M7.7 16.3 6 18"/>',
];

/**
 * Cabecera de una tarjeta: ícono, número de paso, título, una línea de apoyo y
 * el botón de ayuda que abre el modal.
 *
 * La explicación larga NO va aquí: vive en un bloque oculto al final de la
 * página, y `clave` es la que los amarra. Así la pantalla se lee de un vistazo
 * y el detalle sigue estando a un clic para quien lo necesita.
 *
 *   icono   clave de ICONOS_TG7
 *   eyebrow micro-etiqueta ('1 · Paso', 'Resultado'…)
 *   titulo  título de la tarjeta
 *   pie     línea de apoyo, corta. Opcional
 *   ayuda   clave del bloque de ayuda. Sin ella no se pinta el botón
 *   extra   HTML pegado a la derecha, antes del botón (contadores, chips)
 */
function cabezaTg7(array $o): void
{
    $icono = ICONOS_TG7[$o['icono']] ?? '';
    ?>
    <header class="tg7-cabeza">
        <span class="tg7-cabeza__icono" aria-hidden="true">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icono ?></svg>
        </span>
        <div class="tg7-cabeza__texto">
            <div class="eyebrow"><?= htmlspecialchars((string) ($o['eyebrow'] ?? '')) ?></div>
            <h2 class="tg7-cabeza__titulo"><?= htmlspecialchars((string) ($o['titulo'] ?? '')) ?></h2>
            <?php if (!empty($o['pie'])): ?>
                <p class="tg7-cabeza__pie"><?= $o['pie'] ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($o['extra'])): ?>
            <div class="tg7-cabeza__extra"><?= $o['extra'] ?></div>
        <?php endif; ?>
        <?php if (!empty($o['ayuda'])): ?>
            <button type="button" class="tg7-ayuda-btn" data-ayuda="<?= htmlspecialchars((string) $o['ayuda']) ?>"
                    aria-label="Explicación de <?= htmlspecialchars((string) ($o['titulo'] ?? '')) ?>"
                    title="¿Qué es esto?">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="8.8"/>
                    <path d="M9.6 9.4a2.5 2.5 0 1 1 3.3 2.4c-.66.26-1 .82-1 1.5v.4"/>
                    <path d="M12 16.9v.1"/>
                </svg>
            </button>
        <?php endif; ?>
    </header>
    <?php
}

/**
 * Valores iniciales del encabezado.
 *
 * `ramoCredito` es 023 y no 022: el formato pide el ramo de OTORGAMIENTO DE
 * CRÉDITO, no el de afiliación, y 022 es el que traen la nómina y las órdenes.
 * El encabezado del archivo oficial de referencia usa 023.
 */
$encabezado = [
    'tipoNomina'   => '1',
    'version'      => 1,
    'periodicidad' => 'Q',
    'periodo'      => date('Y') . str_pad((string) (((int) date('n') - 1) * 2 + ((int) date('j') > 15 ? 2 : 1)), 2, '0', STR_PAD_LEFT),
    'organismo'    => '520',
    'entidad'      => '09',
    'municipio'    => '001',
    'ramoCredito'  => '023',
];

paginaInicio([
    'slug' => 'tg7',
    'titulo' => 'Nómina de préstamos personales (TG-7)',
    'kicker' => 'Herramientas',
    'h1' => 'Nómina de préstamos personales',
    'subtitulo' => 'Genera el archivo TG-7 que se entrega en SERICA',
    'fuente' => 'navegador',
    'loader' => 'Cruzando y validando',
    'css' => [assetTg7('css/tg7.css')],
]);
?>

        <div class="tg7-hoja">

            <div class="tg7-intro">
                <svg class="tg7-intro__icono" width="17" height="17" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4.6" y="10.4" width="14.8" height="9.6" rx="2.2"/>
                    <path d="M8.4 10.4V7.8a3.6 3.6 0 0 1 7.2 0v2.6"/>
                </svg>
                <p>
                    Los archivos <strong>no se suben a ningún lado</strong>: se leen y se procesan en
                    esta computadora.
                </p>
                <button type="button" class="tg7-ayuda-enlace" data-ayuda="general">Cómo funciona</button>
            </div>

            <?php /* Dos columnas: las dos fuentes de archivo a la izquierda y el
                     encabezado a la derecha, que es corto y se queda a la vista
                     mientras se cargan los archivos. En pantalla angosta se
                     apilan en el orden de siempre. */ ?>
            <form id="tg7-form" class="tg7-captura">

                <div class="tg7-captura__fuentes">

                <section class="card tg7-paso">
                    <?php cabezaTg7([
                        'icono' => 'nomina',
                        'eyebrow' => 'Paso 1 · obligatorio',
                        'titulo' => 'Nómina de la pagaduría',
                        'pie' => 'Los archivos <code>ISSSTE{QQ}{PAG}</code> de una sola quincena',
                        'ayuda' => 'nomina',
                    ]); ?>

                    <?php /* Vacío, la zona ocupa todo el ancho. Con archivos
                             cargados se parte en dos y la lista se va a la
                             derecha con su propio scroll: así la tarjeta no
                             crece por más archivos que se suelten. El reparto
                             lo hace tg7.js con la clase `con-archivos`. */ ?>
                    <div class="tg7-carga">
                        <input type="file" id="tg7-archivos-nomina" multiple hidden>
                        <label class="tg7-zona" for="tg7-archivos-nomina">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/><path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>
                            <span id="tg7-etiqueta-nomina">Selecciona los archivos</span>
                        </label>
                        <ul class="tg7-lista" id="tg7-lista-nomina"></ul>
                    </div>
                </section>

                <section class="card tg7-paso">
                    <?php cabezaTg7([
                        'icono' => 'ordenes',
                        'eyebrow' => 'Paso 2 · opcional',
                        'titulo' => 'Órdenes de descuento',
                        'pie' => 'La única fuente del número de préstamo. Cárgalas <strong>todas</strong>, de todas las quincenas',
                        'ayuda' => 'ordenes',
                    ]); ?>

                    <div class="tg7-carga">
                        <input type="file" id="tg7-archivos-ordenes" multiple accept=".txt,.docx" hidden>
                        <label class="tg7-zona" for="tg7-archivos-ordenes">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/><path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>
                            <span id="tg7-etiqueta-ordenes">Selecciona los archivos .txt o .docx</span>
                        </label>
                        <ul class="tg7-lista" id="tg7-lista-ordenes"></ul>
                    </div>
                </section>

                </div>

                <section class="card tg7-paso tg7-paso--lado">
                    <?php cabezaTg7([
                        'icono' => 'encabezado',
                        'eyebrow' => 'Paso 3',
                        'titulo' => 'Encabezado del archivo',
                        'pie' => 'La primera línea del TG-7; de aquí sale el nombre del archivo',
                        'ayuda' => 'encabezado',
                    ]); ?>

                    <div class="tg7-encabezado" id="tg7-encabezado">
                        <div class="tg7-campo-form">
                            <label for="tg7-tipo-nomina">Tipo de nómina</label>
                            <select id="tg7-tipo-nomina">
                                <option value="1" selected>1 · Ordinaria</option>
                                <option value="2">2 · Extraordinaria</option>
                                <option value="3">3 · Cancelación</option>
                            </select>
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-version">Versión</label>
                            <input type="number" id="tg7-version" min="1" max="99" value="<?= (int) $encabezado['version'] ?>">
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-periodicidad">Periodicidad</label>
                            <select id="tg7-periodicidad">
                                <option value="Q" selected>Q · Quincenal</option>
                                <option value="M">M · Mensual</option>
                            </select>
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-periodo">Periodo</label>
                            <input type="text" id="tg7-periodo" maxlength="6" value="<?= htmlspecialchars($encabezado['periodo']) ?>">
                            <span class="ayuda">AAAAQQ</span>
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-organismo">Organismo</label>
                            <input type="text" id="tg7-organismo" maxlength="3" value="<?= htmlspecialchars($encabezado['organismo']) ?>">
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-entidad">Entidad</label>
                            <input type="text" id="tg7-entidad" maxlength="2" value="<?= htmlspecialchars($encabezado['entidad']) ?>">
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-municipio">Municipio</label>
                            <input type="text" id="tg7-municipio" maxlength="3" value="<?= htmlspecialchars($encabezado['municipio']) ?>">
                        </div>
                        <div class="tg7-campo-form">
                            <label for="tg7-ramo">Ramo de crédito</label>
                            <input type="text" id="tg7-ramo" maxlength="3" value="<?= htmlspecialchars($encabezado['ramoCredito']) ?>">
                            <span class="ayuda">De otorgamiento, no de afiliación</span>
                        </div>
                    </div>

                    <div class="tg7-accion">
                        <div class="tg7-accion__archivo">
                            <span class="eyebrow">Se generará</span>
                            <span class="tg7-nombre-archivo" id="tg7-nombre-archivo"></span>
                        </div>
                        <button type="submit" class="btn-primary" id="tg7-procesar" disabled>Cruzar y validar</button>
                    </div>
                </section>

            </form>

            <div id="tg7-error" class="tg7-alerta" hidden></div>

            <section id="tg7-resultado" hidden>

                <div class="card tg7-paso">
                    <?php cabezaTg7([
                        'icono' => 'resultado',
                        'eyebrow' => 'Resultado',
                        'titulo' => 'Archivo generado',
                        'extra' => '<span class="chip-estado imp" id="tg7-estado"><span class="dot"></span></span>',
                        'ayuda' => 'resultado',
                    ]); ?>

                    <div id="tg7-resumen-archivo" class="tg7-resumen"></div>

                    <div id="tg7-tiles" class="tg7-tiles"></div>

                    <div class="tg7-accion">
                        <button type="button" class="btn-primary" id="tg7-descargar">Descargar TG-7</button>
                        <button type="button" class="btn-ghost" id="tg7-bitacora">Descargar bitácora (CSV)</button>
                    </div>
                </div>

                <div class="card tg7-paso" id="tg7-bloque-sinprestamo" hidden>
                    <?php cabezaTg7([
                        'icono' => 'faltante',
                        'eyebrow' => 'Atención',
                        'titulo' => 'Falta la orden de descuento',
                        'pie' => 'Estas líneas salen sin número de préstamo y SERICA las va a rechazar',
                        'extra' => '<div class="tg7-badge" id="tg7-badge-sinprestamo"></div>',
                        'ayuda' => 'sinprestamo',
                    ]); ?>

                    <div class="tabla">
                        <div class="tabla__head tg7-fila-sinprestamo">
                            <div>RFC</div><div class="tg7-conteo">Retenido</div><div>Archivo</div><div>Línea</div>
                        </div>
                        <div id="tg7-tabla-sinprestamo" class="tg7-scroll"></div>
                    </div>

                    <div class="tg7-accion">
                        <button type="button" class="btn-ghost" id="tg7-descargar-sinprestamo">Descargar faltantes (CSV)</button>
                        <span class="tg7-nota" id="tg7-pie-sinprestamo" hidden></span>
                    </div>
                </div>

                <div class="card tg7-paso" id="tg7-bloque-senales" hidden>
                    <?php cabezaTg7([
                        'icono' => 'senal',
                        'eyebrow' => 'Origen',
                        'titulo' => 'Calidad del dato',
                        'pie' => 'Esto no se arregla desde aquí: hay que reclamarlo a quien genera la nómina',
                        'ayuda' => 'senales',
                    ]); ?>

                    <ul class="tg7-senales" id="tg7-senales"></ul>
                </div>

                <div class="card tg7-paso">
                    <?php cabezaTg7([
                        'icono' => 'hallazgo',
                        'eyebrow' => 'Validación',
                        'titulo' => 'Hallazgos por tipo',
                        'extra' => '<div class="tg7-badge" id="tg7-badge-hallazgos"></div>',
                        'ayuda' => 'hallazgos',
                    ]); ?>

                    <div class="tabla">
                        <div class="tabla__head tg7-fila-hallazgo">
                            <div>Severidad</div><div>Campo</div><div>Motivo</div><div class="tg7-conteo">Registros</div>
                        </div>
                        <div id="tg7-tabla-hallazgos" class="tg7-scroll tg7-scroll--alto"></div>
                    </div>
                </div>

                <div class="card tg7-paso" id="tg7-bloque-rechazados" hidden>
                    <?php cabezaTg7([
                        'icono' => 'rechazo',
                        'eyebrow' => 'Excluidos',
                        'titulo' => 'Registros que no se emiten',
                        'pie' => 'Traen un error que impide declararlos; el motivo va en la bitácora',
                        'extra' => '<div class="tg7-badge" id="tg7-badge-rechazados"></div>',
                    ]); ?>

                    <div class="tabla">
                        <div class="tabla__head tg7-fila-rechazo">
                            <div>RFC</div><div>Préstamo</div><div>Por qué</div>
                        </div>
                        <div id="tg7-tabla-rechazados" class="tg7-scroll tg7-scroll--alto"></div>
                    </div>
                    <div class="tg7-nota" id="tg7-pie-rechazados" hidden></div>
                </div>

                <div class="card tg7-paso" id="tg7-bloque-incidencias" hidden>
                    <?php cabezaTg7([
                        'icono' => 'incidencia',
                        'eyebrow' => 'Proceso',
                        'titulo' => 'Incidencias de lectura y cruce',
                        'extra' => '<div class="tg7-badge" id="tg7-badge-incidencias"></div>',
                    ]); ?>

                    <div class="tabla">
                        <div class="tabla__head tg7-fila-incidencia">
                            <div>Origen</div><div>Línea</div><div>RFC</div><div>Qué pasó</div>
                        </div>
                        <div id="tg7-tabla-incidencias" class="tg7-scroll"></div>
                    </div>
                </div>

            </section>
        </div>

<?php /* ── Modal de ayuda ──────────────────────────────────────────────────
         Uno solo para toda la pantalla. Cada botón «?» trae la clave del
         bloque que hay que mostrar; el contenido vive abajo, en HTML normal,
         para que se corrija sin tocar JavaScript. */ ?>
<div class="tg7-modal" id="tg7-modal" hidden>
    <div class="tg7-modal__fondo" data-cerrar></div>

    <div class="tg7-modal__hoja" role="dialog" aria-modal="true" aria-labelledby="tg7-modal-titulo">
        <div class="tg7-modal__cabeza">
            <div>
                <div class="eyebrow" id="tg7-modal-kicker"></div>
                <h2 id="tg7-modal-titulo" class="tg7-modal__titulo"></h2>
            </div>
            <button type="button" class="tg7-modal__cerrar" data-cerrar aria-label="Cerrar">&times;</button>
        </div>
        <div class="tg7-modal__cuerpo" id="tg7-modal-cuerpo"></div>
    </div>
</div>

<div class="tg7-ayudas" hidden>

    <div data-ayuda-de="general" data-kicker="La herramienta" data-titulo="Cómo funciona">
        <p>
            Produce el archivo <code>NOMPPR-….txt</code> que se entrega en SERICA, junto con la
            bitácora de lo que hay que corregir.
        </p>
        <p>
            Van al archivo <strong>solo los trabajadores a los que la nómina les retuvo préstamo
            personal</strong> (el campo <code>P.C.P.</code>). Es el criterio del propio formato, y
            deja fuera a la mayoría: en una quincena típica son unos 3 000 de 10 000. Las órdenes de
            descuento sirven para ponerle a cada uno su número de préstamo.
        </p>
        <h3>Nada de esto sale de tu computadora</h3>
        <p>
            No hay endpoint, no se sube nada y no se guarda nada: todo el procesamiento ocurre en el
            navegador. Los archivos de entrada traen RFC, CURP, NSS, sueldo y tipo de nombramiento de
            más de 10 000 trabajadores, y un archivo así no tiene por qué tocar el disco del servidor
            ni los registros de acceso.
        </p>
    </div>

    <div data-ayuda-de="nomina" data-kicker="Paso 1" data-titulo="Nómina de la pagaduría">
        <p>
            Son los archivos <code>ISSSTE{QQ}{PAG}.ORD</code>, <code>.EXT</code>, <code>.CAN</code> y
            <code>.RET</code>, de 260 caracteres por línea. <strong>Carga los de una sola
            quincena</strong>, todos los que tengas de ella: son seis pagadurías y cada una puede
            traer hasta cuatro archivos.
        </p>
        <h3>Qué trae y para qué sirve</h3>
        <p>
            Es la que define <strong>quién va en el archivo</strong>. De aquí salen 17 de los 19
            campos del detalle: nombre, RFC, CURP, NSS, sueldo, tipo de nombramiento y las
            deducciones, incluido el <code>P.C.P.</code> que dice cuánto se retuvo de préstamo.
        </p>
        <h3>Ojo con esto</h3>
        <p>
            Si cargas el <code>.ORD</code> y el <code>.RET</code> de la misma quincena, puede que un
            RFC aparezca dos veces. Se conserva el primero y se avisa. Si necesitas la extraordinaria
            por separado, genérala en su propia corrida con <em>Tipo de nómina = 2</em>.
        </p>
    </div>

    <div data-ayuda-de="ordenes" data-kicker="Paso 2" data-titulo="Órdenes de descuento">
        <p>
            Son la <strong>única</strong> fuente del número de préstamo: ese dato no existe en la
            nómina. Sin ellas el archivo se genera igual, pero con ese campo vacío, y SERICA rechaza
            esas líneas.
        </p>
        <h3>Las dos formas valen igual</h3>
        <p>
            ISSSTE las entrega de dos maneras y puedes mezclarlas en la misma carga:
        </p>
        <ul>
            <li><code>1{ramo}{pagaduría}_{folio}.txt</code> — uno por pagaduría, de ancho fijo.</li>
            <li><code>RAMO {ramo} OD {QQAAAA}.docx</code> — el reporte impreso, que trae las seis
                pagadurías juntas.</li>
        </ul>
        <p>
            Son el mismo dato: comparados renglón por renglón coinciden en 58 de 58. Si el mismo
            préstamo viene en los dos, se cuenta una sola vez.
        </p>
        <h3>Por qué «todas las acumuladas»</h3>
        <p>
            Porque <strong>ISSSTE manda cada quincena solo las altas nuevas</strong>, y el préstamo
            se sigue descontando durante todo su plazo — hasta 48 quincenas. Alguien que pidió su
            crédito hace un año sigue apareciendo con retención hoy, pero su número de préstamo está
            en el archivo de aquella quincena, no en el de ésta.
        </p>
        <p>
            Medido sobre los archivos reales: de las 28 órdenes de la quincena 13, <strong>27 seguían
            descontándose en la quincena 18</strong> con el importe idéntico. Por eso hay que juntar
            el histórico. El módulo se queda solo con las vigentes en el periodo que se declara y
            descarta solas las vencidas y las que todavía no empiezan.
        </p>
    </div>

    <div data-ayuda-de="encabezado" data-kicker="Paso 3" data-titulo="Encabezado del archivo">
        <p>
            Es la primera línea del TG-7 y de ella sale el nombre del archivo. Ninguno de estos ocho
            campos se lee de los archivos cargados: se capturan aquí.
        </p>
        <h3>Clave de aportante</h3>
        <p>
            La combinación <strong>organismo + entidad + municipio</strong> tiene que corresponder a
            un aportante válido en el catálogo de SERICA. Los valores que trae la pantalla vienen del
            archivo de ejemplo que entregó ISSSTE, así que <strong>hay que confirmar los de esta
            dependencia antes de la primera entrega</strong>: si están mal, se rechaza el archivo
            completo desde el encabezado.
        </p>
        <h3>Ramo de crédito</h3>
        <p>
            Va <code>023</code>, el de <strong>otorgamiento de crédito</strong>. No es el
            <code>022</code> que traen la nómina y las órdenes, que es el de afiliación y vigencia.
            La especificación lo dice textual.
        </p>
        <h3>Periodo</h3>
        <p>
            Formato <code>AAAAQQ</code>. En nómina ordinaria el periodo de cada registro debe
            coincidir con el del encabezado, nunca uno posterior, así que de aquí salen los dos
            campos de quincena del detalle.
        </p>
    </div>

    <div data-ayuda-de="resultado" data-kicker="Resultado" data-titulo="Cómo leer las cifras">
        <p>
            <strong>Registros de nómina</strong> son todos los que traían los archivos cargados.
            <strong>Con retención</strong> son los que traen <code>P.C.P.</code> mayor a cero, y esos
            son los que van en el TG-7: los demás cotizan al ISSSTE pero no tienen préstamo, así que
            el formato no los pide.
        </p>
        <p>
            Cargar más órdenes de descuento <strong>no aumenta</strong> cuántos se emiten. Lo que
            sube es <em>Con núm. de préstamo</em>, que es lo que hace que SERICA acepte la línea.
        </p>
        <h3>Los dos archivos que puedes bajar</h3>
        <ul>
            <li><strong>El TG-7</strong> es el que se entrega en SERICA. Solo lleva los registros sin
                errores.</li>
            <li><strong>La bitácora</strong> es un CSV con cada hallazgo, registro por registro, para
                corregir el origen y volver a procesar.</li>
        </ul>
    </div>

    <div data-ayuda-de="sinprestamo" data-kicker="Atención" data-titulo="Falta la orden de descuento">
        <p>
            A estos trabajadores la nómina sí les retuvo préstamo, pero no hay ninguna orden cargada
            que diga <strong>cuál</strong> préstamo es. Sus líneas salen con el campo vacío y
            <strong>SERICA las va a rechazar</strong>, porque valida que la combinación de número de
            ISSSTE y préstamo exista en su tabla.
        </p>
        <h3>Por qué se emiten de todos modos</h3>
        <p>
            Es una decisión de operación, tomada a sabiendas: se prefiere que reboten con nombre y
            apellido a que los trabajadores desaparezcan del archivo en silencio.
        </p>
        <h3>Qué hacer</h3>
        <p>
            Carga las órdenes de las quincenas anteriores si ya las tienes — es lo que cierra el
            hueco. Si no, descarga el CSV: es justo lo que se le reclama a ISSSTE para pedir las
            altas que faltan.
        </p>
    </div>

    <div data-ayuda-de="senales" data-kicker="Origen" data-titulo="Calidad del dato">
        <p>
            Son cosas que el módulo detecta pero <strong>no puede arreglar</strong>, porque vienen
            mal desde el archivo que genera la nómina. No impiden entregar, pero conviene reclamarlas.
        </p>
        <h3>La «Ñ» se pierde</h3>
        <p>
            Los archivos llegan en ASCII puro y la <code>Ñ</code> viene sustituida por un espacio:
            <code>AYALA MU OZ</code> era <code>AYALA MUÑOZ</code>. Se marcan, pero el nombre saldrá
            incompleto mientras el origen no se corrija.
        </p>
        <h3>El número de ISSSTE no siempre coincide</h3>
        <p>
            Cuando la orden y la nómina traen números distintos se usa el de la orden, porque la
            nómina lo trae en ceros en dos de cada tres registros. Se levanta aviso porque SERICA
            valida esa combinación contra su tabla de préstamos.
        </p>
    </div>

    <div data-ayuda-de="hallazgos" data-kicker="Validación" data-titulo="Hallazgos por tipo">
        <p>
            Un <strong>error</strong> deja el registro fuera del archivo. Un <strong>aviso</strong> se
            entrega igual, pero conviene revisarlo.
        </p>
        <p>
            Aquí se muestran agrupados por causa, que es como se lee una bitácora grande: el detalle
            registro por registro va en el CSV. Nunca se aborta el lote — cada registro se valida por
            separado, los que traen error salen a la bitácora y los demás se emiten.
        </p>
        <h3>Lo que no se puede validar aquí</h3>
        <p>
            Que el aportante exista, que ramo y pagaduría estén en el catálogo, que el préstamo esté
            vigente y que el salario caiga entre un salario mínimo y 10 UMAs. Todo eso requiere
            catálogos que solo tiene SERICA, y los revisa al recibir el archivo.
        </p>
    </div>

</div>

<?php paginaFin([
    assetTg7('js/core/formato.js'),
    assetTg7('js/core/anchoFijo.js'),
    assetTg7('js/core/layouts.js'),
    assetTg7('js/core/nombres.js'),
    assetTg7('js/core/validaciones.js'),
    assetTg7('js/core/reporteOrdenes.js'),
    assetTg7('js/core/cruce.js'),
    assetTg7('js/tg7.js'),
]); ?>
