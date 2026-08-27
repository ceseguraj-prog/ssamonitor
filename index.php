<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitor — Iniciar sesión</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="css/app.css" rel="stylesheet">
</head>

<body class="text-center">

    <main class="form-signin text-center shadow-lg p-4 mb-5 mx-auto">
        <form id="formLogin" action="login.php" method="POST">
            <h1 class="h3 mb-3 fw-normal">Monitor</h1>

            <div class="form-floating mb-2">
                <input type="text" class="form-control" id="user" name="user" placeholder="Usuario" required>
                <label for="user">Usuario</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="pass" name="pass" placeholder="Contraseña" required>
                <label for="pass">Contraseña</label>
            </div>
            <button class="w-100 btn btn-lg btn-primary" type="submit">Entrar</button>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        <?php if (isset($_GET['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Credenciales incorrectas. Por favor, intenta nuevamente.'
        });
        <?php endif; ?>
    </script>

</body>

</html>
