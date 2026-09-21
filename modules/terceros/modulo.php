<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Terceros. includes/modulos.php lo descubre solo.
 *
 * 'usuarios' va vacío: el cierre de terceros lo consulta cualquiera que entre.
 *
 * 'admin' es aparte y no lo usa includes/modulos.php: es quién puede tocar el
 * catálogo de grupos desde la pantalla. Consultar el reporte es una cosa;
 * redefinir qué conceptos entran en cada grupo mueve las cifras del cierre y de
 * los dos CSV, así que va restringido.
 *
 * Queda junto a Conceptos en el rail porque son las dos pantallas que leen las
 * tablas de nómina de `catalogos`; las de arriba salen de BPM.
 */
return [
    'slug'        => 'terceros',
    'nombre'      => 'Terceros',
    'url'         => 'modules/terceros/index.php',
    'descripcion' => 'Descuentos a terceros de la quincena, por grupo y concepto.',
    'orden'       => 46,
    'usuarios'    => [],
    'admin'       => ['csegura'],
];
