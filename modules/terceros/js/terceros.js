/**
 * Terceros: descuentos a terceros de una quincena.
 * Las piezas compartidas (fmt, esc, pedir, el velo de carga) viven en js/comun.js.
 */
(function () {
    'use strict';

    /* Cuántos renglones se pintan de golpe. El concentrado de una quincena ronda
       los 1,700, pero con un grupo suelto y varias UR puede crecer, así que se
       pinta por tandas igual que en Conceptos. */
    const TANDA = 400;

    const form = document.getElementById('filtros');
    const aviso = document.getElementById('aviso');
    const resultado = document.getElementById('resultado');
    const filtrar = document.getElementById('filtrar');
    const botonMas = document.getElementById('mas');
    const descargar = document.getElementById('descargar');

    /* Última respuesta del servidor. Filtrar y paginar trabajan sobre ella: ninguna
       de las dos vuelve a recorrer las tablas de nómina. */
    let datos = null;
    let visibles = [];
    let pintadas = 0;

    /* No se genera al cargar la página ni al cambiar un selector: cada corrida
       recorre las seis tablas del periodo. Solo con el submit. */
    form.addEventListener('submit', e => {
        e.preventDefault();
        generar();
    });

    filtrar.addEventListener('input', aplicarFiltro);
    botonMas.addEventListener('click', pintarTanda);

    async function generar() {
        let respuesta;

        try {
            respuesta = await pedir('ajax/generar.php', {
                anio: document.getElementById('anio').value,
                quincena: document.getElementById('quincena').value,
                grupo: document.getElementById('grupo').value
            }, { etiqueta: 'Recorriendo las tablas de nómina' });
        } catch (e) {
            respuesta = null;
        }

        if (!respuesta || respuesta.error) {
            resultado.hidden = true;
            return mostrarAviso((respuesta && respuesta.error) || 'No se pudo generar el reporte.');
        }

        datos = respuesta;
        aviso.hidden = true;

        descargar.href = 'ajax/descargar.php?token=' + encodeURIComponent(datos.token);
        descargar.download = datos.nombreZip;
        descargar.title = 'Contiene ' + datos.archivos.join(' y ');

        resultado.hidden = false;
        pintarMetricas();
        pintarChips();
        filtrar.value = '';
        aplicarFiltro();
    }

    function mostrarAviso(texto) {
        aviso.textContent = texto;
        aviso.hidden = false;
    }

    function pintarMetricas() {
        // Registros son los del detalle, no los del concentrado: un trabajador con
        // tres descuentos de terceros aporta tres registros y puede caer en un solo
        // renglón del concentrado.
        document.getElementById('m-registros').textContent = fmt(datos.registros);
        document.getElementById('m-importe').textContent = dinero(datos.importe);
        document.getElementById('m-renglones').textContent = fmt(datos.resumen.length);
        document.getElementById('m-grupos').textContent =
            fmt(datos.porGrupo.filter(g => g.registros > 0).length);
    }

    function pintarChips() {
        /* Un grupo sin registros se atenúa pero no se esconde: saber que se buscó
           y salió en ceros es parte del cierre. */
        document.getElementById('por-grupo').innerHTML = datos.porGrupo.map(g =>
            `<span class="ter-chip ${g.registros ? '' : 'ter-chip--vacio'}">
               <strong>${esc(g.grupo.replace(/_/g, ' '))}</strong>
               <span>${fmt(g.registros)}</span>
               <span class="ter-chip__im">${dinero(g.importe)}</span>
             </span>`
        ).join('');
    }

    /** Filtra en memoria sobre lo ya traído: no hay segunda consulta a la base. */
    function aplicarFiltro() {
        const q = filtrar.value.trim().toUpperCase();

        visibles = q === ''
            ? datos.resumen
            : datos.resumen.filter(f =>
                f.grupo.toUpperCase().includes(q)
                || f.ur.toUpperCase().includes(q)
                || f.rama.toUpperCase().includes(q)
                || f.tipo.toUpperCase().includes(q)
                || f.banco.toUpperCase().includes(q)
                || f.concepto.toUpperCase().includes(q));

        document.getElementById('tabla').innerHTML = '';
        pintadas = 0;

        const suma = visibles.reduce((t, f) => t + f.importe, 0);
        document.getElementById('contador').textContent =
            `${fmt(visibles.length)} renglones · ${dinero(suma)} · ${fmt(datos.ms)} ms`;

        if (!visibles.length) {
            document.getElementById('tabla').innerHTML =
                '<div class="emp-vacio">Nada coincide con este filtro.</div>';
            botonMas.hidden = true;
            return;
        }

        pintarTanda();
    }

    function pintarTanda() {
        const hasta = Math.min(pintadas + TANDA, visibles.length);

        document.getElementById('tabla').insertAdjacentHTML(
            'beforeend',
            visibles.slice(pintadas, hasta).map(fila).join('')
        );

        pintadas = hasta;
        botonMas.hidden = pintadas >= visibles.length;
        botonMas.textContent = `Mostrar más (${fmt(visibles.length - pintadas)} restantes)`;
    }

    function fila(f) {
        return `
        <div class="tabla__row ter-grid">
          <div class="ter-grupo" title="${esc(f.grupo)}">${esc(f.grupo.replace(/_/g, ' '))}</div>
          <div>${esc(f.ur)}</div>
          <div class="ter-suave">${esc(f.rama)}</div>
          <div class="ter-suave">${esc(f.tipo)}</div>
          <div class="ter-suave">${esc(f.banco) || '—'}</div>
          <div><span class="ter-clave">${esc(f.concepto)}</span></div>
          <div class="ter-num">${dinero(f.importe)}</div>
        </div>`;
    }

    function dinero(n) {
        return (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString('es-MX', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    /* ── Catálogo de grupos ───────────────────────────────────────────────
       Solo existe para quien puede administrarlo: si el PHP no pintó el panel,
       todo este bloque se queda sin hacer nada. */

    const panel = document.getElementById('panel-grupos');
    if (!panel) return;

    const abrir = document.getElementById('abrir-grupos');
    const lista = document.getElementById('lista-grupos');
    const formGrupo = document.getElementById('form-grupo');
    const gNombre = document.getElementById('g-nombre');
    const gPrefijos = document.getElementById('g-prefijos');
    const gAviso = document.getElementById('g-aviso');
    const gPrueba = document.getElementById('g-prueba');

    abrir.addEventListener('click', () => abrirPanel());

    panel.addEventListener('click', e => {
        if (e.target.hasAttribute('data-cerrar')) cerrarPanel();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !panel.hidden) cerrarPanel();
    });

    formGrupo.addEventListener('submit', e => {
        e.preventDefault();
        guardarGrupo();
    });

    document.getElementById('g-probar').addEventListener('click', probarPrefijos);

    /* Los botones de cada grupo se crean al vuelo, así que el listener va en el
       contenedor y no en cada uno. */
    lista.addEventListener('click', e => {
        const boton = e.target.closest('button[data-accion]');
        if (!boton) return;

        const nombre = boton.dataset.nombre;

        if (boton.dataset.accion === 'editar') {
            gNombre.value = nombre;
            gPrefijos.value = boton.dataset.prefijos;
            gPrefijos.focus();
            avisoGrupo('', false);
        }

        if (boton.dataset.accion === 'eliminar') eliminarGrupo(nombre, boton.dataset.fabrica === '1');
    });

    async function abrirPanel() {
        panel.hidden = false;
        avisoGrupo('', false);
        gPrueba.hidden = true;
        await cargarGrupos();
        gNombre.focus();
    }

    function cerrarPanel() {
        panel.hidden = true;
    }

    async function cargarGrupos() {
        lista.innerHTML = '<div class="ter-panel__cargando">Cargando…</div>';

        let r;
        try {
            r = await (await fetch('ajax/grupos.php?accion=listar')).json();
        } catch (e) {
            r = null;
        }

        if (!r || r.error) {
            lista.innerHTML = '';
            return avisoGrupo((r && r.error) || 'No se pudo leer el catálogo.', true);
        }

        lista.innerHTML = r.grupos.map(g => {
            /* Tres estados y conviene distinguirlos: de fábrica intacto, de
               fábrica con los prefijos cambiados, y dado de alta aquí. Solo el
               último se puede borrar; a los de fábrica se les devuelve el
               original, porque el grupo sigue existiendo en el código. */
            const etiqueta = !g.deFabrica
                ? '<span class="ter-tag ter-tag--nuevo">agregado</span>'
                : (g.modificado ? '<span class="ter-tag ter-tag--cambiado">modificado</span>' : '');

            const quitar = (!g.deFabrica || g.modificado)
                ? `<button type="button" class="ter-mini" data-accion="eliminar"
                       data-nombre="${esc(g.nombre)}" data-fabrica="${g.deFabrica ? 1 : 0}">${g.deFabrica ? 'Restablecer' : 'Quitar'}</button>`
                : '';

            return `<div class="ter-grupo">
                <div class="ter-grupo__cab">
                    <span class="ter-grupo__nombre">${esc(g.nombre.replace(/_/g, ' '))}</span>
                    ${etiqueta}
                </div>
                <div class="ter-grupo__prefijos">
                    ${g.prefijos.map(p => `<span class="ter-clave">${esc(p)}</span>`).join('')}
                </div>
                <div class="ter-grupo__acciones">
                    <button type="button" class="ter-mini" data-accion="editar"
                            data-nombre="${esc(g.nombre)}" data-prefijos="${esc(g.prefijos.join(', '))}">Editar</button>
                    ${quitar}
                </div>
            </div>`;
        }).join('');
    }

    async function guardarGrupo() {
        const datos = new FormData();
        datos.append('accion', 'guardar');
        datos.append('nombre', gNombre.value);
        datos.append('prefijos', gPrefijos.value);

        const r = await pedirGrupos(datos);
        if (!r) return;

        avisoGrupo(`Grupo ${r.grupo} guardado. Vuelve a generar el reporte para verlo.`, false);
        gPrueba.hidden = true;
        await cargarGrupos();
        await refrescarSelector(r.grupo);
    }

    async function eliminarGrupo(nombre, deFabrica) {
        const pregunta = deFabrica
            ? `¿Devolver ${nombre} a sus prefijos originales?`
            : `¿Quitar el grupo ${nombre} del catálogo?`;

        if (!confirm(pregunta)) return;

        const datos = new FormData();
        datos.append('accion', 'eliminar');
        datos.append('nombre', nombre);

        const r = await pedirGrupos(datos);
        if (!r) return;

        avisoGrupo(deFabrica ? `${nombre} vuelve a su definición original.` : `${nombre} quitado.`, false);
        await cargarGrupos();
        await refrescarSelector();
    }

    /**
     * Consulta cuánto mueve cada prefijo en el periodo elegido arriba, ANTES de
     * darlo de alta. Existe por lo que costó FEGAC: el número que se tenía a
     * mano y el nombre apuntaban a conceptos distintos, y sin ver los importes
     * no había forma de notarlo.
     */
    async function probarPrefijos() {
        const prefijos = gPrefijos.value.split(/[^0-9A-Za-z]+/).filter(Boolean);

        if (!prefijos.length) return avisoGrupo('Escribe al menos un prefijo para consultar.', true);

        gPrueba.hidden = false;
        gPrueba.innerHTML = '<div class="ter-panel__cargando">Recorriendo las tablas…</div>';

        const anio = document.getElementById('anio').value;
        const quincena = document.getElementById('quincena').value;
        const bloques = [];

        for (const prefijo of prefijos) {
            const datos = new FormData();
            datos.append('accion', 'probar');
            datos.append('prefijo', prefijo);
            datos.append('anio', anio);
            datos.append('quincena', quincena);

            let r;
            try {
                r = await (await fetch('ajax/grupos.php', { method: 'POST', body: datos })).json();
            } catch (e) {
                r = null;
            }

            if (!r || r.error) {
                bloques.push(`<div class="ter-prueba__grupo"><strong>${esc(prefijo)}</strong>
                    <span class="ter-suave">${esc((r && r.error) || 'no se pudo consultar')}</span></div>`);
                continue;
            }

            const dueño = r.grupoActual
                ? `<span class="ter-tag ter-tag--cambiado">ya está en ${esc(r.grupoActual)}</span>`
                : '';

            const claves = r.claves.length
                ? r.claves.map(c =>
                    `<div class="ter-prueba__clave">
                        <span class="ter-clave">${esc(c.clave)}</span>
                        <span class="ter-suave">${fmt(c.registros)} reg.</span>
                        <span class="ter-num">${dinero(c.importe)}</span>
                     </div>`).join('')
                : '<div class="ter-suave">Sin movimientos en este periodo.</div>';

            bloques.push(`<div class="ter-prueba__grupo">
                <div class="ter-prueba__cab">
                    <strong>${esc(r.prefijo)}</strong> ${dueño}
                    <span class="ter-num">${fmt(r.registros)} reg. · ${dinero(r.importe)}</span>
                </div>
                ${claves}
            </div>`);
        }

        gPrueba.innerHTML = `<div class="eyebrow">Q${esc(quincena)} de ${esc(anio)}</div>` + bloques.join('');
    }

    /** Petición al endpoint del catálogo, con el velo y el aviso ya resueltos. */
    async function pedirGrupos(datos) {
        marcarCarga(1, 'Guardando catálogo');

        let r;
        try {
            r = await (await fetch('ajax/grupos.php', { method: 'POST', body: datos })).json();
        } catch (e) {
            r = null;
        } finally {
            marcarCarga(-1);
        }

        if (!r || r.error) {
            avisoGrupo((r && r.error) || 'No se pudo guardar.', true);
            return null;
        }

        return r;
    }

    function avisoGrupo(texto, esError) {
        gAviso.textContent = texto;
        gAviso.hidden = texto === '';
        gAviso.classList.toggle('ter-aviso--error', !!esError);
        gAviso.classList.toggle('ter-aviso--ok', !esError && texto !== '');
    }

    /**
     * Vuelve a llenar el selector de grupos de la pantalla. Sin esto habría que
     * recargar la página para poder generar el reporte del grupo recién dado de
     * alta, que es justo lo que se viene a hacer.
     */
    async function refrescarSelector(seleccionar) {
        let r;
        try {
            r = await (await fetch('ajax/grupos.php?accion=listar')).json();
        } catch (e) {
            return;
        }

        if (!r || r.error) return;

        const sel = document.getElementById('grupo');
        const previo = seleccionar || sel.value;

        sel.innerHTML = '<option value="Todos">Todos los grupos</option>'
            + r.grupos.map(g =>
                `<option value="${esc(g.nombre)}">${esc(g.nombre.replace(/_/g, ' '))}</option>`).join('');

        sel.value = [...sel.options].some(o => o.value === previo) ? previo : 'Todos';
    }
})();
