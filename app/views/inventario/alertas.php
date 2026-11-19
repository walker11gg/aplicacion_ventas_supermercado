<?php
$tituloPagina = 'Alertas de stock';
$migas = ['Inventario', 'Alertas'];
$rutaActiva = 'inventario/alertas';
require RUTA_RAIZ . '/app/views/layouts/header.php';
?>

<h1 class="titulo-1">Alertas de stock</h1>
<?php if ($productos === []): ?>
    <div class="alerta alerta--exito" role="status">No hay productos activos con stock crítico.</div>
<?php else: ?>
    <div class="rejilla-modulos">
        <?php foreach ($productos as $producto): ?>
            <?php $cantidad = (int) $producto['cantidadActual']; ?>
            <article class="tarjeta">
                <h2 class="titulo-3 tarjeta__titulo"><?= Seguridad::e($producto['nombre']) ?></h2>
                <p class="texto-pequeno texto-suave">Código: <?= Seguridad::e($producto['codigoBarras']) ?></p>
                <p class="dato">
                    <span class="dato__etiqueta">Existencias</span>
                    <strong class="cifras"><?= $cantidad ?></strong>
                </p>
                <p class="dato">
                    <span class="dato__etiqueta">Stock mínimo</span>
                    <span class="cifras"><?= (int) $producto['stockMinimo'] ?></span>
                </p>
                <p class="dato">
                    <span class="dato__etiqueta">Ubicación</span>
                    <span><?= Seguridad::e($producto['ubicacion']) ?></span>
                </p>
                <p>
                    <span class="badge <?= $cantidad === 0 ? 'badge--error' : 'badge--aviso' ?>">
                        <?= $cantidad === 0 ? 'Agotado' : 'Stock bajo' ?>
                    </span>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
