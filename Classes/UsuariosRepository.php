<?php

declare(strict_types=1);

namespace App;

/**
 * Lectura de la tabla `users`, que es contra la que autentica el sistema.
 *
 * Solo SELECT. Los permisos de módulo NO viven aquí: van en config/permisos.json
 * (ver includes/permisos.php), porque `catalogos` es de solo lectura para este
 * proyecto y no se le crean tablas.
 */
class UsuariosRepository
{
    /**
     * Usuarios que pueden iniciar sesión, ordenados por login.
     *
     * Se descartan las filas con `user` vacío: la tabla trae ocho sin login ni
     * nombre (ids 3, 9, 17, 20, 21, 22, 25, 26) que son capturas a medias. Nadie
     * puede entrar con ellas, así que en una pantalla de permisos solo estorban.
     *
     * La contraseña no se selecciona nunca, ni siquiera para descartarla después.
     *
     * @return list<array{user:string,nombre:string,rol:string}>
     */
    public static function todos(): array
    {
        $rows = Database::getInstance()->query(
            "SELECT `user`, `nombre`, `rol`
               FROM `users`
              WHERE `user` IS NOT NULL AND TRIM(`user`) <> ''
              ORDER BY `user`"
        );

        $usuarios = [];

        foreach ($rows as $row) {
            $login = trim((string) $row['user']);

            if ($login === '') {
                continue;
            }

            $usuarios[] = [
                'user' => $login,
                'nombre' => trim((string) ($row['nombre'] ?? '')),
                'rol' => trim((string) ($row['rol'] ?? '')),
            ];
        }

        return $usuarios;
    }

    /** ¿Existe este login? Se usa para no guardar permisos de un usuario inventado. */
    public static function existe(string $login): bool
    {
        foreach (self::todos() as $usuario) {
            if (strcasecmp($usuario['user'], $login) === 0) {
                return true;
            }
        }

        return false;
    }
}
