/**
 * Lector de los reportes de órdenes de descuento en .docx.
 *
 * ISSSTE entrega las órdenes de dos formas: el archivo de ancho fijo
 * `1{ramo}{pagaduría}_{folio}.txt` y el reporte `RAMO 022 OD {QQAAAA}.docx`.
 * Son el MISMO dato: comparados renglón por renglón los de la quincena 19,
 * coinciden en los 7 campos en 58 de 58.
 *
 * Importa porque el .docx suele ser lo único que se conserva de las quincenas
 * viejas, y sin el histórico no hay número de préstamo. Con los archivos que
 * había en disco, leer también los .docx sube la cobertura de 27 a 280
 * trabajadores.
 *
 * Un .docx es un ZIP con `word/document.xml` adentro. Se descomprime con
 * DecompressionStream, que ya trae el navegador: el proyecto no carga
 * librerías externas y este módulo no iba a ser la excepción.
 */
(function (TG7) {
    'use strict';

    /* ── ZIP ─────────────────────────────────────────────────────────────── */

    /**
     * Saca un archivo del ZIP por nombre.
     *
     * Se recorre el directorio central (no los encabezados locales) porque es
     * el único índice fiable: los encabezados locales pueden traer los tamaños
     * en cero cuando el ZIP se escribió en streaming.
     */
    async function extraerDelZip(buffer, ruta) {
        const vista = new DataView(buffer);
        const bytes = new Uint8Array(buffer);

        // Fin del directorio central (0x06054b50). Va al final, después de un
        // comentario de hasta 64 KB, así que se busca hacia atrás.
        let eocd = -1;
        const minimo = Math.max(0, bytes.length - 65557);

        for (let i = bytes.length - 22; i >= minimo; i--) {
            if (vista.getUint32(i, true) === 0x06054b50) {
                eocd = i;
                break;
            }
        }

        if (eocd < 0) {
            throw new Error('El archivo no es un .docx válido (no se encontró el índice del ZIP).');
        }

        const entradas = vista.getUint16(eocd + 10, true);
        let p = vista.getUint32(eocd + 16, true);

        for (let i = 0; i < entradas; i++) {
            if (vista.getUint32(p, true) !== 0x02014b50) {
                break;
            }

            const metodo = vista.getUint16(p + 10, true);
            const comprimido = vista.getUint32(p + 20, true);
            const largoNombre = vista.getUint16(p + 28, true);
            const largoExtra = vista.getUint16(p + 30, true);
            const largoComentario = vista.getUint16(p + 32, true);
            const offsetLocal = vista.getUint32(p + 42, true);
            const nombre = new TextDecoder('utf-8').decode(bytes.subarray(p + 46, p + 46 + largoNombre));

            if (nombre === ruta) {
                // El encabezado local sí manda para saber dónde empiezan los
                // datos: su relleno «extra» puede medir distinto al del índice.
                const nombreLocal = vista.getUint16(offsetLocal + 26, true);
                const extraLocal = vista.getUint16(offsetLocal + 28, true);
                const inicio = offsetLocal + 30 + nombreLocal + extraLocal;
                const crudo = bytes.subarray(inicio, inicio + comprimido);

                if (metodo === 0) {
                    return new TextDecoder('utf-8').decode(crudo);
                }

                if (metodo !== 8) {
                    throw new Error('El .docx usa un método de compresión no soportado (' + metodo + ').');
                }

                return await inflar(crudo);
            }

            p += 46 + largoNombre + largoExtra + largoComentario;
        }

        throw new Error('El archivo no parece un reporte de Word: no trae ' + ruta + '.');
    }

    /** DEFLATE crudo, con la API del navegador. */
    async function inflar(bytes) {
        if (typeof DecompressionStream !== 'function') {
            throw new Error('Este navegador no puede descomprimir .docx. Usa los archivos .txt de las órdenes.');
        }

        const flujo = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate-raw'));

        return await new Response(flujo).text();
    }

    /* ── Reporte ─────────────────────────────────────────────────────────── */

    // <w:t> y <w:t xml:space="preserve">, pero NO <w:tc>, <w:tcPr>, <w:tbl>…
    const RE_TEXTO = /<w:t(?:\s[^>]*)?>([\s\S]*?)<\/w:t>/g;
    const RE_FILA = /<w:tr[\s>][\s\S]*?<\/w:tr>/g;
    const RE_CELDA = /<w:tc[\s>][\s\S]*?<\/w:tc>/g;

    const ENTIDADES = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&apos;': "'" };

    function textoDe(fragmento) {
        let salida = '';
        let m;

        RE_TEXTO.lastIndex = 0;

        while ((m = RE_TEXTO.exec(fragmento)) !== null) {
            salida += m[1];
        }

        return salida.replace(/&(amp|lt|gt|quot|apos);/g, e => ENTIDADES[e]).trim();
    }

    /**
     * Columnas del reporte, en el orden en que las imprime ISSSTE:
     *
     *   0 Número del ISSSTE   1 RFC        2 Nombre del trabajador
     *   3 Clave de cobro      4 TPOD       5 Pzo.Qna.
     *   6 Periodo desde       7 Periodo hasta
     *   8 Concepto            9 Importe   10 Número de préstamo
     *
     * Un renglón cuenta como dato si trae 11 columnas y la segunda es un RFC
     * con estructura válida. Así se descartan solos los encabezados, los
     * subtotales por pagaduría y los totales del final, sin depender de en qué
     * renglón empiezan.
     */
    const RE_RFC = /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/;

    function ordenesDelXml(xml, nombreArchivo) {
        const ordenes = [];
        const avisos = [];
        let filas = 0;
        let m;

        RE_FILA.lastIndex = 0;

        while ((m = RE_FILA.exec(xml)) !== null) {
            const celdas = (m[0].match(RE_CELDA) || []).map(textoDe);

            filas++;

            if (celdas.length !== 11 || !RE_RFC.test(celdas[1])) {
                continue;
            }

            const importe = Number(String(celdas[9]).replace(/[\s$,]/g, ''));
            const prestamo = String(celdas[10]).replace(/\D/g, '');

            if (!Number.isFinite(importe) || prestamo === '') {
                avisos.push({
                    origen: nombreArchivo,
                    linea: filas,
                    rfc: celdas[1],
                    mensaje: 'Renglón del reporte con importe o número de préstamo ilegible: se omite'
                });

                continue;
            }

            ordenes.push({
                numeroIssste: String(celdas[0]).replace(/\D/g, ''),
                rfc: celdas[1],
                numeroPrestamo: prestamo,
                importe,
                // El reporte los imprime en QQAAAA, igual que el .txt.
                periodoDesde: String(celdas[6]).replace(/\D/g, ''),
                periodoHasta: String(celdas[7]).replace(/\D/g, ''),
                linea: filas
            });
        }

        return { ordenes, avisos };
    }

    /**
     * Lee un reporte .docx y devuelve sus órdenes ya normalizadas, con la misma
     * forma que produce el lector de ancho fijo.
     */
    async function leerReporteDocx(buffer, nombreArchivo) {
        const xml = await extraerDelZip(buffer, 'word/document.xml');
        const resultado = ordenesDelXml(xml, nombreArchivo);

        if (resultado.ordenes.length === 0) {
            throw new Error('No se encontró ninguna orden en el reporte. ¿Es el reporte «RAMO … OD …» de ISSSTE?');
        }

        return resultado;
    }

    TG7.reporteOrdenes = { leerReporteDocx, ordenesDelXml, extraerDelZip };
})(window.TG7 = window.TG7 || {});
