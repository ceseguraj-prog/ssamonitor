<?php

declare(strict_types=1);

/**
 * Quién entra a qué módulo, por usuario.
 *
 * Los permisos viven en un archivo JSON del proyecto y NO en la base: `catalogos`
 * es de solo lectura para este sistema y aquí no se crea ninguna tabla. El
 * archivo se llama config/permisos.json, está git-ignorado igual que config.php
 * y lo escribe únicamente el módulo Permisos.
 *
 * Forma del archivo:
 *
 *   {
 *     "version": 1,
 *     "usuarios": {
 *       "u.damian": ["resumen", "productos", "tg7"],
 *       "j.tavira": []
 *     }
 *   }
 *
 * Hay tres estados por usuario y la diferencia entre los dos últimos importa:
 *
 *   - sin entrada   -> manda el arreglo 'usuarios' de su modulo.php, que es el
 *                      comportamiento de siempre. Así, el día que esto se
 *                      estrenó, nadie ganó ni perdió acceso.
 *   - lista con slugs -> exactamente esos módulos.
 *   - lista vacía   -> ningún módulo. NO es lo mismo que no tener entrada: es
 *                      que alguien le quitó todo a propósito.
 */

require_once __DIR__ . '/auth.php';

/** Módulo que administra esto mismo. Ver permisosGobernados(). */
const PERMISOS_MODULO = 'permisos';

function permisosRuta(): string
{
    return __DIR__ . '/../config/permisos.json';
}

/**
 * Contenido del archivo, cacheado por petición.
 *
 * Un archivo ausente, ilegible o corrupto devuelve el mapa vacío en vez de
 * reventar: eso deja a todo el mundo con los permisos por omisión de sus
 * modulo.php, que es el estado seguro. Un JSON roto no debe tumbar el sitio.
 *
 * @return array<string,list<string>>
 */
function permisosTodos(bool $recargar = false): array
{
    static $cache = null;

    if ($cache !== null && !$recargar) {
        return $cache;
    }

    $cache = [];
    $ruta = permisosRuta();

    if (!is_file($ruta) || !is_readable($ruta)) {
        return $cache;
    }

    $datos = json_decode((string) file_get_contents($ruta), true);

    if (!is_array($datos) || !isset($datos['usuarios']) || !is_array($datos['usuarios'])) {
        return $cache;
    }

    foreach ($datos['usuarios'] as $usuario => $modulos) {
        if (!is_string($usuario) || $usuario === '' || !is_array($modulos)) {
            continue;
        }

        // Las claves se guardan tal cual vienen de la columna `user`, pero se
        // indexan en minúsculas: el login se compara sin distinguir mayúsculas
        // en usuarioEsAlguno() y aquí tiene que dar lo mismo.
        $cache[mb_strtolower($usuario)] = array_values(array_filter(
            array_map('strval', $modulos),
            static fn (string $slug): bool => $slug !== ''
        ));
    }

    return $cache;
}

/**
 * Módulos concedidos a un usuario, o null si no tiene entrada y por lo tanto
 * manda lo que diga cada modulo.php.
 *
 * @return list<string>|null
 */
function permisosDe(string $usuario): ?array
{
    return permisosTodos()[mb_strtolower($usuario)] ?? null;
}

/** ¿Este usuario ya tiene permisos configurados a mano? */
function permisosConfigurado(string $usuario): bool
{
    return permisosDe($usuario) !== null;
}

/**
 * ¿El acceso a este módulo lo decide el archivo?
 *
 * El módulo Permisos queda fuera a propósito: es la única puerta para volver a
 * conceder nada. Si se pudiera quitar desde la propia pantalla, bastaría un
 * clic en la casilla equivocada para que nadie pudiera volver a entrar, y
 * recuperarlo exigiría editar el JSON a mano en el servidor. Su acceso lo sigue
 * mandando el arreglo 'usuarios' de modules/permisos/modulo.php.
 */
