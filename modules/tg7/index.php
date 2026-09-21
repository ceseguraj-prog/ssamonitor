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

        <div style="max-width:1080px">

            <div style="font-size:13.5px;color:var(--ink-muted);line-height:1.6;margin-bottom:22px">
                Produce el archivo <span class="tg7-nombre-archivo" style="font-size:12px">NOMPPR-….txt</span>
                que se entrega en SERICA, junto con la bitácora de lo que hay que corregir.
                Van al archivo los trabajadores a los que la nómina les retuvo préstamo personal
                (campo <code>P.C.P.</code>); las órdenes de descuento sirven para ponerle a cada uno su
                número de préstamo.
                <strong>Los archivos no se suben a ningún lado:</strong> se leen y se procesan en esta
                computadora, porque traen RFC, CURP, NSS y sueldo de miles de trabajadores.
            </div>

            <form id="tg7-form">

                <div class="card" style="padding:26px 30px">
                    <div class="eyebrow" style="letter-spacing:1.2px;margin-bottom:6px">1 · Nómina de la pagaduría</div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 16px">
                        Archivos <code>ISSSTE{QQ}{PAG}.ORD</code> / <code>.EXT</code> / <code>.CAN</code> / <code>.RET</code>,
                        de 260 caracteres por línea. Carga los de una sola quincena.
                    </p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <input type="file" id="tg7-archivos-nomina" multiple hidden>
                        <label class="tg7-zona" for="tg7-archivos-nomina">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--wine)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/><path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>
                            <span id="tg7-etiqueta-nomina">Selecciona los archivos</span>
                        </label>
                    </div>
                    <ul class="tg7-lista" id="tg7-lista-nomina"></ul>
                </div>

                <div class="card" style="padding:26px 30px;margin-top:18px">
                    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:6px">
                        <div class="eyebrow" style="letter-spacing:1.2px">2 · Órdenes de descuento</div>
                        <span style="font-size:11px;color:var(--ink-faint)">opcional, pero cárgalas todas</span>
                    </div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 16px">
                        Son la <strong>única</strong> fuente del número de préstamo. Se aceptan las dos
                        formas en que ISSSTE las entrega, y puedes mezclarlas:
                        <code>1{ramo}{pagaduría}_{folio}.txt</code> y el reporte
                        <code>RAMO {ramo} OD {QQAAAA}.docx</code>.
                        <br><br>
                        ISSSTE manda cada quincena solo las <strong>altas nuevas</strong>, y el préstamo se
                        sigue descontando durante todo su plazo — hasta 48 quincenas. Así que aquí hay que
                        cargar <strong>todas las órdenes acumuladas</strong>, de todas las quincenas, no solo
                        las de la que se declara. El módulo descarta solas las que ya vencieron o todavía
                        no empiezan.
                    </p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <input type="file" id="tg7-archivos-ordenes" multiple accept=".txt,.docx" hidden>
                        <label class="tg7-zona" for="tg7-archivos-ordenes">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--wine)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/><path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>
                            <span id="tg7-etiqueta-ordenes">Selecciona los archivos .txt o .docx</span>
                        </label>
                    </div>
                    <ul class="tg7-lista" id="tg7-lista-ordenes"></ul>
                </div>

                <div class="card" style="padding:26px 30px;margin-top:18px">
                    <div class="eyebrow" style="letter-spacing:1.2px;margin-bottom:6px">3 · Encabezado del archivo</div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 20px">
                        Es la primera línea del TG-7 y de aquí sale el nombre del archivo.
                        La combinación organismo + entidad + municipio tiene que corresponder a un
                        aportante válido en SERICA.
                    </p>

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

                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:24px;border-top:1px solid var(--border-soft);padding-top:20px">
                        <span class="eyebrow" style="letter-spacing:1.2px">Se generará</span>
                        <span class="tg7-nombre-archivo" id="tg7-nombre-archivo"></span>
                        <div style="flex:1"></div>
                        <button type="submit" class="btn-primary" id="tg7-procesar" disabled>Cruzar y validar</button>
                    </div>
                </div>

            </form>

            <div id="tg7-error" class="tg7-alerta" hidden style="margin-top:18px"></div>

            <section id="tg7-resultado" hidden>

                <div class="card" style="padding:26px 30px;margin-top:18px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:22px;flex-wrap:wrap">
                        <span class="chip-estado imp" id="tg7-estado"><span class="dot"></span></span>
                        <span id="tg7-resumen-archivo" style="font-size:12.5px;color:var(--ink-muted)"></span>
                    </div>

                    <div id="tg7-tiles" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:18px"></div>

                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:24px;border-top:1px solid var(--border-soft);padding-top:20px">
                        <button type="button" class="btn-primary" id="tg7-descargar">Descargar TG-7</button>
                        <button type="button" class="btn-ghost" id="tg7-bitacora">Descargar bitácora (CSV)</button>
                    </div>
                </div>

                <div class="card" id="tg7-bloque-sinprestamo" style="padding:26px 30px;margin-top:18px" hidden>
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:8px">
                        <div class="eyebrow" style="letter-spacing:1.3px">Falta la orden de descuento</div>
                        <div id="tg7-badge-sinprestamo" style="font-size:12px;color:var(--ink-faint)"></div>
                    </div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 16px;line-height:1.55">
                        A estos trabajadores la nómina sí les retuvo préstamo, pero no hay ninguna orden
                        cargada que diga <strong>cuál</strong> préstamo es. Sus líneas salen con el campo
                        vacío y <strong>SERICA las va a rechazar</strong>. Descarga el CSV para pedirle a
                        ISSSTE las altas que faltan, o carga las órdenes de las quincenas anteriores si
                        ya las tienes.
                    </p>
                    <div class="tabla">
                        <div class="tabla__head tg7-fila-sinprestamo">
                            <div>RFC</div><div class="tg7-conteo">Retenido</div><div>Archivo</div><div>Línea</div>
                        </div>
                        <div id="tg7-tabla-sinprestamo" style="max-height:360px;overflow-y:auto"></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px">
                        <button type="button" class="btn-ghost" id="tg7-descargar-sinprestamo">Descargar faltantes (CSV)</button>
                        <span style="font-size:11.5px;color:var(--ink-faint)" id="tg7-pie-sinprestamo" hidden></span>
                    </div>
                </div>

                <div class="card" id="tg7-bloque-senales" style="padding:26px 30px;margin-top:18px" hidden>
                    <div class="eyebrow" style="letter-spacing:1.3px;margin-bottom:8px">Calidad del dato de origen</div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 10px">
                        Esto no se puede arreglar desde aquí: hay que reclamarlo a quien genera la nómina.
                    </p>
                    <ul class="tg7-senales" id="tg7-senales"></ul>
                </div>

                <div class="card" style="padding:26px 30px;margin-top:18px">
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:8px">
                        <div class="eyebrow" style="letter-spacing:1.3px">Hallazgos por tipo</div>
                        <div id="tg7-badge-hallazgos" style="font-size:12px;color:var(--ink-faint)"></div>
                    </div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 16px">
                        Un <strong>error</strong> deja el registro fuera del archivo; un <strong>aviso</strong> se
                        entrega igual pero conviene revisarlo. El detalle registro por registro va en la bitácora.
                    </p>
                    <div class="tabla">
                        <div class="tabla__head tg7-fila-hallazgo">
                            <div>Severidad</div><div>Campo</div><div>Motivo</div><div class="tg7-conteo">Registros</div>
                        </div>
                        <div id="tg7-tabla-hallazgos" style="max-height:420px;overflow-y:auto"></div>
                    </div>
                </div>

                <div class="card" id="tg7-bloque-rechazados" style="padding:26px 30px;margin-top:18px" hidden>
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:16px">
                        <div class="eyebrow" style="letter-spacing:1.3px">Registros que no se emiten</div>
                        <div id="tg7-badge-rechazados" style="font-size:12px;color:var(--ink-faint)"></div>
                    </div>
                    <div class="tabla">
                        <div class="tabla__head tg7-fila-rechazo">
                            <div>RFC</div><div>Préstamo</div><div>Por qué</div>
                        </div>
                        <div id="tg7-tabla-rechazados" style="max-height:420px;overflow-y:auto"></div>
                    </div>
                    <div style="font-size:11.5px;color:var(--ink-faint);margin-top:14px" id="tg7-pie-rechazados" hidden></div>
                </div>

                <div class="card" id="tg7-bloque-incidencias" style="padding:26px 30px;margin-top:18px;margin-bottom:10px" hidden>
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:16px">
                        <div class="eyebrow" style="letter-spacing:1.3px">Incidencias de lectura y cruce</div>
                        <div id="tg7-badge-incidencias" style="font-size:12px;color:var(--ink-faint)"></div>
                    </div>
                    <div class="tabla">
                        <div class="tabla__head tg7-fila-incidencia">
                            <div>Origen</div><div>Línea</div><div>RFC</div><div>Qué pasó</div>
                        </div>
                        <div id="tg7-tabla-incidencias" style="max-height:360px;overflow-y:auto"></div>
                    </div>
                </div>

            </section>
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
