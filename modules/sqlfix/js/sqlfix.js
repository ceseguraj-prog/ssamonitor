/**
 * Corrector SQL: envía el archivo, pinta el reporte y ofrece la descarga.
 * Todo el módulo vive bajo modules/sqlfix/, así que las rutas son relativas
 * a esa carpeta.
 */
(function () {
    'use strict';

    const form = document.getElementById('formSqlFix');
    const inputArchivo = document.getElementById('archivo');
    const zona = document.getElementById('zonaArchivo');
    const etiquetaArchivo = document.getElementById('etiquetaArchivo');
    const btnProcesar = document.getElementById('btnProcesar');
    const velo = document.getElementById('loader');
    const cajaError = document.getElementById('error');
    const resultado = document.getElementById('resultado');

    // El input real está oculto: la zona punteada es la que se ve, así que hay
    // que reflejar ahí el archivo elegido.
    inputArchivo.addEventListener('change', function () {
        const archivo = this.files[0];
        etiquetaArchivo.textContent = archivo
            ? archivo.name + ' · ' + formatearBytes(archivo.size)
            : 'Selecciona un archivo .sql';
        zona.classList.toggle('cargado', Boolean(archivo));
    });

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        if (!inputArchivo.files.length) {
            return;
        }

        const datos = new FormData();
        datos.append('archivo', inputArchivo.files[0]);

        mostrarCargando(true);

        try {
            const respuesta = await fetch('ajax/procesar.php', { method: 'POST', body: datos });
            const cuerpo = await respuesta.json().catch(() => null);

            if (!respuesta.ok || !cuerpo) {
                throw new Error((cuerpo && cuerpo.error) || 'El servidor respondió ' + respuesta.status + '.');
            }

            pintarReporte(cuerpo);
        } catch (e) {
            mostrarError(e.message);
        } finally {
            mostrarCargando(false);
        }
    });

    function mostrarCargando(activo) {
        if (velo) velo.hidden = !activo;
        btnProcesar.disabled = activo;
        if (activo) {
            cajaError.hidden = true;
            resultado.hidden = true;
        }
    }

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
    }

    function pintarReporte(r) {
        pintarTarjetas(r);
        pintarCorrecciones(r);
        pintarAvisos(r);
        pintarNoAscii(r.noAscii || {});

        document.getElementById('btnDescargar').href = 'ajax/descargar.php?token=' + encodeURIComponent(r.token);
        document.getElementById('nombreSalida').textContent =
            r.nombreDescarga + ' · ' + formatearBytes(r.bytesSalida);

        resultado.hidden = false;
    }

    function pintarTarjetas(r) {
        const tarjetas = [
            { etiqueta: 'Líneas procesadas', valor: miles(r.lineas) },
            { etiqueta: 'Líneas corregidas', valor: miles(r.lineasCorregidas) },
            { etiqueta: 'Apóstrofes escapados', valor: miles(r.comillasEscapadas) },
            { etiqueta: 'Textos recodificados', valor: miles(r.lineasRecodificadas) },
            { etiqueta: 'Por revisar', valor: miles(r.totalAvisos) },
            { etiqueta: 'Formato', valor: r.salto + (r.bomOriginal ? ' · BOM quitado' : '') }
        ];

        document.getElementById('tarjetas').innerHTML = tarjetas.map(function (t) {
            return '<div class="sqlfix-stat">'
                + '<div class="eyebrow" style="letter-spacing:1.1px">' + escapar(t.etiqueta) + '</div>'
                + '<div class="valor">' + escapar(String(t.valor)) + '</div>'
                + '</div>';
        }).join('');
    }

    function pintarCorrecciones(r) {
        const cuerpo = document.getElementById('tablaCorrecciones');
        const correcciones = r.correcciones || [];

        document.getElementById('badgeCorrecciones').textContent =
            miles(r.lineasCorregidas) + ' línea' + (r.lineasCorregidas === 1 ? '' : 's');

        if (!correcciones.length) {
            cuerpo.innerHTML = '<div class="emp-vacio">No hizo falta corregir nada.</div>';
        } else {
            cuerpo.innerHTML = correcciones.map(function (c) {
                return '<div class="tabla__row" style="display:grid;grid-template-columns:5rem 10rem 1fr;gap:10px;align-items:start">'
                    + '<div style="color:var(--ink-faint)">' + c.linea + '</div>'
                    + '<div style="display:flex;gap:6px;flex-wrap:wrap">' + c.tipos.map(insignia).join('') + '</div>'
                    + '<div class="sqlfix-diff">'
                    + '<div class="sqlfix-antes">' + escapar(c.antes) + '</div>'
                    + '<div class="sqlfix-despues">' + escapar(c.despues) + '</div>'
                    + '</div></div>';
            }).join('');
        }

        // El backend recorta el detalle; el conteo total sí es exacto.
        const pie = document.getElementById('pieCorrecciones');
        const ocultas = r.lineasCorregidas - correcciones.length;
        pie.hidden = ocultas <= 0;
        pie.textContent = ocultas > 0
            ? 'Se listan las primeras ' + miles(correcciones.length) + '; hay ' + miles(ocultas)
              + ' más aplicadas en el archivo descargable.'
            : '';
    }

    function pintarAvisos(r) {
        const bloque = document.getElementById('bloqueAvisos');
        const avisos = r.avisos || [];

        bloque.hidden = avisos.length === 0;

        document.getElementById('tablaAvisos').innerHTML = avisos.map(function (a) {
            return '<div class="tabla__row" style="display:grid;grid-template-columns:5rem 12rem 1fr;gap:10px;align-items:start">'
                + '<div style="color:var(--ink-faint)">' + a.linea + '</div>'
                + '<div>' + escapar(a.motivo) + '</div>'
                + '<div class="sqlfix-diff">' + escapar(recortar(a.texto, 200)) + '</div>'
                + '</div>';
        }).join('');
    }

    function pintarNoAscii(conteo) {
        const entradas = Object.keys(conteo).map(k => [k, conteo[k]]);
        const contenedor = document.getElementById('listaNoAscii');

        if (!entradas.length) {
            contenedor.innerHTML = '<span style="font-size:12.5px;color:var(--ink-muted)">Ninguno: el archivo es ASCII puro.</span>';
            return;
        }

        contenedor.innerHTML = entradas.map(function (par) {
            const caracter = par[0];
            const punto = caracter.codePointAt(0).toString(16).toUpperCase().padStart(4, '0');
            const legitimo = 'ÁÉÍÓÚÜÑáéíóúüñÇç'.indexOf(caracter) !== -1;

            return '<span class="sqlfix-caracter' + (legitimo ? '' : ' sospechoso') + '">'
                + escapar(caracter) + '<span class="punto">U+' + punto + '</span> · ' + miles(par[1])
                + '</span>';
        }).join('');
    }

    function insignia(tipo) {
        const mapa = {
            comillas: ['act', 'apóstrofe'],
            codificacion: ['inv', 'codificación']
        };
        const dato = mapa[tipo] || ['inv', tipo];

        return '<span class="chip-estado ' + dato[0] + '"><span class="dot"></span>'
            + escapar(dato[1]) + '</span>';
    }

    function recortar(texto, largo) {
        return texto.length > largo ? texto.slice(0, largo) + '…' : texto;
    }

    function miles(n) {
        return Number(n || 0).toLocaleString('es-MX');
    }

    function formatearBytes(bytes) {
        if (!bytes) {
            return '0 B';
        }
        const unidades = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));

        return (bytes / Math.pow(1024, i)).toFixed(i ? 2 : 0) + ' ' + unidades[i];
    }

    function escapar(texto) {
        const div = document.createElement('div');
        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML;
    }
})();
