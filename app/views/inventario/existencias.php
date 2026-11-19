<?php
$tituloPagina = 'Existencias';
$migas = ['Inventario', 'Existencias'];
$rutaActiva = 'inventario/existencias';
require RUTA_RAIZ . '/app/views/layouts/header.php';
?>

<h1 class="titulo-1">Existencias</h1>
<section class="tarjeta">
    <form class="formulario formulario--busqueda" method="get"
          action="<?= Seguridad::url('inventario/existencias') ?>">
        <input type="hidden" name="ruta" value="inventario/existencias">
        <div class="campo">
            <label class="campo__etiqueta" for="q">Buscar por nombre o código</label>
            <input class="campo__control" type="text" id="q" name="q"
                   value="<?= Seguridad::e($busqueda) ?>" maxlength="100">
        </div>
        <button class="btn btn--primario" type="submit">Buscar</button>
    </form>
</section>

<?php if ($productos === []): ?>
    <p class="texto-suave">No se encontraron productos.</p>
<?php else: ?>
    <div class="rejilla-modulos">
        <?php foreach ($productos as $producto): ?>
            <?php
            $cantidad = (int) $producto['cantidadActual'];
            $critico = $producto['estado'] === 'Activo' && $cantidad <= (int) $producto['stockMinimo'];
            ?>
            <article class="tarjeta">
                <h2 class="titulo-3 tarjeta__titulo"><?= Seguridad::e($producto['nombre']) ?></h2>
                <p class="texto-pequeno texto-suave">Código: <?= Seguridad::e($producto['codigoBarras']) ?></p>
                <p class="dato">
                    <span class="dato__etiqueta">Existencias</span>
                    <strong class="cifras"><?= $cantidad ?></strong>
                </p>
                <p class="dato">
                    <span class="dato__etiqueta">Mínimo / Máximo</span>
                    <span class="cifras">
                        <?= (int) $producto['stockMinimo'] ?> / <?= (int) $producto['stockMaximo'] ?>
                    </span>
                </p>
                <p class="dato">
                    <span class="dato__etiqueta">Ubicación</span>
                    <span><?= Seguridad::e($producto['ubicacion']) ?></span>
                </p>
                <p>
                    <span class="badge <?= $producto['estado'] === 'Activo' ? 'badge--exito' : 'badge--neutro' ?>">
                        <?= Seguridad::e($producto['estado']) ?>
                    </span>
                    <?php if ($critico): ?>
                        <span class="badge <?= $cantidad === 0 ? 'badge--error' : 'badge--aviso' ?>">
                            <?= $cantidad === 0 ? 'Agotado' : 'Stock bajo' ?>
                        </span>
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require RUTA_RAIZ . '/app/views/layouts/footer.php'; ?>
