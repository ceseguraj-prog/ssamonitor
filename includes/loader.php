<?php

declare(strict_types=1);

/**
 * Velo de carga compartido. Va dentro de .content-area para que cubra solo el
 * área de contenido: el rail y la cabecera tienen que seguir legibles.
 *
 * La página incluyente lo enciende con:
 *   document.getElementById('loader').hidden = false;
 * y cambia la etiqueta con #loader-label.
 */
$etiquetaLoader ??= 'Cargando';
?>
<div id="loader" class="loader" hidden>
    <div class="loader__orb">
        <div class="loader__halo"></div>
        <svg class="loader__ring" width="74" height="74" viewBox="0 0 80 80">
            <circle cx="40" cy="40" r="31" fill="none" stroke="var(--track)" stroke-width="4"/>
            <circle class="arco" cx="40" cy="40" r="31" fill="none" stroke="var(--wine)" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <svg class="loader__ring2" width="48" height="48" viewBox="0 0 80 80">
            <circle cx="40" cy="40" r="28" fill="none" stroke="var(--wine-soft)" stroke-width="4" stroke-linecap="round" stroke-dasharray="26 250"/>
        </svg>
        <div class="loader__core"></div>
    </div>
    <div class="loader__foot">
        <div id="loader-label" class="loader__label"><?= htmlspecialchars($etiquetaLoader) ?></div>
        <div class="loader__bar"><span></span></div>
        <div class="loader__dots"><span></span><span></span><span></span></div>
    </div>
</div>
