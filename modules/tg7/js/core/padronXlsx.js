/**
 * Lector del padrón de préstamos vigentes en .xlsx.
 *
 * ISSSTE lo entrega como `RAMO 022 PRESTAMOS VIGENTES {MES} {AAAA}.xlsx`: un
 * renglón por préstamo, con número de préstamo, RFC, CURP, plazo e importe. Es
 * lo que faltaba — las órdenes de descuento son solo las altas de cada
 * quincena, y este archivo trae todo lo que sigue descontándose.
 *
 * Dos diferencias con las órdenes que importan:
 *   - Los periodos vienen en AAAAQQ (`202616`), NO en QQAAAA como las órdenes.
 *     Se entregan ya en AAAAQQ en `desde`/`hasta` para que el cruce no los
 *     voltee.
 *   - Es un corte: el de septiembre de 2026 llega hasta la quincena 16. Las
 *     altas posteriores hay que cargarlas aparte, con sus órdenes.
 *
 * Un .xlsx es un ZIP como el .docx; se reusa `extraerDelZip`. Las columnas se
 * ubican por el texto del encabezado, no por posición, para que una columna
 * de más o en otro orden no corra los datos.
 */
(function (TG7) {
    'use strict';

    const RO = TG7.reporteOrdenes;

    /** Columnas que se usan, por su encabezado ya normalizado. */
    const COLUMNAS = {
        numeroPrestamo: 'numero de prestamo',
        numeroIssste: 'numero de issste',
        pagaduria: 'pagaduria',
        rfc: 'rfc',
        nombre: 'nombre',
        plazo: 'plazo',
        desde: 'quincena inicial',
        hasta: 'quincena final',
        importe: 'importe',
        curp: 'curp'
    };

    const OBLIGATORIAS = ['numeroPrestamo', 'rfc', 'desde', 'hasta', 'importe'];

    const ENTIDADES = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&apos;': "'" };

    function desescapar(s) {
        return s.replace(/&(amp|lt|gt|quot|apos);/g, e => ENTIDADES[e]);
    }

    /** Texto de un nodo con uno o varios <t> (los textos con formato vienen partidos en runs). */
    function textoDe(fragmento) {
        const re = /<t(?:\s[^>]*)?>([\s\S]*?)<\/t>/g;
        let salida = '';
        let m;

        while ((m = re.exec(fragmento)) !== null) {
            salida += m[1];
        }

        return desescapar(salida);
    }

    function normalizarEncabezado(s) {
        return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase().replace(/\s+/g, ' ').trim();
    }

    /** `AB12` → 27 (índice base 0 de la columna). */
    function indiceColumna(ref) {
        const letras = /^[A-Z]+/.exec(ref);
        let n = 0;

        for (const c of letras ? letras[0] : '') {
            n = n * 26 + (c.charCodeAt(0) - 64);
        }

        return n - 1;
    }

    /**
     * Un número guardado como número en Excel (no como texto) llega como
     * `202616` o `4661.29`, pero también puede llegar como `2.0260380323E10`.
     * Se devuelve como cadena sin notación científica.
     */
    function numeroComoTexto(v) {
        if (!/[eE]/.test(v)) {
            return v;
        }

        const n = Number(v);

        return Number.isFinite(n) ? String(Math.round(n * 100) / 100) : v;
    }

    /** Hoja → arreglo de renglones (arreglos de celdas como texto). */
    function filasDeHoja(xml, compartidas) {
        const filas = [];
        const reFila = /<row[\s>][\s\S]*?<\/row>/g;
        const reCelda = /<c\s([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g;
        let f;

        while ((f = reFila.exec(xml)) !== null) {
            const fila = [];
            let c;
            let siguiente = 0;

            reCelda.lastIndex = 0;

            while ((c = reCelda.exec(f[0])) !== null) {
                const attrs = c[1];
                const cuerpo = c[2] || '';
                const ref = /\br="([A-Z]+\d+)"/.exec(attrs);
                const tipo = /\bt="([^"]+)"/.exec(attrs);
                const col = ref ? indiceColumna(ref[1]) : siguiente;
                const v = /<v>([\s\S]*?)<\/v>/.exec(cuerpo);
                let valor = '';

                if (tipo && tipo[1] === 's') {
                    valor = v ? (compartidas[Number(v[1])] || '') : '';
                } else if (tipo && tipo[1] === 'inlineStr') {
                    valor = textoDe(cuerpo);
                } else if (v) {
                    valor = tipo && tipo[1] === 'str' ? desescapar(v[1]) : numeroComoTexto(v[1]);
                }

                fila[col] = String(valor).trim();
                siguiente = col + 1;
            }

            filas.push(fila);
        }

        return filas;
    }

    /** Ruta de la primera hoja, siguiendo workbook.xml → sus relaciones. */
    async function rutaPrimeraHoja(buffer) {
        const libro = await RO.extraerDelZip(buffer, 'xl/workbook.xml');
        const hoja = /<sheet\s[^>]*r:id="([^"]+)"/.exec(libro);

        if (!hoja) {
            throw new Error('El libro de Excel no trae ninguna hoja.');
        }

        const rels = await RO.extraerDelZip(buffer, 'xl/_rels/workbook.xml.rels');
        const re = /<Relationship\s[^>]*>/g;
        let m;

        while ((m = re.exec(rels)) !== null) {
            if (m[0].indexOf('Id="' + hoja[1] + '"') !== -1) {
                const destino = /Target="([^"]+)"/.exec(m[0])[1];

                return destino.charAt(0) === '/' ? destino.slice(1) : 'xl/' + destino;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * Lee el padrón y devuelve los préstamos con la misma forma que el reporte
     * .docx (`{ ordenes, avisos }`), más `desde`/`hasta` ya en AAAAQQ.
     */
    async function leerPadronXlsx(buffer, nombreArchivo) {
        let compartidas = [];

        try {
            const sst = await RO.extraerDelZip(buffer, 'xl/sharedStrings.xml');
            compartidas = (sst.match(/<si>[\s\S]*?<\/si>/g) || []).map(textoDe);
        } catch (e) {
            // Un libro sin textos compartidos es válido: todo viene en línea.
        }

        const filas = filasDeHoja(await RO.extraerDelZip(buffer, await rutaPrimeraHoja(buffer)), compartidas);

        // El encabezado es el primer renglón que trae las columnas obligatorias;
        // así no importa si arriba hay un título o renglones en blanco.
        let columnas = null;
        let inicio = 0;

        for (let i = 0; i < Math.min(filas.length, 20) && !columnas; i++) {
            const nombres = (filas[i] || []).map(x => normalizarEncabezado(x || ''));
            const mapa = {};

            Object.keys(COLUMNAS).forEach(k => {
                const j = nombres.indexOf(COLUMNAS[k]);

                if (j !== -1) {
                    mapa[k] = j;
                }
            });

            if (OBLIGATORIAS.every(k => mapa[k] !== undefined)) {
                columnas = mapa;
                inicio = i + 1;
            }
        }

        if (!columnas) {
            throw new Error('No se encontraron las columnas del padrón (Número de préstamo, RFC, '
                + 'Quincena inicial, Quincena final, Importe). ¿Es el padrón de préstamos vigentes de ISSSTE?');
        }

        const ordenes = [];
        const avisos = [];
        const celda = (fila, k) => (columnas[k] === undefined ? '' : (fila[columnas[k]] || ''));

        for (let i = inicio; i < filas.length; i++) {
            const fila = filas[i] || [];
            const linea = i + 1;

            if (fila.every(x => !x)) {
                continue;
            }

            const rfc = celda(fila, 'rfc').toUpperCase();
            const prestamo = celda(fila, 'numeroPrestamo').replace(/\D/g, '');
            const importe = Number(celda(fila, 'importe').replace(/[\s$,]/g, ''));
            const desde = celda(fila, 'desde').replace(/\D/g, '');
            const hasta = celda(fila, 'hasta').replace(/\D/g, '');

            if (!rfc || prestamo === '' || !Number.isFinite(importe) || !/^\d{6}$/.test(desde) || !/^\d{6}$/.test(hasta)) {
                avisos.push({
                    origen: nombreArchivo,
                    linea,
                    rfc,
                    mensaje: 'Renglón del padrón con RFC, préstamo, importe o quincenas ilegibles: se omite'
                });

                continue;
            }

            ordenes.push({
                numeroIssste: celda(fila, 'numeroIssste').replace(/\D/g, ''),
                rfc,
                curp: celda(fila, 'curp').toUpperCase(),
                numeroPrestamo: prestamo,
                importe,
                desde,
                hasta,
                linea
            });
        }

        if (ordenes.length === 0) {
            throw new Error('El padrón no trae ningún préstamo legible.');
        }

        return { ordenes, avisos, tipo: 'padron' };
    }

    TG7.padronXlsx = { leerPadronXlsx, filasDeHoja, indiceColumna };
})(window.TG7 = window.TG7 || {});
