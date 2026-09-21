/**
 * Detalle: auditoría fila por fila de los registros de la quincena.
 * Las piezas compartidas viven en js/comun.js.
 */
(function () {
    'use strict';

    const COLUMNAS = '0.9fr 0.9fr 1.4fr 1fr 1fr 1fr 1fr';

    const buscador = document.getElementById('buscar');
    const filtroProducto = document.getElementById('filtro-producto');
    const filtroEstado = document.getElementById('filtro-estado');

    let periodo = null;
    let datos = null;

    document.addEventListener('DOMContentLoaded', () => {
        periodo = montarPeriodo(p => { periodo = p; cargar(); });
        cargar();
    });

    // Un tecleo por letra lanzaría una consulta por letra: se espera a la pausa.
    let temporizador = null;
    buscador.addEventListener('input', () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(cargar, 250);
    });
    filtroProducto.addEventListener('change', cargar);
    filtroEstado.addEventListener('change', cargar);

    async function cargar() {
        let respuesta;

        try {
            respuesta = await pedir('ajax/datos.php', {
                ...periodo,
                search: buscador.value,
                filtroProducto: filtroProducto.value,
                filtroEstado: filtroEstado.value
            }, { etiqueta: 'Cargando registros' });
        } catch (e) {
            respuesta = null;
        }

        if (!respuesta || respuesta.error) {
            document.getElementById('tabla').innerHTML =
                '<div class="emp-vacio">' + esc((respuesta && respuesta.error) || 'No se pudieron cargar los registros.') + '</div>';
            return;
        }

        datos = respuesta;

        // Cada quincena se compone de claves distintas. Si la que estaba
        // filtrada no pertenece a la nueva, el filtro se cae a "todos" y se
        // reconsulta: si no, la tabla quedaría vacía sin que el select explique
        // por qué.
        const claves = datos.productos || [];
        if (filtroProducto.value !== 'todos' && !claves.includes(filtroProducto.value)) {
            filtroProducto.value = 'todos';
            return cargar();
        }

        pintar();
    }

    /**
     * Rellena el filtro de productos con las claves de la quincena. Solo se
     * reconstruye cuando la lista cambia: rehacer el <select> en cada consulta
     * cerraría el desplegable si estuviera abierto.
     */
    function pintarSelectProductos(claves) {
        const firma = claves.join('|');
        if (filtroProducto.dataset.firma === firma) return;

        const elegido = filtroProducto.value;
        filtroProducto.innerHTML = '<option value="todos">Todos los productos</option>'
            + claves.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
        filtroProducto.dataset.firma = firma;
        filtroProducto.value = claves.includes(elegido) ? elegido : 'todos';
    }

    function pintar() {
        pintarSelectProductos(datos.productos || []);

        const filas = datos.rows || [];
        const total = datos.total ?? filas.length;

        // La consulta va ordenada por producto con tope: si la quincena tiene
        // más filas, lo que se ve es solo el primer producto alfabético. Decirlo
        // evita leer la tabla como si fuera la quincena completa.
        document.getElementById('contador').textContent = total > filas.length
            ? `Mostrando ${fmt(filas.length)} de ${fmt(total)} registros — filtra por producto o estado para ver el resto`
            : `${fmt(total)} registros`;

        document.getElementById('tabla').innerHTML = filas.length
            ? filas.map(fila).join('')
            : '<div class="emp-vacio">Ningún registro coincide con estos filtros.</div>';
    }

    function fila(r) {
        const estado = String(r.estado || '').toLowerCase().substring(0, 3);

        return `
        <div class="tabla__row" style="display:grid;grid-template-columns:${COLUMNAS}">
          <div style="font-weight:600">${esc(r.producto)}</div>
          <div><span class="chip-estado ${estado}"><span class="dot"></span>${esc(r.estado)}</span></div>
          <div style="color:var(--ink-muted);letter-spacing:0.3px">${esc(r.rfc)}</div>
          <div>$${esc(r.total1)}</div>
          <div>$${esc(r.total2)}</div>
          <div style="color:var(--ink-muted)">${esc(r.fechai)}</div>
          <div style="color:var(--ink-muted)">${esc(r.fechaf)}</div>
        </div>`;
    }
})();
