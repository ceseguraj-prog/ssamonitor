<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('eventuales');

/** Versión de un asset por su fecha de modificación, igual que en los demás módulos. */
function assetEventuales(string $ruta): string
{
    $absoluta = __DIR__ . '/' . $ruta;

    return $ruta . '?v=' . (is_file($absoluta) ? filemtime($absoluta) : '1');
}

/** Íconos de las tarjetas: trazos de 24x24 sin relleno, como los del rail. */
const ICONOS_EVENTUALES = [
    // Hoja con renglones: la nómina de la quincena.
    'nomina' => '<path d="M6 3.4h7.2L18.4 8.6v12H6z"/><path d="M13 3.6V9h5.2"/>'
        . '<path d="M8.8 12.6h6.6"/><path d="M8.8 16.2h4.2"/>',
    // Dos hojas encimadas: el detalle de la quincena anterior.
    'anterior' => '<path d="M8.4 6.4V3.6h7.2l4.6 4.6v10.2h-2.8"/><path d="M4 6.6h8.8l4 4v9.8H4z"/>'
        . '<path d="M6.8 14h6.4"/><path d="M6.8 17.2h4"/>',
    // Calendario: quincena y fecha de pago.
    'periodo' => '<rect x="3.8" y="5" width="16.4" height="15.2" rx="2.2"/><path d="M3.8 9.6h16.4"/>'
        . '<path d="M8.2 3.2v3.6"/><path d="M15.8 3.2v3.6"/><path d="M8 13.6h2.4"/><path d="M13.6 13.6H16"/><path d="M8 16.8h2.4"/>',
    // Palomita en círculo: el resultado.
    'resultado' => '<circle cx="12" cy="12" r="8.4"/><path d="M8.4 12.2l2.6 2.6 4.7-5.4"/>',
    // Personas: altas, bajas y retroactivos.
    'movimientos' => '<circle cx="9.2" cy="8" r="3.1"/><path d="M3.6 19.6c0-3.1 2.5-5.6 5.6-5.6s5.6 2.5 5.6 5.6"/>'
        . '<path d="M17.4 8.4v5.2"/><path d="M14.8 11h5.2"/>',
    // Lista con lupa: los hallazgos.
    'hallazgo' => '<path d="M4.4 6h9.6"/><path d="M4.4 10.4h6.4"/><path d="M4.4 14.8h4.8"/>'
        . '<circle cx="16.4" cy="14.6" r="3.6"/><path d="M19.1 17.3 21.4 19.6"/>',
];

/**
 * Cabecera de una tarjeta: ícono, micro-etiqueta, título, línea de apoyo y el
 * botón «?» que abre el modal con la explicación larga (bloque `ayuda`).
 */
function cabezaEventuales(array $o): void
{
    $icono = ICONOS_EVENTUALES[$o['icono']] ?? '';
    ?>
    <header class="nev-cabeza">
        <span class="nev-cabeza__icono" aria-hidden="true">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icono ?></svg>
        </span>
        <div class="nev-cabeza__texto">
            <div class="eyebrow"><?= htmlspecialchars((string) ($o['eyebrow'] ?? '')) ?></div>
            <h2 class="nev-cabeza__titulo"><?= htmlspecialchars((string) ($o['titulo'] ?? '')) ?></h2>
            <?php if (!empty($o['pie'])): ?>
                <p class="nev-cabeza__pie"><?= $o['pie'] ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($o['extra'])): ?>
            <div class="nev-cabeza__extra"><?= $o['extra'] ?></div>
        <?php endif; ?>
        <?php if (!empty($o['ayuda'])): ?>
            <button type="button" class="nev-ayuda-btn" data-ayuda="<?= htmlspecialchars((string) $o['ayuda']) ?>"
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

/** Flecha de carga de las zonas de archivo. */
const ICONO_CARGA = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" '
    . 'stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/>'
    . '<path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>';

