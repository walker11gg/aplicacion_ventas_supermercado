<?php
$tituloPagina = 'Factura ' . $factura['numeroConsecutivo'];
$migas = ['Facturas', $factura['numeroConsecutivo']];
$rutaActiva = 'factura/listado';
require RUTA_RAIZ . '/app/views/layouts/header.php';

$totalFactura = (float) $factura['total'];
$impuestoFactura = (float) ($factura['impuesto'] ?? 0);
$subtotalFactura = round($totalFactura - $impuestoFactura, 2);
$metodoVisible = match ($factura['metodoPago'] ?? null) {
    'Digital' => 'Pago digital',
    null => 'No registrado',
    default => (string) $factura['metodoPago'],
};
$claseEstado = $factura['estado'] === 'Pagada' ? 'badge--exito' : ($factura['estado'] === 'Anulada' ? 'badge--error' : 'badge--aviso');
?>
<div class="acciones-pagina no-imprimir">
    <button class="btn btn--primario" type="button" onclick="window.print()">Imprimir factura</button>
    <a class="btn btn--outline" href="<?= Seguridad::url('factura/listado') ?>">Volver al listado</a>
</div>

<article class="tarjeta documento">
    <header class="documento__encabezado">
        <div>
            <p class="texto-pequeno texto-suave">Supermercado POS · Factura de venta</p>
            <h1 class="titulo-1"><?= Seguridad::e($factura['numeroConsecutivo']) ?></h1>
        </div>
        <span class="badge <?= $claseEstado ?>"><?= Seguridad::e($factura['estado']) ?></span>
    </header>

    <div class="documento__datos">
        <p class="dato"><span class="dato__etiqueta">Cliente</span><span>Consumidor final</span></p>
        <p class="dato"><span class="dato__etiqueta">Fecha de emisión</span><span><?= Seguridad::e(date('d/m/Y H:i', strtotime((string) $factura['fechaEmision']))) ?></span></p>
        <p class="dato"><span class="dato__etiqueta">Venta</span><span>#<?= (int) $factura['idVenta'] ?></span></p>
        <p class="dato"><span class="dato__etiqueta">Cajero</span><span><?= Seguridad::e($factura['nombre'] . ' ' . $factura['apellido']) ?></span></p>
        <p class="dato"><span class="dato__etiqueta">Método de pago</span><span><?= Seguridad::e($metodoVisible) ?></span></p>
    </div>

    <div class="tabla-contenedor">
        <table class="tabla">
            <thead>
                <tr><th>Producto</th><th class="num">Cant.</th><th class="num">Precio unit.</th><th class="num">Subtotal</th></tr>
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

    <div class="resumen">
        <p class="dato">
            <span class="dato__etiqueta">Subtotal</span>
            <span class="cifras">$ <?= number_format($subtotalFactura, 2, ',', '.') ?></span>
        </p>
        <p class="dato">
            <span class="dato__etiqueta">IVA (<?= (int) IVA_PORCENTAJE ?> %)</span>
            <span class="cifras">$ <?= number_format($impuestoFactura, 2, ',', '.') ?></span>
        </p>
        <p class="dato dato--total">
            <span class="dato__etiqueta">Total facturado</span>
            <strong class="cifras">$ <?= number_format($totalFactura, 2, ',', '.') ?></strong>
        </p>
    </div>
</article>
<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
