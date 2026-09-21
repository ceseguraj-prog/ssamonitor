<?php

declare(strict_types=1);

/**
 * Descriptor del módulo Permisos. includes/modulos.php lo descubre solo.
 *
 * Este arreglo 'usuarios' es especial: es el ÚNICO que el propio módulo no puede
 * sobrescribir. includes/permisos.php lo deja fuera a propósito (ver
 * permisosGobernados), porque esta pantalla es la única puerta para volver a
 * conceder accesos. Si se pudiera quitar desde aquí, una casilla mal picada
 * dejaría el sistema sin nadie capaz de repararlo salvo editando el JSON a mano
 * en el servidor.
 *
 * O sea: para cambiar quién administra permisos se edita este archivo, no la
 * pantalla.
 */
return [
    'slug'        => 'permisos',
    'nombre'      => 'Permisos',
    'url'         => 'modules/permisos/index.php',
    'descripcion' => 'Quién entra a qué módulo, usuario por usuario.',
    'orden'       => 90,
    'usuarios'    => ['csegura'],
];
