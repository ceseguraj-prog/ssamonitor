<?php

declare(strict_types=1);

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/iconos.php';
require_once __DIR__ . '/fuentes.php';

/**
 * Armazón de una pantalla: <head>, rail, cabecera y velo de carga.
 *
 * Todas las pantallas son módulos y todas se ven igual, así que el armazón vive
 * en un solo sitio. Cuando cambió el área del usuario hubo que tocar cuatro
 * archivos idénticos; eso es justo lo que esto evita.
 *
 * Opciones:
 *   slug      string  módulo activo, para resaltarlo en el rail
 *   titulo    string  nombre de la pantalla; la pestaña queda como
 *                     "<APP_NOMBRE> — <titulo>". No lleva el nombre del
 *                     sistema: lo antepone esta función.
 *   kicker    string  micro-etiqueta encima del título
 *   h1        string  título de la pantalla
 *   subtitulo string  línea de apoyo
 *   fuente    string  clave de includes/fuentes.php (de dónde salen los datos)
 *   loader    string  etiqueta del velo de carga
 *   css       array   hojas de estilo propias del módulo, ya versionadas
 */
function paginaInicio(array $o): void
{
    $rutaBase = '../../';
    $usuario = (string) ($_SESSION['usuario'] ?? 'Usuario');
    $iniciales = mb_strtoupper(mb_substr($usuario, 0, 2));
    $moduloActual = (string) ($o['slug'] ?? '');

    $pantalla = trim((string) ($o['titulo'] ?? ''));
    $titulo = $pantalla === '' ? APP_NOMBRE : APP_NOMBRE . ' — ' . $pantalla;
    ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="icon" href="<?= faviconUrl($rutaBase) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,300..800&family=Roboto+Serif:opsz,wght@8..144,300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $rutaBase ?>css/theme.css?v=<?= filemtime(__DIR__ . '/../css/theme.css') ?>">
    <?php foreach ((array) ($o['css'] ?? []) as $hoja): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars((string) $hoja) ?>">
    <?php endforeach; ?>
</head>

<body>

<div class="app-container">

    <?php require __DIR__ . '/rail.php'; ?>

    <div class="main-content">

        <div class="top-header">
            <div>
                <div class="kicker"><?= htmlspecialchars((string) ($o['kicker'] ?? '')) ?></div>
                <h1><?= htmlspecialchars((string) ($o['h1'] ?? '')) ?></h1>
                <div class="subtitle"><?= htmlspecialchars((string) ($o['subtitulo'] ?? '')) ?></div>
            </div>
            <div class="usuario">
                <div style="text-align:right">
                    <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($usuario) ?></div>
                    <div style="font-size:11px;color:var(--ink-muted);letter-spacing:0.3px"><?= AREA_USUARIO ?></div>
                </div>
                <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
            </div>
        </div>

        <?php /* El velo de carga cubre solo esta zona: el rail y la cabecera
                 siguen legibles mientras algo se consulta. */ ?>
        <div class="content-area">

            <?= fuenteDatos((string) ($o['fuente'] ?? '')) ?>

            <?php $etiquetaLoader = (string) ($o['loader'] ?? 'Cargando'); ?>
            <?php require __DIR__ . '/loader.php'; ?>

            <div class="view-container active">
    <?php
}

/**
 * Cierra el armazón y carga los scripts. js/comun.js va siempre primero: el JS
 * del módulo se apoya en él.
 *
 * @param array $scripts rutas relativas al módulo, ya versionadas.
 */
function paginaFin(array $scripts = []): void
{
    $comun = __DIR__ . '/../js/comun.js';
    ?>
            </div>
        </div>
    </div>
</div>

<script src="../../js/comun.js?v=<?= filemtime($comun) ?>"></script>
<?php foreach ($scripts as $script): ?>
    <script src="<?= htmlspecialchars((string) $script) ?>"></script>
<?php endforeach; ?>
</body>

</html>
    <?php
}

/**
 * Cápsula del selector de periodo. El servidor ya resolvió cuál mostrar y lo
 * deja en data-*, para que js/comun.js solo llene los selectores.
 */
function selectorPeriodo(array $periodo, array $aniosDisponibles): void
{
    ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;flex-wrap:wrap">
        <div class="periodo" id="periodo"
             data-anio="<?= (int) $periodo['anio'] ?>"
             data-quincena="<?= (int) $periodo['quincena'] ?>"
             data-anios="<?= htmlspecialchars(json_encode(array_values($aniosDisponibles)) ?: '[]') ?>">
            <span class="eyebrow" style="font-size:11px;letter-spacing:0.8px">Periodo</span>
            <select id="periodo-quincena"></select>
            <select id="periodo-anio"></select>
        </div>
        <div id="periodo-rango" style="font-size:13px;color:var(--ink-muted);padding-left:6px">
            <?= htmlspecialchars(rangoPeriodo((int) $periodo['anio'], (int) $periodo['quincena'])) ?>
        </div>
    </div>
    <?php
}
