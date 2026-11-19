<?php
/**
 * Punto de entrada y enrutador del sistema (patrón MVC).
 *
 * Las URL tienen la forma:   index.php?ruta=controlador/accion
 *   ?ruta=productos/listar   ->  ProductoController::listar()  (cuando exista)
 *
 * La ruta por defecto es 'inicio': si no hay sesión, redirige al login
 * (auth/login).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

const RUTA_POR_DEFECTO = 'inicio';

// ---------------------------------------------------------------------
// Autocarga de clases: busca en controllers, models y helpers
// ---------------------------------------------------------------------
spl_autoload_register(function (string $clase): void {
    foreach (['controllers', 'models', 'helpers'] as $carpeta) {
        $archivo = __DIR__ . "/app/$carpeta/$clase.php";
        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

// ---------------------------------------------------------------------
// Despacho de la petición
// ---------------------------------------------------------------------
function mostrarError(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body>'
       . '<h1>' . $codigo . '</h1><p>' . htmlspecialchars($mensaje) . '</p></body></html>';
}

$ruta = $_GET['ruta'] ?? RUTA_POR_DEFECTO;

// Formato esperado: controlador/accion (solo letras, números y guion bajo)
if (!preg_match('#^([a-z_]+)(?:/([a-zA-Z_][a-zA-Z0-9_]*))?$#', $ruta, $partes)) {
    mostrarError(404, 'Página no encontrada.');
    exit;
}

$nombreControlador = ucfirst($partes[1]) . 'Controller';
$accion            = $partes[2] ?? 'index';

if (!class_exists($nombreControlador)) {
    mostrarError(404, 'Este módulo aún no está disponible.');
    exit;
}

$controlador = new $nombreControlador();

if (!method_exists($controlador, $accion) || !is_callable([$controlador, $accion])) {
    mostrarError(404, 'Página no encontrada.');
    exit;
}

$controlador->$accion();
