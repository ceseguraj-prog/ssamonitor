<?php

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/ExtraccionRepository.php';
require_once __DIR__ . '/Xlsx.php';

/**
 * Las dos salidas legibles del cierre: la nota en markdown y el libro de Excel.
 *
 * Viven aparte de ajax/generar.php —que es quien consulta y empaqueta— porque
 * son pura presentación y no tocan la base: así se pueden probar con datos
 * inventados, sin conexión a BPM, que es justo lo que uno quiere poder hacer
 * cuando el formato de la nota cambia.
 *
 * La forma de $grupos es la que arma generar.php:
 *   [id => [nombre, etiqueta, productos[], desglose[], totales[], retenciones[]]]
 */
class Reporte
{
    /**
     * Alineación de las columnas de números en las tablas de la nota: los
     * estados centrados y los acumulados a la derecha, que es como venían
     * escritas a mano.
     */
    private const ALINEACION_CIFRAS = [':---:', ':---:', ':---:', '---:', '---:'];

    /**
     * La nota de la quincena: la tabla por unidad, el desglose por producto y
     * los RFC retenidos.
     *
     * Las dos tablas son las mismas del Excel, con las mismas columnas y los
     * mismos totales. Que coincidan no es casualidad ni duplicación por
     * descuido: la nota es lo que se pega en el registro del trámite y el Excel
     * lo que se archiva, y si difirieran no habría forma de saber cuál manda.
     *
     * Las retenciones son los invisibles: un invisible no se timbra nunca, así
     * que es el renglón que queda fuera de la entrega y el que hay que poder
     * justificar después. Por eso van con RFC y no solo como número.
     */
    public static function markdown(string $nombrePeriodo, array $grupos): string
    {
        $conProductos = array_filter($grupos, static fn (array $g): bool => (bool) $g['productos']);

        $lineas = array_merge(
            ['# ' . $nombrePeriodo, ''],
            self::tablaUnidades($conProductos),
            ['', '## Desglose por producto', ''],
            self::tablaProductos($conProductos),
            ['', '## Retenciones / Rechazos']
        );

        foreach ($conProductos as $grupo) {
            $lineas[] = '';
            $lineas[] = '### ' . $grupo['etiqueta'];
            $lineas[] = '';

            foreach ($grupo['retenciones'] as $retencion) {
                $lineas[] = '- ' . $retencion['rfc'];
            }
        }

        return implode("\n", $lineas) . "\n";
    }

    /**
     * El resumen por unidad, con el total de cada grupo y el gran total abajo.
     *
     * @return list<string>
     */
    private static function tablaUnidades(array $grupos): array
    {
        $filas = [];
        $gran = array_fill_keys(ExtraccionRepository::ESTADOS, 0);

        foreach ($grupos as $grupo) {
            $t = $grupo['totales'];
            $filas[] = array_merge([$grupo['nombre']], self::cifras($t));

            foreach (ExtraccionRepository::ESTADOS as $estado) {
                $gran[$estado] += $t[$estado];
            }
        }

        // El gran total en negritas, igual que en el Excel: es el renglón que
        // se compara contra lo que reporta la pagaduría.
        $filas[] = array_merge(
            ['**TOTAL**'],
            array_map(static fn (int $n): string => '**' . $n . '**', self::cifras($gran))
        );

        return self::tabla(
            ['UNIDAD', 'ACT', 'CAN', 'INV', 'IMP', 'Total'],
            array_merge([':---'], self::ALINEACION_CIFRAS),
            $filas
        );
    }

    /**
     * El desglose producto por producto. Sustituye a la lista de viñetas que se
     * escribía antes: dice los mismos productos y además cuánto aportó cada uno,
     * que es lo que había que ir a buscar al Excel.
     *
     * @return list<string>
     */
    private static function tablaProductos(array $grupos): array
    {
        $filas = [];

        foreach ($grupos as $grupo) {
            foreach ($grupo['desglose'] as $fila) {
                $filas[] = array_merge(
                    [$grupo['nombre'], $fila['clave']],
                    self::cifras($fila['counts'])
                );
            }
        }

        return self::tabla(
            ['UNIDAD', 'PRODUCTO', 'ACT', 'CAN', 'INV', 'IMP', 'Total'],
            array_merge([':---', ':---'], self::ALINEACION_CIFRAS),
            $filas
        );
    }

    /**
     * Los cuatro estados en el orden de las columnas, más su suma.
     *
     * @return list<int>
     */
    private static function cifras(array $counts): array
    {
        $cifras = [];

        foreach (ExtraccionRepository::ESTADOS as $estado) {
            $cifras[] = (int) ($counts[$estado] ?? 0);
        }

        $cifras[] = array_sum($cifras);

        return $cifras;
    }

