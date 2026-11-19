<?php
/**
 * Acceso preparado al catálogo de productos.
 */
class Producto
{
    public static function listar(): array
    {
        $consulta = Database::getConexion()->query(
            'SELECT p.idProducto, p.idInventario, p.codigoBarras, p.nombre,
                    p.descripcion, p.precioCompra, p.precioVenta, p.categoria,
                    p.estado, i.cantidadActual
             FROM Producto p
             INNER JOIN Inventario i ON i.idInventario = p.idInventario
             ORDER BY p.nombre'
        );

        return $consulta->fetchAll();
    }

    public static function obtenerPorId(int $idProducto): ?array
    {
        if ($idProducto <= 0) {
            throw new InvalidArgumentException('El identificador del producto no es válido.');
        }

        $consulta = Database::getConexion()->prepare(
            'SELECT p.idProducto, p.idInventario, p.codigoBarras, p.nombre,
                    p.descripcion, p.precioCompra, p.precioVenta, p.categoria,
                    p.estado, i.cantidadActual
             FROM Producto p
             INNER JOIN Inventario i ON i.idInventario = p.idInventario
             WHERE p.idProducto = :idProducto
             LIMIT 1'
        );
        $consulta->execute([':idProducto' => $idProducto]);
        $producto = $consulta->fetch();

        return $producto ?: null;
    }

