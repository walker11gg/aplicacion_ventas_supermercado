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

        $idRecibido = $_GET['id'] ?? $_GET['idFactura'] ?? null;
        $idFactura = is_scalar($idRecibido)
            ? filter_var($idRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($idFactura === false) {
            http_response_code(400);
            echo 'Identificador de factura no válido.';
            return;
        }

        $factura = Factura::obtenerPorId((int) $idFactura);
        if ($factura === null) {
            http_response_code(404);
            echo 'Factura no encontrada.';
            return;
        }

        $detalles = Factura::detallesPorVenta((int) $factura['idVenta']);
        $tituloPagina = 'Factura ' . $factura['numeroConsecutivo'];
        $migas = ['Facturas', $factura['numeroConsecutivo']];
        $rutaActiva = 'factura/listado';

        require RUTA_RAIZ . '/app/views/facturas/ver.php';
    }
}
