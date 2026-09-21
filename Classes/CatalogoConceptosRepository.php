<?php

declare(strict_types=1);

namespace App;

/**
 * Catálogo unificado de conceptos de pago.
 *
 * El problema que resuelve: no hay UNA tabla de conceptos, hay siete, con
 * nombres de columna distintos y ninguna completa. Parecen incompatibles, pero
 * no lo son — todas guardan la misma clave partida en tres pedazos:
 *
 *     TIPO + CONCEPTO + ANTECEDENTE  =  la clave de 5 que trae TR{n}TCP
 *
 *     cat_conceptos       tipocon=2 · concepto=01 · antecedente=00  -> 20100
 *     cat_conceptos_nvo   tipo=2    · cpto=01     · pa=00           -> 20100
 *     conceptospartida    tipo=1    · con=07      · ant=00          -> 10700
 *     conceptosTerceros   ya viene compuesta                        -> 246OM
 *
 * Comprobado contra la nómina real: recomponiendo así, las siete tablas juntas
 * describen el 96% de las claves que de verdad se usaron en 2026 Q17 (136 de
 * 141). Las 5 que faltan son subclaves de FOVISSSTE por 7,688.70 en total.
 *
 * La unificación es en PHP, al leer. No se crea ninguna vista ni tabla: son
 * ~1,700 filas repartidas en siete tablas chicas y se juntan en memoria.
 *
 * Solo SELECT.
 */
class CatalogoConceptosRepository
{
    /**
     * De dónde sale cada descripción, en orden de preferencia. La primera que
     * tenga la clave pone la descripción principal; las demás se conservan como
     * alternativas, porque no siempre coinciden y ocultar la discrepancia sería
     * peor que mostrarla.
     *
     * El orden no es arbitrario:
     *   - `conceptosTerceros` va primero en su terreno: son 24 filas curadas con
     *     el nombre real de cada tercero (la aseguradora, la comercializadora).
     *   - `cat_conceptos` es el catálogo general más completo (92% de cobertura).
     *   - `conceptos202224` cubre un 96% pero es de un trienio concreto, así que
     *     va después: describe bien, pero no es el catálogo vigente.
     *   - el resto rellena huecos.
     *
     * 'clave' => null significa que la tabla ya trae la clave compuesta.
     */
    private const FUENTES = [
        'conceptosTerceros' => [
            'tabla' => 'conceptosTerceros',
            'clave' => null,
            'columnas' => ['concepto AS clave', 'descripcion AS d'],
            'etiqueta' => 'Terceros',
        ],
        'cat_conceptos' => [
            'tabla' => 'cat_conceptos',
            'clave' => ['tipocon', 'concepto', 'antecedente'],
            'columnas' => ['tipocon', 'concepto', 'antecedente', '`descripcin` AS d', 'partida'],
            'etiqueta' => 'Catálogo general',
        ],
        'conceptos202224' => [
            'tabla' => 'conceptos202224',
            'clave' => ['TIPO', 'CONCEPTOENT', 'PARTIDAANTECEDENTEENT'],
            'columnas' => ['TIPO', 'CONCEPTOENT', 'PARTIDAANTECEDENTEENT', 'DESCRIPCIONENTIDAD AS d', 'PARTIDAPRESUP AS partida'],
            'etiqueta' => 'Conceptos 2022-2024',
        ],
        'cptosPartidas' => [
            'tabla' => 'cptosPartidas',
            'clave' => ['tipo', 'concepto', 'partida'],
            'columnas' => ['tipo', 'concepto', 'partida', 'descripcion AS d', 'objetoGasto AS partida2'],
            'etiqueta' => 'Partidas',
        ],
        'conceptospartida' => [
            'tabla' => 'conceptospartida',
            'clave' => ['tipo', 'con', 'ant'],
            'columnas' => ['tipo', 'con', 'ant', 'des AS d', 'pa5 AS partida'],
            'etiqueta' => 'Concepto-partida',
        ],
        'cat_conceptos_nvo' => [
            'tabla' => 'cat_conceptos_nvo',
            'clave' => ['tipo', 'cpto', 'pa'],
            'columnas' => ['tipo', 'cpto', 'pa', 'descripcion AS d', 'ptda AS partida'],
            'etiqueta' => 'Catálogo nuevo',
        ],
        'acum_conceptos' => [
            'tabla' => 'acum_conceptos',
            'clave' => ['tipo', 'cpto', 'pa'],
            'columnas' => ['tipo', 'cpto', 'pa', 'descripcion AS d', 'ptda AS partida'],
            'etiqueta' => 'Acumulados',
        ],
    ];

