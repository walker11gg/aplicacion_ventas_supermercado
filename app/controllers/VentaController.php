<?php
/**
 * Flujo de registro de ventas para Cajero y Administrador.
 */
class VentaController
{
    private const CLAVE_CARRITO = 'venta_carrito';

    public function agregar(): void
    {
        Auth::requerirRol(['Cajero', 'Administrador']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
        if (!Seguridad::validarCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['venta_error'] = 'El formulario expiró. Recarga la página e intenta de nuevo.';
            Auth::redirigir('venta/nueva');
        }

        try {
            $idProducto = $this->enteroPositivo($_POST['idProducto'] ?? null, 'El producto');
            $cantidad = $this->enteroPositivo($_POST['cantidad'] ?? null, 'La cantidad');
            $producto = $this->buscarProductoActivo($idProducto);
            if ($producto === null) {
                throw new OutOfBoundsException('El producto no existe o está inactivo.');
            }

            $carrito = $this->obtenerCarrito();
            $cantidadNueva = $cantidad + (int) ($carrito[$idProducto] ?? 0);
            if ($cantidadNueva > (int) $producto['cantidadActual']) {
                throw new DomainException('La cantidad solicitada supera las existencias disponibles.');
            }

            $carrito[$idProducto] = $cantidadNueva;
            $_SESSION[self::CLAVE_CARRITO] = $carrito;
            unset($_SESSION['venta_error']);
            $_SESSION['venta_exito'] = 'Producto agregado a la venta.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['venta_error'] = $e->getMessage();
        } catch (OutOfBoundsException $e) {
            $_SESSION['venta_error'] = $e->getMessage();
        } catch (DomainException $e) {
            $_SESSION['venta_error'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('No se pudo agregar el producto: ' . $e->getMessage());
            $_SESSION['venta_error'] = 'No se pudo agregar el producto. Intenta de nuevo.';
        }
        Auth::redirigir('venta/nueva');
    }

    public function procesar(): void
    {
        Auth::requerirRol(['Cajero', 'Administrador']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
        if (!Seguridad::validarCsrf($_POST['csrf'] ?? null)) {
            $_SESSION['venta_error'] = 'El formulario expiró. Recarga la página e intenta de nuevo.';
            Auth::redirigir('venta/nueva');
        }

        try {
            $usuario = Auth::usuario();
            if ($usuario === null) {
                throw new DomainException('Tu sesión expiró. Inicia sesión nuevamente.');
            }
            $carrito = $this->obtenerCarrito();
            if (($_POST['accion'] ?? 'confirmar') !== 'confirmar') {
                throw new InvalidArgumentException('La acción solicitada no es válida.');
            }
            $idVenta = $this->crearVenta((int) $usuario['id'], $carrito);
            unset($_SESSION[self::CLAVE_CARRITO]);
            Auth::redirigir('pago/cobrar&idVenta=' . $idVenta);
        } catch (DomainException $e) {
            $_SESSION['venta_error'] = $e->getMessage();
        } catch (OutOfBoundsException $e) {
            $_SESSION['venta_error'] = $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $_SESSION['venta_error'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('No se pudo procesar la venta: ' . $e->getMessage());
            $_SESSION['venta_error'] = 'No se pudo procesar la venta. Intenta de nuevo.';
        }
        Auth::redirigir('venta/nueva');
    }

    public function nueva(): void
    {
        Auth::requerirRol(['Cajero', 'Administrador']);
        $usuario = Auth::usuario();
        if ($usuario === null) {
            http_response_code(401);
            exit;
        }

        $carrito = $this->obtenerCarrito();
        $error = null;
        $exito = $_SESSION['venta_exito'] ?? null;
        unset($_SESSION['venta_exito']);
        if ($error === null && isset($_SESSION['venta_error'])) {
            $error = $_SESSION['venta_error'];
            unset($_SESSION['venta_error']);
        }
        $codigoError = 200;
        $metodoHttp = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($metodoHttp !== 'GET' && $metodoHttp !== 'POST') {
            http_response_code(405);
            header('Allow: GET, POST');
            exit;
        }

        if ($metodoHttp === 'POST') {
            $token = $_POST['csrf'] ?? null;
            if (!is_string($token) || !Seguridad::validarCsrf($token)) {
                $error = 'El formulario expiró. Recarga la página e intenta de nuevo.';
                $codigoError = 400;
            } else {
                try {
                    $accion = $_POST['accion'] ?? '';
                    switch ($accion) {
                        case 'agregar':
                        case 'actualizar':
                            $idProducto = $this->enteroPositivo($_POST['idProducto'] ?? null, 'El producto');
                            $cantidad = $this->enteroPositivo($_POST['cantidad'] ?? null, 'La cantidad');
                            if ($accion === 'actualizar' && !array_key_exists($idProducto, $carrito)) {
                                throw new OutOfBoundsException('El producto no está en la venta.');
                            }

                            $producto = $this->buscarProductoActivo($idProducto);
                            if ($producto === null) {
                                throw new OutOfBoundsException('El producto no existe o está inactivo.');
                            }
                            if ($cantidad > (int) $producto['cantidadActual']) {
                                throw new DomainException('La cantidad supera las existencias disponibles.');
                            }

                            if ($accion === 'agregar') {
                                $cantidad = $cantidad + (int) ($carrito[$idProducto] ?? 0);
                                if ($cantidad > (int) $producto['cantidadActual']) {
                                    throw new DomainException('La cantidad solicitada supera las existencias disponibles.');
                                }
                            }
                            $carrito[$idProducto] = $cantidad;
                            $_SESSION[self::CLAVE_CARRITO] = $carrito;
                            Auth::redirigir('venta/nueva');

                        case 'vaciar':
                            unset($_SESSION[self::CLAVE_CARRITO]);
                            Auth::redirigir('venta/nueva');

                        case 'quitar':
                            $idProducto = $this->enteroPositivo($_POST['idProducto'] ?? null, 'El producto');
                            unset($carrito[$idProducto]);
                            $_SESSION[self::CLAVE_CARRITO] = $carrito;
                            Auth::redirigir('venta/nueva');

                        case 'confirmar':
                            $idVenta = $this->crearVenta($usuario['id'], $carrito);
                            unset($_SESSION[self::CLAVE_CARRITO]);
                            Auth::redirigir('pago/cobrar&idVenta=' . $idVenta);

                        default:
                            throw new InvalidArgumentException('La acción solicitada no es válida.');
                    }
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                    $codigoError = 422;
                } catch (OutOfBoundsException $e) {
                    $error = $e->getMessage();
                    $codigoError = 404;
                } catch (DomainException $e) {
                    $error = $e->getMessage();
                    $codigoError = 409;
                } catch (Throwable $e) {
                    error_log('No se pudo registrar la venta: ' . $e->getMessage());
                    $error = 'No se pudo registrar la venta. Intenta de nuevo.';
                    $codigoError = 500;
                }
            }
        }

        $lineas = $this->obtenerLineasCarrito($carrito);
        $subtotal = round((float) array_sum(array_column($lineas, 'subtotal')), 2);
        $impuesto = round($subtotal * IVA_PORCENTAJE / 100, 2);
        $total = round($subtotal + $impuesto, 2);
        http_response_code($codigoError);
        require RUTA_RAIZ . '/app/views/ventas/nueva.php';
    }

    private function crearVenta(int $idCajero, array $carrito): int
    {
        if ($carrito === []) {
            throw new DomainException('Agrega al menos un producto antes de confirmar la venta.');
        }

        $pdo = Database::getConexion();
        $pdo->beginTransaction();

        try {
            $productos = [];
            foreach ($carrito as $idProducto => $cantidad) {
                $idProducto = $this->enteroPositivo($idProducto, 'El producto');
                $cantidad = $this->enteroPositivo($cantidad, 'La cantidad');
                $producto = $this->buscarProductoActivo($idProducto, true);
                if ($producto === null) {
                    throw new OutOfBoundsException('Un producto ya no existe o está inactivo.');
                }
                if ($cantidad > (int) $producto['cantidadActual']) {
                    throw new DomainException('Las existencias cambiaron y ya no cubren la venta.');
                }
                $productos[] = [$idProducto, $cantidad, $producto['precioVenta']];
            }

            $idVenta = Venta::crear($idCajero);
            foreach ($productos as [$idProducto, $cantidad, $precio]) {
                DetalleVenta::agregarLinea($idVenta, $idProducto, $cantidad, $precio);
            }

            // PUNTO DE INTEGRACIÓN: descuento de inventario (Brandon)
            $pdo->commit();

            return $idVenta;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private function buscarProductoActivo(int $idProducto, bool $bloquear = false): ?array
    {
        $sql = 'SELECT p.idProducto, p.nombre, p.codigoBarras, p.precioVenta,
                    i.cantidadActual
             FROM Producto p
             INNER JOIN Inventario i ON i.idInventario = p.idInventario
             WHERE p.idProducto = :idProducto
               AND p.estado = :estado';
        $sql .= $bloquear ? ' LIMIT 1 FOR UPDATE' : ' LIMIT 1';
        $consulta = Database::getConexion()->prepare($sql);
        $consulta->execute([
            ':idProducto' => $idProducto,
            ':estado' => 'Activo',
        ]);
        $producto = $consulta->fetch();

        return $producto ?: null;
    }

    private function obtenerLineasCarrito(array $carrito): array
    {
        $lineas = [];
        foreach ($carrito as $idProducto => $cantidad) {
            $producto = $this->buscarProductoActivo((int) $idProducto);
            $cantidad = (int) $cantidad;
            $lineas[] = [
                'idProducto' => (int) $idProducto,
                'nombre' => $producto['nombre'] ?? 'Producto no disponible',
                'codigoBarras' => $producto['codigoBarras'] ?? null,
                'cantidad' => $cantidad,
                'precioUnitario' => (float) ($producto['precioVenta'] ?? 0),
                'stock' => (int) ($producto['cantidadActual'] ?? 0),
                'disponible' => $producto !== null,
                'subtotal' => $producto !== null
                    ? round((float) $producto['precioVenta'] * $cantidad, 2)
                    : 0.0,
            ];
        }

        return $lineas;
    }

    private function obtenerCarrito(): array
    {
        $carrito = $_SESSION[self::CLAVE_CARRITO] ?? [];
        if (!is_array($carrito)) {
            $carrito = [];
            $_SESSION[self::CLAVE_CARRITO] = $carrito;
        }

        return $carrito;
    }

    private function enteroPositivo(mixed $valor, string $etiqueta): int
    {
        if (is_bool($valor) || is_float($valor) || (!is_int($valor) && !is_string($valor))) {
            throw new InvalidArgumentException($etiqueta . ' debe ser un entero mayor que cero.');
        }

        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new InvalidArgumentException($etiqueta . ' debe ser un entero mayor que cero.');
        }

        return $entero;
    }
}