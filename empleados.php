<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$paginaActual = 'empleados';
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NOMBRE) ?> — Empleados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
</head>

<body>
    <?php require __DIR__ . '/includes/nav.php'; ?>

    <main class="container mt-4">
        <h1 class="h4 mb-3">Empleados</h1>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaEmpleados" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Apellido paterno</th>
                                <th>Apellido materno</th>
                                <th>RFC</th>
                                <th>CURP</th>
                                <th>NSS</th>
                                <th>Email</th>
                                <th>C.P.</th>
                            </tr>
                            <tr>
                                <th><input type="text" class="form-control form-control-sm" placeholder="Nombre"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="Ap. paterno"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="Ap. materno"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="RFC"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="CURP"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="NSS"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="Email"></th>
                                <th><input type="text" class="form-control form-control-sm" placeholder="C.P."></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const tabla = $('#tablaEmpleados').DataTable({
            serverSide: true,
            processing: true,
            pageLength: 200,
            lengthChange: false,
            searching: true,
            orderCellsTop: true,
            ajax: {
                url: 'ajax/empleados.php'
            },
            columns: [
                { data: 0 },
                { data: 1 },
                { data: 2 },
                { data: 3 },
                { data: 4 },
                { data: 5 },
                { data: 6 },
                { data: 7 }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json'
            }
        });

        $('#tablaEmpleados thead tr:eq(1) th').each(function (i) {
            $('input', this).on('keyup change', function () {
                if (tabla.column(i).search() !== this.value) {
                    tabla.column(i).search(this.value).draw();
                }
            });
        });
    </script>
</body>

</html>
