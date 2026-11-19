<?php
class BusquedaController
{
    public function buscar(): void
    {
        Auth::requerirRol(['Administrador', 'Cajero']);
        header('Content-Type: application/json; charset=utf-8');

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            echo json_encode(['productos' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        $termino = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $consulta = Database::getConexion()->prepare(
            'SELECT p.idProducto, p.codigoBarras, p.nombre, p.precioVenta,
                    i.cantidadActual
             FROM Producto p
             INNER JOIN Inventario i ON i.idInventario = p.idInventario
             WHERE p.estado = :estado
               AND (p.codigoBarras = :codigo
                    OR p.nombre LIKE :nombre ESCAPE "\\\\")
             ORDER BY p.nombre
             LIMIT 20'
        );
        $consulta->execute([
            ':estado' => 'Activo',
            ':codigo' => $q,
            ':nombre' => '%' . $termino . '%',
        ]);
        echo json_encode(['productos' => $consulta->fetchAll()], JSON_UNESCAPED_UNICODE);
    }
}