function permisosGobernados(string $slug): bool
{
    return $slug !== PERMISOS_MODULO;
}

/**
 * Guarda la lista de módulos de un usuario.
 *
 * Se reescribe el archivo entero bajo bloqueo y con reemplazo atómico: se
 * escribe a un temporal y se renombra encima. Así nadie llega a leer un JSON a
 * medio escribir, ni siquiera si el proceso muere a mitad.
 *
 * @param list<string> $modulos slugs; el arreglo vacío es válido y significa
 *                              "ningún módulo".
 * @throws RuntimeException si no se puede escribir.
 */
function permisosGuardar(string $usuario, array $modulos): void
{
    permisosEscribir($usuario, array_values(array_unique(array_map('strval', $modulos))));
}

/**
 * Borra la entrada del usuario: vuelve a los permisos por omisión de cada
 * modulo.php. No es lo mismo que guardarle una lista vacía.
 */
function permisosRestablecer(string $usuario): void
{
    permisosEscribir($usuario, null);
}

/**
 * @param list<string>|null $modulos null borra la entrada.
 * @throws RuntimeException
 */
function permisosEscribir(string $usuario, ?array $modulos): void
{
    $ruta = permisosRuta();
    $directorio = dirname($ruta);

    if (!is_dir($directorio) || !is_writable($directorio)) {
        throw new RuntimeException(
            "La carpeta config/ no acepta escritura. El servidor web necesita permiso "
            . "para escribir ahí, que es donde vive permisos.json."
        );
    }

    // El candado va sobre un archivo aparte porque el JSON se reemplaza por
    // rename(): bloquear el que se va a sustituir no sirve de nada.
    $candado = fopen($ruta . '.lock', 'c');

    if ($candado === false) {
        throw new RuntimeException('No se pudo preparar el candado de config/permisos.json.');
    }

    try {
        if (!flock($candado, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear config/permisos.json.');
        }

        // Se relee con el candado puesto, no del caché: entre la carga de la
        // página y el guardado alguien más pudo haber cambiado otra fila.
        $usuarios = permisosTodosCrudo();

        if ($modulos === null) {
            unset($usuarios[$usuario]);

            // Por si la entrada se había guardado con otra caja.
            foreach (array_keys($usuarios) as $clave) {
                if (mb_strtolower((string) $clave) === mb_strtolower($usuario)) {
                    unset($usuarios[$clave]);
                }
            }
        } else {
            $usuarios[$usuario] = $modulos;
        }

        ksort($usuarios);

        $json = json_encode(
            ['version' => 1, 'usuarios' => $usuarios],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException('No se pudo serializar los permisos.');
        }

        $temporal = $ruta . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temporal, $json . "\n") === false || !@rename($temporal, $ruta)) {
            @unlink($temporal);
            throw new RuntimeException('No se pudo escribir config/permisos.json.');
        }
    } finally {
        flock($candado, LOCK_UN);
        fclose($candado);
    }

    permisosTodos(true); // el caché de esta petición quedó viejo
}

/**
 * El mapa tal cual está en disco, con las claves sin normalizar. Solo lo usa la
 * escritura, que tiene que conservar cómo se escribió cada login.
 *
 * @return array<string,list<string>>
 */
function permisosTodosCrudo(): array
{
    $ruta = permisosRuta();

    if (!is_file($ruta)) {
        return [];
    }

    $datos = json_decode((string) @file_get_contents($ruta), true);

    if (!is_array($datos) || !isset($datos['usuarios']) || !is_array($datos['usuarios'])) {
        return [];
    }

    $salida = [];
    foreach ($datos['usuarios'] as $usuario => $modulos) {
        if (is_string($usuario) && $usuario !== '' && is_array($modulos)) {
            $salida[$usuario] = array_values(array_map('strval', $modulos));
        }
    }

    return $salida;
}
