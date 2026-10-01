/**
 * Generador de nómina de eventuales: carga la nómina de la quincena y el
 * detalle anterior, arma los cuatro archivos y los entrega.
 *
 * Todo ocurre en este navegador: los archivos traen RFC, CURP y sueldo de unas
 * 2 700 personas y no se suben a ningún lado. La lógica vive en js/core/; aquí
 * solo hay lectura de archivos, pintado y descargas.
 */
(function () {
    'use strict';

    const X = window.EV.xlsx;
    const N = window.EV.nomina;

    const velo = document.getElementById('loader');
    const etiquetaVelo = document.getElementById('loader-label');
    const cajaError = document.getElementById('nev-error');
    const resultado = document.getElementById('nev-resultado');
    const btnGenerar = document.getElementById('nev-generar');
    const campoFecha = document.getElementById('nev-fecha-pago');

    const TIPO_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    const estado = {
        /** { nombre, bytes, datos } con `datos` = N.leerEntrada(...) */
        entrada: null,
        /** { archivos: [{ nombre, personas }], datos } con `datos` = N.leerDetalleAnterior(...) */
        anterior: null,
        /** Lo último generado: { r, archivos: [{ nombre, bytes }] } */
        ultimo: null
    };

    /* ── Carga de archivos ───────────────────────────────────────────────── */

    document.getElementById('nev-archivo-nomina').addEventListener('change', async function () {
        const archivo = (this.files || [])[0];

        estado.entrada = null;
        cargando(true, 'Leyendo la nómina');

        let error = null;

        try {
            if (archivo) {
                const libro = await X.leerLibro(await archivo.arrayBuffer());

                estado.entrada = { nombre: archivo.name, bytes: archivo.size, datos: N.leerEntrada(libro) };
            }
        } catch (e) {
            error = archivo.name + ': ' + e.message;
        } finally {
            cargando(false);
        }

        document.getElementById('nev-etiqueta-nomina').textContent = estado.entrada
            ? 'Cambiar archivo' : 'Selecciona el archivo .xlsx';

        pintarListaNomina();
        llenarPeriodo();
        actualizarBoton();

        // Después del `finally`: `cargando(false)` esconde la caja de error.
        if (error) {
            mostrarError('No se pudo leer la nómina. ' + error);
        }
    });

    document.getElementById('nev-archivos-anterior').addEventListener('change', async function () {
        const archivos = Array.from(this.files || []);

        estado.anterior = null;
        cargando(true, 'Leyendo el detalle anterior');

        // Lo natural es soltar las cuatro salidas de la quincena pasada juntas.
        // Cada archivo se clasifica por separado: los detalles se usan, los
        // prod_pago se ignoran con su nota, y uno que no se reconoce se
        // señala sin tumbar a los demás.
        const libros = [];
        const resumen = [];

        try {
            for (const f of archivos) {
                try {
                    const libro = await X.leerLibro(await f.arrayBuffer());
                    const tipo = N.clasificarSalida(libro);

                    if (tipo === 'detalle') {
                        const personas = N.leerDetalleAnterior([{ libro, archivo: f.name }]).porRfc.size;

                        libros.push({ libro, archivo: f.name });
                        resumen.push({ nombre: f.name, tipo, detalle: miles(personas) + (personas === 1 ? ' persona' : ' personas') });
                    } else if (tipo === 'prod') {
                        resumen.push({ nombre: f.name, tipo, detalle: 'prod_pago: no se usa, los nombres vienen en el detalle' });
                    } else {
                        resumen.push({ nombre: f.name, tipo: 'error', detalle: 'no es un DETALLE EMPLEADOS: se deja fuera' });
                    }
                } catch (e) {
                    resumen.push({ nombre: f.name, tipo: 'error', detalle: 'no se pudo leer: ' + e.message });
                }
            }

            if (libros.length) {
                estado.anterior = { archivos: resumen, datos: N.leerDetalleAnterior(libros), detalles: libros.length };
            } else if (resumen.length) {
                estado.anterior = { archivos: resumen, datos: null, detalles: 0 };
            }
        } finally {
            cargando(false);
        }

        document.getElementById('nev-etiqueta-anterior').textContent = resumen.length
            ? 'Cambiar archivos' : 'Selecciona los archivos .xlsx';

        pintarListaAnterior();
        actualizarBoton();
    });

    function pintarListaNomina() {
        const e = estado.entrada;

        pintarLista('nev-lista-nomina', e ? [{
            nombre: e.nombre,
            detalle: 'hoja «' + e.datos.hoja + '» · quincena ' + e.datos.quincena + ' de ' + e.datos.anio
                + ' · ' + miles(e.datos.registros.length) + ' renglones'
        }] : []);
    }

    function pintarListaAnterior() {
        const a = estado.anterior;

        const filas = a ? a.archivos.slice() : [];

        // Son dos detalles, E y S. Con uno solo, el otro juego entero saldría
        // como alta y con el nombre armado desde la nómina.
        if (a && a.detalles === 0) {
            filas.push({ nombre: 'Ningún detalle cargado', tipo: 'error',
                detalle: 'se generará sin nombres corregidos ni orden de la cola' });
        } else if (a && a.detalles === 1) {
            filas.push({ nombre: 'Falta un detalle', tipo: 'error',
                detalle: 'carga también el del otro juego (E o S)' });
        }

        pintarLista('nev-lista-anterior', filas);
    }

    function pintarLista(id, filas) {
        const lista = document.getElementById(id);

        lista.closest('.nev-carga').classList.toggle('con-archivos', filas.length > 0);
        lista.innerHTML = filas.map(f =>
            '<li class="nev-archivo' + (f.tipo === 'prod' ? ' nev-archivo--ignorado' : f.tipo === 'error' ? ' nev-archivo--error' : '') + '">'
            + '<span class="nev-archivo__nombre">' + esc(f.nombre) + '</span>'
            + '<span class="nev-archivo__detalle">' + esc(f.detalle) + '</span>'
            + '</li>'
        ).join('');
    }

    /** Quincena y fecha de pago, en cuanto se conoce la nómina. */
    function llenarPeriodo() {
        const e = estado.entrada;
        const quincena = document.getElementById('nev-quincena');

        if (!e) {
            quincena.value = '';
            campoFecha.value = '';
            pintarSalen();

            return;
        }

        quincena.value = String(e.datos.quincena).padStart(2, '0') + ' / ' + e.datos.anio;
        campoFecha.value = N.finDeQuincena(e.datos.anio, e.datos.quincena);
        pintarSalen();
    }

    campoFecha.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 8);
        actualizarBoton();
    });

    /** Los cuatro nombres de archivo, en vivo: así se ve la quincena antes de generar. */
    function pintarSalen() {
        const e = estado.entrada;
        const lista = document.getElementById('nev-salen');

        if (!e) {
            lista.innerHTML = '<li class="nev-salen__vacio">Carga la nómina para ver los nombres</li>';

            return;
        }

        const qq = String(e.datos.quincena).padStart(2, '0');
        const a = e.datos.anio;

        lista.innerHTML = [
            'detalle prod_pago con 1807 FED ' + qq + 'E.xlsx',
            'DETALLE EMPLEADOS CON 1807 FED' + qq + 'E.xlsx',
            'detalle prod_pago con 1807 FED ' + qq + 'S ' + a + '.xlsx',
            'DETALLE EMPLEADOS CON 1807 FED' + qq + 'S ' + a + '.xlsx'
        ].map(n => '<li>' + esc(n) + '</li>').join('');
    }

    pintarSalen();

    function fechaValida(t) {
        if (!/^\d{8}$/.test(t)) {
            return false;
        }

        const d = new Date(Date.UTC(Number(t.slice(0, 4)), Number(t.slice(4, 6)) - 1, Number(t.slice(6, 8))));

        return d.getUTCFullYear() === Number(t.slice(0, 4))
            && d.getUTCMonth() === Number(t.slice(4, 6)) - 1
            && d.getUTCDate() === Number(t.slice(6, 8));
    }

    function actualizarBoton() {
        const fechaOk = !campoFecha.value || fechaValida(campoFecha.value);

        btnGenerar.disabled = !estado.entrada || !fechaOk;
        document.getElementById('nev-fecha-ayuda').textContent = fechaOk
            ? 'AAAAMMDD · por omisión, el último día de la quincena'
            : 'No es una fecha válida (AAAAMMDD)';
        document.getElementById('nev-fecha-ayuda').classList.toggle('nev-invalido', !fechaOk);
    }

    /* ── Generación ──────────────────────────────────────────────────────── */

    document.getElementById('nev-form').addEventListener('submit', function (evento) {
        evento.preventDefault();
        generar();
    });

    async function generar() {
        if (!estado.entrada) {
            return;
        }

        cargando(true, 'Generando archivos');

        // Un respiro para que el velo alcance a pintarse antes de bloquear el hilo.
        await new Promise(r => setTimeout(r, 30));

        try {
            const r = N.generar(estado.entrada.datos, estado.anterior && estado.anterior.datos,
                { fechaPago: campoFecha.value || undefined });
            const archivos = [];

            // Con errores no se escriben: los archivos saldrían incompletos y
            // es más fácil que alguien descargue uno a medias que que lo note.
            if (!r.errores) {
                for (const k of ['EV', 'SA']) {
                    const j = r.juegos[k];

                    archivos.push({ juego: k, tipo: 'prod', nombre: j.archivos.prod,
                        bytes: await X.escribirLibro([{ nombre: 'Hoja1', filas: j.filasProd }]) });
                    archivos.push({ juego: k, tipo: 'detalle', nombre: j.archivos.detalle,
                        bytes: await X.escribirLibro([{ nombre: 'Hoja1', filas: j.filasDetalle }]) });
                }
            }

            estado.ultimo = { r, archivos };
            pintar(estado.ultimo);
            cajaError.hidden = true;
        } catch (e) {
            mostrarError('No se pudo generar: ' + e.message);
        } finally {
            cargando(false);
        }

        if (!resultado.hidden) {
            irAlResultado();
        }
    }

    function irAlResultado() {
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        resultado.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        resultado.setAttribute('tabindex', '-1');
        resultado.focus({ preventScroll: true });
    }

    function cargando(activo, etiqueta) {
        if (velo) {
            velo.hidden = !activo;
        }

        if (activo && etiqueta && etiquetaVelo) {
            etiquetaVelo.textContent = etiqueta;
        }

        if (activo) {
            btnGenerar.disabled = true;
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

    function pintar(u) {
        const r = u.r;
        const ev = r.juegos.EV;
        const sa = r.juegos.SA;
        const avisos = r.hallazgos.length - r.errores;
        const chip = document.getElementById('nev-estado');

        chip.className = 'chip-estado ' + (r.errores ? 'can' : avisos ? 'act' : 'imp');
        chip.innerHTML = '<span class="dot"></span>' + (r.errores ? 'CON ERRORES' : avisos ? 'LISTO CON AVISOS' : 'LISTO');

        document.getElementById('nev-resumen').textContent = 'Quincena ' + String(r.quincena).padStart(2, '0')
            + ' de ' + r.anio + ' · pago ' + fecha(r.fechaPago) + ' · ' + r.descNomina
            + (r.conAnterior ? ' · con detalle anterior' : ' · sin detalle anterior');

        const retro = ev.retroactivos.length + sa.retroactivos.length;

        document.getElementById('nev-tiles').innerHTML = [
            { etiqueta: 'Renglones', valor: miles(ev.totales.registros + sa.totales.registros) },
            { etiqueta: 'Eventuales', valor: miles(ev.totales.registros) },
            { etiqueta: 'SaNAS', valor: miles(sa.totales.registros) },
            { etiqueta: 'Neto total', valor: pesos(ev.totales.neto + sa.totales.neto), acento: true },
            { etiqueta: 'Retroactivos', valor: miles(retro) },
            { etiqueta: 'Altas / bajas', valor: r.conAnterior ? miles(r.altas.length) + ' / ' + miles(r.bajas.length) : '—' }
        ].map(t =>
            '<div class="nev-stat">'
            + '<div class="eyebrow" style="letter-spacing:1.1px">' + esc(t.etiqueta) + '</div>'
            + '<div class="valor' + (t.acento ? ' acento' : '') + '">' + esc(t.valor) + '</div>'
            + '</div>'
        ).join('');

        document.getElementById('nev-juegos').innerHTML = [ev, sa].map(j => pintarJuego(j, u.archivos)).join('');

        const btnTodo = document.getElementById('nev-descargar-todo');

        btnTodo.disabled = !u.archivos.length;
        document.getElementById('nev-nota-descarga').textContent = r.errores
            ? 'No se generaron los archivos: corrige los errores de abajo y vuelve a generar.'
            : 'También puedes bajarlos uno por uno desde cada juego.';

        pintarMovimientos(r);
        pintarHallazgos(r.hallazgos);
        resultado.hidden = false;
    }

    /** Un juego: sus claves, el cuadre contra el NETO de la nómina y sus dos descargas. */
    function pintarJuego(j, archivos) {
        const t = j.totales;
        const cuadra = Math.abs(t.neto - t.netoEntrada) < 0.005;
        const claves = t.porClave.slice().sort((a, b) => (a.tipo === b.tipo ? 0 : a.tipo === 'P' ? -1 : 1));
        const fila = (a, b, c, d, clase) => '<div class="tabla__row nev-fila-clave' + (clase ? ' ' + clase : '') + '">'
            + '<div class="nev-campo">' + a + '</div><div>' + b + '</div>'
            + '<div class="nev-conteo">' + c + '</div><div class="nev-conteo">' + d + '</div></div>';
        const botones = archivos.filter(a => a.juego === j.clave).map(a =>
            '<button type="button" class="btn-ghost nev-descarga" data-archivo="' + esc(a.nombre) + '">'
            + esc(a.tipo === 'prod' ? 'prod_pago' : 'Detalle empleados') + '</button>'
        ).join('');

        return '<div class="nev-juego">'
            + '<div class="nev-juego__cabeza">'
            + '<div><div class="eyebrow">' + esc(j.prefijo) + '</div>'
            + '<h3 class="nev-juego__titulo">' + esc(j.etiqueta) + '</h3></div>'
            + '<div class="nev-juego__cuenta">' + miles(t.registros) + ' renglones · ' + miles(t.personas) + ' personas</div>'
            + '</div>'
            + '<div class="tabla">'
            + '<div class="tabla__head nev-fila-clave"><div>Clave</div><div>Concepto</div>'
            + '<div class="nev-conteo">Renglones</div><div class="nev-conteo">Importe</div></div>'
            + claves.map(c => fila(esc(c.clave), esc(c.concepto) + (c.tipo === 'P' ? '' : ' <span class="nev-ded">deducción</span>'),
                miles(c.registros), pesos(c.importe))).join('')
            + fila('', 'Percepciones', '', pesos(t.percepciones), 'nev-fila-total')
            + fila('', 'Deducciones', '', pesos(t.deducciones), 'nev-fila-total')
            + fila('', '<strong>Neto</strong>', '', '<strong>' + pesos(t.neto) + '</strong>', 'nev-fila-total')
            + fila('', 'NETO de la nómina', '<span class="nev-cuadre ' + (cuadra ? 'ok' : 'mal') + '">'
                + (cuadra ? 'cuadra' : 'no cuadra') + '</span>', pesos(t.netoEntrada), 'nev-fila-total')
            + '</div>'
            + (botones ? '<div class="nev-juego__descargas">' + botones + '</div>' : '')
            + '</div>';
    }

    function pintarMovimientos(r) {
        const retro = r.juegos.EV.retroactivos.map(x => ({ x, juego: 'Eventuales' }))
            .concat(r.juegos.SA.retroactivos.map(x => ({ x, juego: 'SaNAS' })));
        const bloques = [];
        const armados = new Map(r.nombresArmados.map(a => [a.rfc, a]));
        const tabla = (columnas, clase, filas) => '<div class="tabla">'
            + '<div class="tabla__head ' + clase + '">' + columnas.map(c => '<div>' + c + '</div>').join('') + '</div>'
            + '<div class="nev-scroll nev-scroll--bajo">' + filas.join('') + '</div></div>';

        if (r.conAnterior) {
            bloques.push('<div class="nev-mov"><h3 class="nev-mov__titulo">Altas <span>' + miles(r.altas.length) + '</span></h3>'
                + (r.altas.length ? tabla(['RFC', 'Así queda el nombre', 'Juego'], 'nev-fila-alta', r.altas.map(a => {
                    const n = armados.get(a.rfc);

                    return '<div class="tabla__row nev-fila-alta"><div class="nev-campo">' + esc(a.rfc) + '</div>'
                        + '<div>' + esc(n ? n.paterno + ' · ' + n.materno + ' · ' + n.nombre : a.nombre)
                        + '<div class="nev-sub">' + esc(a.subprograma || a.programa) + '</div></div>'
                        + '<div>' + esc(a.juego) + '</div></div>';
                })) : '<div class="emp-vacio">Sin altas.</div>') + '</div>');

            bloques.push('<div class="nev-mov"><h3 class="nev-mov__titulo">Bajas <span>' + miles(r.bajas.length) + '</span></h3>'
                + (r.bajas.length ? tabla(['RFC', 'Nombre en el detalle anterior'], 'nev-fila-baja', r.bajas.map(b =>
                    '<div class="tabla__row nev-fila-baja"><div class="nev-campo">' + esc(b.rfc) + '</div>'
                    + '<div>' + esc(b.nombre) + '</div></div>'
                )) : '<div class="emp-vacio">Sin bajas.</div>') + '</div>');
        }

        bloques.push('<div class="nev-mov"><h3 class="nev-mov__titulo">Retroactivos <span>' + miles(retro.length) + '</span></h3>'
            + (retro.length ? tabla(['RFC', 'Periodo', 'Sueldo'], 'nev-fila-retro', retro.map(({ x, juego }) =>
                '<div class="tabla__row nev-fila-retro"><div class="nev-campo">' + esc(x.rfc)
                + '<div class="nev-sub">' + esc(juego) + '</div></div>'
                + '<div>' + fecha(x.fecInicio) + ' – ' + fecha(x.fecFin) + '</div>'
                + '<div class="nev-conteo">' + pesos(x.importes.c07) + '</div></div>'
            )) : '<div class="emp-vacio">Sin retroactivos.</div>') + '</div>');

        if (!r.conAnterior) {
            bloques.push('<p class="nev-nota nev-mov__nota">Carga el detalle de la quincena anterior para ver altas y bajas.</p>');
        }

        document.getElementById('nev-movimientos').innerHTML = bloques.join('');
        document.getElementById('nev-bloque-movimientos').hidden = false;
    }

    function pintarHallazgos(hallazgos) {
        const bloque = document.getElementById('nev-bloque-hallazgos');

        bloque.hidden = hallazgos.length === 0;

        if (!hallazgos.length) {
            return;
        }

        const orden = { error: 0, aviso: 1 };
        const filas = hallazgos.slice().sort((a, b) => orden[a.severidad] - orden[b.severidad]);
        const muestra = filas.slice(0, 300);
        const errores = hallazgos.filter(h => h.severidad === 'error').length;

        document.getElementById('nev-badge-hallazgos').textContent = (errores ? miles(errores) + ' con error · ' : '')
            + miles(hallazgos.length - errores) + (hallazgos.length - errores === 1 ? ' aviso' : ' avisos');

        document.getElementById('nev-tabla-hallazgos').innerHTML = muestra.map(h =>
            '<div class="tabla__row nev-fila-hallazgo">'
            + '<div><span class="chip-estado ' + (h.severidad === 'error' ? 'can' : 'act') + '">'
            + '<span class="dot"></span>' + (h.severidad === 'error' ? 'ERROR' : 'AVISO') + '</span></div>'
            + '<div>' + esc(h.renglon ? String(h.renglon) : '—') + '</div>'
            + '<div class="nev-campo">' + esc(h.rfc || '—') + '</div>'
            + '<div class="nev-mensaje"><strong>' + esc(h.tipo) + '.</strong> ' + esc(h.mensaje)
            + (h.nombre ? '<div class="nev-sub">' + esc(h.nombre) + '</div>' : '') + '</div>'
            + '</div>'
        ).join('');

        const pie = document.getElementById('nev-pie-hallazgos');
        const ocultos = filas.length - muestra.length;

        pie.hidden = ocultos <= 0;
        pie.textContent = ocultos > 0 ? 'Se listan los primeros ' + miles(muestra.length) + ' de ' + miles(filas.length) + '.' : '';
    }

    /* ── Descargas ───────────────────────────────────────────────────────── */

    document.getElementById('nev-juegos').addEventListener('click', function (evento) {
        const boton = evento.target.closest('[data-archivo]');
        const a = boton && estado.ultimo
            ? estado.ultimo.archivos.find(x => x.nombre === boton.dataset.archivo) : null;

        if (a) {
            descargar(a.nombre, a.bytes, TIPO_XLSX);
        }
    });

    document.getElementById('nev-descargar-todo').addEventListener('click', async function () {
        const u = estado.ultimo;

        if (!u || !u.archivos.length) {
            return;
        }

        // Cuatro descargas seguidas las bloquea más de un navegador; un .zip
        // llega completo. Los .xlsx ya vienen comprimidos: se guardan tal cual.
        const zip = await X.empaquetar(u.archivos.map(a => ({ nombre: a.nombre, datos: a.bytes })), false);

        descargar('nomina eventuales q' + String(u.r.quincena).padStart(2, '0') + ' ' + u.r.anio + '.zip',
            zip, 'application/zip');
    });

    /** El revoke va en un setTimeout: en el mismo tick que el click corta la descarga en algunos navegadores. */
    function descargar(nombre, bytes, tipo) {
        const url = URL.createObjectURL(new Blob([bytes], { type: tipo }));
        const a = document.createElement('a');

        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    /* ── Ayuda contextual ────────────────────────────────────────────────── */

    const modal = document.getElementById('nev-modal');
    let abridor = null;

    document.addEventListener('click', function (evento) {
        const boton = evento.target.closest ? evento.target.closest('[data-ayuda]') : null;

        if (boton) {
            abrirAyuda(boton.dataset.ayuda, boton);

            return;
        }

        if (!modal.hidden && evento.target.closest && evento.target.closest('[data-cerrar]')) {
            cerrarAyuda();
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && !modal.hidden) {
            cerrarAyuda();
        }
    });

    function abrirAyuda(clave, boton) {
        const fuente = document.querySelector('[data-ayuda-de="' + clave + '"]');

        if (!fuente) {
            return;
        }

        abridor = boton || null;
        document.getElementById('nev-modal-kicker').textContent = fuente.dataset.kicker || '';
        document.getElementById('nev-modal-titulo').textContent = fuente.dataset.titulo || '';
        document.getElementById('nev-modal-cuerpo').innerHTML = fuente.innerHTML;
        document.getElementById('nev-modal-cuerpo').scrollTop = 0;
        modal.hidden = false;
        modal.querySelector('.nev-modal__cerrar').focus();
    }

    function cerrarAyuda() {
        modal.hidden = true;

        if (abridor) {
            abridor.focus();
            abridor = null;
        }
    }

    /* ── Utilidades ──────────────────────────────────────────────────────── */

    function miles(n) {
        return Number(n || 0).toLocaleString('es-MX');
    }

    function pesos(n) {
        return Number(n || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
    }

    /** 20260930 → 30/09/2026 */
    function fecha(t) {
        const s = String(t || '');

        return /^\d{8}$/.test(s) ? s.slice(6, 8) + '/' + s.slice(4, 6) + '/' + s.slice(0, 4) : s;
    }

    function esc(texto) {
        const div = document.createElement('div');

        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML;
    }
})();
