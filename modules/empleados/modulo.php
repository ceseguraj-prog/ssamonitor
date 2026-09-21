<?php

declare(strict_types=1);

/** Descriptor del módulo Empleados. includes/modulos.php lo descubre solo. */
return [
    'slug'        => 'empleados',
    'nombre'      => 'Empleados',
    'url'         => 'modules/empleados/index.php',
    'descripcion' => 'Consulta de empleados y sus timbres por RFC.',
    'orden'       => 40,
    'usuarios'    => [],
];
