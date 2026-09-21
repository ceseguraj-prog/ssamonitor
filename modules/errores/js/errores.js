/**
 * Errores de timbrado: lee el archivo que devuelve el PAC y lo desglosa.
 *
 * Todo pasa en el navegador — el archivo nunca se sube ni se guarda. Cada
 * línea del archivo trae el prefijo "RFC,CURP RFC - " y después la respuesta
 * del PAC en XML, así que aquí se separa el prefijo, se parsea el XML y del
 * mensaje se sacan el código, el folio y la serie.
 */
(function () {
    'use strict';

    const inputArchivo = document.getElementById('archivo');
    const zona = document.getElementById('zonaArchivo');
    const etiquetaArchivo = document.getElementById('etiquetaArchivo');
    const velo = document.getElementById('loader');
    const cajaError = document.getElementById('error');
    const resultado = document.getElementById('resultado');
    const vacio = document.getElementById('vacio');

    const buscador = document.getElementById('err-search');
    const selCodigo = document.getElementById('err-codigo');
    const selSerie = document.getElementById('err-serie');
    const tabla = document.getElementById('err-tabla');

    /** Lo último leído. No se persiste en ningún lado: vive aquí y ya. */
    let registros = [];
    let sinLeer = [];
    let nombreArchivo = '';
    const abiertas = new Set();

    /**
     * Comprobantes que ya se revisaron. Es una marca de trabajo, para no perder
     * el hilo mientras se van corrigiendo: vive en memoria y se va al recargar
     * o al abrir otro archivo, igual que el resto del módulo.
     */
    const marcadas = new Set();

    /* ── Entrada del archivo ─────────────────────────────────────────── */

    inputArchivo.addEventListener('change', function () {
        if (this.files.length) leer(this.files[0]);
    });

    // Arrastrar el archivo sobre la zona es lo natural aquí; el input sigue
    // funcionando igual para quien prefiera el diálogo.
    ['dragenter', 'dragover'].forEach(evento => {
        zona.addEventListener(evento, e => {
            e.preventDefault();
            zona.classList.add('arrastrando');
        });
    });
    ['dragleave', 'drop'].forEach(evento => {
        zona.addEventListener(evento, e => {
            e.preventDefault();
            zona.classList.remove('arrastrando');
        });
    });
    zona.addEventListener('drop', e => {
        const archivo = e.dataTransfer && e.dataTransfer.files[0];
        if (archivo) leer(archivo);
    });

    async function leer(archivo) {
        nombreArchivo = archivo.name + ' · ' + formatearBytes(archivo.size);
        etiquetaArchivo.textContent = nombreArchivo;
        zona.classList.add('cargado');

        cargando(true);
        // Un cuadro para que el velo alcance a pintarse antes de bloquear el
        // hilo con el parseo.
        await new Promise(requestAnimationFrame);

        try {
            const texto = decodificar(await archivo.arrayBuffer());
            const leido = analizar(texto);

            registros = leido.registros;
            sinLeer = leido.sinLeer;
            abiertas.clear();
            marcadas.clear();

            if (!registros.length) {
                throw new Error(sinLeer.length
                    ? 'No se reconoció ninguna línea del archivo. ¿Es un archivo de errores del timbrado?'
                    : 'El archivo está vacío.');
            }

            pintarResumen();
            armarFiltros();
            pintarTabla();

            cajaError.hidden = true;
            resultado.hidden = false;
            vacio.hidden = true;
        } catch (e) {
            mostrarError(e.message);
        } finally {
            cargando(false);
        }
    }

    /**
     * El archivo declara UTF-8 en el XML pero viene en Windows-1252 (los
     * acentos llegan como bytes sueltos). Se intenta UTF-8 en estricto y, si
     * truena, se decodifica como 1252 — que es lo que manda el PAC.
     */
    function decodificar(buffer) {
        try {
            return new TextDecoder('utf-8', { fatal: true }).decode(buffer);
        } catch (e) {
            return new TextDecoder('windows-1252').decode(buffer);
        }
    }

    function cargando(activo) {
        if (velo) velo.hidden = !activo;
        if (activo) {
            cajaError.hidden = true;
            resultado.hidden = true;
            vacio.hidden = true;
        }
    }

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
        resultado.hidden = true;
        vacio.hidden = true;
    }

    /* ── Lectura del archivo ─────────────────────────────────────────── */

    const parser = new DOMParser();

    function analizar(texto) {
        const registros = [];
        const sinLeer = [];

        texto.split(/\r?\n/).forEach((linea, i) => {
            if (!linea.trim()) return;

            const corte = linea.indexOf('<?xml');
            const inicioXml = corte !== -1 ? corte : linea.indexOf('<');

            if (inicioXml === -1) {
                sinLeer.push({ linea: i + 1, motivo: 'La línea no trae XML.' });
                return;
            }

            const registro = leerPrefijo(linea.slice(0, inicioXml));
            const xml = linea.slice(inicioXml);
            const doc = parser.parseFromString(xml, 'text/xml');

            if (doc.getElementsByTagName('parsererror').length) {
                sinLeer.push({ linea: i + 1, motivo: 'El XML de la línea no se pudo leer.' });
                return;
            }

            // La respuesta trae dos niveles: el de la autenticación y el del
            // comprobante. El que interesa es el del comprobante.
            const raiz = doc.documentElement;
            const nodoResultado = doc.getElementsByTagName('CFDIResultadoCertificacion')[0];

            registro.autMensaje = hijoDirecto(raiz, 'mensaje');
            registro.autStatus = hijoDirecto(raiz, 'status');
            registro.status = nodoResultado ? hijoDirecto(nodoResultado, 'status') : '';
            registro.mensaje = nodoResultado ? hijoDirecto(nodoResultado, 'mensaje') : registro.autMensaje;
            registro.xml = xml;

            Object.assign(registro, desglosarMensaje(registro.mensaje));
            registros.push(registro);
        });

        return { registros, sinLeer };
    }

    /** Prefijo "RFC,CURP RFC - ": el primer token trae RFC y CURP separados por coma. */
    function leerPrefijo(prefijo) {
        const partes = prefijo.trim().replace(/-\s*$/, '').trim().split(/[\s,]+/).filter(Boolean);

        return {
            rfc: partes[0] || '',
            // El segundo token es la CURP; el tercero repite el RFC, se ignora.
            curp: partes[1] && partes[1] !== partes[0] ? partes[1] : ''
        };
    }

    /** Texto de un hijo directo, para no confundir <mensaje> de los dos niveles. */
    function hijoDirecto(nodo, etiqueta) {
        if (!nodo) return '';

        for (const hijo of nodo.children) {
            if (hijo.tagName === etiqueta) return (hijo.textContent || '').trim();
        }

        return '';
    }

    /**
     * Del mensaje del PAC salen el código entre corchetes, el folio, la serie
     * y la familia del error ("Error en complemento Nómina", etc.).
     */
    function desglosarMensaje(mensaje) {
        const texto = (mensaje || '').replace(/\s+/g, ' ').trim();
        const mCodigo = /\[\s*Error\s*#\s*([^\]\s]+)\s*\]/i.exec(texto);

        const familia = (mCodigo ? texto.slice(0, mCodigo.index) : texto)
            .replace(/[.\s]+$/, '').trim();
        const resto = mCodigo ? texto.slice(mCodigo.index + mCodigo[0].length).trim() : '';

        const mFolio = /Folio:\s*([^.\s]+)/i.exec(texto);
        const mSerie = /Serie:\s*([^.\s]+)/i.exec(texto);

        return {
            codigo: mCodigo ? mCodigo[1].toUpperCase() : 'SIN CÓDIGO',
            familia: familia || 'Sin descripción',
            folio: mFolio ? mFolio[1] : '',
            serie: mSerie ? mSerie[1] : '',
            // El motivo sin el "Folio: x. Serie: y.", que ya va en sus columnas.
            motivo: (resto || texto)
                .replace(/Folio:\s*[^.\s]+\.?/i, '')
                .replace(/Serie:\s*[^.\s]+\.?/i, '')
                .replace(/\s{2,}/g, ' ')
                .trim()
        };
    }

    /* ── Resumen ─────────────────────────────────────────────────────── */

    /* La serie categórica vive en los tokens del tema, así que se lee cada vez:
       al cambiar a oscuro los colores del anillo tienen que cambiar con él. */
    const paleta = () => [1, 2, 3, 4, 5, 6, 7, 8].map(i =>
        getComputedStyle(document.body).getPropertyValue('--dist-' + i).trim());

    // El anillo y la leyenda llevan el color en el atributo, no en una clase.
    document.addEventListener('tema-cambiado', () => {
        if (registros.length) {
            pintarResumen();
            pintarTabla();
        }
    });

    /** Conteo por código, de mayor a menor. */
    function porCodigo() {
        const colores = paleta();
        const conteo = new Map();
        registros.forEach(r => conteo.set(r.codigo, (conteo.get(r.codigo) || 0) + 1));

        return [...conteo.entries()]
            .sort((a, b) => b[1] - a[1])
            .map(([codigo, total], i) => ({
                codigo,
                total,
                pct: Math.round(total / registros.length * 1000) / 10,
                color: colores[i % colores.length]
            }));
    }

    function pintarResumen() {
        const codigos = porCodigo();
        const rfcs = new Set(registros.map(r => r.rfc).filter(Boolean));
        const series = new Set(registros.map(r => r.serie).filter(Boolean));

        document.getElementById('res-total').textContent = miles(registros.length);
        document.getElementById('res-archivo').textContent = nombreArchivo;
        document.getElementById('res-codigos-n').textContent = miles(codigos.length);

        document.getElementById('res-datos').innerHTML = [
            ['RFC afectados', miles(rfcs.size)],
            ['Series', miles(series.size)],
            ['Códigos', miles(codigos.length)]
        ].map(([etiqueta, valor]) =>
            '<div class="err-hero-dato"><div class="etiqueta">' + escapar(etiqueta) + '</div>'
            + '<div class="valor">' + escapar(valor) + '</div></div>').join('');

        document.getElementById('res-anillo').innerHTML = anillo(
            codigos.map(c => ({ pct: c.pct, color: c.color })), 41, 178, 11
        );

        document.getElementById('res-leyenda').innerHTML = codigos.map(c =>
            '<div class="err-leyenda-fila" data-codigo="' + escapar(c.codigo) + '" title="Filtrar por ' + escapar(c.codigo) + '">'
            + '<span class="punto" style="background:' + c.color + '"></span>'
            + '<span class="codigo">' + escapar(c.codigo) + '</span>'
            + '<span class="valor">' + miles(c.total) + '</span>'
            + '<span class="pct">' + c.pct + '%</span>'
            + '</div>').join('');

        // La leyenda también filtra: es el gesto que uno intenta al verla.
        document.querySelectorAll('.err-leyenda-fila').forEach(fila => {
            fila.addEventListener('click', () => {
                const codigo = fila.dataset.codigo;
                selCodigo.value = selCodigo.value === codigo ? 'todos' : codigo;
                pintarTabla();
            });
        });

        const aviso = document.getElementById('err-sin-leer');
        aviso.hidden = sinLeer.length === 0;
        aviso.textContent = sinLeer.length
            ? sinLeer.length + (sinLeer.length === 1 ? ' línea no se pudo leer' : ' líneas no se pudieron leer')
              + ' (' + sinLeer.slice(0, 6).map(s => s.linea).join(', ') + (sinLeer.length > 6 ? '…' : '') + ')'
            : '';
    }

    /**
     * Arcos SVG con separación entre segmentos, igual que los anillos del
     * resumen: <circle> con stroke-dasharray sobre la circunferencia.
     */
    function anillo(partes, r, tamano, ancho) {
        const C = 2 * Math.PI * r;
        let acc = 0;
        const arcos = partes.map(p => {
            const inicio = acc;
            acc += p.pct;
            if (p.pct <= 0.05) return '';
            const largo = Math.max(C * (p.pct - 0.9) / 100, C * 0.012);

            return '<circle cx="50" cy="50" r="' + r + '" fill="none" stroke="' + p.color + '"'
                + ' stroke-width="' + ancho + '" stroke-linecap="butt"'
                + ' stroke-dasharray="' + largo.toFixed(2) + ' ' + (C - largo).toFixed(2) + '"'
                + ' stroke-dashoffset="' + (-C * inicio / 100).toFixed(2) + '"/>';
        }).join('');

        return '<svg width="' + tamano + '" height="' + tamano + '" viewBox="0 0 100 100" style="transform:rotate(-90deg)">'
            + '<circle cx="50" cy="50" r="' + r + '" fill="none" stroke="var(--track)" stroke-width="' + ancho + '"/>'
            + arcos + '</svg>';
    }

    /* ── Filtros y tabla ─────────────────────────────────────────────── */

    function armarFiltros() {
        const codigos = [...new Set(registros.map(r => r.codigo))].sort();
        const series = [...new Set(registros.map(r => r.serie).filter(Boolean))].sort();

        selCodigo.innerHTML = '<option value="todos">Todos los códigos</option>'
            + codigos.map(c => '<option value="' + escapar(c) + '">' + escapar(c) + '</option>').join('');

        selSerie.innerHTML = '<option value="todos">Todas las series</option>'
            + series.map(s => '<option value="' + escapar(s) + '">Serie ' + escapar(s) + '</option>').join('');

        buscador.value = '';
    }

    function filtrados() {
        const termino = buscador.value.trim().toUpperCase();
        const codigo = selCodigo.value;
        const serie = selSerie.value;

        return registros.filter(r => {
            if (codigo !== 'todos' && r.codigo !== codigo) return false;
            if (serie !== 'todos' && r.serie !== serie) return false;
            if (!termino) return true;

            return (r.rfc + ' ' + r.curp + ' ' + r.folio + ' ' + r.motivo).toUpperCase().indexOf(termino) !== -1;
        });
    }

    function pintarTabla() {
        const filas = filtrados();

        document.getElementById('err-count').textContent = filas.length === registros.length
            ? miles(filas.length) + (filas.length === 1 ? ' comprobante' : ' comprobantes')
            : miles(filas.length) + ' de ' + miles(registros.length);

        document.querySelectorAll('.err-leyenda-fila').forEach(f => {
            f.classList.toggle('activa', f.dataset.codigo === selCodigo.value);
        });

        // Al repintar se pierde el scroll, y con la lista larga eso tira el
        // sitio donde uno iba trabajando.
        const scroll = tabla.scrollTop;

        tabla.innerHTML = filas.length
            ? filas.map(fila).join('')
            : '<div class="emp-vacio">Ningún comprobante coincide con estos filtros.</div>';

        tabla.scrollTop = scroll;
        pintarMarcadas();

        tabla.querySelectorAll('[data-clave]').forEach(el => {
            el.addEventListener('click', () => {
                const clave = el.dataset.clave;
                if (abiertas.has(clave)) abiertas.delete(clave); else abiertas.add(clave);
                pintarTabla();
            });
        });

        // La casilla no repinta la tabla: solo cambia su fila. Repintar aquí
        // sería pelear con el scroll en cada palomita.
        tabla.querySelectorAll('[data-marca]').forEach(caja => {
            caja.addEventListener('click', e => e.stopPropagation());
            caja.addEventListener('change', () => {
                const clave = caja.dataset.marca;
                if (caja.checked) marcadas.add(clave); else marcadas.delete(clave);

                caja.closest('.err-fila').classList.toggle('err-fila-marcada', caja.checked);
                pintarMarcadas();
            });
        });

        // Los botones de copiar viven dentro de la fila, que a su vez abre y
        // cierra al hacer clic: sin frenar la propagación, copiar el RFC
        // cerraría el detalle en el mismo gesto.
        tabla.querySelectorAll('.err-copiar').forEach(boton => {
            boton.addEventListener('click', async evento => {
                evento.stopPropagation();

                try {
                    await navigator.clipboard.writeText(boton.dataset.copiar);
                    boton.classList.add('copiado');
                } catch (e) {
                    boton.classList.add('fallo');
                }

                setTimeout(() => boton.classList.remove('copiado', 'fallo'), 1400);
            });
        });
    }

    const ICONO_COPIAR = '<svg class="ic" width="13" height="13" viewBox="0 0 24 24" fill="none"'
        + ' stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
        + '<rect x="9" y="9" width="11.5" height="11.5" rx="2.4"/>'
        + '<path d="M5.5 15H4.6A1.1 1.1 0 0 1 3.5 13.9V4.6A1.1 1.1 0 0 1 4.6 3.5h9.3A1.1 1.1 0 0 1 15 4.6v.9"/></svg>';

    /** Valor que se copia al hacer clic. Se usa para lo que uno pega en otro lado. */
    function copiable(valor, clase) {
        if (!valor || valor === '—') return escapar(valor || '—');

        // El mensaje del PAC es de varias líneas: en el title se recorta para
        // no soltar un tooltip del tamaño de la pantalla.
        const corto = valor.length > 48 ? valor.slice(0, 48) + '…' : valor;

        return '<button type="button" class="err-copiar' + (clase ? ' ' + clase : '') + '"'
            + ' data-copiar="' + escapar(valor) + '" title="Copiar ' + escapar(corto) + '">'
            + '<span>' + escapar(valor) + '</span>' + ICONO_COPIAR + '</button>';
    }

    /** Contador de revisados y botón de limpiar; ambos sobran si no hay marcas. */
    function pintarMarcadas() {
        const aviso = document.getElementById('err-marcadas');
        const boton = document.getElementById('btnLimpiarMarcas');

        aviso.hidden = marcadas.size === 0;
        boton.hidden = marcadas.size === 0;
        aviso.textContent = marcadas.size + ' de ' + miles(registros.length) + ' revisados';
    }

    document.getElementById('btnLimpiarMarcas').addEventListener('click', () => {
        marcadas.clear();
        pintarTabla();
    });

    /** Clave estable de una fila, para recordar cuáles quedaron abiertas. */
    const claveDe = r => r.rfc + '|' + r.folio + '|' + r.codigo;

    function fila(r) {
        const clave = claveDe(r);
        const abierta = abiertas.has(clave);
        const marcada = marcadas.has(clave);

        // Sin código reconocido el chip va en gris: no es un error tipificado.
        const tono = r.codigo === 'SIN CÓDIGO' ? 'inv' : 'can';

        return '<div class="tabla__row err-fila' + (abierta ? ' err-fila-abierta' : '')
            + (marcada ? ' err-fila-marcada' : '') + '"'
            + ' data-clave="' + escapar(clave) + '" style="cursor:pointer">'
            + '<label class="err-check" title="Marcar como revisado">'
            + '<input type="checkbox" data-marca="' + escapar(clave) + '"' + (marcada ? ' checked' : '') + '>'
            + '</label>'
            + '<div><div class="err-rfc">' + copiable(r.rfc) + '</div>'
            + (r.curp ? '<div class="err-curp">' + copiable(r.curp) + '</div>' : '') + '</div>'
            + '<div><span class="chip-estado ' + tono + '"><span class="dot"></span>' + escapar(r.codigo) + '</span></div>'
            + '<div style="color:var(--ink-muted)">' + escapar(r.serie || '—') + '</div>'
            + '<div style="color:var(--ink-muted)">' + escapar(r.folio || '—') + '</div>'
            + '<div class="err-motivo">' + escapar(r.motivo) + '</div>'
            + (abierta ? detalle(r) : '')
            + '</div>';
    }

    function detalle(r) {
        // El tercer campo dice si el valor se copia: RFC, CURP, folio y el
        // mensaje son lo que uno acaba pegando en otro sistema o en un correo.
        const datos = [
            ['Tipo de error', escapar(r.familia), true],
            ['Mensaje completo del PAC', copiable(r.mensaje, 'err-copiar--texto'), true],
            ['RFC', copiable(r.rfc), false],
            ['CURP', copiable(r.curp), false],
            ['Serie', escapar(r.serie || '—'), false],
            ['Folio', copiable(r.folio), false],
            ['Estatus del comprobante', escapar(r.status || '—'), false],
            ['Estatus de la autenticación', escapar(r.autStatus ? r.autStatus + ' · ' + r.autMensaje : '—'), true]
        ];

        return '<div class="err-detalle">'
            + datos.map(([etiqueta, valor, ancho]) =>
                '<div' + (ancho ? ' class="ancho"' : '') + '>'
                + '<div class="etiqueta">' + escapar(etiqueta) + '</div>'
                + '<div class="valor">' + valor + '</div></div>').join('')
            + '</div>';
    }

    [buscador, selCodigo, selSerie].forEach(el => {
        el.addEventListener('input', pintarTabla);
        el.addEventListener('change', pintarTabla);
    });

    /* ── Acciones ────────────────────────────────────────────────────── */

    document.getElementById('btnCopiarRfc').addEventListener('click', async function () {
        const rfcs = [...new Set(filtrados().map(r => r.rfc).filter(Boolean))];
        const antes = this.textContent;

        try {
            await navigator.clipboard.writeText(rfcs.join('\n'));
            this.textContent = rfcs.length + ' RFC copiados';
        } catch (e) {
            this.textContent = 'No se pudo copiar';
        }
        setTimeout(() => { this.textContent = antes; }, 1600);
    });

    // La descarga se arma en el navegador con lo que ya está en pantalla: el
    // archivo nunca pasó por el servidor y esto tampoco.
    document.getElementById('btnCsv').addEventListener('click', function () {
        const filas = filtrados();
        const columnas = ['RFC', 'CURP', 'Codigo', 'Serie', 'Folio', 'Tipo', 'Motivo', 'Estatus'];

        const csv = [columnas.join(',')].concat(filas.map(r => [
            r.rfc, r.curp, r.codigo, r.serie, r.folio, r.familia, r.motivo, r.status
        ].map(celda => '"' + String(celda || '').replace(/"/g, '""') + '"').join(','))).join('\r\n');

        // El BOM es para que Excel abra los acentos bien.
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const enlace = document.createElement('a');

        enlace.href = url;
        enlace.download = (nombreArchivo.split(' · ')[0] || 'errores').replace(/\.[^.]+$/, '') + '.csv';
        enlace.click();
        URL.revokeObjectURL(url);
    });

    /* ── Utilidades ──────────────────────────────────────────────────── */

    function miles(n) {
        return Number(n || 0).toLocaleString('es-MX');
    }

    function formatearBytes(bytes) {
        if (!bytes) return '0 B';
        const unidades = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));

        return (bytes / Math.pow(1024, i)).toFixed(i ? 2 : 0) + ' ' + unidades[i];
    }

    function escapar(texto) {
        const div = document.createElement('div');
        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML.replace(/"/g, '&quot;');
    }
})();
