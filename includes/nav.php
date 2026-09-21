<?php

declare(strict_types=1);

require_once __DIR__ . '/modulos.php';

/**
 * Barra superior compartida. Requiere que la página incluyente ya haya
 * hecho session_start() (bootstrap.php) y defina:
 *
 *   $paginaActual  'timbres', 'empleados' o el slug de un módulo.
 *   $rutaBase      prefijo hacia la raíz del proyecto ('' en la raíz,
 *                  '../../' para una página dentro de modules/<slug>/).
 */
$paginaActual ??= '';
$rutaBase ??= '';
?>
<header class="p-3 text-bg-dark">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-4">
            <span class="text-white h5 mb-0"><?= htmlspecialchars(APP_NOMBRE) ?></span>
            <nav class="nav">
                <a class="nav-link px-2 <?= $paginaActual === 'timbres' ? 'text-white fw-bold' : 'text-white-50' ?>" href="<?= $rutaBase ?>home.php">Timbres</a>
                <a class="nav-link px-2 <?= $paginaActual === 'empleados' ? 'text-white fw-bold' : 'text-white-50' ?>" href="<?= $rutaBase ?>empleados.php">Empleados</a>
                <?php foreach (modulosVisibles() as $modulo): ?>
                    <a class="nav-link px-2 <?= $paginaActual === $modulo['slug'] ? 'text-white fw-bold' : 'text-white-50' ?>"
                       href="<?= $rutaBase . htmlspecialchars((string) $modulo['url']) ?>"><?= htmlspecialchars((string) $modulo['nombre']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white">Hola, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
            <a href="<?= $rutaBase ?>logout.php" class="btn btn-warning btn-sm">Salir</a>
        </div>
    </div>
</header>
