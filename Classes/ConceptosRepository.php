<?php

declare(strict_types=1);

namespace App;

/**
 * Búsqueda de conceptos de pago en las seis tablas de nómina de `catalogos`.
 *
 * Cada fila guarda hasta 50 conceptos en grupos de tres columnas:
 *   TR{n}TCP varchar(5)  concepto — 3 dígitos de clave + 2 de subclave (26700, 267CG)
 *   TR{n}IM  double      importe
 *   TR{n}AQ  varchar(6)  año/quincena del concepto (solo se llena en retroactivos)
 *
 * Un slot sin usar no viene vacío ni NULL: viene con el centinela '00000',
 * importe 0 y AQ '000000'. Por eso SLOT_VACIO se descarta explícitamente.
 *
 * El desglose (unpivot) se hace en PHP, no en SQL. Hacerlo con un JOIN contra una
 * tabla de 50 números multiplica por 50 las filas que pasaron el filtro, evalúa
 * dos CASE de 50 ramas sobre cada una y obliga a MySQL a materializar y ordenar
 * todo eso en una tabla temporal para el HAVING. Aquí la base solo entrega las
 * filas que ya coinciden — medido: 2,050 filas para 251|257 en federal 2026 — y el
 * desglose sale en microsegundos del lado de PHP.
 *
 * Todo es de solo lectura: esta clase solo ejecuta SELECT.
 */
class ConceptosRepository
{
    /**
     * Las seis tablas de nómina. Es whitelist: el nombre de tabla se interpola en
     * el SQL (no puede ir como parámetro), así que nunca se toma del cliente sin
     * pasar por aquí.
     */
    private const TABLAS = [
        'federal',
        'homologados',
        'regularizados',
        'formalizados',
        'formalizados2015',
        'formalizados2016',
    ];

    /** Slots por fila. Las seis tablas tienen exactamente 50, ya verificado. */
    private const SLOTS = 50;

    /** Un slot sin concepto trae este centinela, no cadena vacía ni NULL. */
    private const SLOT_VACIO = '00000';

    /** Columnas de identidad, iguales en las seis tablas. */
    private const IDENTIDAD = ['ANIO', 'QNA', 'TIPO', 'RFC', 'NOMB', 'UR'];

    /**
     * Unidad responsable que se excluye por omisión. Se compara como cadena:
     * UR es varchar(3) y contra un entero MySQL castea la columna de cada fila a
     * número, lo que anula el índice y convierte en 0 cualquier UR no numérica.
     */
    public const UR_EXCLUIDA = '610';

    /** Tope de coincidencias devueltas, para que una búsqueda amplia no tumbe a PHP. */
    public const TOPE = 20000;

    /** @return list<string> */
    public static function tablas(): array
    {
        return self::TABLAS;
    }

    /**
     * Años presentes en las tablas, de mayor a menor.
     *
     * Sale de `federal` (la más grande) con DISTINCT sobre su índice ANIO. Ojo: el
     * dato viene sucio — hay años como 2124 (39 mil filas), 2030 y 2224, que son
     * capturas erróneas. No se filtran: esto es una herramienta de auditoría y
     * justamente esas filas puede que sean lo que alguien anda buscando.
     *
     * @return list<int>
     */
    public static function aniosDisponibles(): array
    {
        $rows = Database::getInstance()->query(
            'SELECT DISTINCT ANIO FROM `federal` ORDER BY ANIO DESC'
        );

        $anios = [];
        foreach ($rows as $row) {
            $anio = (int) $row['ANIO'];
            if ($anio > 0) {
                $anios[] = $anio;
            }
        }

        return $anios;
    }

    /**
     * Longitud mínima de un código buscado. La clave de concepto son 3 dígitos
     * (más 2 opcionales de subclave), así que un prefijo más corto no identifica
     * nada: '2' traería medio ejercicio y solo serviría para castigar a la base.
     */
    private const LARGO_MINIMO = 3;

