<?php

declare(strict_types=1);

namespace App;

/**
 * Descuentos a terceros de una quincena, sacados de las seis tablas de nómina
 * de `catalogos`.
 *
 * Un "tercero" es quien cobra un descuento que no es del trabajador: la
 * aseguradora, el FOVISSSTE, el sindicato. Cada grupo se identifica por los tres
 * primeros dígitos de la clave de concepto (246 comercializadoras, 250
 * potenciación, 255/256/264/265 FOVISSSTE…), y la quincena se cierra entregando
 * dos archivos: el detalle registro por registro y el resumen por grupo.
 *
 * Igual que en ConceptosRepository, cada fila guarda hasta 50 conceptos en
 * columnas `TR{n}TCP` (clave) y `TR{n}IM` (importe), y un slot sin usar trae el
 * centinela '00000'.
 *
 * Sobre el rendimiento, que aquí es el punto: la versión de la que se portó esto
 * tardaba minutos y quedó en segundos por cuatro razones que se conservan.
 *
 *  1. ANIO y QNA son varchar. Comparándolos contra números (ANIO = 2026) MySQL
 *     convierte la columna fila por fila y no puede usar los índices: recorre los
 *     4.6 millones de renglones de `federal`. Contra cadenas ('2026') la misma
 *     consulta baja de ~53 s a ~0.9 s.
 *  2. Las tablas se recorren UNA vez para todos los grupos, no una vez por grupo:
 *     12 consultas en lugar de 108.
 *  3. Solo se piden las columnas TR que de verdad existen en el periodo, que es
 *     lo que mide MAX(TTR).
 *  4. El resumen se acumula en un hash en una sola pasada, en vez de recorrer las
 *     ~20 mil filas del detalle por cada combinación de grupo/UR/rama/tipo/banco.
 *
 * Todo es de solo lectura: esta clase solo ejecuta SELECT.
 */
class TercerosRepository
{
    /**
     * Las seis tablas de nómina. Es whitelist: el nombre se interpola en el SQL
     * (no puede ir como parámetro), así que nunca se toma del cliente.
     */
    private const TABLAS = [
        'federal',
        'homologados',
        'regularizados',
        'formalizados',
        'formalizados2015',
        'formalizados2016',
    ];

    /**
     * Las tablas de formalizados guardan la rama en `ffina`; las demás en
     * `SECCION`. Es la única diferencia de esquema entre las seis.
     */
    private const TABLAS_FFINA = ['formalizados', 'formalizados2015', 'formalizados2016'];

    /** Slots por fila. Las seis tablas tienen exactamente 50, ya verificado. */
    private const SLOTS = 50;

    /** Un slot sin concepto trae este centinela, no cadena vacía ni NULL. */
    private const SLOT_VACIO = '00000';

    /**
     * Unidad responsable que se excluye siempre, igual que el reporte original.
     * Se compara como cadena: UR es varchar(3) y contra un entero MySQL castea la
     * columna de cada fila, lo que anula el índice.
     */
    public const UR_EXCLUIDA = '610';

    /**
     * Grupos de terceros de fábrica y los prefijos de concepto que los forman.
     * El orden de este arreglo es el orden en que salen los grupos en los dos
     * archivos, así que no es cosmético.
     *
     * Los prefijos son de tres caracteres a propósito: el concepto completo son
     * cinco (26403, 264SS) y agruparlos por subclave multiplicaría los renglones
     * del resumen sin que nadie los lea así.
     *
     * Esto NO es la lista final: encima se aplica config/terceros-grupos.json,
     * que la pantalla escribe. Ver catalogo().
     */
    private const GRUPOS = [
        'COMERCIALIZADORAS_46'      => ['246'],
        'POTENCIACION_50'           => ['250'],
        'AXA_SEGUROS_74'            => ['274'],
        'SEGURO_RETIRO_77'          => ['277'],
        'SEG_INDV_METLIFE_51_57'    => ['251', '257'],
        'FOVISSTE_55_56_64_65'      => ['255', '256', '264', '265'],
        'SINAISSA_267'              => ['267'],
        'AUXILIO_POR_DEFUNCION_270' => ['270'],
        'CUOTAS_SINDICALES_258'     => ['258'],
        // FEGAC es el 221 (221FA y 22121). No te fíes de cat_conceptos, que
        // describe el concepto 21 como FONAC: ese catálogo está viejo y no
        // refleja cómo se usa aquí. Ver el README.
        'FEGAC_221'                 => ['221'],
    ];