paginaInicio([
    'slug' => 'eventuales',
    'titulo' => 'Nómina de eventuales',
    'kicker' => 'Herramientas',
    'h1' => 'Nómina de eventuales',
    'subtitulo' => 'Arma el prod_pago y el detalle de empleados de eventuales y SaNAS',
    'fuente' => 'navegador',
    'loader' => 'Generando archivos',
    'css' => [assetEventuales('css/eventuales.css')],
]);
?>

        <div class="nev-hoja">

            <div class="nev-intro">
                <svg class="nev-intro__icono" width="17" height="17" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4.6" y="10.4" width="14.8" height="9.6" rx="2.2"/>
                    <path d="M8.4 10.4V7.8a3.6 3.6 0 0 1 7.2 0v2.6"/>
                </svg>
                <p>
                    Los archivos <strong>no se suben a ningún lado</strong>: se leen y se arman en esta
                    computadora.
                </p>
                <button type="button" class="nev-ayuda-enlace" data-ayuda="general">Cómo funciona</button>
            </div>

            <form id="nev-form" class="nev-captura">

                <div class="nev-captura__fuentes">

                    <section class="card nev-paso">
                        <?php cabezaEventuales([
                            'icono' => 'nomina',
                            'eyebrow' => 'Paso 1 · obligatorio',
                            'titulo' => 'Nómina de la quincena',
                            'pie' => 'El Excel de eventuales de una quincena, con SaNAS, CUOTAS y GUARDIAS incluidos',
                            'ayuda' => 'nomina',
                        ]); ?>

                        <div class="nev-carga">
                            <input type="file" id="nev-archivo-nomina" accept=".xlsx" hidden>
                            <label class="nev-zona" for="nev-archivo-nomina">
                                <?= ICONO_CARGA ?>
                                <span id="nev-etiqueta-nomina">Selecciona el archivo .xlsx</span>
                            </label>
                            <ul class="nev-lista" id="nev-lista-nomina"></ul>
                        </div>
                    </section>

                    <section class="card nev-paso">
                        <?php cabezaEventuales([
                            'icono' => 'anterior',
                            'eyebrow' => 'Paso 2 · recomendado',
                            'titulo' => 'Detalle de la quincena anterior',
                            'pie' => 'Se usan los dos <code>DETALLE EMPLEADOS</code> (E y S). Puedes soltar las cuatro salidas: los prod_pago se ignoran',
                            'ayuda' => 'anterior',
                        ]); ?>

                        <div class="nev-carga">
                            <input type="file" id="nev-archivos-anterior" accept=".xlsx" multiple hidden>
                            <label class="nev-zona" for="nev-archivos-anterior">
                                <?= ICONO_CARGA ?>
                                <span id="nev-etiqueta-anterior">Selecciona los archivos .xlsx</span>
                            </label>
                            <ul class="nev-lista" id="nev-lista-anterior"></ul>
                        </div>
                    </section>

                </div>

                <section class="card nev-paso nev-paso--lado">
                    <?php cabezaEventuales([
                        'icono' => 'periodo',
                        'eyebrow' => 'Paso 3',
                        'titulo' => 'Datos del archivo',
                        'pie' => 'La quincena sale de la nómina; la fecha de pago se puede ajustar',
                        'ayuda' => 'periodo',
                    ]); ?>

                    <div class="nev-campos">
                        <div class="nev-campo-form">
                            <label for="nev-quincena">Quincena</label>
                            <input type="text" id="nev-quincena" readonly placeholder="Se toma de la nómina">
                        </div>
                        <div class="nev-campo-form">
                            <label for="nev-fecha-pago">Fecha de pago</label>
                            <input type="text" id="nev-fecha-pago" maxlength="8" inputmode="numeric" placeholder="AAAAMMDD">
                            <span class="ayuda" id="nev-fecha-ayuda">AAAAMMDD · por omisión, el último día de la quincena</span>
                        </div>
                    </div>

                    <div class="nev-salen">
                        <span class="eyebrow">Se generarán</span>
                        <ul class="nev-salen__lista" id="nev-salen"></ul>
                    </div>

                    <div class="nev-accion">
                        <button type="submit" class="btn-primary" id="nev-generar" disabled>Generar archivos</button>
                    </div>
                </section>

            </form>

            <div id="nev-error" class="nev-alerta" hidden></div>

            <section id="nev-resultado" hidden>

                <div class="card nev-paso">
                    <?php cabezaEventuales([
                        'icono' => 'resultado',
                        'eyebrow' => 'Resultado',
                        'titulo' => 'Archivos generados',
                        'extra' => '<span class="chip-estado imp" id="nev-estado"><span class="dot"></span></span>',
                        'ayuda' => 'resultado',
                    ]); ?>

                    <div id="nev-resumen" class="nev-resumen"></div>

                    <div id="nev-tiles" class="nev-tiles"></div>

                    <div class="nev-juegos" id="nev-juegos"></div>

                    <div class="nev-accion">
                        <button type="button" class="btn-primary" id="nev-descargar-todo">Descargar los 4 (.zip)</button>
                        <span class="nev-nota" id="nev-nota-descarga"></span>
                    </div>
                </div>

                <div class="card nev-paso" id="nev-bloque-movimientos" hidden>
                    <?php cabezaEventuales([
                        'icono' => 'movimientos',
                        'eyebrow' => 'Contra la quincena anterior',
                        'titulo' => 'Movimientos',
                        'pie' => 'Las altas llevan el nombre armado desde la nómina: revísalo antes de cargar',
                        'ayuda' => 'movimientos',
                    ]); ?>

                    <div class="nev-movs" id="nev-movimientos"></div>
                </div>

                <div class="card nev-paso" id="nev-bloque-hallazgos" hidden>
                    <?php cabezaEventuales([
                        'icono' => 'hallazgo',
                        'eyebrow' => 'Validación',
                        'titulo' => 'Hallazgos',
                        'extra' => '<div class="nev-badge" id="nev-badge-hallazgos"></div>',
                        'ayuda' => 'hallazgos',
                    ]); ?>

                    <div class="tabla">
                        <div class="tabla__head nev-fila-hallazgo">
                            <div>Severidad</div><div>Renglón</div><div>RFC</div><div>Qué pasó</div>
                        </div>
                        <div id="nev-tabla-hallazgos" class="nev-scroll"></div>
                    </div>
                    <div class="nev-nota" id="nev-pie-hallazgos" hidden></div>
                </div>

            </section>
        </div>

