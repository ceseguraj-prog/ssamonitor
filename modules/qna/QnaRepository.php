<?php

declare(strict_types=1);

namespace App;

/**
 * El reporte quincenal de nómina («generador QNA»), armado directo de las seis
 * tablas de `catalogos` en vez de los seis .txt que mandaban cada quincena.
 *
 * Los .txt eran un volcado de estas mismas tablas cruzado con `indeteccr`: los
 * 11,402 renglones de la q16 de 2026 están en la base con el mismo RFC, cheque y
 * tipo, y campo por campo coinciden (ver README). Lo que el .txt traía como 226
 * columnas, una por clave de concepto, aquí son los 50 slots TR{n}TCP/TR{n}IM de
 * cada fila; se suman los que interesan y lo demás no se lee.
 *
 * Hay dos modos (ver MODO_TXT y MODO_CORREGIDO): por omisión el reporte sale
 * idéntico al que salía de los .txt, con sus defectos incluidos.
 *
 * Todo es de solo lectura: esta clase solo ejecuta SELECT.
 */
class QnaRepository
{
    /**
     * Igual que con los .txt, defectos incluidos: es lo que se entrega y contra
     * lo que se compara. El .txt salía de un INNER JOIN contra `indeteccr` por
     * CLUES, y eso le costaba tres cosas que este modo reproduce a propósito:
     *
     *  - Un trabajador sale una vez por cada fila de `indeteccr` con su CLUES. Hay
     *    35 CLUES repetidos (casi siempre la unidad con y sin el municipio), así
     *    que en la q16 salieron 11,260 renglones en vez de 10,188 y el CPTO 07
     *    sumó 76.1 millones en vez de 68.7.
     *  - Un CLUES que no está en `indeteccr` tumba la fila: 5 personas con pago
     *    ordinario normal en la q15 y en la q16.
     *  - La Ñ del nombre sale como «?».
     */
    public const MODO_TXT = 'txt';

    /** Cada pago una sola vez, con todas las personas y con la Ñ. */
    public const MODO_CORREGIDO = 'corregido';

    /** Normaliza el modo que llega del cliente; lo desconocido es el del .txt. */
    public static function modo(string $valor): string
    {
        return $valor === self::MODO_CORREGIDO ? self::MODO_CORREGIDO : self::MODO_TXT;
    }

    /**
     * Las seis tablas, en el orden en que el generador concatenaba los .txt. Es
     * también el orden del reporte. Es whitelist: el nombre se interpola en el SQL.
     */
    public const TABLAS = [
        'federal',
        'formalizados',
        'formalizados2015',
        'formalizados2016',
        'homologados',
        'regularizados',
    ];

    /** Slots por fila; las seis tablas tienen exactamente 50. */
    private const SLOTS = 50;

    /** Solo la nómina ordinaria, igual que el generador (TIPO == '11'). */
    public const TIPO_ORDINARIA = '11';

    /**
     * La UR que el reporte deja fuera. Se compara como cadena: UR es varchar(3) y
     * contra un entero MySQL castea cada fila, pierde el índice y vuelve 0
     * cualquier UR no numérica (FOR, FO2, REG…).
     */
    public const UR_EXCLUIDA = '610';

    /**
     * La UR que el reporte presenta con otra clave, también dentro de la clave
     * presupuestal: así venía en los .txt de la q16. (En los de la q15 la clave
     * todavía traía 416; el generador solo cambiaba la columna UR.)
     */
    private const UR_RENOMBRADA = ['416' => '411'];

    /**
     * Las columnas de importe: cada una suma estas claves de TR{n}TCP.
     *
     * Son las mismas sumas que hacía el generador (10700 → CPTO 07; 130AR+130MR+
     * 130BR → CPTO 30; 1A100…1A500 → QUINQUENIO; 2AS10+2AS20+2AS00+2AS-2 →
     * AHORRO SOLIDARIO) con una añadida: 2AS03. El .txt no tenía columna para
     * ella y la metía en la de 2AS20 (dos renglones en la q15 y la q16, 94.51
     * cada uno), así que el total de ahorro solidario sí la incluía.
     *
     * Una clave que se repite en dos slots de la misma fila se suma, que es lo
     * que traía el .txt.
     */
    public const IMPORTES = [
        'CPTO 07'          => ['10700'],
        'CPTO 30'          => ['130AR', '130MR', '130BR'],
        'QUINQUENIO'       => ['1A100', '1A200', '1A300', '1A400', '1A500'],
        'AHORRO SOLIDARIO' => ['2AS10', '2AS20', '2AS00', '2AS-2', '2AS03'],
    ];