    /**
     * Clave de institución bancaria (TIBA) al nombre que va en el archivo.
     * Cualquier otra clave sale con banco vacío, que es lo que hacía el CASE del
     * SQL original al no encontrar coincidencia.
     */
    private const BANCOS = ['02' => 'HSBC', '05' => 'SANTANDER', '06' => 'BANAMEX'];

    /**
     * Orden en que salen los valores conocidos en el resumen. Es SOLO orden: lo
     * que no esté aquí igual se suma y se reporta, nada más que al final de su
     * nivel. Esto último importa —en la versión vieja estos arreglos eran filtro,
     * y todo lo que no figurara se descartaba en silencio: en la quincena 17 de
     * 2026 eso dejaba fuera el 60.7% del importe.
     *
     * El 'F03' de UR lleva cero y debería ser 'FO3' con o. Se conserva el error
     * para no alterar el orden de los renglones del archivo, que es justo lo que
     * se pidió respetar; hoy solo hace que los formalizados 2016 salgan al final
     * de su nivel en vez de en la sexta posición. Ver el README del módulo.
     */
    private const ORDEN = [
        'UR'    => ['420', '416', 'HOM', 'REG', 'FOR', 'FO2', 'F03'],
        'RAMA'  => ['ra', 'rm'],
        'TIPO'  => ['11', '22', '77'],
        'BANCO' => ['BANAMEX', 'HSBC', 'SANTANDER'],
    ];

    /** Cabeceras de los dos archivos, tal cual las espera quien los consume. */
    public const CABECERA_DETALLE = 'ANIO,QNA,TIPO,RFC,UR,RAMA,BANCO,GRUPO,CPTO,IMPORTE,SPC';
    public const CABECERA_RESUMEN = 'ANIO,QNA,GRUPO,UR,RAMA,TIPO,BANCO,CPTO,IMPORTE';

    /* ── Catálogo de grupos ─────────────────────────────────────────────── */

    /**
     * Archivo donde la pantalla guarda los grupos. No es la base: `catalogos` es
     * de solo lectura para este sistema. Está git-ignorado, igual que config.php
     * y config/permisos.json, porque es estado del despliegue.
     */
    public static function archivoGrupos(): string
    {
        return __DIR__ . '/../config/terceros-grupos.json';
    }

    /** Largo exacto de un prefijo. El código agrupa por substr($cpto, 0, 3). */
    public const LARGO_PREFIJO = 3;

    /**
     * El catálogo que de verdad se usa: los grupos de fábrica más lo que haya en
     * el archivo.
     *
     * Dos tipos de entrada en el archivo, y la diferencia importa:
     *
     *   - una clave que YA existe en GRUPOS reemplaza sus prefijos, conservando
     *     su sitio en el orden;
     *   - una clave nueva se agrega AL FINAL, después de los de fábrica.
     *
     * Que los nuevos vayan al final no es cosmético: así sus renglones se pegan
     * al final de los dos CSV y no mueven ni un byte de los que ya estaban, que
     * es lo que permite dar de alta un grupo sin romper a quien consume los
     * archivos. Es la misma regla que se siguió al agregar FEGAC a mano.
     *
     * Un archivo ausente o corrupto deja solo los de fábrica: estado seguro.
     *
     * @return array<string,list<string>>
     */
    public static function catalogo(bool $recargar = false): array
    {
        static $cache = null;

        if ($cache !== null && !$recargar) {
            return $cache;
        }

        $cache = self::GRUPOS;

        foreach (self::grupoExtras() as $grupo => $prefijos) {
            $cache[$grupo] = $prefijos; // existente: reemplaza en su sitio. Nuevo: al final.
        }

        return $cache;
    }

