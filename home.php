<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
</head>

<body>
    <header class="p-3 text-bg-dark">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="text-white h5 mb-0">Monitor</span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white">Hola, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                <a href="logout.php" class="btn btn-warning btn-sm">Salir</a>
            </div>
        </div>
    </header>

    <main class="container mt-4">
        <div class="card">
            <div class="card-body">
                <h1 class="h4">Bienvenido</h1>
                <p class="text-muted">Este es el esqueleto base del proyecto. Aquí se irán agregando los módulos.</p>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
