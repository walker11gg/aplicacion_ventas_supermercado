<?php
$tituloPagina = 'Cobro de venta';
$migas = ['Ventas', 'Cobro'];
$rutaActiva = 'venta/nueva';
require RUTA_RAIZ . '/app/views/layouts/header.php';
?>

<h1 class="titulo-1">Cobro</h1>

<?php if ($error !== null): ?>
    <div class="alerta alerta--error" role="alert">
        <span><?= Seguridad::e($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($resultado !== null): ?>
    <section class="tarjeta resumen" aria-labelledby="titulo-pago-confirmado">
        <div class="alerta alerta--exito" role="status">
            <strong id="titulo-pago-confirmado">Pago registrado correctamente.</strong>
        </div>
        <p class="dato">
            <span class="dato__etiqueta">Método de pago</span>
            <span><?= Seguridad::e($resultado['metodoPago']) ?></span>
        </p>
        <p class="dato">
            <span class="dato__etiqueta">Estado de la venta</span>
            <span class="badge badge--exito"><?= Seguridad::e($venta['estado'] ?? 'Pagada') ?></span>
        </p>
        <?php if ($resultado['metodoPago'] === 'Efectivo'): ?>
            <p class="dato">
                <span class="dato__etiqueta">Valor recibido</span>
                <span class="cifras">$ <?= number_format($resultado['recibido'], 2, ',', '.') ?></span>
            </p>
            <p class="dato">
                <span class="dato__etiqueta">Cambio</span>
                <strong class="cifras">$ <?= number_format($resultado['cambio'], 2, ',', '.') ?></strong>
            </p>
        <?php endif; ?>
        <p class="dato dato--total">
            <span class="dato__etiqueta">Total</span>
            <strong class="cifras">$ <?= number_format($resultado['total'], 2, ',', '.') ?></strong>
        </p>
        <div class="acciones-formulario">
            <?php if (!empty($resultado['idFactura'])): ?>
                <a class="btn btn--outline btn--grande"
                   href="<?= Seguridad::url('factura/ver&id=' . (int) $resultado['idFactura']) ?>">Ver factura</a>
            <?php endif; ?>
            <a class="btn btn--primario btn--grande" href="<?= Seguridad::url('venta/nueva') ?>">Iniciar otra venta</a>
        </div>
    </section>
<?php elseif ($venta !== null): ?>
    <?php $subtotalVenta = round((float) $venta['total'] - (float) $venta['impuesto'], 2); ?>
    <section class="tarjeta" aria-labelledby="titulo-resumen">
        <div class="encabezado-pagina">
            <h2 class="titulo-2" id="titulo-resumen">Venta #<?= (int) $venta['idVenta'] ?></h2>
            <span class="badge <?= $venta['estado'] === 'Pagada' ? 'badge--exito' : ($venta['estado'] === 'Anulada' ? 'badge--error' : 'badge--aviso') ?>">
                <?= Seguridad::e($venta['estado']) ?>
            </span>
        </div>
        <p class="texto-pequeno texto-suave">Fecha: <?= Seguridad::e(date('d/m/Y H:i', strtotime((string) $venta['fecha']))) ?></p>

        <?php if ($detalles !== []): ?>
            <div class="tabla-contenedor">
                <table class="tabla">
                    <thead>
                        <tr><th>Producto</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?= Seguridad::e($detalle['nombre']) ?></td>
                            <td class="num"><?= (int) $detalle['cantidad'] ?></td>
                            <td class="num">$ <?= number_format((float) $detalle['precioUnitario'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format((float) $detalle['subtotal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="resumen">
            <p class="dato">
                <span class="dato__etiqueta">Subtotal</span>
                <span class="cifras">$ <?= number_format($subtotalVenta, 2, ',', '.') ?></span>
            </p>
            <p class="dato">
                <span class="dato__etiqueta">IVA (<?= (int) IVA_PORCENTAJE ?> %)</span>
                <span class="cifras">$ <?= number_format((float) $venta['impuesto'], 2, ',', '.') ?></span>
            </p>
            <p class="dato dato--total">
                <span class="dato__etiqueta">Total a pagar</span>
                <strong class="cifras">$ <?= number_format((float) $venta['total'], 2, ',', '.') ?></strong>
            </p>
        </div>

        <?php if ($venta['estado'] === 'Abierta' && $error === null): ?>
            <form class="formulario" id="formCobro" method="post"
                  data-total="<?= Seguridad::e(number_format((float) $venta['total'], 2, '.', '')) ?>"
                  action="<?= Seguridad::url('pago/cobrar&idVenta=' . (int) $venta['idVenta']) ?>">
                <?= Seguridad::campoCsrf() ?>
                <input type="hidden" name="idVenta" value="<?= (int) $venta['idVenta'] ?>">
                <div class="campo">
                    <label class="campo__etiqueta" for="metodo">Método de pago</label>
                    <select class="campo__control" id="metodo" name="metodo" required>
                        <option value="">Selecciona un método</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Pago digital">Pago digital</option>
                    </select>
                </div>
                <div class="campo" id="campoRecibido">
                    <label class="campo__etiqueta" for="recibido">Valor recibido (efectivo)</label>
                    <input class="campo__control" type="number" id="recibido" name="recibido"
                           min="0" step="0.01" inputmode="decimal">
                    <span class="campo__ayuda" id="cambioCalculado" aria-live="polite"></span>
                </div>
                <div class="acciones-formulario">
                    <button class="btn btn--acento btn--grande" type="submit">Registrar cobro</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

    <script src="<?= Seguridad::recurso('js/venta.js') ?>"></script>
<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
