<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Conceptos. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' es lista blanca a propósito: cada búsqueda escanea un año completo
 * de las seis tablas de nómina (~12 GB en total, y `federal` es MyISAM, donde un
 * escaneo toma READ lock y detiene escrituras). No es una pantalla para dejar
 * abierta a cualquiera. Para abrirla, deja el arreglo vacío.
 */
return [
    'slug'        => 'conceptos',
    'nombre'      => 'Conceptos',
    'url'         => 'modules/conceptos/index.php',
    'descripcion' => 'Busca conceptos de pago por clave en las seis tablas de nómina.',
    'orden'       => 45,
    'usuarios'    => ['csegura'],
];
