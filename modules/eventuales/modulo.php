<?php

declare(strict_types=1);

/**
 * Descriptor del generador de nómina de eventuales. includes/modulos.php lo
 * descubre solo.
 *
 * 'usuarios' es lista blanca: produce los archivos con los que se paga a cerca
 * de 2 700 eventuales y SaNAS, así que no es una consulta para cualquiera. No
 * toca ninguna base; si otra persona tiene que operarlo, se le da desde el
 * módulo Permisos.
 */
return [
    'slug'        => 'eventuales',
    'nombre'      => 'Nómina de eventuales',
    'url'         => 'modules/eventuales/index.php',
    'descripcion' => 'Arma el prod_pago y el detalle de empleados de eventuales y SaNAS a partir de la nómina de la quincena.',
    'orden'       => 82,
    'usuarios'    => ['csegura'],
];
