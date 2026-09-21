<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permisos.php';

/**
 * Registro de módulos. Cada módulo vive en modules/<slug>/ y se anuncia con un
 * archivo modulo.php que devuelve su metadata, así que dar de alta uno nuevo es
 * soltar la carpeta: nada de esto se edita.
 *
 * Metadata esperada:
 *   slug      string  identificador corto (debe coincidir con la carpeta)
 *   nombre    string  etiqueta para el menú
 *   url       string  ruta de entrada relativa a la raíz del proyecto
 *   usuarios  array   logins con acceso; [] o ausente = todos los autenticados
 *   orden     int     posición en el rail; sin él se va al final
 */

/**
 * Todos los módulos instalados, independientemente de permisos.
 *
 * @return array<string,array<string,mixed>>
 */
function modulosRegistrados(): array
{
    static $modulos = null;

    if ($modulos !== null) {
        return $modulos;
    }

    $modulos = [];

    foreach (glob(__DIR__ . '/../modules/*/modulo.php') ?: [] as $archivo) {
        $definicion = require $archivo;

        if (!is_array($definicion) || !isset($definicion['slug'], $definicion['nombre'], $definicion['url'])) {
            continue;
        }

        $definicion['usuarios'] ??= [];
        $definicion['orden'] ??= 999;
        $modulos[(string) $definicion['slug']] = $definicion;
    }

    // glob() devuelve las carpetas en orden alfabético, que no es el orden en
    // que se leen las pantallas: primero el avance, al final las herramientas.
    uasort($modulos, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

    return $modulos;
}

/**
 * ¿El usuario en sesión puede entrar a este módulo?
 *
 * Manda lo que le hayan configurado en el módulo Permisos (config/permisos.json).
 * Si no tiene nada configurado se aplica lo de siempre: el arreglo 'usuarios' de
 * su modulo.php, donde vacío significa "cualquiera que haya iniciado sesión".
 *
 * Ese respaldo es lo que hizo que estrenar los permisos no moviera a nadie de
 * sitio: mientras el archivo esté vacío, el sistema se comporta igual que antes.
 */
function puedeVerModulo(string $slug): bool
{
    $modulo = modulosRegistrados()[$slug] ?? null;
    $usuario = usuarioActual();

    if ($modulo === null || $usuario === null) {
        return false;
    }

    if (permisosGobernados($slug)) {
        $concedidos = permisosDe($usuario);

        if ($concedidos !== null) {
            return in_array($slug, $concedidos, true);
        }
    }

    if ($modulo['usuarios'] === []) {
        return true;
    }

    return usuarioEsAlguno(...$modulo['usuarios']);
}

/**
 * Los módulos que este usuario puede ver, sin depender de que sea el de la
 * sesión. Lo usa la matriz del módulo Permisos para pintar el estado actual de
 * todos los usuarios, incluido el de quien no ha configurado nada.
 *
 * @return list<string>
 */
function modulosDeUsuario(string $usuario): array
{
    $concedidos = permisosDe($usuario);
    $salida = [];

    foreach (modulosRegistrados() as $slug => $modulo) {
        $slug = (string) $slug;

        if (permisosGobernados($slug) && $concedidos !== null) {
            if (in_array($slug, $concedidos, true)) {
                $salida[] = $slug;
            }
            continue;
        }

        if ($modulo['usuarios'] === []) {
            $salida[] = $slug;
            continue;
        }

        foreach ($modulo['usuarios'] as $permitido) {
            if (strcasecmp($usuario, (string) $permitido) === 0) {
                $salida[] = $slug;
                break;
            }
        }
    }

    return $salida;
}

/**
 * Módulos que el usuario en sesión puede ver, para pintar el menú.
 *
 * @return array<string,array<string,mixed>>
 */
function modulosVisibles(): array
{
    return array_filter(
        modulosRegistrados(),
        static fn (array $modulo): bool => puedeVerModulo((string) $modulo['slug'])
    );
}

/**
 * Exige acceso al módulo o corta la ejecución. Es el control real: ocultar el
 * enlace en el menú no es una restricción, solo evita mostrarlo.
 *
 * $comoJson hace que los endpoints ajax respondan JSON en vez de HTML.
 */
function requireModulo(string $slug, bool $comoJson = false): void
{
    if (!isset($_SESSION['usuario'])) {
        if ($comoJson) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sesión no iniciada.']);
            exit;
        }

        header('Location: ../../index.php');
        exit;
    }

    if (puedeVerModulo($slug)) {
        return;
    }

    http_response_code(403);

    if ($comoJson) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No tienes acceso a este módulo.']);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Sin acceso</title>'
        . '<p style="font-family:system-ui;padding:2rem">No tienes acceso a este módulo.</p>';
    exit;
}
