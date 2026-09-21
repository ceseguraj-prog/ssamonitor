<?php

declare(strict_types=1);

/**
 * Íconos del rail para los módulos. Viven aquí y no en cada plantilla porque
 * el rail se pinta en dos sitios: includes/rail.php (páginas de módulo) y
 * home.php (que arma el suyo en línea).
 *
 * Todos son trazos de 24x24 sin relleno, para que hereden stroke y color del
 * contenedor igual que los íconos de las vistas.
 */
/**
 * Ruta al ícono de la pestaña, versionada con la fecha de este archivo: el
 * dibujo vive aquí, así que al cambiarlo el navegador vuelve a pedirlo en vez
 * de quedarse con el viejo en caché.
 *
 * @param string $rutaBase prefijo hacia la raíz ('' en la raíz, '../../' en un módulo).
 */
function faviconUrl(string $rutaBase = ''): string
{
    return $rutaBase . 'favicon.php?v=' . filemtime(__FILE__);
}

/**
 * Marca de la app: un comprobante de pago con el borde inferior dentado, que es
 * de lo que trata todo el sistema. Va en el cuadro vino del rail, así que se
 * dibuja con trazo y hereda el color del contenedor.
 */
function iconoLogo(): string
{
    return '<path d="M6 2.9 H18 V21.1 l-2 -1.5 l-2 1.5 l-2 -1.5 l-2 1.5 l-2 -1.5 l-2 1.5 Z"/>'
        . '<path d="M9.2 8.2 H14.8"/>'
        . '<path d="M9.2 11.8 H14.8"/>'
        . '<path d="M9.2 15.4 H12.6"/>';
}

function iconoModulo(string $slug): string
{
    $iconos = [
        'sqlfix'  => '<ellipse cx="12" cy="5.8" rx="7.4" ry="2.8"/><path d="M4.6 5.8v6.4c0 1.5 3.3 2.8 7.4 2.8s7.4-1.3 7.4-2.8V5.8"/><path d="M4.6 12.2v6c0 1.5 3.3 2.8 7.4 2.8 1.3 0 2.5-.13 3.5-.36"/><path d="M17.6 17.4l1.7 1.7 2.6-2.9"/>',
        'checkid' => '<circle cx="10.6" cy="10.6" r="6.6"/><path d="M15.6 15.6 L20.4 20.4"/><path d="M7.8 10.8 L9.9 12.9 L13.4 8.6"/>',
        // Hoja de texto con un signo de admiración: archivo que trae errores.
        'errores' => '<path d="M6 3.2h7.4L19 8.6v12.2H6z"/><path d="M13.2 3.4V9h5.4"/><path d="M12.5 12v3.6"/><path d="M12.5 18.1v.1"/>',
        // Almohadilla: el módulo busca por clave de concepto. Se distingue de la
        // lupa de CheckID, que busca personas y no códigos.
        'conceptos' => '<path d="M9.6 3.6 7.8 20.4"/><path d="M16.4 3.6 14.6 20.4"/><path d="M3.8 9.2H20.4"/><path d="M3.2 14.8H19.8"/>',
        // Hoja con signo de pesos: el archivo de nómina de préstamos. Misma hoja
        // que 'errores', con la marca cambiada, porque son dos archivos distintos
        // del mismo trámite.
        'tg7' => '<path d="M6 3.2h7.4L19 8.6v12.2H6z"/><path d="M13.2 3.4V9h5.4"/>'
            . '<path d="M9.6 17.4V12.2h2a1.7 1.7 0 0 1 0 3.4H8.8"/><path d="M8.8 14.3h3.9"/>',
        // Persona con un escudo: quién puede entrar a qué.
        'permisos' => '<circle cx="10" cy="7.6" r="3.2"/>'
            . '<path d="M3.8 20.4c0-3.4 2.8-6.2 6.2-6.2 .9 0 1.7.2 2.5.5"/>'
            . '<path d="M18 12.2l3.4 1.3v3.1c0 2-1.4 3.8-3.4 4.4-2-.6-3.4-2.4-3.4-4.4v-3.1z"/>',
        // Dos figuras, la segunda detrás y más chica: el trabajador y el tercero
        // que le cobra el descuento. Es el mismo ícono que traía la pantalla de
        // Terceros en el sistema del que se portó.
        'terceros' => '<circle cx="9.2" cy="8" r="3.1"/>'
            . '<path d="M3.6 19.6c0-3.1 2.5-5.6 5.6-5.6s5.6 2.5 5.6 5.6"/>'
            . '<circle cx="17.5" cy="8.7" r="2.4"/>'
            . '<path d="M15.7 14.4c2.6.35 4.5 2.6 4.5 5.2"/>',
    ];

    $generico = '<rect x="3.5" y="3.5" width="17" height="17" rx="4"/><path d="M8 9.5 H16"/><path d="M8 13.5 H16"/>';

    return $iconos[$slug] ?? $generico;
}
