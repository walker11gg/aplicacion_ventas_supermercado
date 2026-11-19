<?php
class Factura
{
    private const BLOQUEO_CONSECUTIVO = 'aplicacion_ventas_factura_consecutivo';

    public static function adquirirBloqueoConsecutivo(PDO $pdo): void
    {
        $consulta = $pdo->prepare('SELECT GET_LOCK(:nombre, 10)');
        $consulta->execute([':nombre' => self::BLOQUEO_CONSECUTIVO]);
        if ((int) $consulta->fetchColumn() !== 1) {
            throw new RuntimeException('No se pudo reservar el consecutivo de factura.');
        }
    }

    public static function liberarBloqueoConsecutivo(PDO $pdo): bool
    {
        $consulta = $pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
        $consulta->execute([':nombre' => self::BLOQUEO_CONSECUTIVO]);

        return (int) $consulta->fetchColumn() === 1;
    }

    /**
     * Crea una factura dentro del pago que ya tiene reservado el consecutivo.
     */
    public static function crearEnTransaccion(PDO $pdo, int $idVenta, float $total): int
    {
        if ($idVenta <= 0 || !$pdo->inTransaction()) {
            throw new LogicException('La factura debe crearse para una venta dentro de la transacción de pago.');
        }

        $bloqueo = $pdo->prepare('SELECT IS_USED_LOCK(:nombre) = CONNECTION_ID()');
        $bloqueo->execute([':nombre' => self::BLOQUEO_CONSECUTIVO]);
        if ((int) $bloqueo->fetchColumn() !== 1) {
            throw new LogicException('No se reservó el consecutivo antes de crear la factura.');
        }

        $ventaConsulta = $pdo->prepare(
            'SELECT total, estado
             FROM Venta
             WHERE idVenta = :idVenta
             FOR UPDATE'
        );
        $ventaConsulta->execute([':idVenta' => $idVenta]);
        $venta = $ventaConsulta->fetch();
        if (!$venta || $venta['estado'] !== 'Pagada') {
            throw new DomainException('Solo se puede emitir factura para una venta pagada.');
        }
        if ((int) round($total * 100) !== (int) round((float) $venta['total'] * 100)) {
            throw new DomainException('El total de la factura no coincide con el total de la venta.');
        }

        $existente = $pdo->prepare(
            'SELECT idFactura
             FROM Factura
             WHERE idVenta = :idVenta
             LIMIT 1
             FOR UPDATE'
        );
        $existente->execute([':idVenta' => $idVenta]);
        if ($existente->fetchColumn() !== false) {
            throw new DomainException('La venta ya tiene una factura registrada.');
        }

        $mayorConsecutivo = 0;
        foreach ($pdo->query('SELECT numeroConsecutivo FROM Factura')->fetchAll() as $fila) {
            if (!preg_match('/^FAC-(?:[0-9]{4}-)?([0-9]{6,})$/D', $fila['numeroConsecutivo'], $partes)) {
                throw new RuntimeException('Hay un consecutivo de factura existente con formato no válido.');
            }
            $consecutivo = filter_var($partes[1], FILTER_VALIDATE_INT);
            if ($consecutivo === false) {
                throw new RuntimeException('Un consecutivo de factura existente supera el límite permitido.');
            }
            $mayorConsecutivo = max($mayorConsecutivo, $consecutivo);
        }

        if ($mayorConsecutivo >= PHP_INT_MAX) {
            throw new RuntimeException('Se agotó el consecutivo disponible para facturas.');
        }

        $numero = 'FAC-' . date('Y') . '-'
            . str_pad((string) ($mayorConsecutivo + 1), 6, '0', STR_PAD_LEFT);
        if (strlen($numero) > 20) {
            throw new RuntimeException('El consecutivo excede el tamaño permitido por el esquema.');
        }

        $insertar = $pdo->prepare(
            'INSERT INTO Factura (idVenta, numeroConsecutivo, fechaEmision, total)
             VALUES (:idVenta, :numeroConsecutivo, CURRENT_TIMESTAMP, :total)'
        );
        $insertar->execute([
            ':idVenta' => $idVenta,
            ':numeroConsecutivo' => $numero,
            ':total' => number_format((float) $venta['total'], 2, '.', ''),
        ]);

        return (int) $pdo->lastInsertId();
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
            'SELECT f.*, v.idUsuario, v.fecha AS fechaVenta, v.estado, v.impuesto,
                    u.nombre, u.apellido, u.usuario, pg.metodoPago
             FROM Factura f
             INNER JOIN Venta v ON v.idVenta = f.idVenta
             INNER JOIN Usuario u ON u.idUsuario = v.idUsuario
             LEFT JOIN Pago pg ON pg.idPago = (
                 SELECT MAX(p2.idPago)
                 FROM Pago p2
                 WHERE p2.idVenta = v.idVenta AND p2.estadoPago = :estadoPago
             )
             WHERE f.idFactura = :idFactura
             LIMIT 1'
        );
        $consulta->execute([
            ':estadoPago' => 'Confirmado',
            ':idFactura' => $idFactura,
        ]);
        $factura = $consulta->fetch();

        return $factura ?: null;
    }

    public static function buscarPorId(int $idFactura): ?array
    {
        return self::obtenerPorId($idFactura);
    }

    public static function buscarPorVenta(int $idVenta): ?array
    {
        $consulta = Database::getConexion()->prepare(
            'SELECT idFactura, numeroConsecutivo
             FROM Factura
             WHERE idVenta = :idVenta'
        );
        $consulta->execute([':idVenta' => $idVenta]);
        $factura = $consulta->fetch();

        return $factura ?: null;
    }

    public static function detallesPorVenta(int $idVenta): array
    {
        $consulta = Database::getConexion()->prepare(
            'SELECT d.idProducto, d.cantidad, d.precioUnitario, d.subtotal,
                    p.nombre, p.codigoBarras
             FROM DetalleVenta d
             INNER JOIN Producto p ON p.idProducto = d.idProducto
             WHERE d.idVenta = :idVenta
             ORDER BY d.idDetalleVenta'
        );
        $consulta->execute([':idVenta' => $idVenta]);

        return $consulta->fetchAll();
    }

    public static function listarDetalles(int $idVenta): array
    {
        return self::detallesPorVenta($idVenta);
    }
}