    /**
     * Lo que hay en el archivo, ya saneado.
     *
     * @return array<string,list<string>>
     */
    public static function grupoExtras(): array
    {
        $ruta = self::archivoGrupos();

        if (!is_file($ruta) || !is_readable($ruta)) {
            return [];
        }

        $datos = json_decode((string) @file_get_contents($ruta), true);

        if (!is_array($datos) || !isset($datos['grupos']) || !is_array($datos['grupos'])) {
            return [];
        }

        $salida = [];

        foreach ($datos['grupos'] as $grupo => $prefijos) {
            if (!is_string($grupo) || !is_array($prefijos)) {
                continue;
            }

            $grupo = self::normalizarNombre($grupo);
            $limpios = [];

            foreach ($prefijos as $prefijo) {
                $prefijo = self::normalizarPrefijo((string) $prefijo);
                if ($prefijo !== '') {
                    $limpios[$prefijo] = true;
                }
            }

            // strval() obligatorio: como claves de arreglo, '262' se guardó como
            // int 262. Devolverlo así rompería el array_search() estricto de
            // ordenar(), y los conceptos de un grupo del archivo saldrían en el
            // resumen ordenados alfabéticamente en vez de como se capturaron.
            if ($grupo !== '' && $limpios !== []) {
                $salida[$grupo] = array_map('strval', array_keys($limpios));
            }
        }

        return $salida;
    }

    /** ¿Este grupo viene de fábrica (no se puede borrar desde la pantalla)? */
    public static function esDeFabrica(string $grupo): bool
    {
        return isset(self::GRUPOS[$grupo]);
    }

    /** Los prefijos de fábrica de un grupo, para poder volver a ellos. */
    public static function prefijosDeFabrica(string $grupo): ?array
    {
        return self::GRUPOS[$grupo] ?? null;
    }

    public static function normalizarNombre(string $nombre): string
    {
        $nombre = strtoupper(trim($nombre));
        $nombre = preg_replace('/[^A-Z0-9]+/', '_', $nombre) ?? '';

        return trim($nombre, '_');
    }

    public static function normalizarPrefijo(string $prefijo): string
    {
        $prefijo = strtoupper(trim($prefijo));

        return preg_match('/^[0-9A-Z]{' . self::LARGO_PREFIJO . '}$/', $prefijo) === 1 ? $prefijo : '';
    }

    /** @return list<string> */
    public static function grupos(): array
    {
        return array_keys(self::catalogo());
    }

    /** @return list<string> */
    public static function tablas(): array
    {
        return self::TABLAS;
    }

    /**
     * Años con nómina cargada, de mayor a menor.
     *
     * Se filtran los años imposibles, al revés que en Conceptos: aquello es una
     * herramienta de auditoría donde una captura errónea de 2224 puede ser justo
     * lo que se anda buscando, y esto es el cierre de una quincena, donde ese año
     * solo estorba en el selector.
     *
     * @return list<int>
     */
    public static function aniosDisponibles(): array
    {
        $rows = Database::getInstance()->query(
            'SELECT DISTINCT ANIO FROM `federal` ORDER BY ANIO DESC'
        );

        $tope = (int) date('Y') + 1;
        $anios = [];

        foreach ($rows as $row) {
            $anio = (int) $row['ANIO'];

            if ($anio >= 2000 && $anio <= $tope) {
                $anios[] = $anio;
            }
        }

        return $anios;
    }