<?php /* ── Modal de ayuda ──────────────────────────────────────────────────
         Uno solo para la pantalla. Cada «?» trae la clave del bloque que le
         toca; el texto vive abajo, en HTML normal, para corregirlo sin tocar
         JavaScript. */ ?>
<div class="nev-modal" id="nev-modal" hidden>
    <div class="nev-modal__fondo" data-cerrar></div>

    <div class="nev-modal__hoja" role="dialog" aria-modal="true" aria-labelledby="nev-modal-titulo">
        <div class="nev-modal__cabeza">
            <div>
                <div class="eyebrow" id="nev-modal-kicker"></div>
                <h2 id="nev-modal-titulo" class="nev-modal__titulo"></h2>
            </div>
            <button type="button" class="nev-modal__cerrar" data-cerrar aria-label="Cerrar">&times;</button>
        </div>
        <div class="nev-modal__cuerpo" id="nev-modal-cuerpo"></div>
    </div>
</div>

<div class="nev-ayudas" hidden>

    <div data-ayuda-de="general" data-kicker="La herramienta" data-titulo="Cómo funciona">
        <p>
            Toma la nómina de eventuales de una quincena y arma los <strong>cuatro archivos de
            carga</strong>: el <code>prod_pago</code> y el <code>DETALLE EMPLEADOS</code> de
            eventuales (<code>E</code>) y los mismos dos de SaNAS (<code>S</code>), que van por
            separado.
        </p>
        <p>
            Las reglas se sacaron de las quincenas 16 y 17 de 2026, que se habían armado a mano:
            con la nómina y el detalle anterior, el módulo produce esos archivos
            <strong>idénticos</strong>, celda por celda.
        </p>
        <h3>Nada de esto sale de tu computadora</h3>
        <p>
            No se sube nada y no se guarda nada: todo ocurre en el navegador. Los archivos traen
            RFC, CURP y sueldo de unas 2 700 personas, y no tienen por qué tocar el disco del
            servidor.
        </p>
    </div>

    <div data-ayuda-de="nomina" data-kicker="Paso 1" data-titulo="Nómina de la quincena">
        <p>
            Es el Excel que llega con la nómina de eventuales, por ejemplo
            <code>2026_qna18_event - cuotas -gdias.xlsx</code>. Puede traer otras hojas (SIAP,
            pensiones): se usa la que tiene las columnas de eventuales.
        </p>
        <h3>Qué se toma de cada renglón</h3>
        <ul>
            <li><strong>DESC. PROGRAMA</strong> decide el juego: <code>SaNAS</code> va a los
                archivos S, todo lo demás a los E.</li>
            <li>Los importes <code>07</code>, <code>06</code>, <code>37</code>, <code>ISR</code>,
                <code>PENSION</code>, <code>FALTAS</code> y <code>DESCUENTOS</code> se convierten en
                sus claves de percepción y deducción.</li>
            <li><strong>FEC_INICIO / FEC_FIN</strong> son el periodo del renglón. Un retroactivo
                (tipo 22) sale como un renglón más, con su propio periodo, dentro del mismo
                producto.</li>
        </ul>
        <h3>Una quincena por corrida</h3>
        <p>
            Si la columna QNA trae más de una quincena, no se genera nada: el prefijo del
            producto (<code>EV1826</code>) y la fecha de pago no tendrían de dónde salir.
        </p>
    </div>

    <div data-ayuda-de="anterior" data-kicker="Paso 2" data-titulo="Detalle de la quincena anterior">
        <p>
            Son los dos <code>DETALLE EMPLEADOS CON 1807 FED…</code> de la quincena pasada, el
            E y el S. No es obligatorio, pero sin ellos el resultado <strong>no queda igual al de
            siempre</strong> en dos cosas:
        </p>
        <p>
            Si es más cómodo, suelta las <strong>cuatro</strong> salidas de la quincena pasada: los
            <code>detalle prod_pago</code> se reconocen y se ignoran, porque no traen nombres.
        </p>
        <h3>Los nombres</h3>
        <p>
            El detalle lleva los nombres como se han ido corrigiendo a mano, y no siempre coinciden
            con la nómina: en la quincena 17 fueron 34, como «KARLOS HUMBRETO» que en el detalle va
            «CARLOS HUMBERTO». Con el detalle anterior, a quien ya estaba se le respeta el nombre de
            ahí. Solo a las altas se les arma desde la nómina.
        </p>
        <h3>El orden de la cola</h3>
        <p>
            CUOTAS, REGULACIÓN SANITARIA y GUARDIAS van al final de la numeración y siempre en el
            mismo orden, aunque en la nómina vengan revueltos. Ese orden se toma del detalle
            anterior.
        </p>
        <h3>Movimientos</h3>
        <p>
            Además, con él se listan las altas y las bajas contra la quincena pasada.
        </p>
    </div>

    <div data-ayuda-de="periodo" data-kicker="Paso 3" data-titulo="Datos del archivo">
        <p>
            La <strong>quincena</strong> se lee de la columna QNA (<code>18_2026_0</code> → quincena
            18 de 2026) y da el prefijo de los productos: <code>EV1826</code> y
            <code>SA1826</code>.
        </p>
        <p>
            La <strong>fecha de pago</strong> va en el encabezado del prod_pago. Por omisión es el
            último día de la quincena: el 15 en las nones y fin de mes en las pares, como en las
            quincenas 16, 17 y 18. Si un pago se recorre, se corrige aquí.
        </p>
        <h3>Los nombres de archivo</h3>
        <p>
            Son los de siempre. El «1807» se repite en todas las quincenas y no se sabe qué
            significa, así que se conserva tal cual.
        </p>
    </div>

    <div data-ayuda-de="resultado" data-kicker="Resultado" data-titulo="Cómo leer las cifras">
        <p>
            Cada juego muestra sus claves con número de renglones e importe. El
            <strong>neto</strong> es percepciones menos deducciones, y se compara contra la suma de
            la columna NETO de la nómina: si no cuadra, algo se quedó sin clave.
        </p>
        <h3>Renglones y personas</h3>
        <p>
            Un retroactivo es un renglón más para la misma persona, así que puede haber más
            renglones que personas. «No. Reg.» del encabezado cuenta renglones.
        </p>
        <h3>El número de empleado</h3>
        <p>
            Es un consecutivo que se rehace cada quincena y que comparten los dos juegos: primero
            IMSS BIENESTAR, IMSS BIENESTAR ADMINISTRATIVOS, OTROS PROGRAMAS y SaNAS, en el orden de
            la nómina, y al final la cola de CUOTAS, REGULACIÓN SANITARIA y GUARDIAS. Por eso los
            números de SaNAS caen en medio de los de eventuales.
        </p>
        <h3>Totales del encabezado</h3>
        <p>
            Arriba del prod_pago van los mismos totales de siempre (percepciones en AH1, deducciones
            en BL1 y neto en F2), con fórmulas que suman exactamente sus columnas.
        </p>
    </div>

    <div data-ayuda-de="movimientos" data-kicker="Contra la quincena anterior" data-titulo="Movimientos">
        <ul>
            <li><strong>Altas</strong>: vienen en la nómina y no estaban en el detalle anterior. Su
                nombre se armó desde la nómina (sin acentos, Ñ como N, sin puntos); revísalo,
                porque el detalle de la próxima quincena lo va a heredar.</li>
            <li><strong>Bajas</strong>: estaban en el detalle anterior y ya no vienen.</li>
            <li><strong>Retroactivos</strong>: renglones tipo 22, cada uno con su periodo.</li>
        </ul>
        <p>
            Si cargaste solo uno de los dos detalles anteriores, las personas del otro juego van a
            salir como altas, y las bajas no estarán completas.
        </p>
    </div>

    <div data-ayuda-de="hallazgos" data-kicker="Validación" data-titulo="Hallazgos">
        <p>
            Un <strong>error</strong> detiene la descarga: los archivos saldrían mal. El caso
            esperado es un importe en una columna que no tiene clave (29+, SE o DES.MERCANTIL, o
            cualquier concepto que no sea sueldo o ISR en SaNAS). Esas columnas vinieron en cero en
            las quincenas 16, 17 y 18, así que su clave nunca se vio y no se inventa: hay que darla
            de alta en el módulo.
        </p>
        <p>
            Un <strong>aviso</strong> no impide descargar, pero conviene revisarlo: un neto que no
            cuadra, un nombre sin el formato PATERNO,MATERNO/NOMBRE, un RFC en minúsculas o un
            programa que no se conocía.
        </p>
    </div>

</div>

<?php paginaFin([
    assetEventuales('js/core/xlsx.js'),
    assetEventuales('js/core/nomina.js'),
    assetEventuales('js/eventuales.js'),
]); ?>
