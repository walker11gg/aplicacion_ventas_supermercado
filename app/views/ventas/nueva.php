<?php
$tituloPagina = 'Nueva venta';
$migas = ['Ventas', 'Nueva venta'];
$rutaActiva = 'venta/nueva';
require RUTA_RAIZ . '/app/views/layouts/header.php';
?>

<h1 class="titulo-1">Nueva venta</h1>
<p class="texto-suave">Busca productos por nombre o código de barras y agrégalos a la venta.</p>

<?php if ($error !== null): ?>
    <div class="alerta alerta--error" role="alert">
        <span><?= Seguridad::e($error) ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($exito)): ?>
    <div class="alerta alerta--exito" role="status">
        <span><?= Seguridad::e($exito) ?></span>
    </div>
<?php endif; ?>

<section class="tarjeta" aria-labelledby="titulo-agregar">
    <h2 class="titulo-2" id="titulo-agregar">Agregar producto</h2>
    <div class="campo">
        <label class="campo__etiqueta" for="buscarProducto">Buscar por nombre o código de barras</label>
        <input class="campo__control" type="search" id="buscarProducto" autocomplete="off">
    </div>
    <div id="resultadosBusqueda" class="rejilla-modulos" aria-live="polite"></div>
    <form class="formulario formulario--fila" method="post" action="<?= Seguridad::url('venta/agregar') ?>">
        <?= Seguridad::campoCsrf() ?>
        <input type="hidden" name="accion" value="agregar">
        <div class="campo">
            <label class="campo__etiqueta" for="idProducto">Identificador del producto</label>
            <input class="campo__control" type="number" id="idProducto" name="idProducto"
                   min="1" step="1" required>
        </div>
        <div class="campo">
            <label class="campo__etiqueta" for="cantidad">Cantidad</label>
            <input class="campo__control" type="number" id="cantidad" name="cantidad"
                   min="1" step="1" value="1" required>
        </div>
        <button class="btn btn--primario" type="submit">Agregar</button>
    </form>
</section>

<section class="seccion" aria-labelledby="titulo-carrito">
    <h2 class="titulo-2" id="titulo-carrito">Productos de la venta</h2>
    <?php if ($lineas === []): ?>
        <p class="texto-suave">Aún no has agregado productos.</p>
    <?php else: ?>
        <div class="rejilla-modulos">
            <?php foreach ($lineas as $linea): ?>
                <article class="tarjeta">
                    <h3 class="titulo-3 tarjeta__titulo"><?= Seguridad::e($linea['nombre']) ?></h3>
                    <p class="texto-pequeno texto-suave">
                        ID <?= (int) $linea['idProducto'] ?>
                        <?php if ($linea['codigoBarras'] !== null): ?>
                            · Código <?= Seguridad::e($linea['codigoBarras']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!$linea['disponible']): ?>
                        <div class="alerta alerta--error" role="alert">Producto no disponible.</div>
                    <?php else: ?>
                        <p class="dato">
                            <span class="dato__etiqueta">Precio unitario</span>
                            <span class="cifras">$ <?= number_format($linea['precioUnitario'], 2, ',', '.') ?></span>
                        </p>
                        <p class="dato">
                            <span class="dato__etiqueta">Existencias</span>
                            <span class="cifras"><?= (int) $linea['stock'] ?></span>
                        </p>
                        <p class="dato">
                            <span class="dato__etiqueta">Subtotal</span>
                            <strong class="cifras">$ <?= number_format($linea['subtotal'], 2, ',', '.') ?></strong>
                        </p>
                        <form class="form-linea" method="post" action="<?= Seguridad::url('venta/nueva') ?>">
                            <?= Seguridad::campoCsrf() ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="idProducto" value="<?= (int) $linea['idProducto'] ?>">
                            <label class="campo__etiqueta" for="cantidad-<?= (int) $linea['idProducto'] ?>">Cantidad</label>
                            <div class="fila-campo">
                                <input class="campo__control" type="number"
                                       id="cantidad-<?= (int) $linea['idProducto'] ?>" name="cantidad"
                                       value="<?= (int) $linea['cantidad'] ?>" min="1"
                                       max="<?= (int) $linea['stock'] ?>" step="1" required>
                                <button class="btn btn--outline btn--pequeno" type="submit">Actualizar</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    <div class="tarjeta__acciones">
                        <form class="form-linea" method="post" action="<?= Seguridad::url('venta/nueva') ?>">
                            <?= Seguridad::campoCsrf() ?>
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="idProducto" value="<?= (int) $linea['idProducto'] ?>">
                            <button class="btn btn--peligro btn--pequeno" type="submit">Quitar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="tarjeta resumen" aria-label="Resumen de la venta">
            <p class="dato">
                <span class="dato__etiqueta">Subtotal</span>
                <span class="cifras">$ <?= number_format($subtotal, 2, ',', '.') ?></span>
            </p>
            <p class="dato">
                <span class="dato__etiqueta">IVA (<?= (int) IVA_PORCENTAJE ?> %)</span>
                <span class="cifras">$ <?= number_format($impuesto, 2, ',', '.') ?></span>
            </p>
            <p class="dato dato--total">
                <span class="dato__etiqueta">Total</span>
                <strong class="cifras">$ <?= number_format($total, 2, ',', '.') ?></strong>
            </p>
            <div class="acciones-formulario">
                <form class="form-linea" method="post" action="<?= Seguridad::url('venta/nueva') ?>">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="confirmar">
                    <button class="btn btn--acento btn--grande" type="submit">Confirmar y cobrar</button>
                </form>
                <form class="form-linea" method="post" action="<?= Seguridad::url('venta/nueva') ?>">
                    <?= Seguridad::campoCsrf() ?>
                    <input type="hidden" name="accion" value="vaciar">
                    <button class="btn btn--outline btn--grande" type="submit"
                            onclick="return confirm('¿Cancelar la venta y vaciar los productos?');">Cancelar venta</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</section>

    <script src="<?= Seguridad::recurso('js/busqueda.js') ?>"></script>
<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