    /**
     * Da de alta o cambia los prefijos de un grupo.
     *
     * Valida y, si algo no cuadra, lanza con un mensaje que se le puede enseñar
     * tal cual a quien lo escribió. Lo más importante que revisa es que un
     * prefijo no quede en dos grupos: el detalle emite un renglón por cada grupo
     * que reclame el concepto, así que el mismo importe se sumaría dos veces y el
     * total del cierre saldría inflado sin que nada lo delate.
     *
     * @param list<string> $prefijos
     * @throws \RuntimeException
     */
    public static function guardarGrupo(string $nombre, array $prefijos): string
    {
        $grupo = self::normalizarNombre($nombre);

        if ($grupo === '') {
            throw new \RuntimeException('El nombre del grupo no puede ir vacío.');
        }

        if (mb_strlen($grupo) > 60) {
            throw new \RuntimeException('El nombre del grupo no puede pasar de 60 caracteres.');
        }

        $limpios = [];
        $malos = [];

        foreach ($prefijos as $prefijo) {
            $crudo = trim((string) $prefijo);

            if ($crudo === '') {
                continue;
            }

            $normal = self::normalizarPrefijo($crudo);

            if ($normal === '') {
                $malos[] = $crudo;
            } else {
                $limpios[$normal] = true;
            }
        }

        if ($malos !== []) {
            throw new \RuntimeException(
                'Un prefijo son exactamente ' . self::LARGO_PREFIJO
                . ' caracteres (letras o dígitos). No sirve: ' . implode(', ', $malos) . '.'
            );
        }

        if ($limpios === []) {
            throw new \RuntimeException('Hay que dar al menos un prefijo de concepto.');
        }

        // strval() no sobra: PHP convierte a int las claves numéricas de un
        // arreglo, así que $limpios['264'] vuelve como int 264 y la comparación
        // estricta de abajo daría siempre falso. Es la misma trampa que obliga
        // al casteo en ordenar(), y aquí dejaba pasar prefijos duplicados.
        $lista = array_map('strval', array_keys($limpios));

        // Un prefijo en dos grupos duplicaría el importe en el cierre.
        $catalogo = self::catalogo(true);
        $choques = [];

        foreach ($lista as $prefijo) {
            foreach ($catalogo as $otro => $suyos) {
                if ($otro !== $grupo && in_array($prefijo, $suyos, true)) {
                    $choques[] = "$prefijo ya está en $otro";
                }
            }
        }

        if ($choques !== []) {
            throw new \RuntimeException(
                'Un prefijo solo puede pertenecer a un grupo, si no el importe se contaría '
                . 'dos veces en el cierre. ' . implode('; ', $choques) . '.'
            );
        }

        self::escribirGrupos(static function (array $grupos) use ($grupo, $lista): array {
            $grupos[$grupo] = $lista;

            return $grupos;
        });

        return $grupo;
    }

    /**
     * Quita un grupo del archivo.
     *
     * Uno de fábrica no se borra: vuelve a sus prefijos originales, porque el
     * grupo sigue existiendo en el código. Uno dado de alta aquí desaparece.
     *
     * @throws \RuntimeException
     */
    public static function eliminarGrupo(string $nombre): void
    {
        $grupo = self::normalizarNombre($nombre);

        if (!isset(self::catalogo(true)[$grupo])) {
            throw new \RuntimeException("El grupo $grupo no existe.");
        }

        self::escribirGrupos(static function (array $grupos) use ($grupo): array {
            unset($grupos[$grupo]);

            return $grupos;
        });
    }

    /**
     * Reescribe el archivo bajo candado y con reemplazo atómico, igual que
     * includes/permisos.php: se escribe a un temporal y se renombra encima, así
     * nadie llega a leer un JSON a medias.
     *
     * @param callable(array):array $cambio
     * @throws \RuntimeException
     */
    private static function escribirGrupos(callable $cambio): void
    {
        $ruta = self::archivoGrupos();
        $directorio = dirname($ruta);

        if (!is_dir($directorio) || !is_writable($directorio)) {
            throw new \RuntimeException(
                'La carpeta config/ no acepta escritura. El servidor web necesita permiso '
                . 'sobre ella: ahí vive terceros-grupos.json.'
            );
        }

        $candado = fopen($ruta . '.lock', 'c');

        if ($candado === false) {
            throw new \RuntimeException('No se pudo preparar el candado del catálogo.');
        }

        try {
            if (!flock($candado, LOCK_EX)) {
                throw new \RuntimeException('No se pudo bloquear el catálogo de grupos.');
            }

            // Se relee del disco con el candado puesto, no del caché: entre la
            // carga de la página y el guardado alguien pudo cambiar otro grupo.
            $grupos = $cambio(self::grupoExtras());

            $json = json_encode(
                ['version' => 1, 'grupos' => $grupos],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                throw new \RuntimeException('No se pudo serializar el catálogo.');
            }

            $temporal = $ruta . '.' . bin2hex(random_bytes(6)) . '.tmp';

            if (@file_put_contents($temporal, $json . "\n") === false || !@rename($temporal, $ruta)) {
                @unlink($temporal);
                throw new \RuntimeException('No se pudo escribir config/terceros-grupos.json.');
            }
        } finally {
            flock($candado, LOCK_UN);
            fclose($candado);
        }

        self::catalogo(true);
    }

