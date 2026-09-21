<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use App\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = trim($_POST['user'] ?? '');
$password = $_POST['pass'] ?? '';

if ($username === '' || $password === '') {
    header('Location: index.php?error=1');
    exit;
}

try {
    $db = Database::getInstance();
    $rows = $db->query(
        'SELECT user, nombre, password FROM users WHERE user = ?',
        [$username]
    );

} catch (\Throwable $e) {
    header('Location: index.php?error=1');
    exit;
}

$user = $rows[0] ?? null;

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['usuario'] = $user['nombre'];
    // El login, aparte del nombre para mostrar: es el identificador estable
    // contra el que se resuelven los permisos de módulos.
    $_SESSION['user'] = $user['user'];
    header('Location: home.php');
    exit;
}

header('Location: index.php?error=1');
exit;
