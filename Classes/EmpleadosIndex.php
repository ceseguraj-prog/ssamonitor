<?php

namespace App;

/**
 * Índice local de empleados para la búsqueda por nombre / RFC / CURP.
 *
 * La tabla empleados de BPM es MyISAM, tiene ~55 mil filas y solo índices en
 * rfc, curp y pass. Cualquier búsqueda por nombre, o por RFC parcial, obliga
 * a un escaneo completo con bloqueo de tabla: medido en el servidor, un
 * "rfc LIKE '%...%'" tarda ~1.3 s y un LIKE sobre los nombres ~54 ms. Como la
 * vista consulta en cada tecleo, ese costo caía entero sobre la base y no
 * podemos crear índices ahí (bpm_web es de solo lectura).
 *
 * Aquí se invierte el reparto: una sola consulta cada TTL vuelca la tabla a un
 * archivo plano en cache/ y todas las búsquedas se resuelven en PHP contra ese
 * archivo. La base pasa de una consulta por pulsación a una cada varias horas
 * (~487 ms, menos de lo que costaba un solo tecleo).
 *
 * Formato del snapshot: una línea por empleado, campos separados por TAB.
 * El primer campo es la clave de búsqueda ya normalizada (mayúsculas, sin
 * acentos, sin puntuación) y los siguientes son las columnas de COLUMNAS.
 */
class EmpleadosIndex
{
    /** Columnas que se guardan en el snapshot, en orden. */
    private const COLUMNAS = ['clave', 'nombre', 'apaterno', 'amaterno', 'rfc', 'curp', 'nss', 'email', 'cp'];

    /** Segundos de vida del snapshot antes de reconstruirlo. */
    private const TTL = 21600; // 6 horas

    /** Filas por consulta al construir, para no cargar 55k filas de golpe. */
    private const TAMANO_LOTE = 10000;

    /** Tope de resultados devueltos al navegador. */
    public const MAX_RESULTADOS = 50;

    /**
     * La tabla trae ~55,600 filas pero solo ~30,000 RFC distintos: un mismo
     * empleado aparece una vez por cada clave de plaza. La vista de detalle
     * consulta por RFC (detalle_nomina WHERE rfc = ?), así que esas filas
     * repetidas son indistinguibles en pantalla; se conserva una por RFC y se
     * guarda cuántas plazas tiene.
     */
    private const AGRUPAR_POR_RFC = true;

    private const ACENTOS = [
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'Ñ' => 'N', 'Ç' => 'C',
    ];

    /** Líneas del snapshot ya leídas en esta petición. */
    private static ?array $lineas = null;

    private static function rutaCache(): string
    {
        return __DIR__ . '/../cache';
    }

    private static function rutaSnapshot(): string
    {
        return self::rutaCache() . '/empleados.tsv';
    }

    /**
     * Normaliza texto para comparar: mayúsculas, sin acentos (Ñ→N, para que
     * "MUÑOZ" y "MUNOZ" se encuentren igual) y sin puntuación.
     */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = strtr($texto, self::ACENTOS);
        $texto = preg_replace('/[^A-Z0-9 ]+/', ' ', $texto);

