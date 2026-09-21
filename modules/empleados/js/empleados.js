/**
 * Empleados: búsqueda por nombre/RFC/CURP y timbres de un RFC. Solo lectura.
 * Las piezas compartidas viven en js/comun.js.
 */
(function () {
    'use strict';

    const COLUMNAS = '0.4fr 1.5fr 0.9fr 0.9fr 0.9fr 0.9fr 0.9fr 0.9fr 0.8fr';

    const buscador = document.getElementById('buscar');
    const vistaLista = document.getElementById('vista-lista');
    const vistaDetalle = document.getElementById('vista-detalle');

    let empleados = null;
    let seleccionado = null;
    let pagos = null;

    /* Mismo plegado que EmpleadosIndex::normalizar en PHP, pero conservando la
       longitud del texto para poder resaltar sobre la cadena original. */
    const FOLD = {
        'Á':'A','À':'A','Ä':'A','Â':'A','Ã':'A','É':'E','È':'E','Ë':'E','Ê':'E',
        'Í':'I','Ì':'I','Ï':'I','Î':'I','Ó':'O','Ò':'O','Ö':'O','Ô':'O','Õ':'O',
        'Ú':'U','Ù':'U','Ü':'U','Û':'U','Ñ':'N','Ç':'C'
    };
    const fold = s => String(s ?? '').toUpperCase().replace(/[ÁÀÄÂÃÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇ]/g, c => FOLD[c]);

    /** Divide lo tecleado en los mismos términos que usa el servidor. */
    const terminos = s => fold(s).replace(/[^A-Z0-9 ]+/g, ' ').trim().split(/ +/).filter(Boolean);

    /**
     * Devuelve el texto con los términos encontrados envueltos en <mark>. Todo
     * lo que no es marca pasa por esc(), así que un nombre con caracteres raros
     * en la base no puede inyectar HTML.
     */
    function resaltar(texto, terms) {
        const t = String(texto ?? '');
        if (!t) return '';
        if (!terms.length) return esc(t);

        const f = fold(t);
        const marca = new Array(t.length).fill(false);

        terms.forEach(term => {
            let i = f.indexOf(term);
            while (i !== -1) {
                for (let k = i; k < i + term.length; k++) marca[k] = true;
                i = f.indexOf(term, i + 1);
            }
        });

        let salida = '', buf = '', activo = false;
        for (let i = 0; i <= t.length; i++) {
            const m = i < t.length && marca[i];
            if (i === t.length || m !== activo) {
                if (buf) salida += activo ? `<mark class="hl">${esc(buf)}</mark>` : esc(buf);
                buf = '';
                activo = m;
            }
            if (i < t.length) buf += t[i];
        }
        return salida;
    }

    /** Iniciales para el retrato: nombre + apellido. */
    function iniciales(emp) {
        const n = (emp.nombre || '').trim();
        const a = (emp.apaterno || '').trim();
        return ((n[0] || '') + (a[0] || '')).toUpperCase();
    }

    /* ── Carga ───────────────────────────────────────────────────────────── */

    // Solo puede haber una búsqueda en vuelo: si el usuario sigue tecleando se
    // cancela la anterior, para que una respuesta lenta no pise a los
    // resultados de una consulta más reciente.
    let control = null;
    let temporizador = null;

    buscador.addEventListener('input', () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscar, 250);
    });

    document.getElementById('volver').addEventListener('click', () => {
        // Volver conserva el término tecleado: se está recorriendo una lista.
        seleccionado = null;
        pintar();
    });

    async function buscar() {
        if (control) control.abort();
        control = new AbortController();

        try {
            empleados = await pedir('ajax/datos.php', { accion: 'buscar', search: buscador.value },
                { signal: control.signal, etiqueta: 'Cargando empleados' });
        } catch (e) {
            if (e.name === 'AbortError') return; // la reemplazó una búsqueda posterior
            empleados = { rows: [], total: 0, error: true };
        }

        pintar();
    }

    async function cargarPagos(rfc) {
        pagos = null;
        pintar();

        try {
            pagos = await pedir('ajax/datos.php', { accion: 'pagos', rfc }, { etiqueta: 'Cargando timbres' });
        } catch (e) {
            pagos = { rows: [] };
        }

        pintar();
    }

    document.addEventListener('DOMContentLoaded', buscar);

    /* ── Render ──────────────────────────────────────────────────────────── */

    function pintar() {
        vistaLista.hidden = Boolean(seleccionado);
        vistaDetalle.hidden = !seleccionado;

        if (seleccionado) pintarDetalle(); else pintarLista();
    }

    function pintarLista() {
        const datos = empleados || {};
        const filas = datos.rows || [];
        const terms = terminos(buscador.value);

        // El servidor manda cuántos coincidieron en total y cuántos cupieron.
        const contador = document.getElementById('contador');
        if (datos.error) {
            contador.textContent = 'No se pudo consultar el índice de empleados.';
        } else if (!filas.length) {
            contador.textContent = buscador.value.trim() ? 'Sin coincidencias' : '';
        } else if (datos.total > filas.length) {
            contador.textContent = `Mostrando ${fmt(filas.length)} de ${fmt(datos.total)} coincidencias — afina la búsqueda para ver el resto`;
        } else {
            contador.textContent = `${fmt(datos.total)} ${datos.total === 1 ? 'coincidencia' : 'coincidencias'}`;
        }

        const html = filas.map(e => {
            const nombre = resaltar(`${e.nombre} ${e.apaterno} ${e.amaterno}`.replace(/ +/g, ' ').trim(), terms);
            const plazas = (e.plazas || 1) > 1
                ? `<span class="emp-plazas" title="Este RFC tiene ${e.plazas} plazas registradas">${e.plazas} plazas</span>`
                : '';
            return `
            <div class="emp-row" data-rfc="${esc(e.rfc)}" style="padding:15px 24px;display:flex;align-items:center;gap:16px;border-bottom:1px solid var(--border-soft);transition:background .14s ease">
              <div style="width:38px;height:38px;border-radius:50%;background:var(--chip);color:var(--wine);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px;flex:0 0 auto">${esc(iniciales(e))}</div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:14px">${nombre}</div>
                <div style="font-size:11.5px;color:var(--ink-faint);margin-top:3px;letter-spacing:0.2px">CURP ${resaltar(e.curp, terms)} · C.P. ${esc(e.cp)}</div>
              </div>
              ${plazas}
              <div style="font-size:12.5px;letter-spacing:0.6px;color:var(--ink-muted);background:var(--chip);padding:6px 12px;border-radius:var(--r-chip)">${resaltar(e.rfc, terms)}</div>
              <span style="color:var(--ink-faint);font-size:15px">→</span>
            </div>`;
        }).join('');

        document.getElementById('lista').innerHTML = html || `<div class="emp-vacio">${
            datos.error ? 'Ocurrió un error al buscar.'
                        : (buscador.value.trim() ? 'Ningún empleado coincide con esa búsqueda.' : 'Escribe un nombre, RFC o CURP para buscar.')
        }</div>`;

        document.querySelectorAll('.emp-row').forEach(el => {
            el.addEventListener('click', () => {
                seleccionado = (empleados.rows || []).find(e => e.rfc === el.dataset.rfc) || null;
                if (seleccionado) cargarPagos(seleccionado.rfc);
            });
        });
    }

    function pintarDetalle() {
        document.getElementById('det-iniciales').textContent = iniciales(seleccionado);
        document.getElementById('det-nombre').textContent =
            `${seleccionado.nombre} ${seleccionado.apaterno} ${seleccionado.amaterno}`.replace(/ +/g, ' ').trim();
        document.getElementById('det-claves').textContent =
            `RFC ${seleccionado.rfc}${seleccionado.curp ? ' · CURP ' + seleccionado.curp : ''}`;

        const filas = (pagos && pagos.rows) || [];
        document.getElementById('det-timbres').textContent = fmt(filas.length);

        document.getElementById('det-tabla').innerHTML = filas.length
            ? filas.map((p, i) => {
                const estado = String(p.estado || '').toLowerCase().substring(0, 3);
                return `
                <div class="tabla__row" style="display:grid;grid-template-columns:${COLUMNAS};font-size:12.5px">
                  <div style="color:var(--ink-faint)">${filas.length - i}</div>
                  <div style="font-size:11.5px;letter-spacing:0.3px;color:var(--ink-muted)">—</div>
                  <div>—</div>
                  <div>${esc(p.codigo)}</div>
                  <div style="color:var(--ink-muted)">${esc(p.inicio)}</div>
                  <div style="color:var(--ink-muted)">${esc(p.fin)}</div>
                  <div style="font-weight:600">$${esc(p.percepciones)}</div>
                  <div>$${esc(p.deducciones)}</div>
                  <div><span class="chip-estado ${estado}"><span class="dot"></span>${esc(p.estado)}</span></div>
                </div>`;
            }).join('')
            : `<div class="emp-vacio">${pagos ? 'Este RFC no tiene timbres registrados.' : 'Cargando…'}</div>`;
    }
})();
