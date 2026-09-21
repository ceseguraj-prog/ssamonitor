<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Resumen. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' vacío: el avance de timbrado lo ve cualquiera que entre.
 */
return [
    'slug'        => 'resumen',
    'nombre'      => 'Resumen',
    'url'         => 'modules/resumen/index.php',
    'descripcion' => 'Avance de timbrado de la quincena y acumulado del año.',
    'orden'       => 10,
    'usuarios'    => [],
];
