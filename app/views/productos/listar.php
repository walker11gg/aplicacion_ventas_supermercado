<?php
$tituloPagina = 'Productos';
$migas = ['Productos'];
$rutaActiva = 'producto/listar';
require RUTA_RAIZ . '/app/views/layouts/header.php';
?>

<div class="encabezado-pagina">
    <h1 class="titulo-1">Catálogo de productos</h1>
    <a class="btn btn--primario" href="<?= Seguridad::url('producto/crear') ?>">Nuevo producto</a>
</div>

<?php if (!empty($exito)): ?>
    <div class="alerta alerta--exito" role="status">
        <span><?= Seguridad::e($exito) ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alerta alerta--error" role="alert">
        <span><?= Seguridad::e($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($productos === []): ?>
    <p class="texto-suave">No hay productos registrados.</p>
<?php else: ?>
    <div class="rejilla-modulos">
        <?php foreach ($productos as $producto): ?>
            <article class="tarjeta">
                <h2 class="titulo-3 tarjeta__titulo"><?= Seguridad::e($producto['nombre']) ?></h2>
                <p class="texto-pequeno texto-suave">Código: <?= Seguridad::e($producto['codigoBarras']) ?></p>
                <p class="dato">
                    <span class="dato__etiqueta">Categoría</span>
                    <span><?= Seguridad::e($producto['categoria']) ?></span>
                </p>
                <p class="dato">
                    <span class="dato__etiqueta">Precio de venta</span>
                    <strong class="cifras">$ <?= number_format((float) $producto['precioVenta'], 2, ',', '.') ?></strong>
                </p>
                <p class="dato">
                    <span class="dato__etiqueta">Existencias</span>
                    <strong class="cifras"><?= (int) $producto['cantidadActual'] ?></strong>
                </p>
                <p>
                    <span class="badge <?= $producto['estado'] === 'Activo' ? 'badge--exito' : 'badge--neutro' ?>">
                        <?= Seguridad::e($producto['estado']) ?>
                    </span>
                </p>

                <div class="tarjeta__acciones">
                    <a class="btn btn--outline btn--pequeno"
                       href="<?= Seguridad::url('producto/editar&id=' . (int) $producto['idProducto']) ?>">
                        <?= Iconos::svg('pencil', 16) ?> Editar
                    </a>
                    <?php if ($producto['estado'] === 'Activo'): ?>
                        <button class="btn btn--peligro btn--pequeno btn--icono" type="button"
                                data-baja-id="<?= (int) $producto['idProducto'] ?>"
                                data-baja-nombre="<?= Seguridad::e($producto['nombre']) ?>"
                                aria-label="Dar de baja <?= Seguridad::e($producto['nombre']) ?>"
                                title="Dar de baja">
                            <?= Iconos::svg('trash-2', 16) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<dialog class="modal" id="modalBaja" aria-labelledby="modalBajaTitulo">
    <div class="modal__contenido">
        <span class="modal__icono"><?= Iconos::svg('triangle-alert', 24) ?></span>
        <h2 class="titulo-3" id="modalBajaTitulo">¿Dar de baja este producto?</h2>
        <p class="texto-suave">
            <strong id="modalBajaNombre"></strong> dejará de estar disponible para la venta.
            Seguirá apareciendo en el catálogo como inactivo.
        </p>
        <div class="modal__acciones">
            <button class="btn btn--outline" type="button" id="modalBajaCancelar">Cancelar</button>
            <form method="post" action="<?= Seguridad::url('producto/baja') ?>">
                <?= Seguridad::campoCsrf() ?>
                <input type="hidden" name="idProducto" id="modalBajaId" value="">
                <button class="btn btn--peligro" type="submit">Dar de baja</button>
            </form>
        </div>
    </div>
</dialog>

    <script src="<?= Seguridad::recurso('js/productos.js') ?>"></script>
<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
