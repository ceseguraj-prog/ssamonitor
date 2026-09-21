<?php

declare(strict_types=1);

/**
 * Descriptor del módulo CheckID. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' es la lista blanca de logins con acceso; dejarla vacía abriría el
 * módulo a cualquier usuario autenticado. Cada consulta a CheckID se cobra,
 * así que conviene mantenerla corta.
 */
return [
    'slug'        => 'checkid',
    'nombre'      => 'CheckID',
    'url'         => 'modules/checkid/index.php',
    'descripcion' => 'Consulta RFC, CURP, NSS, código postal, régimen fiscal y listas 69/69-B.',
    'orden'       => 50,
    'usuarios'    => ['csegura'],
];