    /**
     * Primer carácter de la clave: 1 percepción, 2 deducción.
     *
     * Verificado contra los importes reales: 10700 SUELDOS BASE y 155AG AYUDA
     * PARA GASTOS son percepciones; 20100 ISR y 246OG (comercializadora) son
     * deducciones. Coincide con la columna perc_ded de `cat_per_ded`.
     */
    private const NATURALEZA = ['1' => 'Percepción', '2' => 'Deducción'];

    /**
     * El catálogo completo, indexado por clave.
     *
     * @return array<string,array{
     *     clave:string, descripcion:string, naturaleza:string,
     *     partida:string, fuentes:list<string>,
     *     alternativas:list<array{fuente:string,descripcion:string}>
     * }>
     */
    public static function todos(bool $recargar = false): array
    {
        static $cache = null;

        if ($cache !== null && !$recargar) {
            return $cache;
        }

        $cache = [];

        foreach (self::FUENTES as $nombre => $fuente) {
            foreach (self::leer($fuente) as $clave => $filas) {
                foreach ($filas as $fila) {
                    // (string) obligatorio: PHP convierte a int las claves
                    // numéricas de un arreglo, así que '250' vuelve como 250 y
                    // str_starts_with() reventaría con strict_types.
                    self::acumular($cache, (string) $clave, $nombre, $fuente['etiqueta'], $fila);
                }
            }
        }

        // SORT_STRING porque las claves vienen mezcladas: '246OG' es cadena y
        // 250 volvió entero. Sin esto el orden saldría incoherente.
        ksort($cache, SORT_STRING);

        return $cache;
    }

    /**
     * Filas de una fuente, ya con la clave compuesta.
     *
     * @return array<string,list<array{d:string,partida:string}>>
     */
    private static function leer(array $fuente): array
    {
        $sql = 'SELECT ' . implode(', ', $fuente['columnas']) . ' FROM `' . $fuente['tabla'] . '`';

        try {
            $rows = Database::getInstance()->query($sql);
        } catch (\Throwable $e) {
            // Una tabla que ya no exista no debe tumbar el catálogo entero: se
            // pierde su aporte y las demás siguen respondiendo.
            return [];
        }

        $salida = [];

        foreach ($rows as $row) {
            $clave = $fuente['clave'] === null
                ? self::normalizar((string) ($row['clave'] ?? ''))
                : self::componer($row, $fuente['clave']);

            $descripcion = self::limpiar((string) ($row['d'] ?? ''));

            if ($clave === '' || $descripcion === '') {
                continue;
            }

            $salida[$clave][] = [
                'd' => $descripcion,
                'partida' => trim((string) ($row['partida'] ?? $row['partida2'] ?? '')),
            ];
        }

        return $salida;
    }

    /** TIPO + CONCEPTO + ANTECEDENTE, cada pieza sin espacios. */
    private static function componer(array $row, array $partes): string
    {
        $clave = '';

        foreach ($partes as $parte) {
            $clave .= trim((string) ($row[$parte] ?? ''));
        }

        return self::normalizar($clave);
    }

