/**
 * Reporte QNA: pide el reporte de la quincena, pinta el resumen y entrega el
 * Excel. Las piezas compartidas (esc, pedir, el velo de carga) viven en js/comun.js.
 *
 * El .xlsx llega dentro de la respuesta, en base64 (ver ajax/generar.php): lo
 * que se ve en pantalla y lo que se descarga salen de la misma consulta.
 */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);

    /* Con centavos: es un reporte de importes y se cuadra contra otros. */
    const pesos = n => '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const entero = n => Number(n).toLocaleString('es-MX');

    /* El libro de la última corrida, para el botón de descarga. */
    let libro = null;

    $('filtros').addEventListener('submit', e => {
        e.preventDefault();
        generar();
    });

    $('descargar').addEventListener('click', descargar);

    /* Cambiar el periodo invalida lo que está en pantalla: dejarlo a la vista
       con otra quincena seleccionada invita a descargar el que no es. */
    ['anio', 'quincena', 'corregido'].forEach(id => $(id).addEventListener('change', () => {
        $('resultado').hidden = true;
        libro = null;
    }));

    async function generar() {
        $('aviso').hidden = true;
        $('resultado').hidden = true;
        libro = null;

        let datos;

        try {
            datos = await pedir('ajax/generar.php', {
                anio: $('anio').value,
                quincena: $('quincena').value,
                modo: $('corregido').checked ? $('corregido').value : ''
            }, { etiqueta: 'Leyendo la quincena en las seis tablas' });
        } catch (e) {
            datos = null;
        }

        if (!datos || datos.error) {
            return avisar((datos && datos.error) || 'No se pudo generar el reporte.');
        }

        libro = { nombre: datos.archivo, base64: datos.xlsx };
        pintar(datos);
    }

    function pintar(d) {
        const tiles = [['Renglones', entero(d.renglones)]]
            .concat(d.importes.map(c => [c, pesos(d.totales[c])]));

        $('metricas').innerHTML = tiles.map(([etiqueta, valor], i) => `
            <div class="card qna-tile rise-in" style="--i:${i}">
                <div class="eyebrow">${esc(etiqueta)}</div>
                <div class="figure">${esc(valor)}</div>
            </div>`).join('');

        $('archivo').textContent = d.archivo;
        $('pie').textContent = `Quincena ${d.quincena} de ${d.anio} · ${entero(d.renglones)} renglones · `
            + (d.modo === 'corregido' ? 'corregido' : 'igual que con los .txt') + ` · ${(d.ms / 1000).toFixed(1)} s`;

        $('avisos').innerHTML = avisos(d);

        $('cabeza').innerHTML = '<div>Tabla</div><div class="qna-num">Renglones</div>'
            + d.importes.map(c => `<div class="qna-num">${esc(c)}</div>`).join('');

        $('por-tabla').innerHTML = d.porTabla.map(t => `
            <div class="tabla__row qna-grid${t.filas ? '' : ' qna-vacia'}">
                <div class="qna-tabla-nom">${esc(t.tabla)}</div>
                <div class="qna-num">${entero(t.filas)}</div>
                ${d.importes.map(c => `<div class="qna-num">${pesos(t.importes[c])}</div>`).join('')}
            </div>`).join('');

        $('resultado').hidden = false;
    }

    /* Lo que no impide generar pero hay que mirar antes de mandar el archivo. */
    function avisos(d) {
        const lista = [];

        /* En el modo de los .txt el reporte repite y omite a propósito; se dice
           cuánto, porque los totales de arriba van inflados por lo mismo. */
        if (d.modo !== 'corregido' && (d.duplicados || d.omitidos.length)) {
            lista.push(`<strong>Igual que con los .txt:</strong> ${entero(d.pagos)} pagos salen en
                ${entero(d.renglones)} renglones. ${entero(d.duplicados)} son repetidos (por los CLUES
                duplicados en <code>indeteccr</code>) y los importes de arriba los cuentan.
                ${d.omitidos.length ? `Quedan fuera ${entero(d.omitidos.length)} personas cuyo CLUES no está en el catálogo:
                ${codigos(d.omitidos.map(o => `${o.rfc} · ${o.clues}`))}` : ''}
                Marca «Corregir» para tener cada pago una sola vez.`);
        } else if (d.sinDescripcionCr.length) {
            lista.push(`<strong>${entero(d.sinDescripcionCr.length)} CLUES sin descripción</strong> en
                <code>indeteccr</code>; sus renglones van con «DESCRIPCIÓN DEL CR» vacía. Los .txt los
                dejaban fuera del todo. ${codigos(d.sinDescripcionCr)}`);
        }

        if (d.sinDescripcionPuesto.length) {
            lista.push(`<strong>${entero(d.sinDescripcionPuesto.length)} códigos de puesto sin descripción</strong>
                en <code>cat_puesto</code>; van con «DESCRIPCION» vacía. ${codigos(d.sinDescripcionPuesto)}`);
        }

        const fuera = Object.keys(d.clavesFuera);
        if (fuera.length) {
            lista.push(`<strong>Claves que no entran en ninguna suma</strong> aunque son de las mismas
                familias (107, 130, 1A, 2AS). Si alguna debería sumar, hay que agregarla en
                <code>QnaRepository::IMPORTES</code>.
                ${codigos(fuera.map(c => `${c} (${entero(d.clavesFuera[c])})`))}`);
        }

        return lista.map(t => `<div class="qna-aviso qna-aviso--alerta">${t}</div>`).join('');
    }

    const codigos = lista => `<span class="qna-codigos">${lista.map(c => `<code>${esc(c)}</code>`).join(' ')}</span>`;

    function descargar() {
        if (!libro) return;

        const binario = atob(libro.base64);
        const bytes = new Uint8Array(binario.length);
        for (let i = 0; i < binario.length; i++) bytes[i] = binario.charCodeAt(i);

        const url = URL.createObjectURL(new Blob([bytes], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        }));

        const a = document.createElement('a');
        a.href = url;
        a.download = libro.nombre;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function avisar(texto) {
        $('aviso').textContent = texto;
        $('aviso').hidden = false;
    }
})();
