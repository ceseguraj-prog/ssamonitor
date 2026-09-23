<?php

declare(strict_types=1);

namespace App;

/**
 * Consultas del cierre de Regularizados y Eventuales IMSS.
 *
 * Todo lo de aquí es SELECT contra BPM. El módulo no escribe una sola fila:
 * lo que produce son archivos en modules/extraccion/storage, que se borran
 * solos, y texto que el navegador copia al portapapeles.
 *
 * El trámite tiene dos universos, cada uno definido por un criterio sobre el
 * detalle y, cuando hace falta, por la familia de producto donde vale:
 *
 *   REGULARIZADOS  unidad = 'REG', pero solo en los productos de base (PRD*).
 *                  Cuáles de ellos entran cambia de quincena en quincena, y
 *                  por eso se descubren en vez de escribirlos a mano.
 *   IMSS BIENESTAR clavep LIKE '%IMSS%'. Son eventuales, así que en la
 *                  práctica llegan todos en el producto de eventuales, pero
 *                  se busca igual en todos los del periodo: si un mes se
 *                  reparten, el conteo no se parte a la mitad sin avisar.
 *
 * El límite a PRD* del primer grupo es el filtro que se ponía a mano en el IN,
 * y no es una simplificación: los productos de eventuales también traen filas
 * con unidad = 'REG', pero esos son regularizados eventuales y no pertenecen a
 * este cierre. Se ven en la pantalla, apartados y sin contar, para que quede
 * claro que se descartan adrede y no porque nadie los haya mirado.
 */
class ExtraccionRepository
{
    /** Estados de un timbre, en el orden en que se leen en el resumen. */
    public const ESTADOS = ['act', 'can', 'inv', 'imp'];

    /**
     * Los dos grupos del trámite.
     *
     * 'where' es el fragmento de SQL que define el universo y 'params' sus
     * valores; ambos son literales de este archivo y nunca se arman con nada
     * que venga del navegador.
     *
     * 'prefijos' acota en qué familia de producto vale el criterio. Vacío
     * significa "en cualquiera". Los regularizados de verdad son los de los
     * productos de base, que empiezan con PRD; las filas REG de un producto de
     * eventuales son regularizados eventuales y son otro trámite.
     */
    public const GRUPOS = [
        'reg' => [
            'nombre'    => 'REGULARIZADOS',
            'etiqueta'  => 'Regularizados',
            'criterio'  => "unidad = 'REG'",
            'where'     => 'unidad = ?',
            'params'    => ['REG'],
            'prefijos'  => ['PRD'],
            'archivo'   => 'Reg',
        ],
        'imss' => [
            'nombre'    => 'IMSS',
            'etiqueta'  => 'IMSS',
            'criterio'  => "clavep LIKE '%IMSS%'",
            'where'     => 'clavep LIKE ?',
            'params'    => ['%IMSS%'],
            'prefijos'  => [],
            'archivo'   => 'IMSS',
        ],
    ];

