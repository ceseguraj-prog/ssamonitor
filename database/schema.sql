CREATE DATABASE IF NOT EXISTS monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE monitor;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Para insertar un usuario de prueba, genera el hash con PHP:
--   php -r "echo password_hash('tu_password', PASSWORD_DEFAULT), PHP_EOL;"
-- y usa el resultado en el INSERT:
-- INSERT INTO users (user, nombre, password) VALUES ('admin', 'Administrador', '<hash_generado>');
