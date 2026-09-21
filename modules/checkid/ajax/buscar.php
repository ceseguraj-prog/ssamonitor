<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/modulos.php';
require_once __DIR__ . '/../CheckIdClient.php';

requireModulo('checkid', true);

use App\Modules\CheckId\CheckIdClient;

header('Content-Type: application/json; charset=utf-8');

// CheckID consulta SAT, RENAPO e IMSS en vivo y puede tardar más de un minuto.
// Sin esto, el max_execution_time por defecto (30 s) mataría a PHP antes de que
// llegue la respuesta y el usuario vería un error en una consulta que iba bien
// (y que de todos modos ya se le cobró).
@set_time_limit((int) (env('CHECKID_TIMEOUT') ?? 90) + 30);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$termino = strtoupper(trim((string) ($_POST['termino'] ?? '')));

// Las secciones llegan como lista de checkboxes; sin ninguna no hay consulta
// que hacer y se evita gastar un cargo de la API.
$secciones = $_POST['secciones'] ?? [];
$secciones = is_array($secciones) ? array_map('strval', $secciones) : [];

if ($termino === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Escribe un RFC o una CURP.']);
    exit;
}

try {
    $resultado = CheckIdClient::buscar($termino, $secciones ?: null);

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // El mensaje de la excepción es seguro de mostrar: son validaciones o
    // fallos de red, nunca incluye la clave.
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
