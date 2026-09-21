<?php

namespace App;

/**
 * Consultas de solo lectura sobre la base BPM para el dashboard de nómina.
 * El usuario de BPM únicamente tiene permisos SELECT: este repositorio no
 * escribe nada, solo lee producto_nomina y detalle_nomina.
 */
class NominaRepository
{
    private const ESTADOS = [
        'imp' => 'Impresos',
        'can' => 'Cancelados',
        'act' => 'Activos (pendientes de timbrar)',
        'inv' => 'Invisibles',
    ];

    /**
     * Columnas del detalle de registros, en el orden que se muestran en la
     * tabla. Sirve también de whitelist para ORDER BY / filtros por columna,
     * ya que esos índices vienen del navegador (DataTables).
     */
    private const COLUMNAS = ['producto', 'estado', 'rfc', 'total1', 'total2', 'fechai', 'fechaf'];

    public static function estadoLabels(): array
    {
        return self::ESTADOS;
    }

    public static function columnas(): array
    {
        return self::COLUMNAS;
    }

    /**
     * Quincena actual (1-24) según la fecha del servidor: dos quincenas por
     * mes, del 1 al 15 y del 16 al fin de mes. Se usa solo como respaldo si
     * producto_nomina todavía no tiene ninguna quincena cargada en el año.
     */
    public static function currentQuincena(): int
    {
        $month = (int) date('n');
        $day = (int) date('j');

        return ($month - 1) * 2 + ($day <= 15 ? 1 : 2);
    }

    /**
     * Última quincena (1-24) con productos ya cargados en producto_nomina
     * para el año dado. Consulta ligera: producto_nomina es tabla catálogo.
     *
     * Ojo: las claves de una quincena suelen cargarse antes de que exista un
     * solo registro en detalle_nomina, así que esto va por delante de lo que
     * el dashboard puede mostrar. Para el periodo por defecto se usa
     * ultimaQuincenaConRegistros().
     *
     * El CAST es necesario porque mes es de texto: sin él MAX() ordena
     * alfabéticamente y '9' queda por encima de '17'.
     */
    public static function ultimaQuincenaConDatos(int $anio): ?int
    {
        $rows = Database::get('bpm')->query(
            'SELECT MAX(CAST(mes AS UNSIGNED)) AS max_mes FROM producto_nomina WHERE anio = ?',
            [(string) $anio]
        );

        $maxMes = $rows[0]['max_mes'] ?? null;

        return $maxMes !== null ? (int) $maxMes : null;
    }

    /**
     * Última quincena (1-24) del año que ya tiene registros en detalle_nomina,
     * sin importar su estado. Es la que el dashboard abre por defecto: siendo
     * esto un monitor de timbrado, interesa la quincena donde queda trabajo
     * por hacer, no la última ya terminada.
     *
     * Deliberadamente NO se filtra por estado='imp': una quincena recién
     * pagada llega con todos sus registros en 'act' (pendientes de timbrar) y
     * es justo la que hay que vigilar. Filtrando por timbrados se quedaba
     * mostrando la anterior hasta que alguien timbrara el primer registro.
     *
     * Es distinto de ultimaQuincenaConDatos(), que mira producto_nomina: ahí
     * las claves suelen cargarse antes de que exista un solo registro de
     * detalle, y esa quincena todavía no tiene nada que mostrar.
     *
     * El CAST sobre mes es necesario porque la columna es de texto: sin él
     * MAX() ordenaría alfabéticamente y '9' quedaría por encima de '17'.
     */
    public static function ultimaQuincenaConRegistros(int $anio): ?int
    {
        $rows = Database::get('bpm')->query(
            'SELECT MAX(CAST(p.mes AS UNSIGNED)) AS q
               FROM producto_nomina p
              WHERE p.anio = ?
                AND EXISTS (
                      SELECT 1 FROM detalle_nomina d
                       WHERE d.producto = p.clave
                    )',
            [(string) $anio]
        );

        $q = $rows[0]['q'] ?? null;

        return $q !== null ? (int) $q : null;
    }

