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
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($this->connection->connect_error) {
            throw new Exception('Error en la conexión: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset('utf8mb4');
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
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
