/**
 * Módulo TG-7: carga los dos archivos, cruza, valida y entrega el archivo de
 * SERICA más la bitácora.
 *
 * Todo ocurre en este navegador. Los archivos traen RFC, CURP, NSS, sueldo y
 * datos bancarios de miles de trabajadores, así que no se suben a ningún lado:
 * no hay endpoint, no hay fetch, no hay nada que guardar. Por eso el núcleo
 * está en js/core/ y no en PHP.
 */
(function () {
    'use strict';

    const F = window.TG7.formato;
    const L = window.TG7.layouts;
    const C = window.TG7.cruce;
    const V = window.TG7.validaciones;

    const velo = document.getElementById('loader');
    const etiquetaVelo = document.getElementById('loader-label');
    const cajaError = document.getElementById('tg7-error');
    const resultado = document.getElementById('tg7-resultado');
    const btnProcesar = document.getElementById('tg7-procesar');

    /** Lo último procesado, para que los botones de descarga no recalculen. */
    let ultimo = null;

    const estado = {
        nomina: [],
        ordenes: []
    };

    /* ── Carga de archivos ───────────────────────────────────────────────── */

    montarZona('nomina', 'tg7-archivos-nomina', 'tg7-lista-nomina', 'tg7-etiqueta-nomina');
    montarZona('ordenes', 'tg7-archivos-ordenes', 'tg7-lista-ordenes', 'tg7-etiqueta-ordenes');

    function montarZona(clave, idInput, idLista, idEtiqueta) {
        const input = document.getElementById(idInput);

        input.addEventListener('change', async function () {
            const archivos = Array.from(this.files || []);

            document.getElementById(idEtiqueta).textContent = archivos.length
                ? archivos.length + (archivos.length === 1 ? ' archivo' : ' archivos')
                : 'Selecciona los archivos';

            cargando(true, 'Leyendo archivos');

            try {
                estado[clave] = await leerArchivos(archivos);
                cajaError.hidden = true;
            } catch (e) {
                estado[clave] = [];
                mostrarError(e.message);
            } finally {
                cargando(false);
            }

            pintarLista(idLista, estado[clave], clave);
            actualizarBoton();
        });
    }

    /**
     * Los .txt llegan en ASCII puro y se decodifican como latin1 para conservar
     * los bytes tal cual: leerlos como UTF-8 correría todas las posiciones del
     * ancho fijo en cuanto apareciera un byte alto.
     *
     * Los .docx son los reportes «RAMO … OD …» de ISSSTE, que traen las mismas
     * órdenes en tabla. Se interpretan aparte y se entregan ya normalizados.
     */
    async function leerArchivos(archivos) {
        const decodificador = new TextDecoder('latin1');
        const salida = [];

        for (const f of archivos) {
            const buffer = await f.arrayBuffer();

            if (/\.docx$/i.test(f.name)) {
                const leido = await window.TG7.reporteOrdenes.leerReporteDocx(buffer, f.name)
                    .catch(e => {
                        throw new Error(f.name + ': ' + e.message);
                    });

                salida.push({
                    nombre: f.name,
                    bytes: f.size,
                    ordenes: leido.ordenes,
                    avisos: leido.avisos
                });

                continue;
            }

            salida.push({
                nombre: f.name,
                bytes: f.size,
                contenido: decodificador.decode(buffer)
            });
        }

        return salida;
    }

    function pintarLista(id, archivos, clave) {
        const lista = document.getElementById(id);

        if (!archivos.length) {
            lista.innerHTML = '';

            return;
        }

        lista.innerHTML = archivos.map(a => {
            const info = clave === 'nomina' ? L.interpretarNombreArchivo(a.nombre) : null;
            let detalle;

            if (clave !== 'nomina') {
                detalle = a.ordenes
                    ? 'reporte Word · ' + miles(a.ordenes.length)
                      + (a.ordenes.length === 1 ? ' orden' : ' órdenes')
                    : pesar(a.bytes);
            } else if (info) {
                detalle = 'quincena ' + info.quincena
                    + ' · pagaduría ' + (info.pagaduria || '?')
                    + ' · ' + (F.TIPOS_NOMINA[info.tipoNomina] || 'tipo ?');
            } else {
                detalle = 'nombre no reconocido: se leerá igual, pero conviene revisarlo';
            }

            return '<li class="tg7-archivo">'
                + '<span class="tg7-archivo__nombre">' + esc(a.nombre) + '</span>'
                + '<span class="tg7-archivo__detalle">' + esc(detalle) + '</span>'
                + '</li>';
        }).join('');
    }

    /**
     * Solo la nómina es indispensable: es la que dice quién tuvo retención.
     * Las órdenes son un complemento para resolver el número de préstamo, y
     * pueden ser de cualquier quincena.
     */
    function actualizarBoton() {
        btnProcesar.disabled = estado.nomina.length === 0;
    }

    /* ── Encabezado ──────────────────────────────────────────────────────── */

    function leerEncabezado() {
        const v = id => document.getElementById(id).value.trim();

        return {
            tipoNomina: v('tg7-tipo-nomina'),
            version: Number(v('tg7-version')),
            periodicidad: v('tg7-periodicidad'),
            periodo: v('tg7-periodo'),
            organismo: v('tg7-organismo'),
            entidad: v('tg7-entidad'),
            municipio: v('tg7-municipio'),
            ramoCredito: v('tg7-ramo')
        };
    }

    // El nombre del archivo se arma con el encabezado, así que se muestra en
    // vivo: es la forma más directa de ver que la clave de aportante quedó bien.
    document.getElementById('tg7-encabezado').addEventListener('input', pintarNombreArchivo);
    pintarNombreArchivo();

    function pintarNombreArchivo() {
        document.getElementById('tg7-nombre-archivo').textContent = F.nombreArchivo(leerEncabezado());
    }

    /* ── Proceso ─────────────────────────────────────────────────────────── */

    document.getElementById('tg7-form').addEventListener('submit', function (evento) {
        evento.preventDefault();
        procesar();
    });

    function procesar() {
        const encabezado = leerEncabezado();
        const erroresEncabezado = V.validarEncabezado(encabezado);

        if (erroresEncabezado.length) {
            mostrarError('Revisa el encabezado: '
                + erroresEncabezado.map(h => h.campo + ' — ' + h.mensaje).join(' · '));

            return;
        }

        cargando(true, 'Cruzando y validando');

        // Un respiro para que el velo alcance a pintarse: el cruce de 10 000
        // registros bloquea el hilo y si no, la pantalla se queda congelada
        // sin señal de que algo está pasando.
        setTimeout(function () {
            try {
                const cruce = C.cruzar(estado.nomina, estado.ordenes, encabezado);
                const validacion = V.validarLote(encabezado, cruce.registros, cruce.motivos);

                ultimo = { encabezado, cruce, validacion };
                pintar(ultimo);
                cajaError.hidden = true;
            } catch (e) {
                mostrarError('No se pudo procesar: ' + e.message);
            } finally {
                cargando(false);
            }
        }, 30);
    }

    function cargando(activo, etiqueta) {
        if (velo) {
            velo.hidden = !activo;
        }

        if (activo && etiqueta && etiquetaVelo) {
            etiquetaVelo.textContent = etiqueta;
        }

        btnProcesar.disabled = activo;

        if (activo) {
            cajaError.hidden = true;
            resultado.hidden = true;
        } else {
            actualizarBoton();
        }
    }

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
        resultado.hidden = true;
    }

    /* ── Resultado ───────────────────────────────────────────────────────── */

    function pintar(r) {
        const res = r.cruce.resumen;
        const val = r.validacion;
        const emitible = val.validos.length > 0;

        // Estado general: el chip de arriba es lo primero que se lee, así que
        // dice la verdad sin rodeos — cuántos van y cuántos se quedan fuera.
        const chip = document.getElementById('tg7-estado');
        chip.className = 'chip-estado ' + (emitible ? (val.rechazados.length ? 'act' : 'imp') : 'can');
        chip.innerHTML = '<span class="dot"></span>' + (
            !emitible ? 'SIN REGISTROS EMITIBLES'
                : val.rechazados.length ? 'PARCIAL' : 'COMPLETO'
        );

        document.getElementById('tg7-resumen-archivo').textContent =
            F.nombreArchivo(r.encabezado) + ' · ' + miles(val.validos.length)
            + (val.validos.length === 1 ? ' registro' : ' registros');

        pintarTiles([
            { etiqueta: 'Registros de nómina', valor: miles(res.registrosNomina) },
            { etiqueta: 'Con retención', valor: miles(res.conRetencion) },
            { etiqueta: 'Órdenes vigentes', valor: miles(res.ordenesVigentes) + ' / ' + miles(res.ordenesCargadas) },
            { etiqueta: 'Con núm. de préstamo', valor: miles(res.trabajadoresConPrestamo) },
            { etiqueta: 'Se emiten', valor: miles(val.validos.length), acento: true },
            { etiqueta: 'Rechazados', valor: miles(val.rechazados.length) }
        ]);

        pintarSenales(res, val);
        pintarSinPrestamo(r.cruce.sinPrestamo, res);
        pintarHallazgos(val.hallazgos);
        pintarRechazados(val.rechazados);
        pintarIncidencias(r.cruce.incidencias);

        document.getElementById('tg7-descargar').disabled = !emitible;
        resultado.hidden = false;
    }

    function pintarTiles(tiles) {
        document.getElementById('tg7-tiles').innerHTML = tiles.map(t =>
            '<div class="tg7-stat">'
            + '<div class="eyebrow" style="letter-spacing:1.1px">' + esc(t.etiqueta) + '</div>'
            + '<div class="valor' + (t.acento ? ' acento' : '') + '">' + esc(String(t.valor)) + '</div>'
            + '</div>'
        ).join('');
    }

    /**
     * Señales de calidad del dato de origen. No son errores del archivo que se
     * entrega: son cosas que hay que reclamarle a quien genera la nómina, y si
     * no se listan aparte se pierden entre los miles de avisos de la bitácora.
     */
    function pintarSenales(res, val) {
        const senales = [
            {
                n: res.eniesPerdidas,
                texto: 'nombres con una «Ñ» perdida en el origen (llega como espacio). El nombre saldrá incompleto mientras no se corrija la nómina.'
            },
            {
                n: res.nombresARevisar,
                texto: 'nombres que no se pudieron separar con aval de la CURP. Revísalos en la bitácora antes de entregar.'
            },
            {
                n: res.sinTipoNombramiento,
                texto: 'registros cuyo tipo de nombramiento del SIPE no tiene equivalente en el catálogo del TG-7 (claves 3 y 5). Se rechazan en vez de adivinar la clave.'
            },
            {
                n: res.importeDiscrepante,
                texto: 'trabajadores donde la suma de sus órdenes vigentes no cuadra con el importe retenido en la nómina. Puede faltar un alta o sobrar un préstamo ya liquidado.'
            },
            {
                n: res.numeroIsssteDiscrepante,
                texto: 'registros donde el número de ISSSTE difiere entre la orden y la nómina. Se usa el de la orden; SERICA valida número + préstamo.'
            },
            {
                n: res.rfcRepetidoEnNomina,
                texto: 'RFC con retención en más de un archivo de nómina del lote. Se conservó el primero: si cargaste .ORD y .RET juntos, esto es esperado.'
            },
            {
                n: res.ordenesDuplicadas,
                texto: 'órdenes que venían repetidas entre los archivos cargados. Se contaron una sola vez.'
            },
            {
                n: val.hallazgos.filter(h => h.severidad === 'aviso' && h.campo === 'nss').length,
                texto: 'NSS cuyo dígito verificador no cuadra. Se emiten igual; si SERICA lo valida, podrían rebotar.'
            }
        ].filter(s => s.n > 0);

        const caja = document.getElementById('tg7-senales');
        const bloque = document.getElementById('tg7-bloque-senales');

        bloque.hidden = senales.length === 0;
        caja.innerHTML = senales.map(s =>
            '<li><strong>' + miles(s.n) + '</strong> ' + esc(s.texto) + '</li>'
        ).join('');
    }

    /**
     * Trabajadores con retención efectiva cuya orden de descuento no se cargó.
     *
     * Es el bloque más importante de la pantalla cuando el histórico de órdenes
     * está incompleto: esas líneas salen sin número de préstamo y SERICA las va
     * a rebotar. El CSV existe para reclamarle las altas a ISSSTE.
     */
    function pintarSinPrestamo(filas, res) {
        const bloque = document.getElementById('tg7-bloque-sinprestamo');

        bloque.hidden = filas.length === 0;

        if (!filas.length) {
            return;
        }

        const muestra = filas.slice(0, 200);
        const suma = filas.reduce((s, f) => s + f.importe, 0);

        document.getElementById('tg7-badge-sinprestamo').textContent =
            miles(filas.length) + ' de ' + miles(res.conRetencion) + ' · '
            + suma.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }) + ' retenidos';

        document.getElementById('tg7-tabla-sinprestamo').innerHTML = muestra.map(f =>
            '<div class="tabla__row tg7-fila-sinprestamo">'
            + '<div class="tg7-campo">' + esc(f.rfc) + '</div>'
            + '<div class="tg7-conteo">' + f.importe.toFixed(2) + '</div>'
            + '<div>' + esc(f.origen) + '</div>'
            + '<div>' + esc(String(f.linea)) + '</div>'
            + '</div>'
        ).join('');

        const pie = document.getElementById('tg7-pie-sinprestamo');
        const ocultos = filas.length - muestra.length;

        pie.hidden = ocultos <= 0;
        pie.textContent = ocultos > 0
            ? 'Se listan los primeros ' + miles(muestra.length) + '; los ' + miles(ocultos)
              + ' restantes están en el CSV.'
            : '';
    }

    /** Conteo de hallazgos por campo, que es como se lee una bitácora grande. */
    function pintarHallazgos(hallazgos) {
        const agrupado = new Map();

        hallazgos.forEach(h => {
            const clave = h.severidad + '|' + h.campo + '|' + h.mensaje;
            const previo = agrupado.get(clave);

            if (previo) {
                previo.n++;
            } else {
                agrupado.set(clave, { severidad: h.severidad, campo: h.campo, mensaje: h.mensaje, n: 1 });
            }
        });

        const filas = Array.from(agrupado.values()).sort((a, b) =>
            (a.severidad === b.severidad ? b.n - a.n : (a.severidad === 'error' ? -1 : 1))
        );

        document.getElementById('tg7-badge-hallazgos').textContent =
            miles(hallazgos.length) + (hallazgos.length === 1 ? ' hallazgo' : ' hallazgos');

        document.getElementById('tg7-tabla-hallazgos').innerHTML = filas.length
            ? filas.map(f =>
                '<div class="tabla__row tg7-fila-hallazgo">'
                + '<div><span class="chip-estado ' + (f.severidad === 'error' ? 'can' : 'act') + '">'
                + '<span class="dot"></span>' + (f.severidad === 'error' ? 'ERROR' : 'AVISO') + '</span></div>'
                + '<div class="tg7-campo">' + esc(f.campo) + '</div>'
                + '<div class="tg7-mensaje">' + esc(f.mensaje) + '</div>'
                + '<div class="tg7-conteo">' + miles(f.n) + '</div>'
                + '</div>'
            ).join('')
            : '<div class="emp-vacio">Sin hallazgos.</div>';
    }

    function pintarRechazados(rechazados) {
        const bloque = document.getElementById('tg7-bloque-rechazados');

        bloque.hidden = rechazados.length === 0;

        if (!rechazados.length) {
            return;
        }

        const muestra = rechazados.slice(0, 200);

        document.getElementById('tg7-badge-rechazados').textContent =
            miles(rechazados.length) + (rechazados.length === 1 ? ' registro' : ' registros');

        document.getElementById('tg7-tabla-rechazados').innerHTML = muestra.map(r =>
            '<div class="tabla__row tg7-fila-rechazo">'
            + '<div class="tg7-campo">' + esc(r.registro.rfc) + '</div>'
            + '<div>' + esc(r.registro.numeroPrestamo) + '</div>'
            + '<div class="tg7-mensaje">' + r.errores.map(e =>
                '<div>' + esc(e.campo) + ': ' + esc(e.mensaje) + '</div>').join('')
            + '</div>'
        + '</div>').join('');

        const pie = document.getElementById('tg7-pie-rechazados');
        const ocultos = rechazados.length - muestra.length;

        pie.hidden = ocultos <= 0;
        pie.textContent = ocultos > 0
            ? 'Se listan los primeros ' + miles(muestra.length) + '; los ' + miles(ocultos)
              + ' restantes están en la bitácora.'
            : '';
    }

    function pintarIncidencias(incidencias) {
        const bloque = document.getElementById('tg7-bloque-incidencias');

        bloque.hidden = incidencias.length === 0;

        if (!incidencias.length) {
            return;
        }

        const muestra = incidencias.slice(0, 200);

        document.getElementById('tg7-badge-incidencias').textContent =
            miles(incidencias.length) + (incidencias.length === 1 ? ' incidencia' : ' incidencias');

        document.getElementById('tg7-tabla-incidencias').innerHTML = muestra.map(i =>
            '<div class="tabla__row tg7-fila-incidencia">'
            + '<div class="tg7-campo">' + esc(i.origen) + '</div>'
            + '<div>' + esc(String(i.linea)) + '</div>'
            + '<div>' + esc(i.rfc) + '</div>'
            + '<div class="tg7-mensaje">' + esc(i.mensaje) + '</div>'
            + '</div>'
        ).join('');
    }

    /* ── Descargas ───────────────────────────────────────────────────────── */

    document.getElementById('tg7-descargar').addEventListener('click', function () {
        if (!ultimo || !ultimo.validacion.validos.length) {
            return;
        }

        descargar(
            F.nombreArchivo(ultimo.encabezado),
            F.escribirTG7(ultimo.encabezado, ultimo.validacion.validos),
            'text/plain'
        );
    });

    document.getElementById('tg7-bitacora').addEventListener('click', function () {
        if (!ultimo) {
            return;
        }

        descargar(
            'bitacora-' + F.nombreArchivo(ultimo.encabezado).replace(/\.txt$/, '') + '.csv',
            V.bitacoraCSV(ultimo.validacion.hallazgos),
            'text/csv'
        );
    });

    document.getElementById('tg7-descargar-sinprestamo').addEventListener('click', function () {
        if (!ultimo || !ultimo.cruce.sinPrestamo.length) {
            return;
        }

        descargar(
            'ordenes-faltantes-' + ultimo.encabezado.periodo + '.csv',
            C.sinPrestamoCSV(ultimo.cruce.sinPrestamo),
            'text/csv'
        );
    });

    /**
     * El archivo se arma en memoria y se entrega desde el propio navegador.
     *
     * Se escribe en latin1 (un byte por carácter) igual que el archivo de
     * referencia de ISSSTE, que es ASCII puro. El revoke va en un setTimeout:
     * revocar el blob en el mismo tick que el click corta la descarga en
     * algunos navegadores.
     */
    function descargar(nombre, contenido, tipo) {
        const bytes = new Uint8Array(contenido.length);

        for (let i = 0; i < contenido.length; i++) {
            const c = contenido.charCodeAt(i);
            bytes[i] = c < 256 ? c : 63; // 63 = '?', para no partir el ancho
        }

        const url = URL.createObjectURL(new Blob([bytes], { type: tipo + ';charset=iso-8859-1' }));
        const a = document.createElement('a');

        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    /* ── Utilidades ──────────────────────────────────────────────────────── */

    function miles(n) {
        return Number(n || 0).toLocaleString('es-MX');
    }

    function pesar(bytes) {
        if (!bytes) {
            return '0 B';
        }

        const unidades = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));

        return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + unidades[i];
    }

    function esc(texto) {
        const div = document.createElement('div');

        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML;
    }
})();