    /**
     * Periodo que el dashboard muestra por defecto: la quincena más reciente
     * con registros cargados, buscando del año más nuevo hacia atrás. Si
     * ninguna tiene (base vacía), se cae al calendario tomando la quincena
     * anterior a la que está en curso, que es la que se está pagando.
     *
     * @return array{anio:int,quincena:int}
     */
    public static function periodoVigente(): array
    {
        foreach (self::aniosDisponibles() as $anio) {
            $quincena = self::ultimaQuincenaConRegistros($anio);
            if ($quincena !== null) {
                return ['anio' => $anio, 'quincena' => $quincena];
            }
        }

        $anio = (int) date('Y');
        $quincena = self::currentQuincena() - 1;

        if ($quincena < 1) {
            $quincena = 24;
            $anio--;
        }

        return ['anio' => $anio, 'quincena' => $quincena];
    }

    /**
     * Años con datos en producto_nomina, más reciente primero.
     */
    public static function aniosDisponibles(): array
    {
        $rows = Database::get('bpm')->query('SELECT DISTINCT anio FROM producto_nomina ORDER BY anio DESC');

        return array_map('intval', array_column($rows, 'anio'));
    }

    /**
     * Claves de producto_nomina para un año (y opcionalmente una quincena).
     * Sin quincena, junta las claves de las 24 quincenas del año.
     */
    public static function productosClaves(int $anio, ?int $quincena = null): array
    {
        $db = Database::get('bpm');

        if ($quincena !== null) {
            $rows = $db->query(
                'SELECT DISTINCT clave FROM producto_nomina WHERE anio = ? AND mes = ?',
                [(string) $anio, (string) $quincena]
            );
        } else {
            $rows = $db->query(
                'SELECT DISTINCT clave FROM producto_nomina WHERE anio = ?',
                [(string) $anio]
            );
        }

        return array_column($rows, 'clave');
    }

    /**
     * Conteo por estado en detalle_nomina para los productos de un año
     * (y opcionalmente una quincena).
     *
     * @return array<string,int> clave de estado => total
     */
    public static function estadoCounts(int $anio, ?int $quincena = null): array
    {
        $claves = self::productosClaves($anio, $quincena);
        $counts = array_fill_keys(array_keys(self::ESTADOS), 0);

        if (!$claves) {
            return $counts;
        }

        $placeholders = implode(',', array_fill(0, count($claves), '?'));
        $rows = Database::get('bpm')->query(
            "SELECT estado, COUNT(*) AS total FROM detalle_nomina WHERE producto IN ($placeholders) GROUP BY estado",
            $claves
        );

        foreach ($rows as $row) {
            $estado = strtolower(trim((string) $row['estado']));
            if (array_key_exists($estado, $counts)) {
                $counts[$estado] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /**
     * Arma el WHERE + params comunes a registros()/contarRegistros():
     * producto IN (claves) más un LIKE por cada filtro de columna recibido.
     * Los nombres de columna de $filtros siempre se validan contra la
     * whitelist COLUMNAS antes de entrar al SQL.
     */
    private static function condicion(array $claves, array $filtros): array
    {
        $placeholders = implode(',', array_fill(0, count($claves), '?'));
        $where = "producto IN ($placeholders)";
        $params = $claves;

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
     * Filas de detalle_nomina para los productos de una quincena, con
     * filtro opcional por columna, orden y paginación. $ordenColumna es un
     * índice hacia COLUMNAS (whitelist), nunca un nombre libre.
     *
     * @return list<array<string,mixed>>
     */
    public static function registros(array $claves, array $filtros, int $ordenColumna, string $ordenDireccion, int $limit, int $offset): array
    {
        if (!$claves) {
            return [];
        }

        $columna = self::COLUMNAS[$ordenColumna] ?? self::COLUMNAS[0];
        $direccion = strtolower($ordenDireccion) === 'desc' ? 'DESC' : 'ASC';

        [$where, $params] = self::condicion($claves, $filtros);
        $cols = implode(', ', self::COLUMNAS);

        $params[] = $limit;
        $params[] = $offset;

        return Database::get('bpm')->query(
            "SELECT $cols FROM detalle_nomina WHERE $where ORDER BY $columna $direccion LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Total de filas de detalle_nomina que cumplen la misma condición que
     * registros() (sin LIMIT), para la paginación de DataTables.
     */
    public static function contarRegistros(array $claves, array $filtros): int
    {
        if (!$claves) {
            return 0;
        }

        [$where, $params] = self::condicion($claves, $filtros);
        $rows = Database::get('bpm')->query("SELECT COUNT(*) AS total FROM detalle_nomina WHERE $where", $params);

        return (int) ($rows[0]['total'] ?? 0);
    }
}
