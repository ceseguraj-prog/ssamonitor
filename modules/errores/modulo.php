<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Errores de timbrado. includes/modulos.php lo descubre
 * solo: dar de alta un módulo es soltar la carpeta.
 *
 * 'usuarios' es la lista blanca de logins con acceso; dejarla vacía abriría el
 * módulo a cualquier usuario autenticado.
 */
return [
    'slug'        => 'errores',
    'nombre'      => 'Errores',
    'url'         => 'modules/errores/index.php',
    'descripcion' => 'Lee los archivos de error que devuelve el timbrado y los presenta legibles.',
    'orden'       => 60,
    'usuarios'    => ['csegura'],
];
