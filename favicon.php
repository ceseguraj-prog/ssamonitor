<?php

declare(strict_types=1);

/**
 * Ícono de la pestaña. Se sirve desde PHP para que salga del mismo sitio que el
 * logo del rail (includes/iconos.php) y no haya dos dibujos que se desincronicen
 * cuando uno cambie.
 *
 * No pide sesión a propósito: si redirigiera al login, la pestaña se quedaría
 * con el ícono en blanco.
 */
require_once __DIR__ . '/includes/iconos.php';

header('Content-Type: image/svg+xml; charset=utf-8');
// El dibujo solo cambia cuando cambia el código, así que se puede cachear
// agresivamente; el enlace lleva ?v= con la fecha del archivo.
header('Cache-Control: public, max-age=604800');

?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32">
  <?php /* La pastilla vino es lo que hace reconocible el ícono a 16px, más que
           el trazo de adentro: sin fondo, el comprobante se pierde. */ ?>
  <rect x="0" y="0" width="24" height="24" rx="6" fill="#6E1230"/>
  <g transform="translate(12 12) scale(0.72) translate(-12 -12)"
     fill="none" stroke="#FFF6F2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <?= iconoLogo() ?>
  </g>
</svg>