    /**
     * ¿Este producto pertenece a la familia donde el grupo vale?
     *
     * Es lo que antes hacía el IN escrito a mano. Un grupo sin prefijos acepta
     * cualquier producto.
     */
    public static function esDelGrupo(string $clave, string $idGrupo): bool
    {
        $prefijos = self::GRUPOS[$idGrupo]['prefijos'] ?? [];

        if (!$prefijos) {
            return true;
        }

        foreach ($prefijos as $prefijo) {
            if (stripos($clave, (string) $prefijo) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los productos del periodo que un grupo admite, sin mirar todavía si
     * tienen filas. Lo usan los endpoints para validar lo que llega del
     * navegador sin volver a consultar el detalle.
     *
     * @param list<string> $claves
     * @return list<string>
     */
    public static function admitidos(array $claves, string $idGrupo): array
    {
        return array_values(array_filter(
            $claves,
            static fn (string $clave): bool => self::esDelGrupo($clave, $idGrupo)
        ));
    }

    /**
     * Qué productos del periodo aporta cada grupo, con su desglose por estado.
     *
     * Esto es el paso que se hacía a ojo antes de armar el IN: mirar qué
     * productos de la quincena traen filas del grupo, que cambia de quincena
     * en quincena.
     *
     * Un producto solo entra si tiene al menos una fila que cumpla el criterio.
     * Los que lo cumplen pero están fuera de la familia del grupo —las filas
     * REG de un producto de eventuales, que son regularizados eventuales— no se
     * cuentan, pero sí se devuelven aparte en 'descartados': se descartan
     * adrede, y eso solo se distingue de un olvido si se ven.
     *
     * @return array<string,array{
     *     productos:list<array{clave:string,counts:array<string,int>,total:int}>,
     *     descartados:list<array{clave:string,counts:array<string,int>,total:int}>
     * }>
     */
    public static function inventario(int $anio, int $quincena): array
    {
        $claves = array_map('strval', NominaRepository::productosClaves($anio, $quincena));
        $salida = [];

        foreach (self::GRUPOS as $id => $grupo) {
            $productos = [];
            $descartados = [];

            foreach (self::countsPorProducto($claves, $grupo) as $clave => $counts) {
                if (array_sum($counts) === 0) {
                    continue;
                }

                $fila = [
                    'clave'  => (string) $clave,
                    'counts' => $counts,
                    'total'  => array_sum($counts),
                ];

                if (self::esDelGrupo((string) $clave, (string) $id)) {
                    $productos[] = $fila;
                } else {
                    $descartados[] = $fila;
                }
            }

            // El producto que más pesa primero: es el que explica la quincena.
            $porPeso = static fn (array $a, array $b): int => $b['total'] <=> $a['total'];
            usort($productos, $porPeso);
            usort($descartados, $porPeso);

            $salida[$id] = ['productos' => $productos, 'descartados' => $descartados];
        }

        return $salida;
    }

    /**
     * Conteo por estado de varios productos dentro del universo de un grupo.
     *
     * Una sola consulta para todos los productos, no una por cada uno: con los
     * cinco productos de una quincena y los dos grupos eran diez viajes a la
     * base y cinco segundos de espera antes de que la pantalla pintara nada.
     *
     * Devuelve una entrada por cada clave pedida, aunque la base no tenga ni una
     * fila suya: quien llama necesita el cero explícito para poder distinguir
     * "no aplica" de "no consultado".
     *
     * @param list<string> $claves
     * @return array<string,array<string,int>> clave => counts con las cuatro claves
     */
    private static function countsPorProducto(array $claves, array $grupo): array
    {
        $counts = [];

        foreach ($claves as $clave) {
            $counts[$clave] = array_fill_keys(self::ESTADOS, 0);
        }

        if (!$claves) {
            return $counts;
        }

        $marcas = implode(',', array_fill(0, count($claves), '?'));

        $rows = Database::get('bpm')->query(
            "SELECT producto, estado, COUNT(*) AS total
               FROM detalle_nomina
              WHERE producto IN ($marcas) AND {$grupo['where']}
              GROUP BY producto, estado",
            array_merge($claves, $grupo['params'])
        );

        foreach ($rows as $row) {
            $producto = trim((string) $row['producto']);
            $estado = strtolower(trim((string) $row['estado']));

            if (isset($counts[$producto]) && array_key_exists($estado, $counts[$producto])) {
                $counts[$producto][$estado] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /**
     * Desglose por producto de un grupo, ya con los productos elegidos.
     *
     * Se vuelve a consultar en vez de reutilizar lo que pintó la pantalla:
     * entre que se abre el módulo y se pulsa Generar puede haberse timbrado
     * algo, y el Excel y los .txt tienen que contar lo mismo.
     *
     * @param list<string> $productos
     * @return list<array{clave:string,counts:array<string,int>,total:int}>
     */
    public static function desglose(string $idGrupo, array $productos): array
    {
        $grupo = self::GRUPOS[$idGrupo];
        $filas = [];

        // Se respeta el orden en que llegaron los productos, que es el que el
        // usuario vio en la pantalla al marcar las casillas.
        foreach (self::countsPorProducto(array_values($productos), $grupo) as $clave => $counts) {
            $filas[] = [
                'clave'  => (string) $clave,
                'counts' => $counts,
                'total'  => array_sum($counts),
            ];
        }

        return $filas;
    }

    /**
     * Los UUID que van al archivo de extracción: los timbres impresos del
     * grupo. Llega de uno en uno porque una quincena de regularizados ronda
     * los dos mil y el archivo se escribe conforme se lee, sin juntarlos
     * todos en memoria.
     *
     * @param list<string> $productos
     * @return \Generator<int,string>
     */
    public static function uuidsImpresos(string $idGrupo, array $productos): \Generator
    {
        if (!$productos) {
            return;
        }

        $grupo = self::GRUPOS[$idGrupo];
        $marcas = implode(',', array_fill(0, count($productos), '?'));

        $filas = Database::get('bpm')->stream(
            "SELECT uuid
               FROM detalle_nomina
              WHERE producto IN ($marcas) AND {$grupo['where']} AND estado = 'imp'
              ORDER BY rfc",
            array_merge($productos, $grupo['params'])
        );

        foreach ($filas as $fila) {
            $uuid = trim((string) ($fila['uuid'] ?? ''));

            // Un impreso sin UUID no existe, pero si la base lo tuviera en
            // blanco, escribirlo metería una línea vacía en el archivo de
            // extracción y el proceso de allá tronaría sin decir por qué.
            if ($uuid !== '') {
                yield $uuid;
            }
        }
    }

    /**
     * Los RFC invisibles del grupo: las retenciones que hay que anotar en las
     * notas de la quincena.
     *
     * Van ordenados por RFC y sin repetir. Un trabajador con dos conceptos
     * retenidos aparece dos veces en el detalle, y en la lista de retenciones
     * lo que interesa es a quién se le retuvo, no cuántas veces.
     *
     * @param list<string> $productos
     * @return list<array{rfc:string,producto:string,veces:int}>
     */
    public static function retenciones(string $idGrupo, array $productos): array
    {
        if (!$productos) {
            return [];
        }

        $grupo = self::GRUPOS[$idGrupo];
        $marcas = implode(',', array_fill(0, count($productos), '?'));

        $rows = Database::get('bpm')->query(
            "SELECT rfc, MIN(producto) AS producto, COUNT(*) AS veces
               FROM detalle_nomina
              WHERE producto IN ($marcas) AND {$grupo['where']} AND estado = 'inv'
              GROUP BY rfc
              ORDER BY rfc",
            array_merge($productos, $grupo['params'])
        );

        return array_map(static fn (array $r): array => [
            'rfc'      => trim((string) $r['rfc']),
            'producto' => trim((string) $r['producto']),
            'veces'    => (int) $r['veces'],
        ], $rows);
    }

    /**
     * Cómo se nombra la quincena: QNA17_26. El año va en dos dígitos porque así
     * está escrito en todas las notas anteriores.
     */
    public static function sufijo(int $anio, int $quincena): string
    {
        return sprintf('QNA%d_%02d', $quincena, $anio % 100);
    }

    /** Nombre del paquete y de la nota: RegYEvenQNA17_26. */
    public static function nombrePeriodo(int $anio, int $quincena): string
    {
        return 'RegYEven' . self::sufijo($anio, $quincena);
    }

    /** Suma de los counts de varios productos. @return array<string,int> */
    public static function totales(array $filas): array
    {
        $totales = array_fill_keys(self::ESTADOS, 0);

        foreach ($filas as $fila) {
            foreach (self::ESTADOS as $estado) {
                $totales[$estado] += (int) ($fila['counts'][$estado] ?? 0);
            }
        }

        return $totales;
    }
}
