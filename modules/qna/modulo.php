<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Reporte QNA. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' es lista blanca: el reporte trae CURP, RFC y sueldo de diez mil
 * personas, y cada corrida recorre una quincena de las seis tablas de nómina
 * (`federal` es MyISAM: un escaneo toma READ lock). Para abrirlo a alguien más,
 * dáselo desde el módulo Permisos.
 */
return [
    'slug'        => 'qna',
    'nombre'      => 'Reporte QNA',
    'url'         => 'modules/qna/index.php',
    'descripcion' => 'Arma el reporte quincenal de nómina ordinaria directo de las seis tablas, sin los .txt.',
    'orden'       => 47,
    'usuarios'    => ['csegura'],
];
