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

    public static function consultarExistencias(): array
    {
        return self::listar();
    }

    public static function consultarAlertas(): array
    {
        return self::alertasStock();
    }

    /** Descarga todas las líneas con bloqueos de fila y registra las salidas. */
    public static function descontarParaVenta(PDO $pdo, int $idVenta, int $idUsuario): void
    {
        if (!$pdo->inTransaction()) {
            throw new LogicException('La actualización de inventario requiere una transacción activa.');
        }

        $consulta = $pdo->prepare(
            'SELECT d.cantidad, p.nombre, i.idInventario, i.cantidadActual
             FROM DetalleVenta d
             INNER JOIN Producto p ON p.idProducto = d.idProducto
             INNER JOIN Inventario i ON i.idInventario = p.idInventario
             WHERE d.idVenta = :idVenta
             ORDER BY i.idInventario
             FOR UPDATE'
        );
        $consulta->execute([':idVenta' => $idVenta]);
        $filas = $consulta->fetchAll();
        if ($filas === []) {
            throw new DomainException('No se puede cobrar una venta sin productos.');
        }

        $porInventario = [];
        foreach ($filas as $fila) {
            $idInventario = (int) $fila['idInventario'];
            if (!isset($porInventario[$idInventario])) {
                $porInventario[$idInventario] = [
                    'cantidadActual' => (int) $fila['cantidadActual'],
                    'cantidadSolicitada' => 0,
                    'nombre' => $fila['nombre'],
                ];
            }
            $porInventario[$idInventario]['cantidadSolicitada'] += (int) $fila['cantidad'];
        }

        foreach ($porInventario as $existencia) {
            if ($existencia['cantidadSolicitada'] > $existencia['cantidadActual']) {
                throw new DomainException(
                    'Stock insuficiente para ' . $existencia['nombre']
                    . ': disponibles ' . $existencia['cantidadActual']
                    . ', solicitados ' . $existencia['cantidadSolicitada'] . '.'
                );
            }
        }

        $actualizar = $pdo->prepare(
            'UPDATE Inventario
             SET cantidadActual = cantidadActual - :cantidadDescontar
             WHERE idInventario = :idInventario AND cantidadActual >= :cantidadValidar'
        );
        foreach ($porInventario as $idInventario => $existencia) {
            $actualizar->execute([
                ':cantidadDescontar' => $existencia['cantidadSolicitada'],
                ':idInventario' => $idInventario,
                ':cantidadValidar' => $existencia['cantidadSolicitada'],
            ]);
            if ($actualizar->rowCount() !== 1) {
                throw new DomainException('El stock cambió durante el cobro. Verifica las existencias e intenta de nuevo.');
            }

            MovimientoInventario::registrarSalida(
                $pdo,
                (int) $idInventario,
                $idUsuario,
                $existencia['cantidadSolicitada'],
                $idVenta
            );
        }
    }

    private static function escaparLike(string $termino): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino);
    }
}
