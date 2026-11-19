(() => {
    const entrada = document.getElementById('buscarProducto');
    const resultados = document.getElementById('resultadosBusqueda');
    const formulario = document.querySelector('form[action*="venta/agregar"]');
    if (!entrada || !resultados || !formulario) return;

    let temporizador = null;

    entrada.addEventListener('input', () => {
        window.clearTimeout(temporizador);
        const consulta = entrada.value.trim();
        if (consulta === '') {
            resultados.innerHTML = '';
            return;
        }
        temporizador = window.setTimeout(async () => {
            try {
                const respuesta = await fetch(
                    'index.php?ruta=busqueda/buscar&q=' + encodeURIComponent(consulta),
                    { headers: { 'Accept': 'application/json' } }
                );
                const datos = await respuesta.json();
                resultados.innerHTML = '';
                (datos.productos || []).forEach((producto) => {
                    const tarjeta = document.createElement('article');
                    tarjeta.className = 'tarjeta';

                    const titulo = document.createElement('h3');
                    titulo.className = 'titulo-3';
                    titulo.textContent = producto.nombre;
                    const codigo = document.createElement('p');
                    codigo.textContent = 'Código: ' + producto.codigoBarras;
                    const stock = document.createElement('p');
                    stock.textContent = 'Existencias: ' + producto.cantidadActual;
                    const precio = document.createElement('p');
                    precio.textContent = 'Precio: $ ' + Number(producto.precioVenta).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'btn btn--outline btn--pequeno';
                    boton.textContent = 'Seleccionar';
                    boton.addEventListener('click', () => {
                        const id = formulario.querySelector('[name="idProducto"]');
                        if (id) id.value = String(producto.idProducto);
                        const cantidad = formulario.querySelector('[name="cantidad"]');
                        if (cantidad) {
                            if (cantidad.value === '') cantidad.value = '1';
                            cantidad.focus();
                            cantidad.select();
                        }
                        entrada.value = producto.nombre;
                        resultados.innerHTML = '';
                    });
                    tarjeta.append(titulo, codigo, stock, precio, boton);
                    resultados.appendChild(tarjeta);
                });
            } catch (error) {
                resultados.innerHTML = '<p class="texto-suave">No se pudo realizar la búsqueda.</p>';
            }
        }, 250);
    });
})();