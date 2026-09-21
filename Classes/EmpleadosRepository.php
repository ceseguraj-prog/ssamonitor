<?php

namespace App;

/**
 * Consultas de solo lectura sobre empleados (base BPM). El usuario de BPM
 * únicamente tiene permisos SELECT.
 */
class EmpleadosRepository
{
    /**
     * Columnas mostradas, en orden. También es la whitelist para ORDER BY
     * y filtros por columna, ya que esos índices vienen del navegador
     * (DataTables).
     */
    private const COLUMNAS = ['nombre', 'apaterno', 'amaterno', 'rfc', 'curp', 'nss', 'email', 'cp'];

    public static function columnas(): array
    {
        return self::COLUMNAS;
    }

    private static function condicion(array $filtros): array
    {
        $where = '1=1';
        $params = [];

        foreach ($filtros as $columna => $valor) {
            if ($valor === '' || !in_array($columna, self::COLUMNAS, true)) {
                continue;
            }
            $where .= " AND $columna LIKE ?";
            $params[] = '%' . $valor . '%';
        }

        return [$where, $params];
    }

    /**
     * Filas de empleados con filtro opcional por columna, orden y
     * paginación. $ordenColumna es un índice hacia COLUMNAS (whitelist).
     *
     * @return list<array<string,mixed>>
     */
    public static function registros(array $filtros, int $ordenColumna, string $ordenDireccion, int $limit, int $offset): array
    {
        $columna = self::COLUMNAS[$ordenColumna] ?? self::COLUMNAS[0];
        $direccion = strtolower($ordenDireccion) === 'desc' ? 'DESC' : 'ASC';

        [$where, $params] = self::condicion($filtros);
        $cols = implode(', ', self::COLUMNAS);

        $params[] = $limit;
        $params[] = $offset;

        return Database::get('bpm')->query(
            "SELECT $cols FROM empleados WHERE $where ORDER BY $columna $direccion LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Total de filas que cumplen la misma condición que registros() (sin
     * LIMIT), para la paginación de DataTables.
     */
    public static function contarRegistros(array $filtros): int
    {
        [$where, $params] = self::condicion($filtros);
        $rows = Database::get('bpm')->query("SELECT COUNT(*) AS total FROM empleados WHERE $where", $params);

        return (int) ($rows[0]['total'] ?? 0);
    }
}
