<?php
/**
 * Configuración general del sistema.
 * Todo valor que cambie de un computador a otro o de una regla de negocio
 * se define aquí, en un solo lugar.
 */

// ---------------------------------------------------------------------
// Entorno
// ---------------------------------------------------------------------
define('ENTORNO', 'desarrollo');   // 'desarrollo' muestra errores; 'produccion' los oculta

if (ENTORNO === 'desarrollo') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

date_default_timezone_set('America/Bogota');

// ---------------------------------------------------------------------
// Rutas
// ---------------------------------------------------------------------
define('RUTA_RAIZ', dirname(__DIR__));
$directorioAplicacion = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
define('URL_BASE', rtrim(str_replace('\\', '/', $directorioAplicacion), '/') . '/');

// ---------------------------------------------------------------------
// Base de datos (XAMPP por defecto: usuario root y sin contraseña)
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NOMBRE', getenv('DB_NOMBRE') ?: 'supermercado_db');
define('DB_USUARIO', getenv('DB_USUARIO') ?: 'root');
define('DB_CLAVE', getenv('DB_CLAVE') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// Reglas del negocio y seguridad
// ---------------------------------------------------------------------
define('IVA_PORCENTAJE', 19);          // % de impuesto aplicado a las ventas
define('SESION_MINUTOS', 30);          // la sesión expira tras este tiempo de inactividad
define('MAX_INTENTOS_LOGIN', 5);       // intentos fallidos antes del bloqueo temporal
define('BLOQUEO_MINUTOS', 15);         // duración del bloqueo temporal

// ---------------------------------------------------------------------
// Sesión
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
