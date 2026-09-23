<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Extracción. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' es lista blanca: el módulo arma los archivos con los que se manda
 * la extracción de timbres de la quincena, y equivocarse de periodo ahí cuesta
 * una extracción entera. Para abrirlo a cualquier usuario autenticado, deja el
 * arreglo vacío.
 */
return [
    'slug'        => 'extraccion',
    'nombre'      => 'Extracción',
    'url'         => 'modules/extraccion/index.php',
    'descripcion' => 'Arma los UUID de Regularizados e IMSS Bienestar, el resumen en Excel y la nota en markdown.',
    'orden'       => 35,
    'usuarios'    => ['csegura'],
];