        return trim(preg_replace('/ +/', ' ', $texto));
    }

    /**
     * Busca por nombre, apellidos, RFC o CURP. Los términos se piden todos
     * (AND) y en cualquier orden, así que "garcia maria" encuentra a
     * "MARIA ELENA GARCIA LOPEZ" igual que "maria garcia".
     *
     * @return array{rows: list<array<string,mixed>>, total: int, mostrados: int, generado: int}
     */
    public static function buscar(string $consulta, int $limite = self::MAX_RESULTADOS): array
    {
        $lineas = self::lineas();
        $terminos = array_filter(explode(' ', self::normalizar($consulta)), 'strlen');

        if (!$terminos) {
            $encontradas = array_slice($lineas, 0, $limite);
            $total = count($lineas);
        } else {
            // Los lookahead se limitan a [^\t]* para que la coincidencia solo
            // ocurra dentro de la clave de búsqueda y no en el resto de la
            // línea. preg_grep filtra las 55k líneas dentro de PCRE, en C.
            $patron = '/^';
            foreach ($terminos as $termino) {
                $patron .= '(?=[^\t]*' . preg_quote($termino, '/') . ')';
            }
            $patron .= '/';

            $encontradas = preg_grep($patron, $lineas);
            $total = count($encontradas);
            $encontradas = self::ordenar($encontradas, $terminos, $limite);
        }

        return [
            'rows' => array_map([self::class, 'aFila'], array_values($encontradas)),
            'total' => $total,
            'mostrados' => count($encontradas),
            'generado' => self::generadoEn(),
        ];
    }

    /**
     * Ordena las coincidencias por qué tan "al principio" caen los términos:
     * primero quien empieza con lo tecleado, luego quien lo tiene al inicio de
     * alguno de sus nombres o apellidos, y al final las coincidencias sueltas.
     * Solo se aplica al subconjunto ya filtrado, que es pequeño.
     */
    private static function ordenar(array $lineas, array $terminos, int $limite): array
    {
        $consulta = implode(' ', $terminos);
        $puntuadas = [];

        foreach ($lineas as $linea) {
            $clave = substr($linea, 0, strpos($linea, "\t"));

            if (strpos($clave, $consulta) === 0) {
                $puntos = 0;
            } else {
                $puntos = 1;
                foreach ($terminos as $termino) {
                    // ¿El término cae al inicio de alguna palabra de la clave?
                    if (strpos($clave, $termino) !== 0 && strpos($clave, ' ' . $termino) === false) {
                        $puntos = 2;
                        break;
                    }
                }
            }

            $puntuadas[] = [$puntos, $clave, $linea];
        }

        usort($puntuadas, static function (array $a, array $b): int {
            return $a[0] <=> $b[0] ?: strcmp($a[1], $b[1]);
        });

        return array_column(array_slice($puntuadas, 0, $limite), 2);
    }

    /**
     * Convierte una línea del snapshot en la fila asociativa que espera el
     * navegador.
     */
    private static function aFila(string $linea): array
    {
        $campos = explode("\t", $linea);
        array_shift($campos); // la clave de búsqueda no se manda al navegador

        $fila = [];
        foreach (self::COLUMNAS as $indice => $columna) {
            $fila[$columna] = $campos[$indice] ?? '';
        }
        $fila['plazas'] = (int) ($campos[count(self::COLUMNAS)] ?? 1);

        return $fila;
    }

    public static function generadoEn(): int
    {
        $ruta = self::rutaSnapshot();

        return is_file($ruta) ? (int) filemtime($ruta) : 0;
    }

    /**
     * Devuelve las líneas del snapshot, reconstruyéndolo si venció el TTL.
     */
    private static function lineas(): array
    {
        if (self::$lineas !== null) {
            return self::$lineas;
        }

        self::asegurarSnapshot();

        $contenido = @file_get_contents(self::rutaSnapshot());
        if ($contenido === false || $contenido === '') {
            return self::$lineas = [];
        }

        return self::$lineas = explode("\n", rtrim($contenido, "\n"));
    }

    /**
     * Garantiza que exista un snapshot utilizable. Si venció, lo reconstruye;
     * si otra petición ya lo está reconstruyendo, sigue usando el vigente en
     * lugar de esperar (o de lanzar otra consulta en paralelo).
     */
    private static function asegurarSnapshot(): void
    {
        $ruta = self::rutaSnapshot();
        $existe = is_file($ruta) && filesize($ruta) > 0;

        if ($existe && (time() - filemtime($ruta)) < self::TTL) {
            return;
        }

        if (!is_dir(self::rutaCache())) {
            @mkdir(self::rutaCache(), 0775, true);
        }

        $candado = @fopen(self::rutaCache() . '/empleados.lock', 'c');
        if ($candado === false) {
            return; // sin candado no arriesgamos una estampida de consultas
        }

        // Si ya hay otra petición reconstruyendo: con snapshot vigente se sigue
        // usando el viejo; sin snapshot no queda más que esperar su turno.
        $bloqueo = flock($candado, $existe ? LOCK_EX | LOCK_NB : LOCK_EX);

        if ($bloqueo) {
            clearstatcache(true, $ruta);
            $vigente = is_file($ruta) && filesize($ruta) > 0 && (time() - filemtime($ruta)) < self::TTL;

            if (!$vigente) {
                try {
                    self::construir();
                } catch (\Throwable $e) {
                    // Si BPM falla, se sigue sirviendo el snapshot anterior.
                    if (!$existe) {
                        flock($candado, LOCK_UN);
                        fclose($candado);
                        throw $e;
                    }
                }
            }

            flock($candado, LOCK_UN);
        }

        fclose($candado);
    }

    /**
     * Vuelca empleados a un archivo temporal y lo pone en su sitio de un solo
     * movimiento, para que ninguna petición llegue a leer un archivo a medias.
     * Se lee por lotes ordenados por la llave primaria (rfc, clave) para no
     * tener las 55 mil filas en memoria a la vez.
     */
    public static function construir(): int
    {
        if (!is_dir(self::rutaCache())) {
            @mkdir(self::rutaCache(), 0775, true);
        }

        $db = Database::get('bpm');
        $columnas = implode(', ', self::COLUMNAS);
        $temporal = self::rutaSnapshot() . '.' . getmypid() . '.tmp';

        $handle = fopen($temporal, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo escribir el índice de empleados en ' . self::rutaCache());
        }

        $vistos = [];
        $escritas = 0;
        $offset = 0;

        try {
            do {
                $filas = $db->query(
                    "SELECT $columnas FROM empleados ORDER BY rfc, clave LIMIT ? OFFSET ?",
                    [self::TAMANO_LOTE, $offset]
                );

                foreach ($filas as $fila) {
                    $rfc = (string) $fila['rfc'];
                    // BPM coteja los RFC sin distinguir mayúsculas, así que el
                    // agrupado usa la misma regla: existe al menos un RFC que
                    // solo difiere en el caso de sus letras.
                    $llave = strtoupper(trim($rfc));

                    if (self::AGRUPAR_POR_RFC) {
                        if (isset($vistos[$llave])) {
                            $vistos[$llave]++;
                            continue;
                        }
                        $vistos[$llave] = 1;
                    }

                    $clave = self::normalizar(implode(' ', [
                        $fila['nombre'], $fila['apaterno'], $fila['amaterno'], $rfc, $fila['curp'],
                    ]));

                    $campos = [$clave];
                    foreach (self::COLUMNAS as $columna) {
                        $campos[] = strtr((string) $fila[$columna], ["\t" => ' ', "\n" => ' ', "\r" => ' ']);
                    }
                    $campos[] = '1'; // se corrige más abajo con el conteo real

                    fwrite($handle, implode("\t", $campos) . "\n");
                    $escritas++;
                }

                $offset += self::TAMANO_LOTE;
            } while (count($filas) === self::TAMANO_LOTE);
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($temporal);
            throw $e;
        }

        fclose($handle);

        if (self::AGRUPAR_POR_RFC) {
            self::escribirPlazas($temporal, $vistos);
        }

        if (!@rename($temporal, self::rutaSnapshot())) {
            @unlink($temporal);
            throw new \RuntimeException('No se pudo publicar el índice de empleados.');
        }

        self::$lineas = null;
        clearstatcache(true, self::rutaSnapshot());

        return $escritas;
    }

    /**
     * Reescribe la última columna con el número real de plazas por RFC, que
     * solo se conoce cuando ya se recorrió toda la tabla.
     *
     * @param array<string,int> $conteos
     */
    private static function escribirPlazas(string $ruta, array $conteos): void
    {
        $indiceRfc = array_search('rfc', self::COLUMNAS, true) + 1; // +1 por la clave de búsqueda
        $origen = fopen($ruta, 'r');
        $destino = fopen($ruta . '.plazas', 'w');

        while (($linea = fgets($origen)) !== false) {
            $campos = explode("\t", rtrim($linea, "\n"));
            $campos[count(self::COLUMNAS) + 1] = (string) ($conteos[strtoupper(trim($campos[$indiceRfc]))] ?? 1);
            fwrite($destino, implode("\t", $campos) . "\n");
        }

        fclose($origen);
        fclose($destino);
        @unlink($ruta);
        @rename($ruta . '.plazas', $ruta);
    }
}
