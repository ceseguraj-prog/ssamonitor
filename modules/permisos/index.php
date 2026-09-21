<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/modulos.php';
require_once __DIR__ . '/../../includes/pagina.php';

requireModulo('permisos');

use App\UsuariosRepository;

/**
 * Matriz usuario × módulo. Cada celda es un permiso; cada renglón se guarda por
 * separado para que dos ediciones no se pisen y para que un error en un usuario
 * no arrastre a los demás.
 *
 * El estado de cada usuario se calcula en el servidor con modulosDeUsuario(),
 * que ya resuelve el respaldo a los modulo.php cuando no hay nada configurado.
 */
$usuarios = UsuariosRepository::todos();
$modulos = modulosRegistrados();

// Permisos es el único módulo que no se administra desde aquí: su acceso lo
// manda su propio modulo.php. Se muestra igual, en gris, para que no parezca
// que falta uno.
$columnas = [];
foreach ($modulos as $slug => $modulo) {
    $columnas[] = [
        'slug' => (string) $slug,
        'nombre' => (string) $modulo['nombre'],
        'editable' => permisosGobernados((string) $slug),
    ];
}

$filas = [];
foreach ($usuarios as $usuario) {
    $filas[] = [
        'user' => $usuario['user'],
        'nombre' => $usuario['nombre'],
        'modulos' => modulosDeUsuario($usuario['user']),
        'configurado' => permisosConfigurado($usuario['user']),
    ];
}

$escribible = is_dir(__DIR__ . '/../../config') && is_writable(__DIR__ . '/../../config');

paginaInicio([
    'slug' => 'permisos',
    'titulo' => 'Permisos',
    'kicker' => 'Administración',
    'h1' => 'Permisos por usuario',
    'subtitulo' => 'Quién entra a qué módulo',
    'fuente' => 'catalogos',
    'loader' => 'Guardando permisos',
    'css' => ['css/permisos.css?v=' . filemtime(__DIR__ . '/css/permisos.css')],
]);
?>

<?php if (!$escribible): ?>
    <div class="prm-aviso prm-aviso--error">
        La carpeta <code>config/</code> no acepta escritura, así que no se puede guardar nada.
        El servidor web necesita permiso sobre ella: ahí vive <code>permisos.json</code>.
    </div>
<?php endif; ?>

<div class="prm-barra">
    <input id="filtrar" class="field field--pill" placeholder="Filtrar por usuario o nombre…"
           autocomplete="off" spellcheck="false">
    <div id="contador" class="prm-contador"><?= count($filas) ?> usuarios</div>
</div>

<div id="aviso" class="prm-aviso" hidden></div>

<div class="tabla prm-tabla">
    <div class="tabla__head prm-grid" style="--prm-cols: <?= count($columnas) ?>">
        <div>Usuario</div>
        <?php foreach ($columnas as $col): ?>
            <div class="prm-col <?= $col['editable'] ? '' : 'prm-col--fijo' ?>"
                 title="<?= htmlspecialchars($col['nombre']) ?><?= $col['editable'] ? '' : ' · lo manda su modulo.php, no se edita aquí' ?>">
                <span><?= htmlspecialchars($col['nombre']) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="prm-acciones"></div>
    </div>

    <div id="cuerpo">
        <?php foreach ($filas as $fila): ?>
            <div class="tabla__row prm-grid prm-fila"
                 style="--prm-cols: <?= count($columnas) ?>"
                 data-user="<?= htmlspecialchars($fila['user']) ?>"
                 data-busca="<?= htmlspecialchars(mb_strtolower($fila['user'] . ' ' . $fila['nombre'])) ?>">

                <div class="prm-usuario">
                    <span class="prm-login"><?= htmlspecialchars($fila['user']) ?></span>
                    <span class="prm-nombre"><?= htmlspecialchars($fila['nombre'] ?: '—') ?></span>
                    <span class="prm-estado <?= $fila['configurado'] ? 'prm-estado--fijo' : '' ?>">
                        <?= $fila['configurado'] ? 'configurado' : 'por omisión' ?>
                    </span>
                </div>

                <?php foreach ($columnas as $col): ?>
                    <?php $tiene = in_array($col['slug'], $fila['modulos'], true); ?>
                    <div class="prm-celda">
                        <label class="prm-check" title="<?= htmlspecialchars($col['nombre']) ?>">
                            <input type="checkbox"
                                   data-slug="<?= htmlspecialchars($col['slug']) ?>"
                                   <?= $tiene ? 'checked' : '' ?>
                                   <?= $col['editable'] && $escribible ? '' : 'disabled' ?>>
                            <span class="prm-caja" aria-hidden="true"></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <div class="prm-acciones">
                    <button type="button" class="prm-btn prm-guardar" hidden>Guardar</button>
                    <button type="button" class="prm-btn prm-restablecer"
                            <?= $fila['configurado'] && $escribible ? '' : 'hidden' ?>
                            title="Vuelve a los permisos por omisión de cada módulo">Restablecer</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="vacio" class="prm-vacio" hidden>Ningún usuario coincide con ese filtro.</div>

<details class="prm-nota">
    <summary>Cómo se resuelve un permiso</summary>
    <p>
        Un usuario <strong>por omisión</strong> no tiene nada guardado: ve lo que diga el
        <code>usuarios</code> de cada <code>modulo.php</code>, que es como funcionaba todo antes
        de esta pantalla. En cuanto le mueves una casilla pasa a <strong>configurado</strong> y a
        partir de ahí manda exactamente lo que esté palomeado aquí — dejarle todo sin palomear
        significa que no entra a ningún módulo, que no es lo mismo que estar por omisión.
        <em>Restablecer</em> borra su registro y lo devuelve a por omisión.
    </p>
    <p>
        <strong>Permisos</strong> es la única columna que no se edita: su acceso lo sigue mandando
        <code>modules/permisos/modulo.php</code>. Si se pudiera quitar desde aquí, una casilla mal
        picada dejaría el sistema sin nadie capaz de devolver accesos.
    </p>
    <p>
        Todo esto se guarda en <code>config/permisos.json</code>, no en la base de datos:
        <code>catalogos</code> es de solo lectura para este sistema.
    </p>
</details>

<?php paginaFin(['js/permisos.js?v=' . filemtime(__DIR__ . '/js/permisos.js')]); ?>
