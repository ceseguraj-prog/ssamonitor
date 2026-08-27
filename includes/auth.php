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
