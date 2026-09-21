<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/iconos.php';

/**
 * Pantalla de acceso. Es la única que vive fuera del armazón de módulos
 * (includes/pagina.php), porque no lleva rail ni cabecera, pero pinta con los
 * mismos tokens de css/theme.css: no hay Bootstrap ni SweetAlert de por medio.
 *
 * Carga bootstrap.php solo por AREA_USUARIO, que es el mismo rótulo que sale en
 * la cabecera de todas las demás pantallas. No abre conexión: Database la crea
 * cuando alguien la pide, y aquí nadie la pide.
 *
 * El error llega como ?error=1 desde login.php y se pinta aquí mismo, en el
 * flujo de la tarjeta. Antes era un modal que había que cerrar para volver a
 * teclear; así el usuario ve qué pasó sin que se le tape el formulario.
 */
$hayError = isset($_GET['error']);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_NOMBRE) ?> — Iniciar sesión</title>
    <link rel="icon" href="<?= faviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:opsz,wght@8..144,300..800&family=Roboto+Serif:opsz,wght@8..144,300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme.css?v=<?= filemtime(__DIR__ . '/css/theme.css') ?>">
    <link rel="stylesheet" href="css/login.css?v=<?= filemtime(__DIR__ . '/css/login.css') ?>">
</head>

<body>

<?php /* Antes de pintar: si ya se eligió tema en otra sesión, se respeta. Va
         aquí arriba para que no se vea el destello claro antes del oscuro. */ ?>
<script>
    try {
        var guardado = localStorage.getItem('monitor-tema');
        if (guardado) document.body.setAttribute('data-theme', guardado);
    } catch (e) { /* modo privado: se queda en el tema por defecto */ }
</script>

<button type="button" class="login__tema" id="tema" title="Cambiar tema" aria-label="Cambiar tema">
    <svg id="icono-tema" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></svg>
</button>

<main class="login">

    <aside class="login__marca">
        <span class="login__sheen"></span>

        <div class="login__logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= iconoLogo() ?></svg>
        </div>

        <h1 class="login__nombre"><?= htmlspecialchars(APP_NOMBRE) ?></h1>
        <p class="login__lema">Avance de timbrado, nómina y descuentos a terceros, en un solo lugar.</p>

        <div class="login__pie"><?= htmlspecialchars(AREA_USUARIO) ?></div>
    </aside>

    <section class="login__panel">

        <div>
            <div class="eyebrow">Acceso</div>
            <h2 class="login__titulo">Iniciar sesión</h2>
        </div>

        <p class="login__ayuda">Entra con tu usuario del sistema.</p>

        <?php if ($hayError): ?>
            <div class="login__error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7.6v5"/><path d="M12 16.2v.1"/>
                </svg>
                <span>Usuario o contraseña incorrectos. Revisa y vuelve a intentar.</span>
            </div>
        <?php endif; ?>

        <form class="login__form" action="login.php" method="POST">

            <label class="login__campo">
                <span class="eyebrow">Usuario</span>
                <input class="field" type="text" id="user" name="user"
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       required autofocus>
            </label>

            <label class="login__campo">
                <span class="eyebrow">Contraseña</span>
                <div class="login__clave">
                    <input class="field" type="password" id="pass" name="pass"
                           autocomplete="current-password" required>
                    <button type="button" class="login__ojo" id="ver"
                            aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña">
                        <svg id="icono-ojo" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></svg>
                    </button>
                </div>
            </label>

            <button class="btn-primary login__entrar" type="submit">Entrar</button>
        </form>

    </section>

</main>

<script>
    /* ── Tema ─────────────────────────────────────────────────────────── */

    /* Misma clave que usa includes/rail.php, para que lo que elijas aquí siga
       puesto al entrar y al revés. */
    function pintarIconoTema() {
        document.getElementById('icono-tema').innerHTML =
            document.body.getAttribute('data-theme') === 'dark'
                ? '<circle cx="12" cy="12" r="4.4"/><path d="M12 2.4V4M12 20v1.6M4.9 4.9 6 6M18 18l1.1 1.1M2.4 12H4M20 12h1.6M4.9 19.1 6 18M18 6l1.1-1.1"/>'
                : '<path d="M20 14.6A8.6 8.6 0 1 1 9.4 4a7.1 7.1 0 0 0 10.6 10.6Z"/>';
    }

    document.getElementById('tema').addEventListener('click', function () {
        var nuevo = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', nuevo);
        try { localStorage.setItem('monitor-tema', nuevo); } catch (e) { /* ignorado */ }
        pintarIconoTema();
    });

    pintarIconoTema();

    /* ── Ver la contraseña ────────────────────────────────────────────── */

    var OJO = {
        /* Ojo abierto: la contraseña está oculta y el botón la muestra. */
        ver: '<path d="M2.6 12S6.1 5.6 12 5.6 21.4 12 21.4 12 17.9 18.4 12 18.4 2.6 12 2.6 12Z"/><circle cx="12" cy="12" r="3.1"/>',
        /* Ojo tachado: la contraseña está a la vista y el botón la esconde. */
        ocultar: '<path d="M10.7 6.2A9 9 0 0 1 12 6.1c5.9 0 9.4 5.9 9.4 5.9a16.4 16.4 0 0 1-3.2 3.8"/>'
            + '<path d="M6.5 8.2A16.3 16.3 0 0 0 2.6 12S6.1 17.9 12 17.9a8.9 8.9 0 0 0 3.3-.6"/>'
            + '<path d="M10 10a3 3 0 0 0 4.2 4.2"/><path d="M3.6 3.6 20.4 20.4"/>'
    };

    var campo = document.getElementById('pass');
    var boton = document.getElementById('ver');
    var icono = document.getElementById('icono-ojo');

    function pintarOjo() {
        var visible = campo.type === 'text';
        icono.innerHTML = visible ? OJO.ocultar : OJO.ver;
        boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        var etiqueta = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
        boton.setAttribute('aria-label', etiqueta);
        boton.setAttribute('title', etiqueta);
    }

    boton.addEventListener('click', function () {
        /* Se devuelve el foco al campo y el cursor al final: si no, revisar la
           contraseña obliga a volver a hacer clic para seguir escribiendo. */
        var pos = campo.selectionStart;
        campo.type = campo.type === 'password' ? 'text' : 'password';
        pintarOjo();
        campo.focus();
        try { campo.setSelectionRange(pos, pos); } catch (e) { /* algunos navegadores no dejan */ }
    });

    pintarOjo();
</script>

</body>

</html>