    /**
     * Cuánto mueve un prefijo en un periodo, para poder comprobarlo ANTES de
     * darlo de alta.
     *
     * Existe por lo que costó FEGAC: el número que se tenía a mano (21 → 221) y
     * el nombre apuntaban a conceptos distintos, y sin ver los importes no había
     * forma de notarlo. Aquí se ve qué claves trae el prefijo y por cuánto.
     *
     * @return array{registros:int,importe:float,claves:list<array{clave:string,registros:int,importe:float}>}
     */
    public static function probarPrefijo(string $prefijo, int $anio, int $quincena): array
    {
        $prefijo = self::normalizarPrefijo($prefijo);

        if ($prefijo === '') {
            throw new \RuntimeException(
                'Un prefijo son exactamente ' . self::LARGO_PREFIJO . ' caracteres.'
            );
        }

        $claves = [];
        $registros = 0;
        $importe = 0.0;
        $bd = Database::getInstance();

        foreach (self::TABLAS as $tabla) {
            $slots = self::maxSlots($tabla, $anio, $quincena);

            if ($slots < 1) {
                continue;
            }

            [$where, $params] = self::filtro($anio, $quincena);

            $cols = [];
            for ($n = 1; $n <= $slots; $n++) {
                $cols[] = "`TR{$n}TCP`";
                $cols[] = "`TR{$n}IM`";
            }

            $sql = 'SELECT ' . implode(', ', $cols) . " FROM `$tabla` WHERE $where";

            foreach ($bd->stream($sql, $params) as $fila) {
                for ($n = 1; $n <= $slots; $n++) {
                    $concepto = (string) ($fila["TR{$n}TCP"] ?? '');

                    if ($concepto === self::SLOT_VACIO || substr($concepto, 0, self::LARGO_PREFIJO) !== $prefijo) {
                        continue;
                    }

                    $monto = (float) ($fila["TR{$n}IM"] ?? 0);
                    $claves[$concepto] ??= ['clave' => $concepto, 'registros' => 0, 'importe' => 0.0];
                    $claves[$concepto]['registros']++;
                    $claves[$concepto]['importe'] += $monto;
                    $registros++;
                    $importe += $monto;
                }
            }
        }

        uasort($claves, static fn (array $a, array $b): int => $b['importe'] <=> $a['importe']);

        return [
            'registros' => $registros,
            'importe' => $importe,
            'claves' => array_values($claves),
        ];
    }

    /** ¿A qué grupo pertenece ya este prefijo, si a alguno? */
    public static function grupoDelPrefijo(string $prefijo): ?string
    {
        $prefijo = self::normalizarPrefijo($prefijo);

        foreach (self::catalogo() as $grupo => $prefijos) {
            if (in_array($prefijo, $prefijos, true)) {
                return (string) $grupo;
            }
        }

        return null;
    }

    public static function periodoValido(int $anio, int $quincena): bool
    {
        return $anio >= 2000 && $anio <= 2100 && $quincena >= 1 && $quincena <= 24;
    }

    /* ── Filtro de periodo ──────────────────────────────────────────────── */

    /**
     * Las dos formas en que puede venir escrito un número en estas tablas. ANIO y
     * QNA son varchar y el dato está sucio: la quincena 9 aparece como '09' y
     * también como '9'. Filtrar por una sola perdería el resto sin avisar.
     *
     * @return list<string>
     */
    private static function formas(int $valor, int $ancho): array
    {
        return array_values(array_unique([
            (string) $valor,
            sprintf('%0' . $ancho . 'd', $valor),
        ]));
    }

