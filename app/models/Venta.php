<?php
/**
 * Acceso a los datos de las ventas.
 */
class Venta
{
    /** Crea una venta abierta con la fecha y hora actuales de la base de datos. */
    public static function crear(int $idCajero, string $estado = 'Abierta'): int
    {
        if ($idCajero <= 0) {
            throw new InvalidArgumentException('El cajero no es válido.');
        }
        self::validarEstado($estado);
        if ($estado === 'Pagada') {
            throw new DomainException('Una venta solo puede quedar pagada al registrar su pago.');
        }

        $consulta = Database::getConexion()->prepare(
            'INSERT INTO Venta (idUsuario, fecha, impuesto, total, estado)
             VALUES (:idUsuario, CURRENT_TIMESTAMP, 0.00, 0.00, :estado)'
        );
        $consulta->execute([
            ':idUsuario' => $idCajero,
            ':estado' => $estado,
        ]);

        return (int) Database::getConexion()->lastInsertId();
    }

    /** Devuelve una venta por su identificador, o null si no existe. */
    public static function obtenerPorId(int $idVenta): ?array
    {
        if ($idVenta <= 0) {
            throw new InvalidArgumentException('El identificador de venta no es válido.');
        }

        $consulta = Database::getConexion()->prepare(
            'SELECT idVenta, idUsuario, fecha, impuesto, total, estado
             FROM Venta
             WHERE idVenta = :idVenta
             LIMIT 1'
        );
        $consulta->execute([':idVenta' => $idVenta]);
        $venta = $consulta->fetch();

        return $venta ?: null;
    }

    /** Recalcula subtotal, impuesto y total a partir de las líneas de detalle. */
    public static function recalcularTotales(int $idVenta): array
    {
        if ($idVenta <= 0) {
            throw new InvalidArgumentException('El identificador de venta no es válido.');
        }
        if (!defined('IVA_PORCENTAJE') || IVA_PORCENTAJE < 0) {
            throw new LogicException('La constante IVA_PORCENTAJE no está configurada correctamente.');
        }

        $pdo = Database::getConexion();
        $existe = $pdo->prepare('SELECT 1 FROM Venta WHERE idVenta = :idVenta LIMIT 1');
        $existe->execute([':idVenta' => $idVenta]);
        if ($existe->fetchColumn() === false) {
            throw new OutOfBoundsException('La venta indicada no existe.');
        }

        $consulta = $pdo->prepare(
            'SELECT COALESCE(SUM(subtotal), 0.00)
             FROM DetalleVenta
             WHERE idVenta = :idVenta'
        );
        $consulta->execute([':idVenta' => $idVenta]);
        $subtotalSinIva = round((float) $consulta->fetchColumn(), 2);
        $impuesto = round($subtotalSinIva * IVA_PORCENTAJE / 100, 2);
        $total = round($subtotalSinIva + $impuesto, 2);

        $actualizar = $pdo->prepare(
            'UPDATE Venta
             SET impuesto = :impuesto, total = :total
             WHERE idVenta = :idVenta'
        );
        $actualizar->execute([
            ':impuesto' => number_format($impuesto, 2, '.', ''),
            ':total' => number_format($total, 2, '.', ''),
            ':idVenta' => $idVenta,
        ]);

        return [
            'subtotalSinIva' => $subtotalSinIva,
            'impuesto' => $impuesto,
            'total' => $total,
        ];
    }

    /**
     * Cambia estados no pagados. El pago es el único flujo que puede marcar
     * una venta como Pagada, porque también confirma inventario y factura.
     */
    public static function actualizarEstado(int $idVenta, string $estado): void
    {
        if ($idVenta <= 0) {
            throw new InvalidArgumentException('El identificador de venta no es válido.');
        }
        self::validarEstado($estado);
        if ($estado === 'Pagada') {
            throw new DomainException('Una venta solo puede quedar pagada al registrar su pago.');
        }

        $venta = self::obtenerPorId($idVenta);
        if ($venta === null) {
            throw new OutOfBoundsException('La venta indicada no existe.');
        }
        if ($venta['estado'] === 'Pagada') {
            throw new DomainException('No se puede cambiar el estado de una venta ya pagada.');
        }

        Database::getConexion()
            ->prepare('UPDATE Venta SET estado = :estado WHERE idVenta = :idVenta')
            ->execute([
                ':estado' => $estado,
                ':idVenta' => $idVenta,
            ]);
    }

    /** Marca como pagada una venta bloqueada dentro de la transacción de pago. */
    public static function marcarPagadaEnTransaccion(PDO $pdo, int $idVenta): void
    {
        $consulta = $pdo->prepare(
            'UPDATE Venta
             SET estado = :pagada
             WHERE idVenta = :idVenta AND estado = :abierta'
        );
        $consulta->execute([
            ':pagada' => 'Pagada',
            ':idVenta' => $idVenta,
            ':abierta' => 'Abierta',
        ]);

        if ($consulta->rowCount() !== 1) {
            throw new DomainException('La venta ya no está abierta y no puede confirmarse.');
        }
    }

    private static function validarEstado(string $estado): void
    {
        if (!in_array($estado, ['Abierta', 'Pagada', 'Anulada'], true)) {
            throw new InvalidArgumentException('El estado de venta no es válido.');
        }
    }
}
