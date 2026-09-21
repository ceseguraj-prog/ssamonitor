<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Corrector SQL. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' es la lista blanca de logins con acceso; dejarla vacía abriría el
 * módulo a cualquier usuario autenticado.
 */
return [
    'slug'        => 'sqlfix',
    'nombre'      => 'Corrector SQL',
    'url'         => 'modules/sqlfix/index.php',
    'descripcion' => 'Repara apóstrofes sin escapar y codificación corrupta en volcados .sql.',
    'orden'       => 70,
    'usuarios'    => ['csegura'],
];
