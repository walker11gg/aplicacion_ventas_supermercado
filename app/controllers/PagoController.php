<?php
/**
 * Presentación y registro del cobro de una venta.
 */
class PagoController
{
    public function cobrar(): void
    {
        Auth::requerirRol(['Cajero', 'Administrador']);

        $metodoHttp = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($metodoHttp !== 'GET' && $metodoHttp !== 'POST') {
            http_response_code(405);
            header('Allow: GET, POST');
            exit;
        }

        $idRecibido = $metodoHttp === 'POST'
            ? ($_POST['idVenta'] ?? $_GET['idVenta'] ?? null)
            : ($_GET['idVenta'] ?? null);
        $idVenta = is_scalar($idRecibido)
            ? filter_var($idRecibido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        $error = null;
        $codigoError = 200;
        $venta = null;
        $detalles = [];
        $resultado = null;

        if ($idVenta === false) {
            $error = 'El identificador de venta no es válido.';
            $codigoError = 400;
        } else {
            $claveResultado = 'pago_resultado_' . $idVenta;
            $resultado = $_SESSION[$claveResultado] ?? null;
            unset($_SESSION[$claveResultado]);

            try {
                $venta = Venta::obtenerPorId($idVenta);
                if ($venta === null) {
                    $error = 'La venta indicada no existe.';
                    $codigoError = 404;
                } else {
                    $detalles = Factura::detallesPorVenta($idVenta);
                }
            } catch (Throwable $e) {
                error_log('No se pudo consultar la venta para el cobro: ' . $e->getMessage());
                $error = 'No se pudo consultar la venta. Intenta de nuevo.';
                $codigoError = 500;
            }

            if ($error === null && $metodoHttp === 'POST') {
                $token = $_POST['csrf'] ?? null;
                if (!is_string($token) || !Seguridad::validarCsrf($token)) {
                    $error = 'El formulario expiró. Recarga la página e intenta de nuevo.';
                    $codigoError = 400;
                } elseif ($detalles === []) {
                    $error = 'No se puede cobrar una venta sin productos.';
                    $codigoError = 409;
                } else {
                    $metodo = $_POST['metodo'] ?? '';
                    $metodo = is_string($metodo) ? $metodo : '';
                    $recibido = null;
                    if ($metodo === 'Efectivo') {
                        $recibido = $_POST['recibido'] ?? null;
                        if (is_string($recibido)) {
                            $recibido = trim($recibido);
                            if ($recibido === '') {
                                $recibido = null;
                            }
                        }
                    }

                    try {
                        $usuario = Auth::usuario();
                        if ($usuario === null) {
                            throw new DomainException('Tu sesión expiró. Inicia sesión nuevamente.');
                        }
                        $resultado = Pago::registrar(
                            $idVenta,
                            (int) $usuario['id'],
                            $metodo,
                            $recibido
                        );
                        $_SESSION[$claveResultado] = $resultado;
                        Auth::redirigir('pago/cobrar&idVenta=' . $idVenta);
                    } catch (InvalidArgumentException $e) {
                        $error = $e->getMessage();
                        $codigoError = 422;
                    } catch (DomainException $e) {
                        $error = $e->getMessage();
                        $codigoError = 409;
                    } catch (OutOfBoundsException $e) {
                        $error = $e->getMessage();
                        $codigoError = 404;
                    } catch (Throwable $e) {
                        error_log('No se pudo registrar el pago: ' . $e->getMessage());
                        $error = 'No se pudo registrar el pago. Intenta de nuevo.';
                        $codigoError = 500;
                    }
                }
            } elseif (
                $error === null
                && $resultado === null
                && $venta !== null
                && $venta['estado'] !== 'Abierta'
            ) {
                $error = 'La venta ya no está abierta para cobro.';
                $codigoError = 409;
            } elseif ($error === null && $detalles === []) {
                $error = 'No se puede cobrar una venta sin productos.';
                $codigoError = 409;
            }
        }

        $tituloPagina = 'Cobro';
        $migas = ['Ventas', 'Cobro'];
        $rutaActiva = 'venta/nueva';
        http_response_code($codigoError);
        require RUTA_RAIZ . '/app/views/ventas/cobro.php';
    }
}
