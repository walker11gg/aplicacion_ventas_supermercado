(() => {
    // Modal de confirmación para dar de baja un producto
    const modal = document.getElementById('modalBaja');
    if (!modal || typeof modal.showModal !== 'function') return;

    const nombre = document.getElementById('modalBajaNombre');
    const idProducto = document.getElementById('modalBajaId');
    const cancelar = document.getElementById('modalBajaCancelar');

    document.querySelectorAll('[data-baja-id]').forEach((boton) => {
        boton.addEventListener('click', () => {
            idProducto.value = boton.dataset.bajaId;
            nombre.textContent = boton.dataset.bajaNombre;
            modal.showModal();
            cancelar.focus();
        });
    });

    cancelar.addEventListener('click', () => modal.close());

    // Clic en el fondo oscuro cierra el modal
    modal.addEventListener('click', (evento) => {
        if (evento.target === modal) modal.close();
    });
})();
