<?php
class FacturaController
{
    public function listado(): void
    {
        Auth::requerirRol(['Administrador', 'Cajero']);
        $facturas = Factura::listar();

        $tituloPagina = 'Facturas';
        $migas = ['Facturas'];
        $rutaActiva = 'factura/listado';
        require RUTA_RAIZ . '/app/views/facturas/listado.php';
    }

    public function ver(): void
    {
        Auth::requerirRol(['Administrador', 'Cajero']);
        $idFactura = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($idFactura === false) {
            http_response_code(400);
            echo 'Identificador de factura no válido.';
            exit;
        }
        $factura = Factura::obtenerPorId($idFactura);
        if ($factura === null) {
            http_response_code(404);
            echo 'Factura no encontrada.';
            exit;
        }
        $detalles = Factura::detallesPorVenta((int) $factura['idVenta']);

        $tituloPagina = 'Factura ' . $factura['numeroConsecutivo'];
        $migas = ['Facturas', $factura['numeroConsecutivo']];
        $rutaActiva = 'factura/listado';
        require RUTA_RAIZ . '/app/views/facturas/ver.php';
    }
}