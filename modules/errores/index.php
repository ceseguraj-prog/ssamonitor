<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('errores');

/** Versión de un asset por su fecha de modificación, igual que en los demás módulos. */
function assetErrores(string $ruta): string
{
    $absoluta = __DIR__ . '/' . $ruta;

    return $ruta . '?v=' . (is_file($absoluta) ? filemtime($absoluta) : '1');
}

paginaInicio([
    'slug' => 'errores',
    'titulo' => 'Errores de timbrado',
    'kicker' => 'Herramientas',
    'h1' => 'Errores de timbrado',
    'subtitulo' => 'Lee el archivo que devuelve el PAC y lo deja legible',
    'fuente' => 'navegador',
    'loader' => 'Leyendo archivo',
    'css' => [assetErrores('css/errores.css')],
]);
?>

<div style="font-size:13.5px;color:var(--ink-muted);line-height:1.6;margin-bottom:22px;max-width:780px">
    El archivo se lee <strong style="color:var(--ink);font-weight:600">en tu navegador</strong>:
    no se sube al servidor ni se guarda en ninguna base de datos. Al cerrar la
    página no queda nada.
</div>

<div class="card" style="padding:26px 30px">
    <div class="eyebrow" style="letter-spacing:1.2px;margin-bottom:16px">Archivo de errores</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <?php /* El input real se esconde: la zona punteada es la que se ve y
                 también recibe el archivo arrastrado. */ ?>
        <input type="file" id="archivo" accept=".txt,.xml,.log" hidden>
        <label class="err-zona" for="archivo" id="zonaArchivo">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--wine)" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7.6 8.4 L12 4 L16.4 8.4"/><path d="M4 16v2.6A1.4 1.4 0 0 0 5.4 20h13.2A1.4 1.4 0 0 0 20 18.6V16"/></svg>
            <span id="etiquetaArchivo">Selecciona o arrastra el archivo .txt del timbrado</span>
        </label>
    </div>
    <div style="font-size:11.5px;color:var(--ink-faint);margin-top:14px">
        Una línea por comprobante rechazado, con la respuesta del PAC en XML.
    </div>
</div>

<div id="error" class="err-alerta" hidden style="margin-top:18px"></div>

<section id="resultado" hidden>

    <!-- Hero: el dato que se viene a ver es cuántos se rechazaron -->
    <div class="hero rise-in" style="padding:30px 34px;margin-top:20px">
        <div class="hero__sheen"></div>
        <div class="err-hero">
            <div>
                <div class="eyebrow" style="color:var(--hero-ink-faint);letter-spacing:1.3px">Comprobantes rechazados</div>
                <div class="figure err-hero-cifra"><span id="res-total">0</span></div>
                <div id="res-archivo" class="err-hero-archivo"></div>
            </div>
            <div class="err-hero-datos" id="res-datos"></div>
        </div>
    </div>

    <div class="err-columnas">

        <!-- Distribución por código de error -->
        <div class="card rise-in" style="--i:1;padding:24px;display:flex;flex-direction:column;gap:18px">
            <div class="eyebrow" style="letter-spacing:1.2px">Errores por código</div>
            <div style="display:flex;justify-content:center">
                <div class="ring-in" style="--i:2;width:178px;height:178px;position:relative">
                    <div id="res-anillo"></div>
                    <div class="err-anillo-centro">
                        <div class="figure" style="font-size:25px;letter-spacing:-0.6px" id="res-codigos-n">0</div>
                        <div style="font-size:10.5px;color:var(--ink-muted);margin-top:6px;line-height:1.3">códigos distintos</div>
                    </div>
                </div>
            </div>
            <div id="res-leyenda" style="display:flex;flex-direction:column;gap:11px;border-top:1px solid var(--border-soft);padding-top:16px"></div>
        </div>

        <!-- Tabla de comprobantes -->
        <div class="rise-in" style="--i:2;min-width:0">
            <div class="err-filtros">
                <input id="err-search" class="field field--pill" placeholder="Buscar RFC, CURP o folio…" autocomplete="off" spellcheck="false" style="min-width:220px;flex:1">
                <select id="err-codigo" class="field field--pill" style="cursor:pointer"></select>
                <select id="err-serie" class="field field--pill" style="cursor:pointer"></select>
                <div id="err-count" style="font-size:12.5px;color:var(--ink-faint);margin-left:auto"></div>
            </div>

            <div class="tabla">
                <div class="tabla__head err-fila">
                    <?php /* La primera columna es la casilla de "ya lo revisé". */ ?>
                    <div></div>
                    <div>RFC</div><div>Código</div><div>Serie</div><div>Folio</div><div>Motivo</div>
                </div>
                <div id="err-tabla" style="max-height:560px;overflow-y:auto"></div>
            </div>

            <div class="err-acciones">
                <button type="button" class="btn-ghost" id="btnCopiarRfc">Copiar RFC de la lista</button>
                <button type="button" class="btn-ghost" id="btnCsv">Descargar CSV</button>
                <span id="err-marcadas" class="err-marcadas" hidden></span>
                <button type="button" class="err-limpiar" id="btnLimpiarMarcas" hidden>Limpiar marcas</button>
                <span id="err-sin-leer" class="err-sin-leer" hidden></span>
            </div>
        </div>
    </div>
</section>

<div id="vacio" class="emp-vacio">
    Elige un archivo de errores para verlo desglosado.
</div>

<?php paginaFin([assetErrores('js/errores.js')]); ?>
