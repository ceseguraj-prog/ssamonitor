/**
 * Lectura y escritura de .xlsx, y empaquetado .zip, sin librerías.
 *
 * Un .xlsx es un ZIP con unos cuantos XML dentro. Leerlo ya se hacía en el
 * TG-7 con `DecompressionStream`; aquí además hay que escribirlo, y para eso
 * basta su gemelo `CompressionStream` más un CRC-32. Las dos APIs vienen en el
 * navegador (y en Node 18+, que es donde se prueba), así que el proyecto sigue
 * sin cargar nada de fuera.
 *
 * El escritor hace lo justo: varias hojas, texto, números y fórmulas con su
 * valor ya calculado. Sin estilos, fechas ni celdas combinadas — los archivos
 * de carga de nómina no los usan.
 */
(function (EV) {
    'use strict';

    /* ── ZIP: lectura ────────────────────────────────────────────────────── */

    /**
     * Índice del ZIP: nombre → { metodo, comprimido, inicio }.
     *
     * Se recorre el directorio central y no los encabezados locales, porque
     * estos pueden traer los tamaños en cero cuando el ZIP se escribió en
     * streaming.
     */
    function indiceZip(buffer) {
        const vista = new DataView(buffer);
        const bytes = new Uint8Array(buffer);
        const minimo = Math.max(0, bytes.length - 65557);
        let eocd = -1;

        // El fin del directorio central va al final, después de un comentario
        // de hasta 64 KB, así que se busca hacia atrás.
        for (let i = bytes.length - 22; i >= minimo; i--) {
            if (vista.getUint32(i, true) === 0x06054b50) {
                eocd = i;
                break;
            }
        }

        if (eocd < 0) {
            throw new Error('No es un archivo de Excel (.xlsx) válido.');
        }

        const entradas = vista.getUint16(eocd + 10, true);
        const indice = new Map();
        const utf8 = new TextDecoder('utf-8');
        let p = vista.getUint32(eocd + 16, true);

        for (let i = 0; i < entradas && vista.getUint32(p, true) === 0x02014b50; i++) {
            const largoNombre = vista.getUint16(p + 28, true);
            const largoExtra = vista.getUint16(p + 30, true);
            const largoComentario = vista.getUint16(p + 32, true);
            const offsetLocal = vista.getUint32(p + 42, true);
            const nombre = utf8.decode(bytes.subarray(p + 46, p + 46 + largoNombre));

            // El relleno «extra» del encabezado local puede medir distinto al
            // del índice; manda el local para saber dónde empiezan los datos.
            indice.set(nombre, {
                metodo: vista.getUint16(p + 10, true),
                comprimido: vista.getUint32(p + 20, true),
                inicio: offsetLocal + 30 + vista.getUint16(offsetLocal + 26, true)
                    + vista.getUint16(offsetLocal + 28, true)
            });

            p += 46 + largoNombre + largoExtra + largoComentario;
        }

        return { bytes, indice };
    }

    async function leerDelZip(zip, ruta) {
        const e = zip.indice.get(ruta);

        if (!e) {
            return null;
        }

        const crudo = zip.bytes.subarray(e.inicio, e.inicio + e.comprimido);

        if (e.metodo === 0) {
            return new TextDecoder('utf-8').decode(crudo);
        }

        if (e.metodo !== 8) {
            throw new Error('El Excel usa un método de compresión no soportado (' + e.metodo + ').');
        }

        if (typeof DecompressionStream !== 'function') {
            throw new Error('Este navegador no puede abrir archivos de Excel. Usa Chrome, Edge o Firefox recientes.');
        }

        const flujo = new Blob([crudo]).stream().pipeThrough(new DecompressionStream('deflate-raw'));

        return await new Response(flujo).text();
    }

    /* ── XLSX: lectura ───────────────────────────────────────────────────── */

    const ENTIDADES = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&apos;': "'" };

    function desescapar(s) {
        return s
            .replace(/&#x([0-9a-fA-F]+);/g, (m, h) => String.fromCodePoint(parseInt(h, 16)))
            .replace(/&#(\d+);/g, (m, d) => String.fromCodePoint(Number(d)))
            .replace(/&(amp|lt|gt|quot|apos);/g, e => ENTIDADES[e]);
    }

    /** Texto de un nodo con uno o varios <t> (el texto con formato viene partido en runs). */
    function textoDe(fragmento) {
        const re = /<t(?:\s[^>]*)?>([\s\S]*?)<\/t>/g;
        let salida = '';
        let m;

        while ((m = re.exec(fragmento)) !== null) {
            salida += m[1];
        }

        return desescapar(salida);
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

    /** 27 → `AB`. */
    function letraColumna(i) {
        let s = '';

        for (let n = i + 1; n > 0; n = Math.floor((n - 1) / 26)) {
            s = String.fromCharCode(65 + ((n - 1) % 26)) + s;
        }

        return s;
    }

    /**
     * Un número guardado como número llega como `20260916`, `1150.39` o, a
     * veces, como `1.214791580E9`. Se devuelve sin notación científica: los
     * centros de responsabilidad y las fechas AAAAMMDD se usan como texto.
     */
    function numeroComoTexto(v) {
        if (!/[eE]/.test(v)) {
            return v;
        }

        const n = Number(v);

        return Number.isFinite(n) ? String(Math.round(n * 100) / 100) : v;
    }

    /**
     * Hoja → arreglo de renglones; cada renglón, arreglo de celdas como texto.
     * Los renglones conservan su número real: un renglón vacío en medio deja
     * su hueco, para que «renglón N» signifique lo mismo que en Excel.
     */
    function filasDeHoja(xml, compartidas) {
        const filas = [];
        // La forma que se cierra sola va primero: si no, `<row …/>` casaría con
        // la primera alternativa y se tragaría el renglón siguiente.
        const reFila = /<row\s([^>]*)\/>|<row\s([^>]*)>([\s\S]*?)<\/row>/g;
        const reCelda = /<c\s([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g;
        let f;

        while ((f = reFila.exec(xml)) !== null) {
            const attrsFila = f[1] !== undefined ? f[1] : f[2];
            const cuerpoFila = f[3] || '';
            const numero = /\br="(\d+)"/.exec(attrsFila);
            const fila = [];
            let c;
            let siguiente = 0;

            if (numero) {
                filas.length = Math.max(filas.length, Number(numero[1]) - 1);
            }

            reCelda.lastIndex = 0;

            while (cuerpoFila && (c = reCelda.exec(cuerpoFila)) !== null) {
                const attrs = c[1];
                const cuerpo = c[2] || '';
                const ref = /\br="([A-Z]+)\d+"/.exec(attrs);
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

                fila[col] = valor;
                siguiente = col + 1;
            }

            filas.push(fila);
        }

        for (let i = 0; i < filas.length; i++) {
            filas[i] = filas[i] || [];
        }

        return filas;
    }

    /**
     * Lee todas las hojas del libro, en el orden de sus pestañas.
     *
     * @returns {Promise<{hojas: {nombre: string, filas: string[][]}[]}>}
     */
    async function leerLibro(buffer) {
        const zip = indiceZip(buffer);
        const libro = await leerDelZip(zip, 'xl/workbook.xml');

        if (!libro) {
            throw new Error('El archivo no es un libro de Excel: no trae xl/workbook.xml.');
        }

        const rels = (await leerDelZip(zip, 'xl/_rels/workbook.xml.rels')) || '';
        const destinos = new Map();
        const reRel = /<Relationship\s[^>]*>/g;
        let m;

        while ((m = reRel.exec(rels)) !== null) {
            const id = /\bId="([^"]+)"/.exec(m[0]);
            const destino = /\bTarget="([^"]+)"/.exec(m[0]);

            if (id && destino) {
                destinos.set(id[1], destino[1].charAt(0) === '/' ? destino[1].slice(1) : 'xl/' + destino[1]);
            }
        }

        const sst = await leerDelZip(zip, 'xl/sharedStrings.xml');
        const compartidas = sst ? (sst.match(/<si>[\s\S]*?<\/si>/g) || []).map(textoDe) : [];
        const hojas = [];
        const reHoja = /<sheet\s[^>]*>/g;

        while ((m = reHoja.exec(libro)) !== null) {
            const nombre = /\bname="([^"]*)"/.exec(m[0]);
            const rid = /\br:id="([^"]+)"/.exec(m[0]);
            const ruta = rid && destinos.get(rid[1]);
            const xml = ruta ? await leerDelZip(zip, ruta) : null;

            if (xml) {
                hojas.push({ nombre: desescapar(nombre ? nombre[1] : ''), filas: filasDeHoja(xml, compartidas) });
            }
        }

        if (!hojas.length) {
            throw new Error('El libro de Excel no trae ninguna hoja legible.');
        }

        return { hojas };
    }

    /* ── XLSX: escritura ─────────────────────────────────────────────────── */

    function escaparXml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            // Caracteres de control que XML 1.0 no admite ni escapados.
            .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '');
    }

    /**
     * Una celda:
     *   null / undefined / ''  → vacía
     *   number                 → número
     *   string                 → texto (se respeta tal cual: '0' sigue siendo texto)
     *   { f, v }               → fórmula `f` (sin el `=`) con su valor `v` ya calculado
     *
     * El valor calculado va para que el archivo se lea bien aunque quien lo
     * abra no recalcule (un lector de carga, una vista previa). Además el libro
     * pide recálculo al abrir, así que en Excel manda siempre la fórmula.
     */
    function celdaXml(ref, valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '';
        }

        if (typeof valor === 'number') {
            return Number.isFinite(valor) ? '<c r="' + ref + '"><v>' + valor + '</v></c>' : '';
        }

        if (typeof valor === 'object') {
            const v = typeof valor.v === 'number' && Number.isFinite(valor.v) ? '<v>' + valor.v + '</v>' : '';

            return '<c r="' + ref + '"><f>' + escaparXml(valor.f) + '</f>' + v + '</c>';
        }

        return '<c r="' + ref + '" t="inlineStr"><is><t xml:space="preserve">' + escaparXml(valor) + '</t></is></c>';
    }

    function hojaXml(filas) {
        const partes = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
            + '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'];

        filas.forEach((fila, i) => {
            const r = i + 1;
            let celdas = '';

            (fila || []).forEach((valor, j) => {
                celdas += celdaXml(letraColumna(j) + r, valor);
            });

            partes.push(celdas ? '<row r="' + r + '">' + celdas + '</row>' : '');
        });

        partes.push('</sheetData></worksheet>');

        return partes.join('');
    }

    /**
     * Arma un .xlsx.
     *
     * @param {{nombre: string, filas: Array<Array<*>>}[]} hojas
     * @returns {Promise<Uint8Array>}
     */
    async function escribirLibro(hojas) {
        const ns = 'http://schemas.openxmlformats.org/';
        const archivos = [
            ['[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                + '<Types xmlns="' + ns + 'package/2006/content-types">'
                + '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                + '<Default Extension="xml" ContentType="application/xml"/>'
                + '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                + '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                + hojas.map((h, i) => '<Override PartName="/xl/worksheets/sheet' + (i + 1) + '.xml" '
                    + 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>').join('')
                + '</Types>'],
            ['_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                + '<Relationships xmlns="' + ns + 'package/2006/relationships">'
                + '<Relationship Id="rId1" Type="' + ns + 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                + '</Relationships>'],
            ['xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                + '<workbook xmlns="' + ns + 'spreadsheetml/2006/main" xmlns:r="' + ns + 'officeDocument/2006/relationships"><sheets>'
                + hojas.map((h, i) => '<sheet name="' + escaparXml(h.nombre.slice(0, 31)) + '" sheetId="' + (i + 1) + '" r:id="rId' + (i + 1) + '"/>').join('')
                + '</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>'],
            ['xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                + '<Relationships xmlns="' + ns + 'package/2006/relationships">'
                + hojas.map((h, i) => '<Relationship Id="rId' + (i + 1) + '" Type="' + ns + 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' + (i + 1) + '.xml"/>').join('')
                + '<Relationship Id="rId' + (hojas.length + 1) + '" Type="' + ns + 'officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                + '</Relationships>'],
            ['xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\n'
                + '<styleSheet xmlns="' + ns + 'spreadsheetml/2006/main">'
                + '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
                + '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                + '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                + '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                + '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
                + '</styleSheet>']
        ];

        hojas.forEach((h, i) => archivos.push(['xl/worksheets/sheet' + (i + 1) + '.xml', hojaXml(h.filas)]));

        const utf8 = new TextEncoder();

        return await empaquetar(archivos.map(([nombre, xml]) => ({ nombre, datos: utf8.encode(xml) })), true);
    }

    /* ── ZIP: escritura ──────────────────────────────────────────────────── */

    const TABLA_CRC = (function () {
        const t = new Uint32Array(256);

        for (let n = 0; n < 256; n++) {
            let c = n;

            for (let k = 0; k < 8; k++) {
                c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
            }

            t[n] = c >>> 0;
        }

        return t;
    })();

    function crc32(bytes) {
        let c = 0xFFFFFFFF;

        for (let i = 0; i < bytes.length; i++) {
            c = TABLA_CRC[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
        }

        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    async function desinflar(bytes) {
        const flujo = new Blob([bytes]).stream().pipeThrough(new CompressionStream('deflate-raw'));

        return new Uint8Array(await new Response(flujo).arrayBuffer());
    }

    /**
     * ZIP con los archivos dados.
     *
     * `comprimir` en falso los guarda tal cual: es lo que conviene para meter
     * varios .xlsx en un paquete, porque ya vienen comprimidos y desinflarlos
     * otra vez solo gasta tiempo. Sin `CompressionStream` también se guardan
     * sin comprimir; el ZIP sigue siendo válido, solo más grande.
     *
     * @param {{nombre: string, datos: Uint8Array}[]} archivos
     * @returns {Promise<Uint8Array>}
     */
    async function empaquetar(archivos, comprimir) {
        const utf8 = new TextEncoder();
        const puedeComprimir = comprimir && typeof CompressionStream === 'function';
        const locales = [];
        const centrales = [];
        let offset = 0;

        // Fecha DOS fija (1-ene-2026): el contenido manda, no la hora en que
        // se armó, y así dos corridas iguales producen bytes iguales.
        const hora = 0;
        const fecha = ((2026 - 1980) << 9) | (1 << 5) | 1;

        for (const a of archivos) {
            const nombre = utf8.encode(a.nombre);
            const datos = puedeComprimir ? await desinflar(a.datos) : a.datos;
            const metodo = puedeComprimir ? 8 : 0;
            const crc = crc32(a.datos);
            const local = new DataView(new ArrayBuffer(30));

            local.setUint32(0, 0x04034b50, true);
            local.setUint16(4, 20, true);
            local.setUint16(6, 0x0800, true); // bit 11: nombre en UTF-8
            local.setUint16(8, metodo, true);
            local.setUint16(10, hora, true);
            local.setUint16(12, fecha, true);
            local.setUint32(14, crc, true);
            local.setUint32(18, datos.length, true);
            local.setUint32(22, a.datos.length, true);
            local.setUint16(26, nombre.length, true);
            local.setUint16(28, 0, true);

            const central = new DataView(new ArrayBuffer(46));

            central.setUint32(0, 0x02014b50, true);
            central.setUint16(4, 20, true);
            central.setUint16(6, 20, true);
            central.setUint16(8, 0x0800, true);
            central.setUint16(10, metodo, true);
            central.setUint16(12, hora, true);
            central.setUint16(14, fecha, true);
            central.setUint32(16, crc, true);
            central.setUint32(20, datos.length, true);
            central.setUint32(24, a.datos.length, true);
            central.setUint16(28, nombre.length, true);
            central.setUint32(42, offset, true);

            locales.push(new Uint8Array(local.buffer), nombre, datos);
            centrales.push(new Uint8Array(central.buffer), nombre);
            offset += 30 + nombre.length + datos.length;
        }

        const largoCentral = centrales.reduce((s, b) => s + b.length, 0);
        const fin = new DataView(new ArrayBuffer(22));

        fin.setUint32(0, 0x06054b50, true);
        fin.setUint16(8, archivos.length, true);
        fin.setUint16(10, archivos.length, true);
        fin.setUint32(12, largoCentral, true);
        fin.setUint32(16, offset, true);

        const partes = locales.concat(centrales, [new Uint8Array(fin.buffer)]);
        const salida = new Uint8Array(partes.reduce((s, b) => s + b.length, 0));
        let p = 0;

        partes.forEach(b => {
            salida.set(b, p);
            p += b.length;
        });

        return salida;
    }

    EV.xlsx = { leerLibro, escribirLibro, empaquetar, letraColumna, indiceColumna };
})(window.EV = window.EV || {});
