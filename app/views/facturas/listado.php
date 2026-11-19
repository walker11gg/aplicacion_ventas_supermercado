<?php
$tituloPagina = 'Facturas';
$migas = ['Facturas'];
$rutaActiva = 'factura/listado';
require RUTA_RAIZ . '/app/views/layouts/header.php';
?>

<h1 class="titulo-1">Facturas</h1>
<?php if ($facturas === []): ?>
    <p class="texto-suave">Aún no hay facturas emitidas.</p>
<?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Venta</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th class="num">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($facturas as $factura): ?>
                <?php
                $claseEstado = $factura['estado'] === 'Pagada'
                    ? 'badge--exito'
                    : ($factura['estado'] === 'Anulada' ? 'badge--error' : 'badge--aviso');
                ?>
                <tr>
                    <td><strong><?= Seguridad::e($factura['numeroConsecutivo']) ?></strong></td>
                    <td>#<?= (int) $factura['idVenta'] ?></td>
                    <td><?= Seguridad::e(date('d/m/Y H:i', strtotime((string) $factura['fechaEmision']))) ?></td>
                    <td><span class="badge <?= $claseEstado ?>"><?= Seguridad::e($factura['estado']) ?></span></td>
                    <td class="num">$ <?= number_format((float) $factura['total'], 2, ',', '.') ?></td>
                    <td class="num">
                        <a class="btn btn--outline btn--pequeno"
                           href="<?= Seguridad::url('factura/ver&id=' . (int) $factura['idFactura']) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