    /**
     * WHERE del periodo y sus parámetros, compartido por el conteo de slots y por
     * la consulta de filas para que no puedan divergir.
     *
     * @return array{0:string,1:list<string>}
     */
    private static function filtro(int $anio, int $quincena): array
    {
        $anios = self::formas($anio, 4);
        $quincenas = self::formas($quincena, 2);

        $marcadores = static fn (array $v): string => implode(', ', array_fill(0, count($v), '?'));

        $where = 'ANIO IN (' . $marcadores($anios) . ')'
            . ' AND QNA IN (' . $marcadores($quincenas) . ')'
            . ' AND UR <> ?';

        return [$where, array_merge($anios, $quincenas, [self::UR_EXCLUIDA])];
    }

    /**
     * Cuántos slots trae realmente el periodo en esta tabla. TTR es el contador de
     * conceptos de la fila; pedir las 50 columnas cuando la quincena solo usó 12
     * es traer 76 columnas de relleno por cada renglón.
     */
    public static function maxSlots(string $tabla, int $anio, int $quincena): int
    {
        [$where, $params] = self::filtro($anio, $quincena);

        $rows = Database::getInstance()->query(
            "SELECT MAX(CAST(TTR AS SIGNED)) AS maxCpto FROM `$tabla` WHERE $where",
            $params
        );

        $max = (int) ($rows[0]['maxCpto'] ?? 0);

        return max(0, min($max, self::SLOTS));
    }

    /**
     * Columnas a traer: identidad, banco, rama y los pares de los slots vivos.
     */
    private static function columnas(string $tabla, int $slots): string
    {
        $cols = [];

        foreach (['ANIO', 'QNA', 'TIPO', 'SPC', 'RFC', 'UR', 'TIBA'] as $columna) {
            $cols[] = "`$columna`";
        }

        $cols[] = in_array($tabla, self::TABLAS_FFINA, true) ? '`ffina` AS RAMA' : '`SECCION` AS RAMA';

        for ($n = 1; $n <= $slots; $n++) {
            $cols[] = "`TR{$n}TCP`";
            $cols[] = "`TR{$n}IM`";
        }

        return implode(', ', $cols);
    }

    /**
     * Rama de la fila. Los regularizados llegan sin ella (cadena vacía o '0'), así
     * que se deduce de la CLUES con el mismo criterio que OficiosQuincenales: el
     * SPC que empieza en GRSSA es rectoría y cualquier otro es unidad médica.
     */
    private static function rama(array $fila): string
    {
        $rama = trim((string) ($fila['RAMA'] ?? ''));

        if ($rama !== '' && $rama !== '0') {
            return $rama;
        }

        return str_starts_with((string) ($fila['SPC'] ?? ''), 'GRSSA') ? 'REC' : 'UM';
    }

    /* ── Detalle ────────────────────────────────────────────────────────── */

