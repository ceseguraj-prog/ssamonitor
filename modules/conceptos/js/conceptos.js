/**
 * Conceptos: busca una clave de concepto en las seis tablas de nómina.
 * Las piezas compartidas (fmt, esc, pedir, el velo de carga) viven en js/comun.js.
 */
(function () {
    'use strict';

    /* Cuántas filas se pintan de golpe. El tope del servidor son 20,000
       coincidencias y meter todas al DOM de una vez congela la pestaña, así que se
       pinta por tandas con "Mostrar más". */
    const TANDA = 400;

    const form = document.getElementById('filtros');
    const aviso = document.getElementById('aviso');
    const resultado = document.getElementById('resultado');
    const filtrar = document.getElementById('filtrar');
    const botonMas = document.getElementById('mas');

    /* Última respuesta completa del servidor. Se guarda para que filtrar, paginar y
       exportar trabajen en memoria: ninguna de las tres vuelve a tocar la base. */
    let datos = null;
    let visibles = [];
    let pintadas = 0;

    /* A diferencia de Detalle, aquí NO se busca al teclear ni al cargar la página:
       cada búsqueda escanea un año de las seis tablas. Solo corre con el submit. */
    form.addEventListener('submit', e => {
        e.preventDefault();
        buscar();
    });

    filtrar.addEventListener('input', () => {
        aplicarFiltro();
    });

    botonMas.addEventListener('click', () => {
        pintarTanda();
    });

    document.getElementById('exportar').addEventListener('click', exportar);

    function tablasElegidas() {
        return Array.from(document.querySelectorAll('input[name="tabla"]:checked'))
            .map(c => c.value);
    }

    async function buscar() {
        const tablas = tablasElegidas();

        if (!tablas.length) {
            return mostrarAviso('Elige al menos una tabla en las opciones avanzadas.');
        }

        let respuesta;

        try {
            respuesta = await pedir('ajax/buscar.php', {
                codigos: document.getElementById('codigos').value,
                anio: document.getElementById('anio').value,
                quincena: document.getElementById('quincena').value,
                tablas: tablas.join(','),
                excluirUr: document.getElementById('excluir-ur').checked ? '1' : '0'
            }, { etiqueta: 'Buscando en las tablas de nómina' });
        } catch (e) {
            respuesta = null;
        }

        if (!respuesta || respuesta.error) {
            resultado.hidden = true;
            return mostrarAviso((respuesta && respuesta.error) || 'No se pudo completar la búsqueda.');
        }

        datos = respuesta;
        aviso.hidden = true;

        /* Los avisos se juntan en una sola caja: un código ignorado y un tope
           alcanzado pueden pasar en la misma búsqueda, y pisarse uno al otro
           dejaría al usuario sin saber por qué faltan filas. */
        const avisos = [];

        if (datos.ignorados && datos.ignorados.length) {
            avisos.push(
                `Se ignoró ${datos.ignorados.join(', ')}: un código lleva de 3 a 5 caracteres.`
            );
        }

        if (datos.truncado) {
            avisos.push(
                `La búsqueda topó en ${fmt(datos.tope)} coincidencias y hay más. `
                + 'Acota por quincena o por tabla para ver el resto.'
            );
        }

        if (!datos.filas.length) {
            resultado.hidden = true;
            avisos.push(
                `Ningún registro tiene ${datos.codigos.map(c => c + '…').join(' ni ')} en este ejercicio.`
            );
            return mostrarAviso(avisos.join(' '));
        }

        if (avisos.length) mostrarAviso(avisos.join(' '));

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
        const filas = datos.filas;

        document.getElementById('m-matches').textContent = fmt(filas.length);
        document.getElementById('m-claves').textContent = fmt(datos.porConcepto.length);
        document.getElementById('m-importe').textContent = dinero(datos.importe);

        // Registros != coincidencias: un RFC con el concepto en tres slots aparece
        // en tres filas pero es un solo registro de nómina.
        const registros = Object.values(datos.porTabla).reduce((t, x) => t + x.filas, 0);
        document.getElementById('m-filas').textContent = fmt(registros);
    }

    function pintarChips() {
        document.getElementById('por-concepto').innerHTML = datos.porConcepto.map(c =>
            `<span class="cnp-chip"><strong>${esc(c.concepto)}</strong>
             <span>${fmt(c.veces)}</span>
             <span class="cnp-chip__im">${dinero(c.importe)}</span></span>`
        ).join('');

        document.getElementById('por-tabla').innerHTML = Object.entries(datos.porTabla)
            .map(([tabla, x]) =>
                `<span class="cnp-chip ${x.matches ? '' : 'cnp-chip--vacio'}"><strong>${esc(tabla)}</strong>
                 <span>${fmt(x.matches)}</span></span>`
            ).join('');
    }

    /** Filtra en memoria sobre lo ya traído: no hay segunda consulta a la base. */
    function aplicarFiltro() {
        const q = filtrar.value.trim().toUpperCase();

        visibles = q === ''
            ? datos.filas
            : datos.filas.filter(f =>
                f.rfc.toUpperCase().includes(q)
                || f.nomb.toUpperCase().includes(q)
                || f.concepto.toUpperCase().includes(q));

        document.getElementById('tabla').innerHTML = '';
        pintadas = 0;

        const suma = visibles.reduce((t, f) => t + f.importe, 0);
        document.getElementById('contador').textContent =
            `${fmt(visibles.length)} coincidencias · ${dinero(suma)} · ${fmt(datos.ms)} ms`;

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
        <div class="tabla__row cnp-grid">
          <div class="cnp-tabla-nom">${esc(f.origen)}</div>
          <div>${esc(f.qna)}</div>
          <div>${esc(f.tipo)}</div>
          <div>${esc(f.ur)}</div>
          <div class="cnp-rfc">${esc(f.rfc)}</div>
          <div class="cnp-nomb" title="${esc(f.nomb)}">${esc(f.nomb)}</div>
          <div><span class="cnp-clave">${esc(f.concepto)}</span></div>
          <div class="cnp-suave">${f.slot}</div>
          <div class="cnp-suave">${esc(f.aq) || '—'}</div>
          <div class="cnp-num">${dinero(f.importe)}</div>
        </div>`;
    }

    function dinero(n) {
        return (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString('es-MX', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    /**
     * CSV de lo que está filtrado, armado en el navegador. Se exporta desde la
     * respuesta que ya está en memoria: descargar no vuelve a consultar la base.
     */
    function exportar() {
        if (!visibles.length) return;

        const cabeceras = ['tabla', 'anio', 'qna', 'tipo', 'ur', 'rfc', 'nombre', 'concepto', 'slot', 'aq', 'importe'];
        const campo = v => {
            const s = String(v ?? '');
            return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        };

        const lineas = [cabeceras.join(',')];
        visibles.forEach(f => lineas.push([
            f.origen, f.anio, f.qna, f.tipo, f.ur, f.rfc, f.nomb,
            f.concepto, f.slot, f.aq, f.importe.toFixed(2)
        ].map(campo).join(',')));

        // BOM para que Excel en Windows abra los acentos bien sin preguntar nada.
        const blob = new Blob(['﻿' + lineas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const enlace = document.createElement('a');

        enlace.href = URL.createObjectURL(blob);
        enlace.download = `conceptos_${datos.codigos.join('-')}_${document.getElementById('anio').value}.csv`;
        enlace.click();
        URL.revokeObjectURL(enlace.href);
    }

    /* ── Catálogo de conceptos ────────────────────────────────────────────
       Es un directorio de significados, no un reporte: no trae importes ni
       consulta la nómina. Solo dice qué quiere decir cada clave. */

    const modal = document.getElementById('modal-catalogo');
    const catBuscar = document.getElementById('cat-buscar');
    const catLista = document.getElementById('cat-lista');
    const catContador = document.getElementById('cat-contador');
    const catResumen = document.getElementById('cat-resumen');

    /* El catálogo entero son ~426 claves: se trae una vez y se filtra en el
       navegador. Volver al servidor por cada tecla no aportaría nada. */
    let catalogo = null;

    document.getElementById('abrir-catalogo').addEventListener('click', abrirCatalogo);

    modal.addEventListener('click', e => {
        if (e.target.hasAttribute('data-cerrar')) modal.hidden = true;
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.hidden) modal.hidden = true;
    });

    catBuscar.addEventListener('input', pintarCatalogo);

    /* Clic en una clave: se pasa a la caja de búsqueda de la pantalla y se
       cierra. Así se encadena "qué significa" con "quién lo tiene". */
    catLista.addEventListener('click', e => {
        const clave = e.target.closest('[data-clave]');
        if (!clave) return;

        document.getElementById('codigos').value = clave.dataset.clave;
        modal.hidden = true;
        document.getElementById('codigos').focus();
    });

    async function abrirCatalogo() {
        modal.hidden = false;
        catBuscar.focus();

        if (catalogo) return pintarCatalogo();

        catLista.innerHTML = '<div class="cnp-modal__cargando">Juntando las tablas de conceptos…</div>';

        let r;
        try {
            r = await (await fetch('ajax/catalogo.php')).json();
        } catch (e) {
            r = null;
        }

        if (!r || r.error) {
            catLista.innerHTML = `<div class="cnp-modal__cargando">${esc((r && r.error) || 'No se pudo leer el catálogo.')}</div>`;
            return;
        }

        catalogo = r.filas;
        catResumen.textContent =
            `${fmt(r.fuentes.claves)} claves, de ${Object.keys(r.fuentes.porFuente).length} tablas`;
        pintarCatalogo();
    }

    function pintarCatalogo() {
        if (!catalogo) return;

        const q = catBuscar.value.trim().toUpperCase();
        const porClave = /^[0-9A-Z]{1,5}$/.test(q);

        const visibles = q === '' ? catalogo : catalogo.filter(c => porClave
            ? c.clave.startsWith(q)
            : c.descripcion.toUpperCase().includes(q)
                || c.alternativas.some(a => a.descripcion.toUpperCase().includes(q)));

        catContador.textContent = visibles.length === 1
            ? '1 concepto' : `${fmt(visibles.length)} conceptos`;

        if (!visibles.length) {
            catLista.innerHTML = '<div class="cnp-modal__cargando">Ninguna clave coincide.</div>';
            return;
        }

        catLista.innerHTML = visibles.map(c => {
            const natura = c.naturaleza
                ? `<span class="cnp-nat cnp-nat--${c.naturaleza === 'Percepción' ? 'p' : 'd'}">${esc(c.naturaleza)}</span>`
                : '';

            /* Cuando dos catálogos describen distinto la misma clave se muestran
               los dos. Esconder la discrepancia sería peor que enseñarla: es
               justo lo que hay que revisar antes de fiarse de un nombre. */
            const otras = c.alternativas.map(a =>
                `<div class="cnp-cat__alt">también: ${esc(a.descripcion)}
                 <span class="cnp-suave">· ${esc(a.fuente)}</span></div>`).join('');

            const partida = c.partida && !/^0+$/.test(c.partida)
                ? `<span class="cnp-suave">partida ${esc(c.partida)}</span>` : '';

            return `<div class="cnp-cat">
                <button type="button" class="cnp-clave cnp-cat__clave" data-clave="${esc(c.clave)}"
                        title="Buscar ${esc(c.clave)} en la nómina">${esc(c.clave)}</button>
                <div class="cnp-cat__texto">
                    <div class="cnp-cat__desc">${esc(c.descripcion)}</div>
                    ${otras}
                    <div class="cnp-cat__meta">${natura}${partida}
                        <span class="cnp-suave">${esc(c.fuentes.join(' · '))}</span>
                    </div>
                </div>
            </div>`;
        }).join('');
    }
})();
