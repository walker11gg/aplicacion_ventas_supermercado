<?php
class Factura
{
    public static function crearEnTransaccion(PDO $pdo, int $idVenta, float $total): int
    {
        if ($idVenta <= 0) {
            throw new InvalidArgumentException('La venta no es válida.');
        }

        $existente = $pdo->prepare(
            'SELECT idFactura FROM Factura WHERE idVenta = :idVenta LIMIT 1 FOR UPDATE'
        );
        $existente->execute([':idVenta' => $idVenta]);
        if ($existente->fetchColumn() !== false) {
            throw new DomainException('La venta ya tiene una factura registrada.');
        }

        $insertar = $pdo->prepare(
            'INSERT INTO Factura
                (idVenta, numeroConsecutivo, fechaEmision, total)
             VALUES
                (:idVenta, :numeroConsecutivo, CURRENT_TIMESTAMP, :total)'
        );
        $marker = 'TEMP-' . $idVenta;
        $insertar->execute([
            ':idVenta' => $idVenta,
            ':numeroConsecutivo' => $marker,
            ':total' => number_format(round($total, 2), 2, '.', ''),
        ]);
        $idFactura = (int) $pdo->lastInsertId();
        $numero = 'FAC-' . str_pad((string) $idFactura, 6, '0', STR_PAD_LEFT);

        $actualizar = $pdo->prepare(
            'UPDATE Factura SET numeroConsecutivo = :numero WHERE idFactura = :idFactura'
        );
        $actualizar->execute([
            ':numero' => $numero,
            ':idFactura' => $idFactura,
        ]);

        return $idFactura;
    }

    public static function listar(): array
    {
        $consulta = Database::getConexion()->prepare(
            'SELECT f.idFactura, f.idVenta, f.numeroConsecutivo, f.fechaEmision,
                    f.total, v.estado
             FROM Factura f
             INNER JOIN Venta v ON v.idVenta = f.idVenta
             ORDER BY f.fechaEmision DESC, f.idFactura DESC'
        );
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public static function obtenerPorId(int $idFactura): ?array
    {
        if ($idFactura <= 0) {
            throw new InvalidArgumentException('La factura no es válida.');
        }
        $consulta = Database::getConexion()->prepare(
            'SELECT f.*, v.idUsuario, v.fecha AS fechaVenta, v.estado, v.impuesto, pg.metodoPago,
                    u.nombre, u.apellido
             FROM Factura f
             INNER JOIN Venta v ON v.idVenta = f.idVenta
             INNER JOIN Usuario u ON u.idUsuario = v.idUsuario
             LEFT JOIN Pago pg ON pg.idVenta = v.idVenta
             WHERE f.idFactura = :idFactura
             LIMIT 1'
        );
        $consulta->execute([':idFactura' => $idFactura]);
        $factura = $consulta->fetch();
        return $factura ?: null;
    }

    public static function detallesPorVenta(int $idVenta): array
    {
        $consulta = Database::getConexion()->prepare(
            'SELECT dv.idProducto, dv.cantidad, dv.precioUnitario, dv.subtotal,
                    p.nombre, p.codigoBarras
             FROM DetalleVenta dv
             INNER JOIN Producto p ON p.idProducto = dv.idProducto
             WHERE dv.idVenta = :idVenta
             ORDER BY dv.idDetalleVenta'
        );
        $consulta->execute([':idVenta' => $idVenta]);
        return $consulta->fetchAll();
    }
}