    /**
     * Un renglón por concepto de tercero encontrado, agrupado por grupo.
     *
     * Las seis tablas se recorren una sola vez para todos los grupos pedidos, y el
     * concepto se clasifica por prefijo contra una tabla hash en vez de comparar
     * cada columna de cada fila contra la lista de todos los grupos.
     *
     * Las columnas de cada renglón son las de CABECERA_DETALLE.
     *
     * @param  list<string> $grupos claves de GRUPOS; vacío = todos
     * @return array<string,list<list<mixed>>>
     */
    public static function detalle(int $anio, int $quincena, array $grupos = []): array
    {
        $grupos = $grupos === []
            ? self::grupos()
            : array_values(array_intersect(self::grupos(), $grupos));

        // prefijo -> [[grupo, posición del prefijo dentro del grupo], …]. Es lista
        // y no un solo par porque nada impide que dos grupos compartan prefijo.
        $mapa = [];
        foreach ($grupos as $grupo) {
            foreach (self::catalogo()[$grupo] as $posicion => $prefijo) {
                $mapa[$prefijo][] = [$grupo, $posicion];
            }
        }

        $salida = array_fill_keys($grupos, []);
        $bd = Database::getInstance();

        foreach (self::TABLAS as $tabla) {
            $slots = self::maxSlots($tabla, $anio, $quincena);

            if ($slots < 1) {
                continue; // el periodo no trae conceptos de terceros en esta tabla
            }

            [$where, $params] = self::filtro($anio, $quincena);
            $sql = 'SELECT ' . self::columnas($tabla, $slots) . " FROM `$tabla` WHERE $where";

            foreach ($bd->stream($sql, $params) as $fila) {
                $rama = self::rama($fila);
                $banco = self::BANCOS[(string) ($fila['TIBA'] ?? '')] ?? '';

                // Se acumula por grupo y por posición del prefijo antes de volcar,
                // para que los renglones de una misma fila salgan en el orden del
                // catálogo del grupo (primero los 255, luego los 256…) y no en el
                // orden en que aparecieron los slots.
                $porGrupo = [];

                for ($n = 1; $n <= $slots; $n++) {
                    $concepto = (string) ($fila["TR{$n}TCP"] ?? '');

                    if ($concepto === self::SLOT_VACIO) {
                        continue;
                    }

                    $prefijo = substr($concepto, 0, 3);

                    if (!isset($mapa[$prefijo])) {
                        continue;
                    }

                    foreach ($mapa[$prefijo] as [$grupo, $posicion]) {
                        $porGrupo[$grupo][$posicion][] = [
                            (string) $fila['ANIO'],
                            (string) $fila['QNA'],
                            (string) $fila['TIPO'],
                            (string) $fila['RFC'],
                            (string) $fila['UR'],
                            $rama,
                            $banco,
                            $grupo,
                            $concepto,
                            (float) ($fila["TR{$n}IM"] ?? 0),
                            (string) $fila['SPC'],
                        ];
                    }
                }

                foreach ($porGrupo as $grupo => $porPosicion) {
                    ksort($porPosicion);

                    foreach ($porPosicion as $registros) {
                        foreach ($registros as $registro) {
                            $salida[$grupo][] = $registro;
                        }
                    }
                }
            }
        }

        return $salida;
    }

    /* ── Resumen ────────────────────────────────────────────────────────── */

    /**
     * Ordena los valores de un nivel del resumen: primero los del catálogo y en su
     * orden, después los demás alfabéticamente.
     *
     * La comparación es estricta y contra (string) porque PHP convierte a int las
     * claves numéricas de un arreglo: la UR '420' vuelve como int 420, y sin el
     * casteo `array_search(0, ['ra','rm'])` daría por bueno el primer elemento.
     *
     * @param  list<mixed>  $valores
     * @param  list<string> $catalogo
     * @return list<mixed>
     */
    private static function ordenar(array $valores, array $catalogo): array
    {
        $conocidos = [];
        $nuevos = [];

        foreach ($valores as $valor) {
            $posicion = array_search((string) $valor, $catalogo, true);

            if ($posicion === false) {
                $nuevos[] = $valor;
            } else {
                $conocidos[$posicion] = $valor;
            }
        }

        ksort($conocidos);
        sort($nuevos, SORT_STRING);

        return array_merge(array_values($conocidos), $nuevos);
    }

