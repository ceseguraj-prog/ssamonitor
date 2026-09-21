<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';
require_once __DIR__ . '/CheckIdClient.php';

requireModulo('checkid');

use App\Modules\CheckId\CheckIdClient;

$configurada = CheckIdClient::configurada();

/** Versión de un asset por su fecha de modificación, igual que en los demás módulos. */
function assetCheckId(string $ruta): string
{
    $absoluta = __DIR__ . '/' . $ruta;

    return $ruta . '?v=' . (is_file($absoluta) ? filemtime($absoluta) : '1');
}

paginaInicio([
    'slug' => 'checkid',
    'titulo' => 'CheckID',
    'kicker' => 'Identidad',
    'h1' => 'CheckID',
    'subtitulo' => 'Consulta de RFC, CURP, NSS y estatus ante el SAT',
    'fuente' => 'checkid',
    'loader' => 'Consultando SAT / RENAPO',
    'css' => [assetCheckId('css/checkid.css')],
]);
?>

<?php if (!$configurada): ?>
    <div class="ck-alerta ck-alerta-aviso" style="margin-bottom:22px">
        <strong>Falta la clave de CheckID.</strong>
        Agrega <code>CHECKID_API_KEY</code> al archivo <code>.env</code> en la raíz del
        proyecto y recarga esta página. Puedes partir de <code>.env.example</code>.
    </div>
<?php endif; ?>

<?php /* Ya no se eligen "datos a consultar": siempre se traen todos. */ ?>
<div class="ck-buscador card">
    <form id="ck-form" autocomplete="off">
        <div class="eyebrow" style="letter-spacing:1.2px;margin-bottom:12px">RFC o CURP</div>
        <div class="ck-fila-busqueda">
            <input type="text" id="ck-termino" name="termino" class="field ck-termino"
                   placeholder="SEJC010503N24" maxlength="18" spellcheck="false"
                   <?= $configurada ? '' : 'disabled' ?>>
            <button type="submit" class="btn-primary" id="ck-buscar" <?= $configurada ? '' : 'disabled' ?>>
                Consultar
            </button>
        </div>
        <div class="ck-ayuda" id="ck-ayuda">
            RFC de 12 o 13 caracteres, o CURP de 18.
        </div>
    </form>
</div>

<div id="ck-error" class="ck-alerta ck-alerta-error" hidden></div>

<div id="ck-resultado" hidden></div>

<div id="ck-vacio" class="emp-vacio">
    Escribe un RFC o una CURP y presiona <strong>Consultar</strong>.
</div>

<?php paginaFin([assetCheckId('js/checkid.js')]); ?>
