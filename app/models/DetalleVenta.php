<?php
/**
 * Acceso a las líneas de detalle de una venta.
 */
class DetalleVenta
{
    /**
     * Agrega una línea y mantiene actualizados el total y el impuesto de venta.
     */
    public static function agregarLinea(
        int $idVenta,
        int $idProducto,
        mixed $cantidad,
        mixed $precioUnitario
    ): int {
        if ($idVenta <= 0 || $idProducto <= 0) {
            throw new InvalidArgumentException('La venta o el producto no son válidos.');
        }

        if (is_bool($cantidad) || is_float($cantidad) || (!is_int($cantidad) && !is_string($cantidad))) {
            throw new InvalidArgumentException('La cantidad debe ser un entero mayor que cero.');
        }
        $cantidadValidada = filter_var(
            $cantidad,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($cantidadValidada === false) {
            throw new InvalidArgumentException('La cantidad debe ser un entero mayor que cero.');
        }

        if (
            (!is_int($precioUnitario) && !is_float($precioUnitario) && !is_string($precioUnitario))
            || !is_numeric($precioUnitario)
        ) {
            throw new InvalidArgumentException('El precio unitario no es válido.');
        }

        $precio = (float) $precioUnitario;
        if (!is_finite($precio) || $precio < 0 || $precio > 9999999999.99) {
            throw new InvalidArgumentException('El precio unitario no es válido.');
        }

        $precio = round($precio, 2);
        $subtotal = round($cantidadValidada * $precio, 2);
        if (!is_finite($subtotal) || $subtotal > 9999999999.99) {
            throw new InvalidArgumentException('El subtotal de la línea excede el límite permitido.');
        }

        $pdo = Database::getConexion();
        $iniciarTransaccion = !$pdo->inTransaction();
        if ($iniciarTransaccion) {
            $pdo->beginTransaction();
        }

        try {
            $consulta = $pdo->prepare(
                'INSERT INTO DetalleVenta
                    (idVenta, idProducto, cantidad, precioUnitario, subtotal)
                 VALUES
                    (:idVenta, :idProducto, :cantidad, :precioUnitario, :subtotal)'
            );
            $consulta->execute([
                ':idVenta' => $idVenta,
                ':idProducto' => $idProducto,
                ':cantidad' => $cantidadValidada,
                ':precioUnitario' => number_format($precio, 2, '.', ''),
                ':subtotal' => number_format($subtotal, 2, '.', ''),
            ]);

            Venta::recalcularTotales($idVenta);
            $idDetalle = (int) $pdo->lastInsertId();

            if ($iniciarTransaccion) {
                $pdo->commit();
            }

            return $idDetalle;
        } catch (Throwable $error) {
            if ($iniciarTransaccion && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /** Lista las líneas de una venta en el orden en que fueron registradas. */
    public static function listarPorVenta(int $idVenta): array
    {
        if ($idVenta <= 0) {
            throw new InvalidArgumentException('El identificador de venta no es válido.');
        }

        $consulta = Database::getConexion()->prepare(
            'SELECT idDetalleVenta, idVenta, idProducto, cantidad, precioUnitario, subtotal
             FROM DetalleVenta
             WHERE idVenta = :idVenta
             ORDER BY idDetalleVenta'
        );
        $consulta->execute([':idVenta' => $idVenta]);

        return $consulta->fetchAll();
    }
}
