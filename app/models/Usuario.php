<?php
/**
 * Modelo Usuario: acceso a la tabla `usuario` (CU-01).
 * Todas las consultas usan sentencias preparadas (PDO).
 */
class Usuario
{
    /** Busca un usuario por su nombre de usuario. Devuelve null si no existe. */
    public static function buscarPorUsuario(string $usuario): ?array
    {
        $sql = 'SELECT idUsuario, nombre, apellido, usuario, `contraseña` AS clave,
                       rol, estado, intentosFallidos, bloqueadoHasta
                FROM Usuario
                WHERE usuario = :usuario
                LIMIT 1';

        $consulta = Database::getConexion()->prepare($sql);
        $consulta->execute([':usuario' => $usuario]);
        $fila = $consulta->fetch();

        return $fila ?: null;
    }

    /**
     * Suma un intento fallido. Si se alcanza el máximo, bloquea la cuenta
     * temporalmente y devuelve la fecha de fin del bloqueo; si no, null.
     */
    public static function registrarFallo(int $idUsuario, int $intentosActuales): ?string
    {
        $pdo     = Database::getConexion();
        $nuevos  = $intentosActuales + 1;

        if ($nuevos >= MAX_INTENTOS_LOGIN) {
            $hasta = date('Y-m-d H:i:s', time() + BLOQUEO_MINUTOS * 60);
            $pdo->prepare('UPDATE Usuario SET intentosFallidos = 0, bloqueadoHasta = :hasta
                           WHERE idUsuario = :id')
                ->execute([':hasta' => $hasta, ':id' => $idUsuario]);
            return $hasta;
        }

        $pdo->prepare('UPDATE Usuario SET intentosFallidos = :n WHERE idUsuario = :id')
            ->execute([':n' => $nuevos, ':id' => $idUsuario]);
        return null;
    }

    /** Limpia el contador de intentos y el bloqueo tras un acceso correcto. */
    public static function reiniciarIntentos(int $idUsuario): void
    {
        Database::getConexion()
            ->prepare('UPDATE Usuario SET intentosFallidos = 0, bloqueadoHasta = NULL
                       WHERE idUsuario = :id')
            ->execute([':id' => $idUsuario]);
    }
}
