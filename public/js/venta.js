(() => {
    document.querySelectorAll('[data-metodo-pago]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const metodo = document.querySelector('[name="metodo"]');
            if (metodo) metodo.value = boton.dataset.metodoPago;
        });
    });

    // Pantalla de cobro: el valor recibido solo aplica a efectivo y se calcula el cambio en vivo
    const formulario = document.getElementById('formCobro');
    const metodo = document.getElementById('metodo');
    const campoRecibido = document.getElementById('campoRecibido');
    const recibido = document.getElementById('recibido');
    const cambio = document.getElementById('cambioCalculado');
    if (!formulario || !metodo || !campoRecibido || !recibido || !cambio) return;

    const total = Number(formulario.dataset.total || 0);
    const formato = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const actualizar = () => {
        const efectivo = metodo.value === 'Efectivo';
        campoRecibido.hidden = !efectivo;
        recibido.required = efectivo;
        if (!efectivo) recibido.value = '';

        if (!efectivo || recibido.value === '') {
            cambio.textContent = '';
            return;
        }
        const valor = Number(recibido.value);
        cambio.textContent = valor >= total
            ? 'Cambio: $ ' + formato.format(valor - total)
            : 'El valor recibido no cubre el total.';
    };

    metodo.addEventListener('change', actualizar);
    recibido.addEventListener('input', actualizar);
    actualizar();
})();
