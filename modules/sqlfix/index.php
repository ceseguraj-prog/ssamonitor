<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('sqlfix');

/** Versión de un asset por su fecha de modificación, igual que en los demás módulos. */
function assetSqlFix(string $ruta): string
{
    $absoluta = __DIR__ . '/' . $ruta;

    return $ruta . '?v=' . (is_file($absoluta) ? filemtime($absoluta) : '1');
}

paginaInicio([
    'slug' => 'sqlfix',
    'titulo' => 'Corrector SQL',
    'kicker' => 'Herramientas',
    'h1' => 'Corrector SQL',
    'subtitulo' => 'Repara volcados de una sentencia por línea',
    'fuente' => 'archivo',
    'loader' => 'Analizando y corrigiendo',
    'css' => [assetSqlFix('css/sqlfix.css')],
]);
?>

        <div style="max-width:980px">

            <div style="font-size:13.5px;color:var(--ink-muted);line-height:1.6;margin-bottom:22px">
                Escapa los apóstrofes sueltos
                (<span class="sqlfix-muestra">YOIC'S → YOIC''S</span>)
                y arregla la codificación corrompida
                (<span class="sqlfix-muestra">AGÃœERO → AGÜERO</span>).
                El archivo original no se modifica.
            </div>

            <div class="card" style="padding:26px 30px">
                <form id="formSqlFix">
                    <div class="eyebrow" style="letter-spacing:1.2px;margin-bottom:16px">Archivo .sql</div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <!-- El input real se esconde: la zona punteada es la que se ve. -->
                        <input type="file" id="archivo" name="archivo" accept=".sql,.txt" required hidden>
                        <label class="sqlfix-zona" for="archivo" id="zonaArchivo">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--wine)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/><path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>
                            <span id="etiquetaArchivo">Selecciona un archivo .sql</span>
                        </label>
                        <button type="submit" class="btn-primary" id="btnProcesar">Analizar y corregir</button>
                    </div>
                    <div style="font-size:11.5px;color:var(--ink-faint);margin-top:14px">
                        Límite del servidor: <?= htmlspecialchars(ini_get('upload_max_filesize') ?: '?') ?>
                        por archivo (<?= htmlspecialchars(ini_get('post_max_size') ?: '?') ?> por envío).
                    </div>
                </form>
            </div>

            <div id="error" class="sqlfix-alerta" hidden style="margin-top:18px"></div>

            <section id="resultado" hidden>

                <div class="card" style="padding:26px 30px;margin-top:18px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:22px;flex-wrap:wrap">
                        <span class="chip-estado imp"><span class="dot"></span>CORREGIDO</span>
                        <span id="nombreSalida" style="font-size:12.5px;color:var(--ink-muted)"></span>
                    </div>
                    <div id="tarjetas" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:18px"></div>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:24px;border-top:1px solid var(--border-soft);padding-top:20px">
                        <a id="btnDescargar" class="btn-ghost" style="text-decoration:none" href="#">Descargar .sql corregido</a>
                    </div>
                </div>

                <div id="bloqueAvisos" class="card" style="padding:26px 30px;margin-top:18px" hidden>
                    <div class="eyebrow" style="letter-spacing:1.3px;margin-bottom:8px">Líneas que conviene revisar a mano</div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 16px">
                        No se modificaron: su estructura no coincide con lo esperado.
                    </p>
                    <div class="tabla">
                        <div class="tabla__head" style="display:grid;grid-template-columns:5rem 12rem 1fr">
                            <div>Línea</div><div>Motivo</div><div>Contenido</div>
                        </div>
                        <div id="tablaAvisos" style="max-height:320px;overflow-y:auto"></div>
                    </div>
                </div>

                <div class="card" id="bloqueCorrecciones" style="padding:26px 30px;margin-top:18px">
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:16px">
                        <div class="eyebrow" style="letter-spacing:1.3px">Correcciones aplicadas</div>
                        <div id="badgeCorrecciones" style="font-size:12px;color:var(--ink-faint)"></div>
                    </div>
                    <div class="tabla">
                        <div class="tabla__head" style="display:grid;grid-template-columns:5rem 10rem 1fr">
                            <div>Línea</div><div>Tipo</div><div>Antes → Después</div>
                        </div>
                        <div id="tablaCorrecciones" style="max-height:420px;overflow-y:auto"></div>
                    </div>
                    <div style="font-size:11.5px;color:var(--ink-faint);margin-top:14px" id="pieCorrecciones" hidden></div>
                </div>

                <div class="card" style="padding:26px 30px;margin-top:18px;margin-bottom:10px">
                    <div class="eyebrow" style="letter-spacing:1.3px;margin-bottom:8px">Caracteres no-ASCII en el resultado</div>
                    <p style="font-size:12.5px;color:var(--ink-muted);margin:0 0 16px">
                        Todos deberían ser acentos o eñes legítimos. Si aparece algo distinto,
                        quedó codificación sin resolver.
                    </p>
                    <div id="listaNoAscii" style="display:flex;flex-wrap:wrap;gap:8px"></div>
                </div>

            </section>
        </div>

<?php paginaFin([assetSqlFix('js/sqlfix.js')]); ?>