    /**
     * Importe sumado por grupo, UR, rama, tipo, banco y prefijo de concepto.
     *
     * Se calcula sobre el detalle que ya está en memoria: no hay segunda consulta
     * a la base. El total del resumen siempre cuadra con el del detalle, porque
     * las llaves salen de lo que realmente se encontró y no de un catálogo fijo.
     *
     * Las columnas de cada renglón son las de CABECERA_RESUMEN.
     *
     * @param  array<string,list<list<mixed>>> $detalle
     * @return list<list<mixed>>
     */
    public static function resumen(array $detalle, int $anio, int $quincena): array
    {
        $suma = [];

        foreach ($detalle as $grupo => $filas) {
            foreach ($filas as $fila) {
                // 2=TIPO 4=UR 5=RAMA 6=BANCO 8=CPTO 9=IMPORTE
                [, , $tipo, , $ur, $rama, $banco, , $concepto, $importe] = $fila;
                $prefijo = substr((string) $concepto, 0, 3);

                $suma[$grupo][$ur][$rama][$tipo][$banco][$prefijo] =
                    ($suma[$grupo][$ur][$rama][$tipo][$banco][$prefijo] ?? 0) + $importe;
            }
        }

        $rpt = [];

        foreach (self::grupos() as $grupo) {
            if (!isset($suma[$grupo])) {
                continue;
            }

            foreach (self::ordenar(array_keys($suma[$grupo]), self::ORDEN['UR']) as $ur) {
                foreach (self::ordenar(array_keys($suma[$grupo][$ur]), self::ORDEN['RAMA']) as $rama) {
                    foreach (self::ordenar(array_keys($suma[$grupo][$ur][$rama]), self::ORDEN['TIPO']) as $tipo) {
                        foreach (self::ordenar(array_keys($suma[$grupo][$ur][$rama][$tipo]), self::ORDEN['BANCO']) as $banco) {
                            $porConcepto = $suma[$grupo][$ur][$rama][$tipo][$banco];

                            foreach (self::ordenar(array_keys($porConcepto), self::catalogo()[$grupo]) as $concepto) {
                                $importe = $porConcepto[$concepto];

                                // Un renglón en cero no aporta nada al cierre; se
                                // omite, igual que en el reporte original.
                                if ($importe == 0) {
                                    continue;
                                }

                                $rpt[] = [$anio, $quincena, $grupo, $ur, $rama, $tipo, $banco, $concepto, $importe];
                            }
                        }
                    }
                }
            }
        }

        return $rpt;
    }

    /**
     * Cuántos registros y cuánto importe aporta cada grupo, para los indicadores
     * de la pantalla.
     *
     * @param  array<string,list<list<mixed>>> $detalle
     * @return list<array{grupo:string,registros:int,importe:float}>
     */
    public static function totalesPorGrupo(array $detalle): array
    {
        $salida = [];

        foreach ($detalle as $grupo => $filas) {
            $importe = 0.0;

            foreach ($filas as $fila) {
                $importe += (float) $fila[9];
            }

            $salida[] = [
                'grupo' => (string) $grupo,
                'registros' => count($filas),
                'importe' => $importe,
            ];
        }

        return $salida;
    }

    /* ── Archivos ───────────────────────────────────────────────────────── */

    public static function nombreDetalle(int $anio, int $quincena, string $grupo): string
    {
        return "TercerosN{$anio}Qna{$quincena}_{$grupo}.csv";
    }

    public static function nombreResumen(int $anio, int $quincena): string
    {
        return "TercerosN{$anio}Qna{$quincena}_RPT.csv";
    }

    /**
     * Escribe un CSV con el formato exacto que espera quien consume estos
     * archivos: sin comillas, sin BOM y con fin de línea CRLF.
     *
     * Se escribe por bloques y no renglón por renglón porque el detalle de una
     * quincena ronda los 20 mil, y son 20 mil llamadas al sistema de archivos.
     *
     * @param iterable<list<mixed>> $filas
     */
    public static function escribirCsv(string $ruta, string $cabecera, iterable $filas): int
    {
        $manejador = fopen($ruta, 'w');

        if ($manejador === false) {
            throw new \RuntimeException("No se pudo crear el archivo $ruta.");
        }

        try {
            fwrite($manejador, $cabecera . "\r\n");

            $escritas = 0;
            $bloque = '';

            foreach ($filas as $fila) {
                $bloque .= implode(',', $fila) . "\r\n";
                $escritas++;

                if (($escritas % 1024) === 0) {
                    fwrite($manejador, $bloque);
                    $bloque = '';
                }
            }

            if ($bloque !== '') {
                fwrite($manejador, $bloque);
            }
        } finally {
            fclose($manejador);
        }

        return $escritas;
    }
}
