<?php
class InventarioController
{
    public function existencias(): void
    {
        Auth::requerirRol(['Administrador', 'Encargado']);

        $busqueda = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $productos = Inventario::listar($busqueda);
        $tituloPagina = 'Existencias';
        $migas = ['Inventario', 'Existencias'];
        $rutaActiva = 'inventario/existencias';

        require RUTA_RAIZ . '/app/views/inventario/existencias.php';
    }

    public function alertas(): void
    {
        Auth::requerirRol(['Administrador', 'Encargado']);

        $productos = Inventario::alertasStock();
        $tituloPagina = 'Alertas de stock';
        $migas = ['Inventario', 'Alertas'];
        $rutaActiva = 'inventario/alertas';

        require RUTA_RAIZ . '/app/views/inventario/alertas.php';
    }
}
