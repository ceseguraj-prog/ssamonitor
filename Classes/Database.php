<?php

namespace App;

use mysqli;
use Exception;

/**
 * Conexión única (singleton) a la base de datos vía mysqli,
 * con consultas siempre parametrizadas.
 */
class Database
{
    private static array $instances = [];
    private mysqli $connection;

    private function __construct(string $host, string $user, string $pass, string $name)
    {
        $this->connection = new mysqli($host, $user, $pass, $name);

        if ($this->connection->connect_error) {
            throw new Exception('Error en la conexión: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset('utf8mb4');
    }

    public static function getInstance(): Database
    {
        return self::get('default');
    }

    /**
     * Devuelve la conexión con nombre dado. 'default' es la base del propio
     * proyecto (usuarios/sesión); 'bpm' es la base BPM de solo lectura.
     */
    public static function get(string $key = 'default'): Database
    {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = match ($key) {
                'bpm' => new self(DB_HOSTBPM, DB_USERBPM, DB_PASSBPM, DB_NAMEBPM),
                default => new self(DB_HOST, DB_USER, DB_PASS, DB_NAME),
            };
        }

        return self::$instances[$key];
    }

    /**
     * Ejecuta un SELECT parametrizado y devuelve las filas como arreglos asociativos.
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql, $params);
        $stmt->execute();

        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }

    /**
     * Igual que query(), pero entrega las filas de una en una.
     *
     * query() arma un arreglo PHP con el resultado completo, que es lo correcto
     * para unos miles de filas. Cuando la consulta barre un periodo entero de
     * las seis tablas de nómina son cientos de miles de filas de ~110 columnas,
     * y materializarlas todas a la vez se lleva cientos de MB.
     *
     * El resultado igual viaja completo del servidor al cliente —mysqli lo
     * almacena en buffer— pero del lado de PHP solo vive una fila convertida a
     * arreglo a la vez, que es de donde sale el ahorro.
     *
     * @return \Generator<int,array<string,mixed>>
     */
    public function stream(string $sql, array $params = []): \Generator
    {
        $stmt = $this->prepare($sql, $params);
        $stmt->execute();

        $result = $stmt->get_result();

        try {
            if (!$result instanceof \mysqli_result) {
                return;
            }

            while (($row = $result->fetch_assoc()) !== null) {
                yield $row;
            }
        } finally {
            // Quien consume puede abandonar el generador a medias (un break, una
            // excepción); sin esto la sentencia se quedaría abierta.
            if ($result instanceof \mysqli_result) {
                $result->free();
            }

            $stmt->close();
        }
    }

    /**
     * Ejecuta un INSERT/UPDATE/DELETE parametrizado.
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->prepare($sql, $params);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    private function prepare(string $sql, array $params)
    {
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            throw new Exception('Error preparando la consulta: ' . $this->connection->error);
        }

        if ($params) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }
}