    /**
     * Normaliza lo que el usuario escribió en la caja de códigos: "251, 257" o
     * "251 257" o "26700" salen como ['251','257'].
     *
     * Se aceptan de 3 a 5 caracteres alfanuméricos porque TR{n}TCP es varchar(5)
     * y admite subclaves con letras (267CG). Lo que no cumple se devuelve aparte
     * en 'ignorados' en vez de descartarse callando: si alguien teclea "25" hay
     * que decírselo, no buscar otra cosa.
     *
     * Los códigos se devuelven como strings a propósito. Usar el código como
     * clave de arreglo para deduplicar convierte '251' en int 251 (PHP normaliza
     * las claves numéricas), y ese int termina en str_starts_with(), que bajo
     * strict_types lanza TypeError. De ahí el strval().
     *
     * @return array{codigos:list<string>,ignorados:list<string>}
     */
    public static function normalizarCodigos(string $entrada): array
    {
        $partes = preg_split('/[^0-9A-Za-z]+/', mb_strtoupper(trim($entrada))) ?: [];

        $codigos = [];
        $ignorados = [];

        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }

            if (preg_match('/^[0-9A-Z]{' . self::LARGO_MINIMO . ',5}$/', $parte) === 1) {
                $codigos[$parte] = true;
            } else {
                $ignorados[$parte] = true;
            }
        }

        return [
            'codigos' => array_map('strval', array_keys($codigos)),
            'ignorados' => array_map('strval', array_keys($ignorados)),
        ];
    }

    /**
     * Las dos formas en que puede estar escrita una quincena. QNA es varchar(2) y
     * el dato está sucio: la quincena 1 aparece como '01' (13,093 filas en 2026) y
     * también como '1' (2,542). Filtrar por una sola perdería el resto sin avisar.
     *
     * Se buscan ambas con IN en vez de CAST(QNA AS UNSIGNED) porque el cast
     * inutilizaría el índice QNA.
     *
     * @return list<string>
     */
    private static function formasQuincena(int $quincena): array
    {
        $formas = [sprintf('%02d', $quincena), (string) $quincena];

        return array_values(array_unique($formas));
    }

    /** ¿El concepto empieza con alguno de los prefijos buscados? */
    private static function coincide(string $concepto, array $prefijos): bool
    {
        foreach ($prefijos as $prefijo) {
            if (str_starts_with($concepto, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lista de columnas a traer: identidad + los 150 campos de los 50 slots. Se
     * piden todos porque no se sabe de antemano en cuál slot cayó la coincidencia.
     */
    private static function columnas(): string
    {
        $cols = self::IDENTIDAD;

        for ($n = 1; $n <= self::SLOTS; $n++) {
            $cols[] = "TR{$n}TCP";
            $cols[] = "TR{$n}IM";
            $cols[] = "TR{$n}AQ";
        }

        return '`' . implode('`, `', $cols) . '`';
    }

    /**
     * En cuántos tramos se parte el recorrido de una tabla, cada uno con las
     * formas de la quincena que le tocan.
     *
     * Con quincena elegida es uno solo. Sin ella el filtro es un año entero, y
     * eso no cabe en memoria para un concepto masivo: el 221 casa con 181,478
     * filas de 156 columnas en 2026, y el resultado se almacena completo del lado
     * del cliente antes de que PHP lea la primera. Partiendo por quincena cada
     * consulta queda en unos miles de filas.
     *
     * Los tramos salen de las quincenas que la tabla realmente tiene y no de
     * range(1, 24), porque el dato está sucio: en 2026 `federal` trae también
     * '31', que un rango fijo dejaría fuera sin avisar.
     *
     * @return list<list<string>>
     */
    private static function tramos(string $tabla, int $anio, ?int $quincena): array
    {
        if ($quincena !== null) {
            return [self::formasQuincena($quincena)];
        }

        $rows = Database::getInstance()->query(
            "SELECT DISTINCT QNA FROM `$tabla` WHERE ANIO = ?",
            [(string) $anio]
        );

        // '01' y '1' son la misma quincena escrita distinto: van en el mismo
        // tramo para no consultarla dos veces ni partir sus filas.
        $porNumero = [];
        foreach ($rows as $row) {
            $qna = (string) $row['QNA'];
            $porNumero[(int) $qna][$qna] = true;
        }

        ksort($porNumero);

        return array_values(array_map(
            static fn (array $formas): array => array_keys($formas),
            $porNumero
        ));
    }

    /**
     * Filas de una tabla que contienen alguno de los conceptos buscados.
     *
     * El filtro es LIKE 'prefijo%' sobre los 50 slots, no REGEXP: son 50 × número
     * de códigos evaluaciones por fila (5 millones para un año de federal) y el
     * motor de regex cuesta bastante más por fila que un prefijo. Los prefijos van
     * como parámetros, nunca interpolados.
     *
     * ANIO se compara como cadena porque es varchar(4); así se aprovecha el índice
     * ANIO que existe en las seis tablas y que es lo único que evita el escaneo
     * completo (un año son ~1/21 de la tabla).
     *
     * Entrega las filas de una en una y por tramos. Traerlas todas de golpe
     * reventaba el memory_limit con cualquier concepto de uso masivo, y el error
     * llegaba al navegador como "No se pudo completar la búsqueda".
     *
     * @return \Generator<int,array<string,mixed>>
     */
    private static function filasDe(
        string $tabla,
        int $anio,
        array $prefijos,
        ?int $quincena,
        bool $excluirUr
    ): \Generator {
        // El LIKE de los 50 slots es idéntico en todos los tramos: se arma una vez.
        $condiciones = [];
        $filtroSlots = [];
        for ($n = 1; $n <= self::SLOTS; $n++) {
            foreach ($prefijos as $prefijo) {
                $condiciones[] = "TR{$n}TCP LIKE ?";
                $filtroSlots[] = $prefijo . '%';
            }
        }
        $condicionSlots = '(' . implode(' OR ', $condiciones) . ')';

        foreach (self::tramos($tabla, $anio, $quincena) as $formas) {
            $where = ['ANIO = ?'];
            $params = [(string) $anio];

            $where[] = 'QNA IN (' . implode(', ', array_fill(0, count($formas), '?')) . ')';
            $params = array_merge($params, $formas);

            if ($excluirUr) {
                $where[] = 'UR <> ?';
                $params[] = self::UR_EXCLUIDA;
            }

            $where[] = $condicionSlots;
            $params = array_merge($params, $filtroSlots);

            $sql = 'SELECT ' . self::columnas() . " FROM `$tabla`"
                . ' WHERE ' . implode(' AND ', $where);

            foreach (Database::getInstance()->stream($sql, $params) as $fila) {
                yield $fila;
            }
        }
    }

    /**
     * Desglosa una fila ancha en una coincidencia por slot, igual que el unpivot
     * que hacía el JOIN contra la tabla de 50 números.
     *
     * @return list<array<string,mixed>>
     */
    private static function desglosar(string $tabla, array $fila, array $prefijos): array
    {
        $salida = [];

        for ($n = 1; $n <= self::SLOTS; $n++) {
            $concepto = trim((string) ($fila["TR{$n}TCP"] ?? ''));

            if ($concepto === '' || $concepto === self::SLOT_VACIO) {
                continue;
            }

            if (!self::coincide($concepto, $prefijos)) {
                continue;
            }

            // El AQ del slot puede diferir de la QNA de la fila: un concepto
            // retroactivo se paga en una quincena pero corresponde a otra. Se
            // devuelven los dos y '000000' se normaliza a vacío, que es lo que
            // significa.
            $aq = trim((string) ($fila["TR{$n}AQ"] ?? ''));

            $salida[] = [
                'origen' => $tabla,
                'anio' => (string) ($fila['ANIO'] ?? ''),
                'qna' => (string) ($fila['QNA'] ?? ''),
                'tipo' => (string) ($fila['TIPO'] ?? ''),
                'ur' => (string) ($fila['UR'] ?? ''),
                'rfc' => (string) ($fila['RFC'] ?? ''),
                'nomb' => trim((string) ($fila['NOMB'] ?? '')),
                'slot' => $n,
                'concepto' => $concepto,
                'importe' => (float) ($fila["TR{$n}IM"] ?? 0),
                'aq' => $aq === '000000' ? '' : $aq,
            ];
        }

        return $salida;
    }

    /**
     * Busca los conceptos en las tablas pedidas y devuelve una fila por
     * coincidencia, ordenadas por origen, RFC, quincena y concepto.
     *
     * @param list<string>  $prefijos códigos ya normalizados por normalizarCodigos()
     * @param list<string>  $tablas   subconjunto de TABLAS; vacío = las seis
     * @return array{filas:list<array<string,mixed>>,porTabla:array<string,array{filas:int,matches:int}>,truncado:bool,importe:float}
     */
    public static function buscar(
        int $anio,
        array $prefijos,
        ?int $quincena = null,
        array $tablas = [],
        bool $excluirUr = true,
        int $tope = self::TOPE
    ): array {
        // Se fuerzan a string aunque normalizarCodigos() ya los entregue así: un
        // código numérico que llegue como int reventaría en str_starts_with() bajo
        // strict_types, y el error saldría hasta el fondo de la búsqueda.
        $prefijos = array_values(array_filter(
            array_map('strval', $prefijos),
            static fn (string $prefijo): bool => $prefijo !== ''
        ));

        if ($prefijos === []) {
            return ['filas' => [], 'porTabla' => [], 'truncado' => false, 'importe' => 0.0];
        }

        $elegidas = $tablas === []
            ? self::TABLAS
            : array_values(array_intersect(self::TABLAS, $tablas));

        $filas = [];
        $porTabla = [];
        $truncado = false;
        $importe = 0.0;

        foreach ($elegidas as $tabla) {
            $matches = 0;
            $leidas = 0;

            foreach (self::filasDe($tabla, $anio, $prefijos, $quincena, $excluirUr) as $fila) {
                $leidas++;

                foreach (self::desglosar($tabla, $fila, $prefijos) as $match) {
                    if (count($filas) >= $tope) {
                        $truncado = true;
                        break 2;
                    }

                    $filas[] = $match;
                    $importe += $match['importe'];
                    $matches++;
                }
            }

            $porTabla[$tabla] = ['filas' => $leidas, 'matches' => $matches];
        }

        // El mismo orden que traía la consulta original: origen, RFC, quincena,
        // concepto. Ordenar aquí evita el filesort sobre la temporal en MySQL.
        usort($filas, static function (array $a, array $b): int {
            return [$a['origen'], $a['rfc'], $a['qna'], $a['concepto'], $a['slot']]
               <=> [$b['origen'], $b['rfc'], $b['qna'], $b['concepto'], $b['slot']];
        });

        return [
            'filas' => $filas,
            'porTabla' => $porTabla,
            'truncado' => $truncado,
            'importe' => $importe,
        ];
    }

    /**
     * Agrupa las coincidencias por concepto: cuántas y por cuánto. Es el equivalente
     * del query de resumen, calculado sobre lo que ya se trajo en vez de con una
     * segunda pasada a la base.
     *
     * @return list<array{concepto:string,veces:int,importe:float}>
     */
    public static function resumenPorConcepto(array $filas): array
    {
        $porConcepto = [];

        foreach ($filas as $fila) {
            $clave = $fila['concepto'];
            $porConcepto[$clave] ??= ['concepto' => $clave, 'veces' => 0, 'importe' => 0.0];
            $porConcepto[$clave]['veces']++;
            $porConcepto[$clave]['importe'] += $fila['importe'];
        }

        usort($porConcepto, static fn (array $a, array $b): int => $b['veces'] <=> $a['veces']);

        return array_values($porConcepto);
    }
}