    /**
     * El CPTO 07 sale como texto y no como número. El generador leía los .txt
     * todo como texto y solo convertía las columnas que sumaba; 10700 nada más se
     * renombraba, así que en el Excel quedaba como texto («10096.5»).
     */
    public const IMPORTE_COMO_TEXTO = 'CPTO 07';

    /**
     * Prefijos de las familias de arriba. Una clave que empiece así y no esté en
     * IMPORTES (un 1A600, un 2AS04) no se suma, pero se cuenta y se avisa: es la
     * señal de que la regla se quedó corta, y callarla bajaría un total sin que
     * nadie se enterara.
     */
    private const FAMILIAS = ['107', '130', '1A', '2AS'];

    /** Encabezado del reporte, en orden. Es el del reporte que se entregaba. */
    public const COLUMNAS = [
        'UR', 'FECHA DE INGRESO', 'CURP', 'RFC', 'NOMBRE', 'CLUES', 'CR',
        'DESCRIPCIÓN DEL CR', 'CLABE PRESUPUESTAL', 'CODIGO', 'DESCRIPCION',
        'CPTO 07', 'CPTO 30', 'QUINQUENIO', 'AHORRO SOLIDARIO',
    ];

    /** Columnas que se piden a la base, además de los slots. */
    private const CAMPOS = [
        'RFC', 'NOMB', 'CURP', 'UR', 'TIPO', 'AHISA1', 'SPC', 'cr',
        'AA', 'PTDA', 'COD', 'PGAI', 'NUM',
    ];

    /**
     * Cuántas filas de `indeteccr` entraban en cada tanda del cruce que armaba
     * el .txt. Se dedujo del propio .txt: dentro de cada tabla el reporte vuelve
     * a empezar cinco veces, y cada tramo corresponde exactamente a 255 filas
     * del catálogo en orden de id (0–254, 255–509, …). Reproducirlo, junto con
     * el orden físico de la tabla (ver filasDe), es lo que hace que los
     * renglones salgan en el mismo orden.
     */
    private const TANDA_CATALOGO = 255;

    /**
     * Las dos formas en que puede venir escrita la quincena: QNA es varchar(2) y
     * el dato está sucio ('01' y '1' conviven). Se buscan ambas con IN y no con
     * CAST, que perdería el índice.
     *
     * @return list<string>
     */
    private static function formasQuincena(int $quincena): array
    {
        return array_values(array_unique([sprintf('%02d', $quincena), (string) $quincena]));
    }

    private static function columnasSql(): string
    {
        $cols = self::CAMPOS;

        for ($n = 1; $n <= self::SLOTS; $n++) {
            $cols[] = "TR{$n}TCP";
            $cols[] = "TR{$n}IM";
        }

        return '`' . implode('`, `', $cols) . '`';
    }

    /**
     * Las filas de `indeteccr` agrupadas por CLUES, cada una con su descripción y
     * la tanda del cruce en que caía (ver TANDA_CATALOGO).
     *
     * Va por CLUES y no por CR, aunque la columna del reporte se llame
     * «descripción del CR»: un mismo CR agrupa varias unidades (el 1214790480
     * salió en la q16 con cinco descripciones distintas), y el .txt traía la de
     * la unidad. Por CLUES coinciden los 11,402 renglones.
     *
     * @return array<string,list<array{tanda:int,descripcion:string}>>
     */
    public static function catalogoClues(): array
    {
        $mapa = [];
        $posicion = 0;

        foreach (Database::getInstance()->query(
            'SELECT CLUES, descripcioncr FROM indeteccr ORDER BY id'
        ) as $fila) {
            $mapa[(string) $fila['CLUES']][] = [
                'tanda' => intdiv($posicion++, self::TANDA_CATALOGO),
                'descripcion' => trim((string) $fila['descripcioncr']),
            ];
        }

        return $mapa;
    }

    /**
     * Descripción de cada código de puesto, de `cat_puesto`. Reemplaza al Excel
     * de referencia del generador, que era el reporte de una quincena anterior:
     * las 115 claves de la q16 salen idénticas.
     *
     * @return array<string,string>
     */
    public static function descripcionesPuesto(): array
    {
        $mapa = [];

        foreach (Database::getInstance()->query(
            'SELECT CAT_PUESTO, DESC_PUEST FROM cat_puesto'
        ) as $fila) {
            $mapa[trim((string) $fila['CAT_PUESTO'])] ??= (string) $fila['DESC_PUEST'];
        }

        return $mapa;
    }

