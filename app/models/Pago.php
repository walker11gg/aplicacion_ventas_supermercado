<?php
/**
 * Acceso a los pagos asociados a una venta.
 */
class Pago
{
    /**
     * Registra un único pago para una venta abierta.
     *
     * @return array{idPago: int, metodoPago: string, total: float, recibido: float, cambio: float}
     */
    public static function registrar(int $idVenta, int $idUsuario, string $metodo, mixed $recibido = null): array
    {
        if ($idVenta <= 0) {
            throw new InvalidArgumentException('El identificador de venta no es válido.');
        }

        $metodos = [
            'Efectivo' => 'Efectivo',
            'Tarjeta' => 'Tarjeta',
            'Digital' => 'Digital',
            'Pago digital' => 'Digital',
        ];
        if (!isset($metodos[$metodo])) {
            throw new InvalidArgumentException('El método de pago no está permitido.');
        }

        $metodoBaseDatos = $metodos[$metodo];
        if ($metodoBaseDatos !== 'Efectivo' && $recibido !== null) {
            throw new InvalidArgumentException('El valor recibido solo aplica para pagos en efectivo.');
        }

        $pdo = Database::getConexion();
        $iniciarTransaccion = !$pdo->inTransaction();
        if ($iniciarTransaccion) {
            $pdo->beginTransaction();
        }

        try {
            $consultaVenta = $pdo->prepare(
                'SELECT total, estado
                 FROM Venta
                 WHERE idVenta = :idVenta
                 FOR UPDATE'
            );
            $consultaVenta->execute([':idVenta' => $idVenta]);
            $venta = $consultaVenta->fetch();
            if (!$venta) {
                throw new OutOfBoundsException('La venta indicada no existe.');
            }

            $consultaPago = $pdo->prepare(
                'SELECT idPago
                 FROM Pago
                 WHERE idVenta = :idVenta
                 LIMIT 1
                 FOR UPDATE'
            );
            $consultaPago->execute([':idVenta' => $idVenta]);
            if ($consultaPago->fetchColumn() !== false) {
                throw new DomainException('La venta ya tiene un pago registrado.');
            }

            if ($venta['estado'] !== 'Abierta') {
                throw new DomainException('Solo se puede cobrar una venta abierta.');
            }

            $totalCentavos = self::aCentavos($venta['total'], 'El total de la venta');
            $recibidoCentavos = $totalCentavos;
            $cambioCentavos = 0;

            if ($metodoBaseDatos === 'Efectivo') {
                if ($recibido === null) {
                    throw new InvalidArgumentException('Debes indicar el valor recibido en efectivo.');
                }

                $recibidoCentavos = self::aCentavos($recibido, 'El valor recibido');
                if ($recibidoCentavos < $totalCentavos) {
                    throw new InvalidArgumentException('El valor recibido no cubre el total de la venta.');
                }
                $cambioCentavos = $recibidoCentavos - $totalCentavos;
            }

            $insertar = $pdo->prepare(
                'INSERT INTO Pago (idVenta, fechaPago, monto, metodoPago, estadoPago)
                 VALUES (:idVenta, CURRENT_TIMESTAMP, :monto, :metodoPago, :estadoPago)'
            );
            $insertar->execute([
                ':idVenta' => $idVenta,
                ':monto' => self::formatearCentavos($totalCentavos),
                ':metodoPago' => $metodoBaseDatos,
                ':estadoPago' => 'Confirmado',
            ]);

            $idPago = (int) $pdo->lastInsertId();

            $detallesConsulta = $pdo->prepare(
                'SELECT dv.idProducto, dv.cantidad, p.idInventario
                 FROM DetalleVenta dv
                 INNER JOIN Producto p ON p.idProducto = dv.idProducto
                 WHERE dv.idVenta = :idVenta
                 FOR UPDATE'
            );
            $detallesConsulta->execute([':idVenta' => $idVenta]);
            $detalles = $detallesConsulta->fetchAll();
            if ($detalles === []) {
                throw new DomainException('No se puede cobrar una venta sin productos.');
            }

            foreach ($detalles as $detalle) {
                $inventarioConsulta = $pdo->prepare(
                    'SELECT cantidadActual
                     FROM Inventario
                     WHERE idInventario = :idInventario
                     FOR UPDATE'
                );
                $inventarioConsulta->execute([':idInventario' => (int) $detalle['idInventario']]);
                $inventario = $inventarioConsulta->fetch();
                if (!$inventario) {
                    throw new OutOfBoundsException('El inventario de un producto ya no existe.');
                }

                $cantidadActual = (int) $inventario['cantidadActual'];
                $cantidadVendida = (int) $detalle['cantidad'];
                if ($cantidadVendida > $cantidadActual) {
                    throw new DomainException('La existencias de un producto son insuficientes para completar la venta.');
                }

                $nuevaCantidad = $cantidadActual - $cantidadVendida;
                $actualizarInventario = $pdo->prepare(
                    'UPDATE Inventario
                     SET cantidadActual = :cantidadActual,
                         fechaActualizacion = CURRENT_TIMESTAMP
                     WHERE idInventario = :idInventario'
                );
                $actualizarInventario->execute([
                    ':cantidadActual' => $nuevaCantidad,
                    ':idInventario' => (int) $detalle['idInventario'],
                ]);

                $movimiento = $pdo->prepare(
                    'INSERT INTO MovimientoInventario
                        (idInventario, idUsuario, fecha, tipo, cantidad, motivo)
                     VALUES
                        (:idInventario, :idUsuario, CURRENT_TIMESTAMP, :tipo, :cantidad, :motivo)'
                );
                $movimiento->execute([
                    ':idInventario' => (int) $detalle['idInventario'],
                    ':idUsuario' => $idUsuario,
                    ':tipo' => 'Salida',
                    ':cantidad' => $cantidadVendida,
                    ':motivo' => 'Venta #' . $idVenta,
                ]);
            }

            $actualizarVenta = $pdo->prepare(
                'UPDATE Venta SET estado = :estado WHERE idVenta = :idVenta'
            );
            $actualizarVenta->execute([
                ':estado' => 'Pagada',
                ':idVenta' => $idVenta,
            ]);

            $idFactura = Factura::crearEnTransaccion($pdo, $idVenta, $totalCentavos / 100);

            if ($iniciarTransaccion) {
                $pdo->commit();
            }

            return [
                'idPago' => $idPago,
                'idFactura' => $idFactura,
                'metodoPago' => $metodo,
                'total' => $totalCentavos / 100,
                'recibido' => $recibidoCentavos / 100,
                'cambio' => $cambioCentavos / 100,
            ];
        } catch (Throwable $error) {
            if ($iniciarTransaccion && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function aCentavos(mixed $valor, string $etiqueta): int
    {
        if (
            is_bool($valor)
            || (!is_int($valor) && !is_float($valor) && !is_string($valor))
            || !is_numeric($valor)
        ) {
            throw new InvalidArgumentException($etiqueta . ' no es válido.');
        }

        $monto = (float) $valor;
        if (!is_finite($monto) || $monto < 0 || $monto > 9999999999.99) {
            throw new InvalidArgumentException($etiqueta . ' no es válido.');
        }

        return (int) round(round($monto, 2) * 100);
    }

    private static function formatearCentavos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}