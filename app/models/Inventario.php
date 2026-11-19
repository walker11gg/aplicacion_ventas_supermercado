<?php
class Inventario
{
    public static function listar(string $busqueda = ''): array
    {
        $sql = 'SELECT p.idProducto, p.codigoBarras, p.nombre, p.estado,
                       i.idInventario, i.cantidadActual, i.stockMinimo,
                       i.stockMaximo, i.ubicacion
                FROM Producto p
                INNER JOIN Inventario i ON i.idInventario = p.idInventario';
        $parametros = [];
        $termino = trim($busqueda);
        if ($termino !== '') {
            $sql .= ' WHERE (p.nombre LIKE :nombre ESCAPE "\\\\" OR p.codigoBarras = :codigo)';
            $parametros[':nombre'] = '%' . self::escaparLike($termino) . '%';
            $parametros[':codigo'] = $termino;
        }
        $sql .= ' ORDER BY p.nombre';

        $consulta = Database::getConexion()->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public static function alertasStock(): array
    {
        $consulta = Database::getConexion()->prepare(
            'SELECT p.idProducto, p.codigoBarras, p.nombre,
                    i.idInventario, i.cantidadActual, i.stockMinimo, i.ubicacion,
                    (i.stockMinimo - i.cantidadActual) AS diferencia
             FROM Producto p
             INNER JOIN Inventario i ON i.idInventario = p.idInventario
             WHERE p.estado = :estado
               AND i.cantidadActual <= i.stockMinimo
             ORDER BY i.cantidadActual ASC,
                      (i.stockMinimo - i.cantidadActual) DESC,
                      p.nombre ASC'
        );
        $consulta->execute([':estado' => 'Activo']);
        return $consulta->fetchAll();
    }

    private static function escaparLike(string $termino): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino);
    }
}