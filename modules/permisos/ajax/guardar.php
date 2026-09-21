<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';

requireModulo('permisos', true);

use App\UsuariosRepository;

header('Content-Type: application/json; charset=utf-8');

/** Responde y termina. */
function responder(array $cuerpo, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido.'], 405);
}

$usuario = trim((string) ($_POST['usuario'] ?? ''));
$accion = (string) ($_POST['accion'] ?? 'guardar');

if ($usuario === '') {
    responder(['error' => 'Falta el usuario.'], 400);
}

// El login se cruza contra `users`: así no se guardan permisos de alguien que
// no existe, y el archivo no se llena de entradas muertas por un typo.
if (!UsuariosRepository::existe($usuario)) {
    responder(['error' => "El usuario $usuario no existe en la tabla users."], 400);
}

if ($accion === 'restablecer') {
    try {
        permisosRestablecer($usuario);
    } catch (\Throwable $e) {
        responder(['error' => $e->getMessage()], 500);
    }

    responder([
        'usuario' => $usuario,
        'configurado' => false,
        'modulos' => modulosDeUsuario($usuario),
    ]);
}

/* Los slugs llegan del cliente, así que se cruzan contra los módulos instalados
   y se descarta el que no se administra aquí. Sin esto, un POST a mano podría
   meter basura en el JSON o concederse el propio módulo de Permisos. */
$pedidos = (array) ($_POST['modulos'] ?? []);
$validos = [];

foreach (modulosRegistrados() as $slug => $modulo) {
    $slug = (string) $slug;

    if (permisosGobernados($slug) && in_array($slug, $pedidos, true)) {
        $validos[] = $slug;
    }
}

try {
    permisosGuardar($usuario, $validos);
} catch (\Throwable $e) {
    responder(['error' => $e->getMessage()], 500);
}

responder([
    'usuario' => $usuario,
    'configurado' => true,
    'modulos' => modulosDeUsuario($usuario),
]);
