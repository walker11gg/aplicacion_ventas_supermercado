<?php
class MovimientoInventario
{
    public static function registrarSalida(
        PDO $pdo,
        int $idInventario,
        int $idUsuario,
        int $cantidad,
        int $idVenta
    ): void {
        $consulta = $pdo->prepare(
            'INSERT INTO MovimientoInventario (idInventario, idUsuario, tipo, cantidad, motivo)
             VALUES (:idInventario, :idUsuario, :tipo, :cantidad, :motivo)'
        );
        $consulta->execute([
            ':idInventario' => $idInventario,
            ':idUsuario' => $idUsuario,
            ':tipo' => 'Salida',
            ':cantidad' => $cantidad,
            ':motivo' => 'Venta confirmada #' . $idVenta,
        ]);
    }
}
