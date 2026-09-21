<?php

declare(strict_types=1);

/**
 * Descriptor del módulo TG-7. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' va vacío a propósito: cualquier pagaduría tiene que entregar su
 * nómina de préstamos personales, así que la herramienta es para cualquier
 * usuario autenticado. No hay razón de costo ni de carga para restringirla —
 * no consulta ninguna base de datos ni ningún servicio de paga.
 */
return [
    'slug'        => 'tg7',
    'nombre'      => 'TG-7',
    'url'         => 'modules/tg7/index.php',
    'descripcion' => 'Genera la nómina de préstamos personales (TG-7) para SERICA cruzando nómina y órdenes de descuento.',
    'orden'       => 80,
    'usuarios'    => [],
];