    public static function crear(array $datos): int
    {
        self::validarDatos($datos, false);

        $pdo = Database::getConexion();
        $codigo = $datos['codigoBarras'];
        if (self::codigoExiste($codigo)) {
            throw new DomainException('Ya existe un producto con ese código de barras.');
        }
        $cantidadActual = self::enteroNoNegativo($datos['cantidadActual'] ?? 0, 'La cantidad inicial');
        $stockMinimo = self::enteroNoNegativo($datos['stockMinimo'] ?? null, 'El stock mínimo');
        $stockMaximo = self::enteroNoNegativo($datos['stockMaximo'] ?? null, 'El stock máximo');
        if ($stockMaximo < $stockMinimo) {
            throw new DomainException('El stock máximo debe ser mayor o igual al stock mínimo.');
        }
        $ubicacion = trim((string) ($datos['ubicacion'] ?? ''));
        if (mb_strlen($ubicacion) > 50) {
            throw new InvalidArgumentException('La ubicación debe tener máximo 50 caracteres.');
        }

        $pdo->beginTransaction();
        try {
            $insertarInventario = $pdo->prepare(
                'INSERT INTO Inventario
                    (cantidadActual, stockMinimo, stockMaximo, ubicacion)
                 VALUES
                    (:cantidadActual, :stockMinimo, :stockMaximo, :ubicacion)'
            );
            $insertarInventario->execute([
                ':cantidadActual' => $cantidadActual,
                ':stockMinimo' => $stockMinimo,
                ':stockMaximo' => $stockMaximo,
                ':ubicacion' => $ubicacion,
            ]);
            $idInventario = (int) $pdo->lastInsertId();

            $consulta = $pdo->prepare(
                'INSERT INTO Producto
                    (idInventario, codigoBarras, nombre, descripcion,
                     precioCompra, precioVenta, categoria, estado)
                 VALUES
                    (:idInventario, :codigoBarras, :nombre, :descripcion,
                     :precioCompra, :precioVenta, :categoria, :estado)'
            );
            $consulta->execute([
                ':idInventario' => $idInventario,
                ':codigoBarras' => $codigo,
                ':nombre' => $datos['nombre'],
                ':descripcion' => $datos['descripcion'],
                ':precioCompra' => self::precioDecimal($datos['precioCompra']),
                ':precioVenta' => self::precioDecimal($datos['precioVenta']),
                ':categoria' => $datos['categoria'],
                ':estado' => 'Activo',
            ]);

            $pdo->commit();
            return (int) $pdo->lastInsertId();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof PDOException && (int) $error->errorInfo[1] === 1062) {
                throw new DomainException('Ya existe un producto con ese código de barras.');
            }
            throw $error;
        }
    }

    public static function actualizar(int $idProducto, array $datos): void
    {
        if ($idProducto <= 0) {
            throw new InvalidArgumentException('El identificador del producto no es válido.');
        }
        self::validarDatos($datos, false);

        $codigo = $datos['codigoBarras'];
        if (self::codigoExiste($codigo, $idProducto)) {
            throw new DomainException('Ya existe otro producto con ese código de barras.');
        }

        $consulta = Database::getConexion()->prepare(
            'UPDATE Producto
             SET codigoBarras = :codigoBarras,
                 nombre = :nombre,
                 descripcion = :descripcion,
                 precioCompra = :precioCompra,
                 precioVenta = :precioVenta,
                 categoria = :categoria
             WHERE idProducto = :idProducto'
        );

        try {
            $consulta->execute([
                ':codigoBarras' => $codigo,
                ':nombre' => $datos['nombre'],
                ':descripcion' => $datos['descripcion'],
                ':precioCompra' => self::precioDecimal($datos['precioCompra']),
                ':precioVenta' => self::precioDecimal($datos['precioVenta']),
                ':categoria' => $datos['categoria'],
                ':idProducto' => $idProducto,
            ]);
            if ($consulta->rowCount() === 0 && self::obtenerPorId($idProducto) === null) {
                throw new OutOfBoundsException('El producto no existe.');
            }
        } catch (PDOException $error) {
            if ((int) $error->errorInfo[1] === 1062) {
                throw new DomainException('Ya existe otro producto con ese código de barras.');
            }
            throw $error;
        }
    }

    public static function darDeBaja(int $idProducto): void
    {
        if ($idProducto <= 0) {
            throw new InvalidArgumentException('El identificador del producto no es válido.');
        }

        $consulta = Database::getConexion()->prepare(
            'UPDATE Producto SET estado = :estado WHERE idProducto = :idProducto'
        );
        $consulta->execute([
            ':estado' => 'Inactivo',
            ':idProducto' => $idProducto,
        ]);

        if ($consulta->rowCount() === 0) {
            if (self::obtenerPorId($idProducto) === null) {
                throw new OutOfBoundsException('El producto no existe.');
            }
        }
    }

    public static function inventariosDisponibles(): array
    {
        $consulta = Database::getConexion()->query(
            'SELECT i.idInventario, i.cantidadActual, i.stockMinimo, i.ubicacion
             FROM Inventario i
             LEFT JOIN Producto p ON p.idInventario = i.idInventario
             WHERE p.idProducto IS NULL
             ORDER BY i.idInventario'
        );

        // PUNTO DE INTEGRACIÓN: inventario (Brandon). Solo consulta registros existentes.
        return $consulta->fetchAll();
    }

    private static function codigoExiste(string $codigo, ?int $idExcluir = null): bool
    {
        $sql = 'SELECT 1 FROM Producto WHERE codigoBarras = :codigo';
        $parametros = [':codigo' => $codigo];
        if ($idExcluir !== null) {
            $sql .= ' AND idProducto <> :idProducto';
            $parametros[':idProducto'] = $idExcluir;
        }
        $sql .= ' LIMIT 1';

        $consulta = Database::getConexion()->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchColumn() !== false;
    }

    private static function validarDatos(array $datos, bool $requiereInventario = true): void
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $codigo = trim((string) ($datos['codigoBarras'] ?? ''));
        $categoria = trim((string) ($datos['categoria'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('El nombre es obligatorio y debe tener máximo 100 caracteres.');
        }
        if ($codigo === '' || mb_strlen($codigo) > 20) {
            throw new InvalidArgumentException('El código de barras es obligatorio y debe tener máximo 20 caracteres.');
        }
        if ($categoria === '' || mb_strlen($categoria) > 50) {
            throw new InvalidArgumentException('La categoría es obligatoria y debe tener máximo 50 caracteres.');
        }

        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        if (mb_strlen($descripcion) > 200) {
            throw new InvalidArgumentException('La descripción debe tener máximo 200 caracteres.');
        }
        $datos['nombre'] = $nombre;
        $datos['codigoBarras'] = $codigo;
        $datos['descripcion'] = $descripcion;
        $datos['categoria'] = $categoria;

        foreach (['precioCompra', 'precioVenta'] as $campo) {
            $valor = $datos[$campo] ?? null;
            if (
                $valor === null
                || (!is_int($valor) && !is_float($valor) && !is_string($valor))
                || !is_numeric($valor)
            ) {
                throw new InvalidArgumentException('El precio es obligatorio y debe ser un valor válido.');
            }
            $precio = (float) $valor;
            if (!is_finite($precio) || $precio < 0 || $precio > 9999999999.99) {
                throw new InvalidArgumentException('El precio debe ser mayor o igual a cero.');
            }
        }

        if ($requiereInventario) {
            $idInventario = filter_var(
                $datos['idInventario'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($idInventario === false) {
                throw new InvalidArgumentException('Debes seleccionar un registro de inventario disponible.');
            }
        }
    }

    private static function enteroNoNegativo(mixed $valor, string $etiqueta): int
    {
        if (is_bool($valor) || is_float($valor) || (!is_int($valor) && !is_string($valor))) {
            throw new InvalidArgumentException($etiqueta . ' debe ser un entero mayor o igual a cero.');
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($entero === false) {
            throw new InvalidArgumentException($etiqueta . ' debe ser un entero mayor o igual a cero.');
        }
        return $entero;
    }

    private static function precioDecimal(mixed $precio): string
    {
        return number_format(round((float) $precio, 2), 2, '.', '');
    }
}