    /**
     * Las filas de una tabla para la quincena, en el orden en que se cargaron.
     *
     * Ese orden es el del .txt: dentro de cada tanda del catálogo, los renglones
     * siguen exactamente el orden físico de la tabla (cero inversiones en las
     * seis tablas, en la q15 y en la q16). No hay columna que lo represente —
     * `conse` coincidía en la q16 y no en la q15 —, así que la consulta va sin
     * ORDER BY y con el mismo filtro que se usó para comprobarlo: las seis
     * tablas carecen de llave primaria y MySQL resuelve ANIO + QNA con un
     * index_merge (o con el índice de ANIO), que entrega las filas en el orden
     * de inserción. Por eso TIPO y UR se filtran en PHP y no aquí: otro WHERE
     * puede cambiar el plan y, con él, el orden.
     *
     * @return \Generator<int,array<string,mixed>>
     */
    private static function filasDe(string $tabla, int $anio, int $quincena): \Generator
    {
        $formas = self::formasQuincena($quincena);

        $sql = 'SELECT ' . self::columnasSql() . " FROM `$tabla`"
            . ' WHERE ANIO = ? AND QNA IN (' . implode(', ', array_fill(0, count($formas), '?')) . ')';

        foreach (Database::getInstance()->stream($sql, array_merge([(string) $anio], $formas)) as $fila) {
            if ((string) $fila['TIPO'] === self::TIPO_ORDINARIA && (string) $fila['UR'] !== self::UR_EXCLUIDA) {
                yield $fila;
            }
        }
    }

