/**
 * Permisos: matriz usuario × módulo.
 * Las piezas compartidas (esc, el velo de carga) viven en js/comun.js.
 */
(function () {
    'use strict';

    const cuerpo = document.getElementById('cuerpo');
    const filtrar = document.getElementById('filtrar');
    const contador = document.getElementById('contador');
    const aviso = document.getElementById('aviso');
    const vacio = document.getElementById('vacio');

    /* Lo palomeado cuando cargó la página. Sirve para saber si un renglón tiene
       cambios sin guardar y para deshacerlos si el guardado falla. */
    const original = new Map();

    document.querySelectorAll('.prm-fila').forEach(fila => {
        original.set(fila.dataset.user, leer(fila));
    });

    /* Un solo listener en el contenedor en vez de uno por casilla: son 26
       usuarios por 11 módulos y serían cerca de 300 listeners. */
    cuerpo.addEventListener('change', e => {
        if (e.target.matches('input[type="checkbox"]')) marcarSucio(fila(e.target));
    });

    cuerpo.addEventListener('click', e => {
        if (e.target.matches('.prm-guardar')) guardar(fila(e.target));
        if (e.target.matches('.prm-restablecer')) restablecer(fila(e.target));
    });

    filtrar.addEventListener('input', aplicarFiltro);

    function fila(elemento) {
        return elemento.closest('.prm-fila');
    }

    /** Slugs palomeados de un renglón. */
    function leer(f) {
        return Array.from(f.querySelectorAll('input[type="checkbox"]:checked'))
            .map(c => c.dataset.slug)
            .sort()
            .join(',');
    }

    /** Enseña el botón Guardar solo si de verdad cambió algo. */
    function marcarSucio(f) {
        const cambiado = leer(f) !== original.get(f.dataset.user);
        f.classList.toggle('prm-fila--sucia', cambiado);
        f.querySelector('.prm-guardar').hidden = !cambiado;
    }

    async function guardar(f) {
        const usuario = f.dataset.user;
        const cuerpoForm = new FormData();
        cuerpoForm.append('usuario', usuario);
        cuerpoForm.append('accion', 'guardar');

        f.querySelectorAll('input[type="checkbox"]:checked').forEach(c => {
            cuerpoForm.append('modulos[]', c.dataset.slug);
        });

        await enviar(f, cuerpoForm, `Permisos de ${usuario} guardados.`);
    }

    async function restablecer(f) {
        const usuario = f.dataset.user;

        if (!confirm(`¿Devolver a ${usuario} a los permisos por omisión de cada módulo?`)) return;

        const cuerpoForm = new FormData();
        cuerpoForm.append('usuario', usuario);
        cuerpoForm.append('accion', 'restablecer');

        await enviar(f, cuerpoForm, `${usuario} vuelve a los permisos por omisión.`);
    }

    async function enviar(f, datos, exito) {
        f.classList.add('prm-fila--ocupada');
        marcarCarga(1, 'Guardando permisos');

        let respuesta;

        try {
            const res = await fetch('ajax/guardar.php', { method: 'POST', body: datos });
            respuesta = await res.json();
        } catch (e) {
            respuesta = null;
        } finally {
            marcarCarga(-1);
            f.classList.remove('prm-fila--ocupada');
        }

        if (!respuesta || respuesta.error) {
            /* Se devuelven las casillas a como estaban: si no se guardó, la
               pantalla no debe quedarse enseñando algo que no es verdad. */
            pintar(f, (original.get(f.dataset.user) || '').split(',').filter(Boolean));
            marcarSucio(f);
            return mostrarAviso((respuesta && respuesta.error) || 'No se pudo guardar.', true);
        }

        pintar(f, respuesta.modulos);
        original.set(f.dataset.user, leer(f));
        marcarSucio(f);

        f.querySelector('.prm-restablecer').hidden = !respuesta.configurado;

        const estado = f.querySelector('.prm-estado');
        estado.textContent = respuesta.configurado ? 'configurado' : 'por omisión';
        estado.classList.toggle('prm-estado--fijo', respuesta.configurado);

        mostrarAviso(exito, false);
    }

    /** Deja las casillas del renglón igual a la lista que mandó el servidor. */
    function pintar(f, modulos) {
        f.querySelectorAll('input[type="checkbox"]').forEach(c => {
            if (!c.disabled) c.checked = modulos.includes(c.dataset.slug);
        });
    }

    let temporizador = null;

    function mostrarAviso(texto, esError) {
        aviso.textContent = texto;
        aviso.classList.toggle('prm-aviso--error', !!esError);
        aviso.classList.toggle('prm-aviso--ok', !esError);
        aviso.hidden = false;

        /* El aviso de éxito se va solo; el de error se queda hasta el siguiente,
           porque es el que hay que leer con calma. */
        clearTimeout(temporizador);
        if (!esError) temporizador = setTimeout(() => { aviso.hidden = true; }, 3200);
    }

    function aplicarFiltro() {
        const q = filtrar.value.trim().toLowerCase();
        let visibles = 0;

        document.querySelectorAll('.prm-fila').forEach(f => {
            const coincide = q === '' || f.dataset.busca.includes(q);
            f.hidden = !coincide;
            if (coincide) visibles++;
        });

        contador.textContent = visibles === 1 ? '1 usuario' : `${visibles} usuarios`;
        vacio.hidden = visibles > 0;
    }
})();
