<?php

declare(strict_types=1);

/**
 * Lector mínimo de .env. Evita meter secretos en config.php (que se comparte
 * por otros medios) y sobre todo evita que acaben en el repositorio: .env está
 * en .gitignore y solo se versiona .env.example con las claves vacías.
 *
 * Las variables reales del entorno (Apache SetEnv, variables del sistema)
 * tienen prioridad sobre el archivo, para que un despliegue pueda inyectarlas
 * sin tocar disco.
 */

/**
 * Lee y cachea el .env del proyecto. Es idempotente: llamarla varias veces no
 * vuelve a tocar el disco.
 *
 * @return array<string,string>
 */
function cargarEnv(?string $ruta = null): array
{
    static $valores = null;

    if ($valores !== null) {
        return $valores;
    }

    $valores = [];
    $ruta ??= __DIR__ . '/../.env';

    if (!is_file($ruta) || !is_readable($ruta)) {
        return $valores;
    }

    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        $partes = explode('=', $linea, 2);
        if (count($partes) !== 2) {
            continue;
        }

        $clave = trim($partes[0]);
        $valor = trim($partes[1]);

        // Permite VALOR, "VALOR" y 'VALOR'; las comillas se quitan solo si
        // envuelven todo el valor, para no romper una clave que las contenga.
        $largo = strlen($valor);
        if ($largo >= 2) {
            $primero = $valor[0];
            if (($primero === '"' || $primero === "'") && $valor[$largo - 1] === $primero) {
                $valor = substr($valor, 1, -1);
            }
        }

        if ($clave !== '') {
            $valores[$clave] = $valor;
        }
    }

    return $valores;
}

/**
 * Valor de una variable de entorno, buscando primero en el entorno real y
 * después en el .env. Devuelve $porDefecto si no existe o está vacía.
 */
function env(string $clave, ?string $porDefecto = null): ?string
{
    $delSistema = getenv($clave);
    if (is_string($delSistema) && $delSistema !== '') {
        return $delSistema;
    }

    $valor = cargarEnv()[$clave] ?? null;

    return $valor !== null && $valor !== '' ? $valor : $porDefecto;
}

/**
 * Igual que env(), pero falla en vez de devolver null. Para secretos sin los
 * cuales la función no puede operar: mejor un error claro que una llamada a la
 * API con la clave vacía.
 */
function envRequerida(string $clave): string
{
    $valor = env($clave);

    if ($valor === null) {
        throw new RuntimeException(
            "Falta la variable $clave. Agrégala al archivo .env en la raíz del proyecto "
            . '(puedes partir de .env.example).'
        );
    }

    return $valor;
}
