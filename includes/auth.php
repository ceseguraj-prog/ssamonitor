<?php

declare(strict_types=1);

/**
 * Corta la ejecución y redirige al login si no hay sesión iniciada.
 * Llamar después de includes/bootstrap.php en toda página protegida.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Login del usuario en sesión (columna `user` de la tabla users), o null si
 * no hay sesión. Es el identificador estable para permisos; $_SESSION['usuario']
 * guarda el nombre para mostrar, que puede repetirse o cambiar.
 */
function usuarioActual(): ?string
{
    $usuario = $_SESSION['user'] ?? null;

    return is_string($usuario) && $usuario !== '' ? $usuario : null;
}

/**
 * ¿El usuario en sesión es alguno de los indicados? La comparación ignora
 * mayúsculas para que no dependa de cómo se haya tecleado el login.
 */
function usuarioEsAlguno(string ...$permitidos): bool
{
    $actual = usuarioActual();

    if ($actual === null) {
        return false;
    }

    foreach ($permitidos as $permitido) {
        if (strcasecmp($actual, $permitido) === 0) {
            return true;
        }
    }

    return false;
}