    /**
     * Arma el reporte de la quincena.
     *
     * @return array{
     *   filas: list<list<mixed>>,
     *   modo: string,
     *   porTabla: array<string,array{filas:int,pagos:int,importes:array<string,float>}>,
     *   totales: array<string,float>,
     *   pagos: int,
     *   duplicados: int,
     *   omitidos: list<array{tabla:string,rfc:string,nombre:string,clues:string}>,
     *   sinDescripcionCr: list<string>,
     *   sinDescripcionPuesto: list<string>,
     *   clavesFuera: array<string,int>
     * }
     */
    public static function generar(int $anio, int $quincena, string $modo = self::MODO_TXT): array
    {
        $modo = self::modo($modo);
        $comoTxt = $modo === self::MODO_TXT;

        $catalogo = self::catalogoClues();
        $puestos = self::descripcionesPuesto();

        // clave de concepto → columna del reporte, para no recorrer IMPORTES por slot.
        $destino = [];
        foreach (self::IMPORTES as $columna => $claves) {
            foreach ($claves as $clave) {
                $destino[$clave] = $columna;
            }
        }

        $ceros = array_fill_keys(array_keys(self::IMPORTES), 0.0);

        $filas = [];
        $porTabla = [];
        $totales = $ceros;
        $pagos = 0;
        $omitidos = [];
        $sinCr = [];
        $sinPuesto = [];
        $fuera = [];

        foreach (self::TABLAS as $tabla) {
            $porTabla[$tabla] = ['filas' => 0, 'pagos' => 0, 'importes' => $ceros];

            // Renglones de la tabla con su llave de orden: [tanda, secuencia].
            $deLaTabla = [];
            $secuencia = 0;

            foreach (self::filasDe($tabla, $anio, $quincena) as $f) {
                $secuencia++;
                $importes = $ceros;

                for ($n = 1; $n <= self::SLOTS; $n++) {
                    $clave = (string) $f["TR{$n}TCP"];

                    if (isset($destino[$clave])) {
                        $importes[$destino[$clave]] += (float) $f["TR{$n}IM"];
                        continue;
                    }

                    foreach (self::FAMILIAS as $prefijo) {
                        if (str_starts_with($clave, $prefijo)) {
                            $fuera[$clave] = ($fuera[$clave] ?? 0) + 1;
                            break;
                        }
                    }
                }

                // Los importes son double en la base; sumarlos deja colas como
                // 1009.6500000000001 que en Excel se verían en la barra de fórmulas.
                $importes = array_map(static fn (float $v): float => round($v, 2), $importes);

                $urBase = (string) $f['UR'];
                $ur = self::UR_RENOMBRADA[$urBase] ?? $urBase;
                $clues = (string) $f['SPC'];
                $codigo = (string) $f['COD'];
                $cr = (string) $f['cr'];
                $nombre = (string) $f['NOMB'];

                $pagos++;
                $porTabla[$tabla]['pagos']++;

                $unidades = $catalogo[$clues] ?? [];

                if (!$unidades) {
                    $sinCr[$clues] = true;

                    if ($comoTxt) {
                        $omitidos[] = ['tabla' => $tabla, 'rfc' => (string) $f['RFC'], 'nombre' => $nombre, 'clues' => $clues];
                        continue;
                    }
                }

                if (!isset($puestos[$codigo])) {
                    $sinPuesto[$codigo] = true;
                }

                if ($comoTxt) {
                    $nombre = str_replace(['Ñ', 'ñ'], '?', $nombre);
                } else {
                    // Una sola vez, con la primera descripción por id (la que el
                    // .txt ponía primero); sin unidad, con la descripción vacía.
                    $unidades = [$unidades[0] ?? ['tanda' => 0, 'descripcion' => '']];
                }

                $valores = array_values($importes);
                $posTexto = array_search(self::IMPORTE_COMO_TEXTO, array_keys(self::IMPORTES), true);
                $valores[$posTexto] = (string) $valores[$posTexto];

                foreach ($unidades as $unidad) {
                    $deLaTabla[] = [
                        // Un trabajador con dos filas en la misma tanda empata:
                        // usort es estable, así que quedan en orden de id.
                        'orden' => [$unidad['tanda'], $secuencia],
                        'fila' => [
                            $ur,
                            (string) $f['AHISA1'],
                            (string) $f['CURP'],
                            (string) $f['RFC'],
                            $nombre,
                            $clues,
                            $cr,
                            $unidad['descripcion'],
                            // La clave presupuestal no es columna de la base: el
                            // .txt la armaba con estos seis campos.
                            $f['AA'] . $ur . $f['PTDA'] . ' ' . $f['COD'] . $f['PGAI'] . $f['NUM'],
                            $codigo,
                            $puestos[$codigo] ?? '',
                            ...$valores,
                        ],
                    ];

                    $porTabla[$tabla]['filas']++;

                    foreach ($importes as $columna => $valor) {
                        $porTabla[$tabla]['importes'][$columna] += $valor;
                        $totales[$columna] += $valor;
                    }
                }
            }

            usort($deLaTabla, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

            foreach ($deLaTabla as $renglon) {
                $filas[] = $renglon['fila'];
            }
        }

        ksort($fuera);

        // Sumar miles de doubles acumula error (5932650.629999878); a centavos.
        $centavos = static fn (array $importes): array =>
            array_map(static fn (float $v): float => round($v, 2), $importes);

        foreach ($porTabla as $tabla => $datos) {
            $porTabla[$tabla]['importes'] = $centavos($datos['importes']);
        }

        return [
            'filas' => $filas,
            'modo' => $modo,
            'porTabla' => $porTabla,
            'totales' => $centavos($totales),
            'pagos' => $pagos,
            'duplicados' => count($filas) - ($pagos - count($omitidos)),
            'omitidos' => $omitidos,
            'sinDescripcionCr' => array_map('strval', array_keys($sinCr)),
            'sinDescripcionPuesto' => array_map('strval', array_keys($sinPuesto)),
            'clavesFuera' => $fuera,
        ];
    }

    /** Nombre del archivo, el mismo que se usaba a mano: QNA_16_2026.xlsx. */
    public static function nombreArchivo(int $anio, int $quincena): string
    {
        return sprintf('QNA_%02d_%04d.xlsx', $quincena, $anio);
    }

    /**
     * Ejercicios para el selector: los de `federal`, sin los imposibles (hay 2124
     * y 2224 por capturas erróneas). Aquí no tiene sentido ofrecerlos: el reporte
     * es de una quincena que se acaba de pagar.
     *
     * @return list<int>
     */
    public static function aniosDisponibles(): array
    {
        $tope = (int) date('Y') + 1;
        $anios = [];

        foreach (Database::getInstance()->query('SELECT DISTINCT ANIO FROM `federal` ORDER BY ANIO DESC') as $fila) {
            $anio = (int) $fila['ANIO'];

            if ($anio > 2000 && $anio <= $tope) {
                $anios[] = $anio;
            }
        }

        return $anios;
    }

    /**
     * La quincena más reciente con nómina ordinaria en `federal`, para abrir la
     * pantalla en ella. Sale de las que de verdad hay y no del calendario: la del
     * calendario suele estar en curso y vacía. QNA trae basura ('31'), así que
     * solo cuentan las de 1 a 24.
     */
    public static function ultimaQuincena(int $anio): ?int
    {
        $ultima = null;

        foreach (Database::getInstance()->query(
            'SELECT DISTINCT QNA FROM `federal` WHERE ANIO = ? AND TIPO = ?',
            [(string) $anio, self::TIPO_ORDINARIA]
        ) as $fila) {
            $qna = (int) $fila['QNA'];

            if ($qna >= 1 && $qna <= 24 && ($ultima === null || $qna > $ultima)) {
                $ultima = $qna;
            }
        }

        return $ultima;
    }
}
