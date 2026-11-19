<?php
/**
 * Acceso a los pagos asociados a una venta.
 */
class Pago
{
    /**
     * Registra el pago, actualiza inventario y crea factura de forma atómica.
     *
     * @return array{idPago: int, idFactura: int, metodoPago: string, total: float, recibido: float, cambio: float}
     */
    public static function registrar(int $idVenta, int $idUsuario, string $metodo, mixed $recibido = null): array
    {
        if ($idVenta <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('La venta o el usuario no son válidos.');
        }

        $metodos = [
            'Efectivo' => 'Efectivo',
            'Tarjeta' => 'Tarjeta',
            'Digital' => 'Digital',
            'Pago digital' => 'Digital',
            'Contraentrega' => 'Contraentrega',
        ];
        if (!isset($metodos[$metodo])) {
            throw new InvalidArgumentException('El método de pago no está permitido.');
        }

        $metodoBaseDatos = $metodos[$metodo];
        if ($metodoBaseDatos !== 'Efectivo' && $recibido !== null) {
            throw new InvalidArgumentException('El valor recibido solo aplica para pagos en efectivo.');
        }

        $pdo = Database::getConexion();
        if ($pdo->inTransaction()) {
            throw new LogicException('El registro de pago debe controlar su propia transacción.');
        }

        $pdo->beginTransaction();
        $bloqueoConsecutivo = false;

        try {
            Factura::adquirirBloqueoConsecutivo($pdo);
            $bloqueoConsecutivo = true;

            $consultaVenta = $pdo->prepare(
                'SELECT idUsuario, total, estado
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

            Venta::marcarPagadaEnTransaccion($pdo, $idVenta);
            Inventario::descontarParaVenta($pdo, $idVenta, (int) $venta['idUsuario']);

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

            $idFactura = Factura::crearEnTransaccion($pdo, $idVenta, $totalCentavos / 100);
            $pdo->commit();

            return [
                'idPago' => $idPago,
                'idFactura' => $idFactura,
                'metodoPago' => $metodoBaseDatos,
                'total' => $totalCentavos / 100,
                'recibido' => $recibidoCentavos / 100,
                'cambio' => $cambioCentavos / 100,
            ];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        } finally {
            if ($bloqueoConsecutivo) {
                try {
                    if (!Factura::liberarBloqueoConsecutivo($pdo)) {
                        error_log('No se pudo liberar el bloqueo del consecutivo de factura.');
                    }
                } catch (PDOException $error) {
                    error_log('Error al liberar el bloqueo del consecutivo de factura: ' . $error->getMessage());
                }
            }
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
