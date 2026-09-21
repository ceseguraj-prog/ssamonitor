<?php

declare(strict_types=1);

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/iconos.php';

/**
 * Rail de navegación. Todas las pantallas son módulos, así que la lista sale
 * entera de modulosVisibles(): agregar una pantalla es soltar su carpeta en
 * modules/, sin tocar este archivo.
 *
 * En reposo solo se ven los íconos; al pasar el cursor (o al llegar el foco con
 * el tabulador) se abre hacia la derecha y aparecen las etiquetas. Todo eso lo
 * resuelve el CSS: aquí solo se marca qué es ícono y qué es etiqueta, para que
 * el texto exista siempre en el documento aunque no se vea. Un lector de
 * pantalla necesita leerlo, y ocultarlo con display:none se lo quitaría.
 *
 * La página incluyente define:
 *   $rutaBase       prefijo hacia la raíz ('' en la raíz, '../../' en un módulo).
 *   $moduloActual   slug del módulo activo, para resaltarlo.
 */
$rutaBase ??= '';
$moduloActual ??= '';
?>
<div class="rail-nav">

    <div class="rail-marca">
        <a class="rail-logo" href="<?= $rutaBase ?>home.php" title="Ir al resumen">
            <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= iconoLogo() ?></svg>
        </a>
        <span class="rail-nombre"><?= htmlspecialchars(APP_NOMBRE) ?></span>
    </div>

    <?php foreach (modulosVisibles() as $modulo): ?>
        <?php $slug = (string) $modulo['slug']; ?>
        <a class="rail-item <?= $slug === $moduloActual ? 'active' : '' ?>"
           href="<?= $rutaBase . htmlspecialchars((string) $modulo['url']) ?>"
           title="<?= htmlspecialchars((string) ($modulo['descripcion'] ?? '')) ?>">
            <span class="rail-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><?= iconoModulo($slug) ?></svg>
            </span>
            <span class="rail-etiqueta"><?= htmlspecialchars((string) $modulo['nombre']) ?></span>
        </a>
    <?php endforeach; ?>

    <div style="flex:1"></div>

    <?php /* Botón de verdad y no un div con onclick: así se alcanza con el
             tabulador, que además es lo que abre el rail al enfocarlo. */ ?>
    <button type="button" class="rail-theme-toggle" onclick="alternarTema()" title="Cambiar tema">
        <span class="rail-pie-icono">
            <svg id="icono-tema" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></svg>
        </span>
        <span class="rail-etiqueta">Tema</span>
    </button>

    <a href="<?= $rutaBase ?>logout.php" class="rail-salir" title="Cerrar sesión">
        <span class="rail-pie-icono">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9.6 3.6H6A1.5 1.5 0 0 0 4.5 5.1v13.8A1.5 1.5 0 0 0 6 20.4h3.6"/>
                <path d="M15.4 8.4 19 12l-3.6 3.6"/>
                <path d="M19 12H9.4"/>
            </svg>
        </span>
        <span class="rail-etiqueta">Salir</span>
    </a>
</div>
<script>
    /* El tema se guarda por navegador para que no se pierda al cambiar de
       página: ahora cada pantalla es una página distinta. */
    (function () {
        try {
            const guardado = localStorage.getItem('monitor-tema');
            if (guardado) document.body.setAttribute('data-theme', guardado);
        } catch (e) { /* modo privado: se queda en el tema por defecto */ }
        pintarIconoTema();
    })();

    /** Sol o luna, según a qué tema lleva el botón. */
    function pintarIconoTema() {
        const svg = document.getElementById('icono-tema');
        if (!svg) return;
        svg.innerHTML = document.body.getAttribute('data-theme') === 'dark'
            ? '<circle cx="12" cy="12" r="4.4"/><path d="M12 2.4V4M12 20v1.6M4.9 4.9 6 6M18 18l1.1 1.1M2.4 12H4M20 12h1.6M4.9 19.1 6 18M18 6l1.1-1.1"/>'
            : '<path d="M20 14.6A8.6 8.6 0 1 1 9.4 4a7.1 7.1 0 0 0 10.6 10.6Z"/>';
    }

    function alternarTema() {
        const actual = document.body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const nuevo = actual === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', nuevo);
        try { localStorage.setItem('monitor-tema', nuevo); } catch (e) { /* ignorado */ }
        pintarIconoTema();
        document.dispatchEvent(new CustomEvent('tema-cambiado', { detail: nuevo }));
    }
</script>