    /**
     * Envuelve unas cifras en celdas de Xlsx con el mismo estilo.
     *
     * @param list<int> $cifras
     * @return list<array{0:int,1:string}>
     */
    private static function conEstilo(array $cifras, string $estilo): array
    {
        return array_map(static fn (int $n): array => [$n, $estilo], $cifras);
    }

    /**
     * Una tabla de markdown. Las celdas van sin relleno de espacios: la nota se
     * lee ya renderizada, y alinear a mano solo haría el diff ilegible cuando
     * una cifra cambia de ancho.
     *
     * @param list<string> $encabezados
     * @param list<string> $alineacion  la fila de guiones, columna por columna
     * @param list<list<string|int>> $filas
     * @return list<string>
     */
    private static function tabla(array $encabezados, array $alineacion, array $filas): array
    {
        $renglon = static fn (array $celdas): string => '|' . implode('|', $celdas) . '|';

        $lineas = [$renglon($encabezados), $renglon($alineacion)];

        foreach ($filas as $fila) {
            $lineas[] = $renglon($fila);
        }

        return $lineas;
    }

    /**
     * El libro de Excel: una hoja con el resumen y el desglose por producto, y
     * otra con los RFC retenidos.
     *
     * El resumen de arriba es el mismo de la nota, para poder cotejar los dos
     * de un vistazo; el desglose por producto es lo que no cabe en la nota y es
     * justo lo que se pierde cuando la quincena se arma a mano.
     */
    public static function libro(string $ruta, string $nombrePeriodo, string $rango, array $grupos): void
    {
        $xlsx = new Xlsx();

        $xlsx->hoja('Resumen', [24, 18, 10, 10, 10, 12, 12], self::filasResumen($nombrePeriodo, $rango, $grupos));
        $xlsx->hoja('Retenciones', [24, 18, 20, 12], self::filasRetenciones($grupos));

        $xlsx->guardar($ruta);
    }

    /** @return list<array> */
    private static function filasResumen(string $nombrePeriodo, string $rango, array $grupos): array
    {
        $filas = [
            [[$nombrePeriodo, 'titulo']],
            [$rango],
            [],
            [['Unidad', 'encabezado'], ['ACT', 'encabezado'], ['CAN', 'encabezado'],
                ['INV', 'encabezado'], ['IMP', 'encabezado'], ['Total', 'encabezado']],
        ];

        // Las cifras salen de cifras(), la misma que arma las tablas de la nota:
        // el Excel y el markdown son dos vistas del mismo cierre, y si cada uno
        // sumara por su cuenta podrían dejar de coincidir sin que nadie lo note.
        $gran = array_fill_keys(ExtraccionRepository::ESTADOS, 0);

        foreach ($grupos as $grupo) {
            if (!$grupo['productos']) {
                continue;
            }

            $filas[] = array_merge(
                [$grupo['nombre']],
                self::conEstilo(self::cifras($grupo['totales']), 'numero')
            );

            foreach (ExtraccionRepository::ESTADOS as $estado) {
                $gran[$estado] += $grupo['totales'][$estado];
            }
        }

        $filas[] = array_merge(
            [['TOTAL', 'total']],
            self::conEstilo(self::cifras($gran), 'totalNumero')
        );

        $filas[] = [];
        $filas[] = [['Desglose por producto', 'fuerte']];
        $filas[] = [
            ['Unidad', 'encabezado'], ['Producto', 'encabezado'], ['ACT', 'encabezado'],
            ['CAN', 'encabezado'], ['INV', 'encabezado'], ['IMP', 'encabezado'],
            ['Total', 'encabezado'],
        ];

        foreach ($grupos as $grupo) {
            foreach ($grupo['desglose'] as $fila) {
                $filas[] = array_merge(
                    [$grupo['nombre'], $fila['clave']],
                    self::conEstilo(self::cifras($fila['counts']), 'numero')
                );
            }
        }

        return $filas;
    }

    /** @return list<array> */
    private static function filasRetenciones(array $grupos): array
    {
        $filas = [
            [['Retenciones (invisibles)', 'titulo']],
            ['Un invisible no se timbra: son los renglones que quedan fuera de la entrega.'],
            [],
            [['Unidad', 'encabezado'], ['Producto', 'encabezado'], ['RFC', 'encabezado'],
                ['Registros', 'encabezado']],
        ];

        $hay = false;

        foreach ($grupos as $grupo) {
            foreach ($grupo['retenciones'] as $retencion) {
                $hay = true;

                $filas[] = [
                    $grupo['nombre'], $retencion['producto'], $retencion['rfc'],
                    [$retencion['veces'], 'numero'],
                ];
            }
        }

        if (!$hay) {
            $filas[] = ['Sin retenciones en el periodo.'];
        }

        return $filas;
    }
}