    private static function normalizar(string $clave): string
    {
        $clave = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $clave) ?? '');

        // Se aceptan de 3 (la familia: 250, 264) a 5 (la subclave: 26430).
        return (strlen($clave) >= 3 && strlen($clave) <= 5) ? $clave : '';
    }

    /**
     * Las descripciones vienen con saltos de línea y espacios dobles metidos a
     * mano (cat_per_ded parte "AGUINALDO…\n(PERSONAL ACTIVO)").
     */
    private static function limpiar(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }

    /** Mete una fila en el acumulado, respetando la precedencia de fuentes. */
    private static function acumular(
        array &$cache,
        string $clave,
        string $fuente,
        string $etiqueta,
        array $fila
    ): void {
        if (!isset($cache[$clave])) {
            $cache[$clave] = [
                'clave' => $clave,
                'descripcion' => $fila['d'],
                'naturaleza' => self::NATURALEZA[substr($clave, 0, 1)] ?? '',
                'partida' => $fila['partida'],
                'fuentes' => [$etiqueta],
                'alternativas' => [],
            ];

            return;
        }

        $entrada = &$cache[$clave];

        if (!in_array($etiqueta, $entrada['fuentes'], true)) {
            $entrada['fuentes'][] = $etiqueta;
        }

        if ($entrada['partida'] === '' && $fila['partida'] !== '') {
            $entrada['partida'] = $fila['partida'];
        }

        // Una descripción distinta a la principal se guarda aparte. No se
        // descarta: que dos catálogos no coincidan es justo lo que hay que ver.
        if (self::equivalen($entrada['descripcion'], $fila['d'])) {
            return;
        }

        foreach ($entrada['alternativas'] as $alterna) {
            if (self::equivalen($alterna['descripcion'], $fila['d'])) {
                return;
            }
        }

        $entrada['alternativas'][] = ['fuente' => $etiqueta, 'descripcion' => $fila['d']];
    }

    /** ¿Son la misma descripción salvo acentos, caja y puntuación? */
    private static function equivalen(string $a, string $b): bool
    {
        $plano = static function (string $t): string {
            $t = mb_strtoupper($t, 'UTF-8');
            $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);

            return preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
        };

        return $plano($a) === $plano($b);
    }

    /**
     * Busca en el catálogo por clave o por descripción.
     *
     * Una consulta que parece clave (3 a 5 alfanuméricos) busca por prefijo de
     * clave; cualquier otra cosa busca dentro del texto. Así "264" trae la
     * familia completa y "fovissste" trae todo lo que la mencione.
     *
     * OJO con $tope: recorta el resultado. El modal NO usa este método —se trae
     * el catálogo entero con todos() y filtra en el navegador— justamente porque
     * llamarlo sin consulta devolvía solo las primeras 300 claves de 426, y
     * buscar 258 no encontraba nada pese a existir.
     *
     * @return array{filas:list<array<string,mixed>>,total:int,truncado:bool}
     */
    public static function buscar(string $consulta, int $tope = 300): array
    {
        $consulta = trim($consulta);
        $catalogo = self::todos();

        if ($consulta === '') {
            $encontradas = array_values($catalogo);
        } else {
            $porClave = preg_match('/^[0-9A-Za-z]{1,5}$/', $consulta) === 1;
            $aguja = mb_strtoupper($consulta, 'UTF-8');
            $encontradas = [];

            foreach ($catalogo as $entrada) {
                // Se usa $entrada['clave'] y no la clave del arreglo: esa pudo
                // volver como entero (ver todos()), y aquí hace falta cadena.
                $coincide = $porClave
                    ? str_starts_with($entrada['clave'], $aguja)
                    : mb_stripos($entrada['descripcion'], $consulta) !== false;

                // Buscando por texto, también valen las alternativas.
                if (!$coincide && !$porClave) {
                    foreach ($entrada['alternativas'] as $alterna) {
                        if (mb_stripos($alterna['descripcion'], $consulta) !== false) {
                            $coincide = true;
                            break;
                        }
                    }
                }

                if ($coincide) {
                    $encontradas[] = $entrada;
                }
            }
        }

        $total = count($encontradas);

        return [
            'filas' => array_slice($encontradas, 0, $tope),
            'total' => $total,
            'truncado' => $total > $tope,
        ];
    }

    /** Cuántas claves tiene el catálogo y de dónde salen. */
    public static function resumenFuentes(): array
    {
        $catalogo = self::todos();
        $porFuente = [];

        foreach ($catalogo as $entrada) {
            foreach ($entrada['fuentes'] as $fuente) {
                $porFuente[$fuente] = ($porFuente[$fuente] ?? 0) + 1;
            }
        }

        arsort($porFuente);

        return ['claves' => count($catalogo), 'porFuente' => $porFuente];
    }
}
