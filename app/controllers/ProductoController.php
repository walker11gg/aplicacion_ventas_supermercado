<?php
/**
 * Administración del catálogo de productos.
 */
class ProductoController
{
    public function index(): void
    {
        $this->listar();
    }

    public function listar(): void
    {
        Auth::requerirRol(['Administrador']);

        $error = $_SESSION['producto_error'] ?? null;
        $exito = $_SESSION['producto_exito'] ?? null;
        unset($_SESSION['producto_error'], $_SESSION['producto_exito']);

        try {
            $productos = Producto::listar();
        } catch (Throwable $errorBD) {
            error_log('No se pudo listar productos: ' . $errorBD->getMessage());
            $productos = [];
            $error = 'No se pudieron cargar los productos.';
            http_response_code(500);
        }

        $tituloPagina = 'Productos';
        $migas = ['Productos'];
        $rutaActiva = 'producto/listar';
        require RUTA_RAIZ . '/app/views/productos/listar.php';
    }

    public function crear(): void
    {
        Auth::requerirRol(['Administrador']);
        $this->mostrarFormulario(null);
    }

    public function editar(): void
    {
        Auth::requerirRol(['Administrador']);

        $idProducto = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($idProducto === false) {
            http_response_code(400);
            echo 'Identificador de producto no válido.';
            exit;
        }

        try {
            $producto = Producto::obtenerPorId($idProducto);
        } catch (Throwable $error) {
            error_log('No se pudo obtener el producto: ' . $error->getMessage());
            http_response_code(500);
            echo 'No se pudo cargar el producto.';
            exit;
        }
        if ($producto === null) {
            http_response_code(404);
            echo 'Producto no encontrado.';
            exit;
        }

        $this->mostrarFormulario($producto);
    }

    public function guardar(): void
    {
        Auth::requerirRol(['Administrador']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
        if (!Seguridad::validarCsrf($_POST['csrf'] ?? null)) {
            http_response_code(400);
            $_SESSION['producto_error'] = 'El formulario expiró. Intenta de nuevo.';
            Auth::redirigir('producto/listar');
        }

        $idProducto = filter_var(
            $_POST['idProducto'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $datos = [
            'idInventario' => $_POST['idInventario'] ?? null,
            'codigoBarras' => $_POST['codigoBarras'] ?? '',
            'nombre' => $_POST['nombre'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'precioCompra' => $_POST['precioCompra'] ?? null,
            'precioVenta' => $_POST['precioVenta'] ?? null,
            'categoria' => $_POST['categoria'] ?? '',
            'cantidadActual' => $_POST['cantidadActual'] ?? 0,
            'stockMinimo' => $_POST['stockMinimo'] ?? null,
            'stockMaximo' => $_POST['stockMaximo'] ?? null,
            'ubicacion' => $_POST['ubicacion'] ?? '',
        ];

        try {
            if ($idProducto === false) {
                Producto::crear($datos);
                $_SESSION['producto_exito'] = 'Producto registrado correctamente.';
            } else {
                Producto::actualizar($idProducto, $datos);
                $_SESSION['producto_exito'] = 'Producto actualizado correctamente.';
            }
            Auth::redirigir('producto/listar');
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            $_SESSION['producto_error'] = $error->getMessage();
        } catch (DomainException $error) {
            http_response_code(409);
            $_SESSION['producto_error'] = $error->getMessage();
        } catch (OutOfBoundsException $error) {
            http_response_code(404);
            $_SESSION['producto_error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('No se pudo guardar el producto: ' . $error->getMessage());
            http_response_code(500);
            $_SESSION['producto_error'] = 'No se pudo guardar el producto.';
        }

        if ($idProducto !== false) {
            Auth::redirigir('producto/editar&id=' . $idProducto);
        }
        Auth::redirigir('producto/crear');
    }

    public function baja(): void
    {
        Auth::requerirRol(['Administrador']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
        if (!Seguridad::validarCsrf($_POST['csrf'] ?? null)) {
            http_response_code(400);
            $_SESSION['producto_error'] = 'El formulario expiró. Intenta de nuevo.';
            Auth::redirigir('producto/listar');
        }

        $idProducto = filter_var(
            $_POST['idProducto'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($idProducto === false) {
            http_response_code(400);
            $_SESSION['producto_error'] = 'Identificador de producto no válido.';
            Auth::redirigir('producto/listar');
        }

        try {
            Producto::darDeBaja($idProducto);
            $_SESSION['producto_exito'] = 'Producto dado de baja correctamente.';
        } catch (OutOfBoundsException $error) {
            http_response_code(404);
            $_SESSION['producto_error'] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('No se pudo dar de baja el producto: ' . $error->getMessage());
            http_response_code(500);
            $_SESSION['producto_error'] = 'No se pudo dar de baja el producto.';
        }

        Auth::redirigir('producto/listar');
    }

    private function mostrarFormulario(?array $producto): void
    {
        $error = $_SESSION['producto_error'] ?? null;
        unset($_SESSION['producto_error']);

        $inventariosDisponibles = [];
        if ($producto === null) {
            try {
                $inventariosDisponibles = Producto::inventariosDisponibles();
            } catch (Throwable $errorBD) {
                error_log('No se pudieron consultar inventarios disponibles: ' . $errorBD->getMessage());
                $error = 'No se pudieron consultar los registros de inventario disponibles.';
                http_response_code(500);
            }
        }

        $tituloPagina = $producto === null ? 'Nuevo producto' : 'Editar producto';
        $migas = ['Productos', $tituloPagina];
        $rutaActiva = 'producto/listar';
        require RUTA_RAIZ . '/app/views/productos/formulario.php';
    }
}