<?php
$tituloPagina = $producto === null ? 'Nuevo producto' : 'Editar producto';
$migas = ['Productos', $tituloPagina];
$rutaActiva = 'producto/listar';
require RUTA_RAIZ . '/app/views/layouts/header.php';

$valores = $producto ?? [
    'idInventario' => '',
    'codigoBarras' => '',
    'nombre' => '',
    'descripcion' => '',
    'precioCompra' => '',
    'precioVenta' => '',
    'categoria' => '',
];
?>

<h1 class="titulo-1"><?= Seguridad::e($tituloPagina) ?></h1>

<?php if (!empty($error)): ?>
    <div class="alerta alerta--error" role="alert">
        <span><?= Seguridad::e($error) ?></span>
    </div>
<?php endif; ?>

<section class="tarjeta tarjeta--formulario">
    <form class="formulario formulario--columnas" method="post" action="<?= Seguridad::url('producto/guardar') ?>">
        <?= Seguridad::campoCsrf() ?>
        <?php if ($producto !== null): ?>
            <input type="hidden" name="idProducto" value="<?= (int) $producto['idProducto'] ?>">
        <?php endif; ?>

        <div class="campo campo--ancho">
            <label class="campo__etiqueta" for="nombre">Nombre *</label>
            <input class="campo__control" type="text" id="nombre" name="nombre"
                   value="<?= Seguridad::e($valores['nombre']) ?>" maxlength="100" required>
        </div>

        <div class="campo">
            <label class="campo__etiqueta" for="codigoBarras">Código de barras *</label>
            <input class="campo__control" type="text" id="codigoBarras" name="codigoBarras"
                   value="<?= Seguridad::e($valores['codigoBarras']) ?>" maxlength="20" required>
        </div>

        <div class="campo">
            <label class="campo__etiqueta" for="categoria">Categoría *</label>
            <input class="campo__control" type="text" id="categoria" name="categoria"
                   value="<?= Seguridad::e($valores['categoria']) ?>" maxlength="50" required>
        </div>

        <div class="campo campo--ancho">
            <label class="campo__etiqueta" for="descripcion">Descripción</label>
            <input class="campo__control" type="text" id="descripcion" name="descripcion"
                   value="<?= Seguridad::e($valores['descripcion']) ?>" maxlength="200">
        </div>

        <div class="campo">
            <label class="campo__etiqueta" for="precioCompra">Precio de compra *</label>
            <input class="campo__control" type="number" id="precioCompra" name="precioCompra"
                   value="<?= Seguridad::e((string) $valores['precioCompra']) ?>"
                   min="0" step="0.01" required>
        </div>

        <div class="campo">
            <label class="campo__etiqueta" for="precioVenta">Precio de venta *</label>
            <input class="campo__control" type="number" id="precioVenta" name="precioVenta"
                   value="<?= Seguridad::e((string) $valores['precioVenta']) ?>"
                   min="0" step="0.01" required>
        </div>

        <?php if ($producto === null): ?>
            <div class="campo">
                <label class="campo__etiqueta" for="cantidadActual">Existencias iniciales *</label>
                <input class="campo__control" type="number" id="cantidadActual" name="cantidadActual"
                       min="0" step="1" value="0" required>
            </div>
            <div class="campo">
                <label class="campo__etiqueta" for="ubicacion">Ubicación</label>
                <input class="campo__control" type="text" id="ubicacion" name="ubicacion"
                       maxlength="50">
            </div>
            <div class="campo">
                <label class="campo__etiqueta" for="stockMinimo">Stock mínimo *</label>
                <input class="campo__control" type="number" id="stockMinimo" name="stockMinimo"
                       min="0" step="1" value="0" required>
            </div>
            <div class="campo">
                <label class="campo__etiqueta" for="stockMaximo">Stock máximo *</label>
                <input class="campo__control" type="number" id="stockMaximo" name="stockMaximo"
                       min="0" step="1" value="0" required>
            </div>
        <?php endif; ?>

        <div class="acciones-formulario campo--ancho">
            <button class="btn btn--primario" type="submit">
                <?= $producto === null ? 'Crear producto' : 'Guardar cambios' ?>
            </button>
            <a class="btn btn--outline" href="<?= Seguridad::url('producto/listar') ?>">Cancelar</a>
        </div>
    </form>
</section>

<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
