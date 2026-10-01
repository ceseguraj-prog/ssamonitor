/**
 * Núcleo del generador de nómina de eventuales: de la hoja de la quincena a
 * los cuatro archivos de carga (prod_pago y detalle de empleados, de
 * eventuales y de SaNAS).
 *
 * Las reglas se sacaron comparando entradas contra las salidas que se armaban a
 * mano en las quincenas 16 y 17 de 2026, y el prod_pago se reproduce idéntico
 * en las dos. Lo que hay que saber está en el README; aquí van las reglas como
 * datos, para que un cambio de clave sea editar una constante.
 *
 * Sin DOM y sin red: recibe libros ya leídos por EV.xlsx y devuelve filas.
 */
(function (EV) {
    'use strict';

    /* ── Reglas ──────────────────────────────────────────────────────────── */

    /**
     * Columnas de la hoja de entrada, por su encabezado normalizado (sin
     * acentos, en minúsculas). Se ubican por texto y no por posición, para que
     * una columna de más no corra los datos.
     *
     * 'TIPO DE NOMINA' viene DOS veces: la primera dice ORDINARIA (va al
     * encabezado del prod_pago) y la segunda 11 o 22 (22 = retroactivo). Por
     * eso `ocurrencia`.
     */
    const COLUMNAS = {
        descNomina: { encabezado: 'tipo de nomina', ocurrencia: 0 },
        qna: { encabezado: 'qna' },
        anio: { encabezado: 'ano' },
        descPrograma: { encabezado: 'desc. programa' },
        descSubprograma: { encabezado: 'desc. subprograma', opcional: true },
        cr: { encabezado: 'cen.resp.' },
        rfc: { encabezado: 'rfc' },
        curp: { encabezado: 'curp' },
        fingreso: { encabezado: 'fingreso' },
        nombre: { encabezado: 'nombre' },
        codigo: { encabezado: 'codigo' },
        c07: { encabezado: '07' },
        c06: { encabezado: '06' },
        c37: { encabezado: '37' },
        c29: { encabezado: '29+' },
        isr: { encabezado: 'isr' },
        se: { encabezado: 'se' },
        pension: { encabezado: 'pension' },
        faltas: { encabezado: 'faltas' },
        desMercantil: { encabezado: 'des.mercantil' },
        descuentos: { encabezado: 'descuentos' },
        neto: { encabezado: 'neto' },
        tipoNomina: { encabezado: 'tipo de nomina', ocurrencia: 1 },
        fecInicio: { encabezado: 'fec_inicio' },
        fecFin: { encabezado: 'fec_fin' },
        rama: { encabezado: 'tipo de rama' }
    };

    /**
     * Cada importe de la entrada y la clave que lleva en cada juego, con su
     * casilla fija en el prod_pago (la casilla no se compacta: el 21700 va en
     * la tercera de deducciones aunque el renglón no traiga pensión).
     *
     * Un concepto sin clave para un juego no se puede emitir. 29+, SE y
     * DES.MERCANTIL vinieron en cero en las quincenas 16, 17 y 18, así que su
     * clave nunca se vio: si un día traen importe, el lote se detiene en vez de
     * inventarla. Igual SaNAS, que solo ha traído sueldo e ISR.
     */
    const CONCEPTOS = [
        { campo: 'c07', etiqueta: '07', tipo: 'P', EV: { clave: '10200', casilla: 0 }, SA: { clave: '102SA', casilla: 0 } },
        { campo: 'c06', etiqueta: '06', tipo: 'P', EV: { clave: '106EE', casilla: 1 } },
        { campo: 'c37', etiqueta: '37', tipo: 'P', EV: { clave: '105CG', casilla: 2 } },
        { campo: 'c29', etiqueta: '29+', tipo: 'P' },
        { campo: 'isr', etiqueta: 'ISR', tipo: 'D', EV: { clave: '20102', casilla: 0 }, SA: { clave: '201HA', casilla: 0 } },
        { campo: 'se', etiqueta: 'SE', tipo: 'D' },
        { campo: 'pension', etiqueta: 'PENSION', tipo: 'D', EV: { clave: '262DE', casilla: 1 } },
        { campo: 'faltas', etiqueta: 'FALTAS', tipo: 'D', EV: { clave: '21700', casilla: 2 } },
        { campo: 'desMercantil', etiqueta: 'DES.MERCANTIL', tipo: 'D' },
        { campo: 'descuentos', etiqueta: 'DESCUENTOS', tipo: 'D', EV: { clave: '246OM', casilla: 3 } }
    ];

    /** Los dos juegos de archivos. SaNAS va aparte, con su propio prefijo y contrato. */
    const JUEGOS = {
        EV: { clave: 'EV', etiqueta: 'Eventuales', sufijo: 'E', contrato: 'EVENTUAL', primeraCelda: ' ' },
        SA: { clave: 'SA', etiqueta: 'SaNAS', sufijo: 'S', contrato: 'HONORARIOS', primeraCelda: 'prod pago' }
    };

    /**
     * Orden del número de empleado. Es un consecutivo que se rehace cada
     * quincena y que comparten los dos juegos:
     *   1. el bloque principal, por programa en este orden y, dentro de cada
     *      programa, en el orden de la entrada;
     *   2. al final, la «cola» de CUOTAS, REGULACIÓN SANITARIA y GUARDIAS.
     *      Son siempre las mismas ~31 personas y se numeran en un orden fijo,
     *      no en el de la entrada: en la q16 venían revueltas y aun así salieron
     *      en el mismo orden que en la q17. Ese orden se toma del detalle
     *      anterior.
     */
    const ORDEN_PROGRAMAS = ['IMSS BIENESTAR', 'IMSS BIENESTAR ADMINISTRATIVOS', 'OTROS PROGRAMAS', 'SANAS'];
    const PROGRAMAS_COLA = ['CUOTAS', 'REGULACION SANITARIA', 'GUARDIAS'];

    const CASILLAS_PERCEPCION = 10;
    const CASILLAS_DEDUCCION = 18;
    const COL_PERCEPCIONES = 31; // AF
    const COL_DEDUCCIONES = COL_PERCEPCIONES + 3 * CASILLAS_PERCEPCION; // BJ
    const COL_FIN = COL_DEDUCCIONES + 3 * CASILLAS_DEDUCCION; // DL, la primera que ya no se usa
    const FILA_DATOS = 4;

    /**
     * Encabezado del prod_pago, idéntico al de las plantillas (con sus rarezas:
     * el espacio duro tras «percepción» y la numeración repetida de las
     * deducciones). El sistema de carga lo lee por posición, pero quien lo
     * revise a ojo lo compara contra el de siempre.
     */
    const ENCABEZADO_PROD = (function () {
        const fijo = ['num empl.', 'secuencia empleado', 'instrumento de pago', 'PROGRAMA', 'RFC',
            'centro de trabajo', 'puesto', 'cve de pago', 'tipo de contrato', 'regimen', 'tipo de jornada',
            'periodo pago ini', 'periodo pago fin', 'num_cheque', 'salario bse cot_aportaciones', 'sdi',
            'tot_dias faltados', 'tot_dias incapacidad', 'tipo de incapacidad', 'importe de incapacidad',
            'tot_dias pag horas dobles', 'num_hrs extras dobles', 'imp_pagado hrs dobles',
            'tot_dias pag hrs triple', 'num_hrs triples', 'imp_pag_hrs triple', 'ADICIONAL', 'CLUE',
            'SINDICATO', 'num trailers'];
        const salida = [null].concat(fijo);

        for (let i = 0; i < CASILLAS_PERCEPCION; i++) {
            salida.push('Clave de percepción ', 'Importe gravado', 'Importe exento');
        }

        for (let i = 0; i < 4; i++) {
            salida.push('Clave de deducción de nómina', 'Importe gravado', 'Importe exento');
        }

        [[' 2', '2'], [' 3', '3'], [' 4', '4'], [' 5', '5'], [' 5', '6'], [' 5', '6'], [' 5', '6'], [' 5', '6'],
            [' 6', '6'], [' 7', '7'], [' 8', '8'], [' 9', '9'], [' 9', '9'], ['10', '10']].forEach(([c, n]) => {
            salida.push(c + 'Clave de deducción de nómina', 'Importe gravado ' + n, 'Importe exento ' + n);
        });

        return salida;
    })();

    const ENCABEZADO_DETALLE = ['numero de empleado', 'apellido paterno', 'apellido materno', 'nombre (s)',
        'filiacion', 'curp', 'numero de seg. Social', 'fecha de ingreso', 'indicador de reg.', 'rama'];

    /* ── Utilidades ──────────────────────────────────────────────────────── */

    function normalizar(s) {
        return String(s === undefined || s === null ? '' : s)
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function centavos(n) {
        return Math.round(n * 100) / 100;
    }

    /** Importe de la entrada; vacío cuenta como cero, basura como NaN. */
    function importe(texto) {
        const t = String(texto === undefined || texto === null ? '' : texto).replace(/[\s$,]/g, '');

        return t === '' ? 0 : centavos(Number(t));
    }

    /**
     * Nombre o apellido como va en el detalle: sin acentos, la Ñ como N, sin
     * puntos («MA.» → «MA») y sin espacios de más. Vacío se escribe '0', que es
     * como el detalle marca a quien no tiene segundo apellido.
     */
    function limpiarNombre(s) {
        const t = String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toUpperCase().replace(/\./g, '').replace(/\s+/g, ' ').trim();

        return t || '0';
    }

    /**
     * `PATERNO,MATERNO/NOMBRE` → partes. Alguno llega como
     * `PATERNO,MATERNO,NOMBRE`; se acepta y se avisa.
     */
    function partirNombre(completo) {
        const t = String(completo || '').trim();
        const coma = t.indexOf(',');

        if (coma < 0) {
            return null;
        }

        const resto = t.slice(coma + 1);
        const separador = resto.indexOf('/') >= 0 ? '/' : resto.indexOf(',') >= 0 ? ',' : null;

        if (!separador) {
            return null;
        }

        const corte = resto.indexOf(separador);

        return {
            paterno: t.slice(0, coma),
            materno: resto.slice(0, corte),
            nombre: resto.slice(corte + 1),
            irregular: separador === ','
        };
    }

    /** Último día de la quincena, AAAAMMDD: el 15 si es non, fin de mes si es par. */
    function finDeQuincena(anio, quincena) {
        const mes = Math.ceil(quincena / 2);
        const dia = quincena % 2 ? 15 : new Date(Date.UTC(anio, mes, 0)).getUTCDate();

        return String(anio) + String(mes).padStart(2, '0') + String(dia).padStart(2, '0');
    }

    /**
     * Ubica el renglón de encabezado: el primero, entre los 15 de arriba, que
     * trae todas las columnas obligatorias.
     */
    function ubicarColumnas(filas, columnas) {
        for (let i = 0; i < Math.min(filas.length, 15); i++) {
            const nombres = (filas[i] || []).map(normalizar);
            const mapa = {};
            let completo = true;

            Object.keys(columnas).forEach(k => {
                const def = columnas[k];
                let vistas = 0;
                let j = -1;

                for (let x = 0; x < nombres.length; x++) {
                    if (nombres[x] === def.encabezado && vistas++ === (def.ocurrencia || 0)) {
                        j = x;
                        break;
                    }
                }

                if (j >= 0) {
                    mapa[k] = j;
                } else if (!def.opcional) {
                    completo = false;
                }
            });

            if (completo) {
                return { mapa, renglon: i };
            }
        }

        return null;
    }

    /* ── Entrada ─────────────────────────────────────────────────────────── */

    /**
     * Lee la hoja de eventuales de la quincena.
     *
     * Los libros de la q16 y la q17 traen primero la hoja SIAP (la nómina
     * federal, que aquí no se usa) y la de la q18 trae además «pensiones». Se
     * toma la primera hoja que tenga las columnas de la nómina de eventuales.
     */
    function leerEntrada(libro) {
        let hoja = null;
        let ubicacion = null;

        for (const h of libro.hojas) {
            ubicacion = ubicarColumnas(h.filas, COLUMNAS);

            if (ubicacion) {
                hoja = h;
                break;
            }
        }

        if (!hoja) {
            const faltan = Object.keys(COLUMNAS).filter(k => !COLUMNAS[k].opcional).map(k => COLUMNAS[k].encabezado.toUpperCase());

            throw new Error('Ninguna hoja del libro trae las columnas de la nómina de eventuales ('
                + faltan.slice(0, 8).join(', ') + '…). ¿Es el archivo de entrada correcto?');
        }

        const m = ubicacion.mapa;
        const registros = [];
        const hallazgos = [];
        const quincenas = new Map();

        for (let i = ubicacion.renglon + 1; i < hoja.filas.length; i++) {
            const fila = hoja.filas[i];

            if (!fila || fila.every(x => x === undefined || String(x).trim() === '')) {
                continue;
            }

            const celda = k => (m[k] === undefined || fila[m[k]] === undefined ? '' : String(fila[m[k]]).trim());
            const renglon = i + 1;
            const r = {
                renglon,
                descNomina: celda('descNomina'),
                qna: celda('qna'),
                anio: celda('anio'),
                descPrograma: celda('descPrograma'),
                descSubprograma: celda('descSubprograma'),
                cr: celda('cr'),
                rfc: celda('rfc').toUpperCase(),
                curp: celda('curp').toUpperCase(),
                fingreso: celda('fingreso'),
                nombre: celda('nombre'),
                codigo: celda('codigo'),
                neto: importe(celda('neto')),
                tipoNomina: celda('tipoNomina'),
                fecInicio: celda('fecInicio'),
                fecFin: celda('fecFin'),
                rama: celda('rama'),
                importes: {}
            };

            CONCEPTOS.forEach(c => {
                r.importes[c.campo] = importe(celda(c.campo));

                if (Number.isNaN(r.importes[c.campo])) {
                    hallazgos.push(hallazgo('error', 'Importe ilegible', r,
                        'La columna ' + c.etiqueta + ' trae «' + celda(c.campo) + '», que no es un número.'));
                    r.importes[c.campo] = 0;
                }
            });

            if (!r.rfc) {
                hallazgos.push(hallazgo('error', 'Sin RFC', r, 'El renglón no trae RFC.'));
            } else if (r.rfc !== celda('rfc')) {
                // Pasó en la q16, la q17 y la q18 (homoclave en minúsculas) y la salida a mano
                // lo dejó igual. Una homoclave en minúsculas no es un RFC
                // válido, así que se corrige, pero a la vista.
                hallazgos.push(hallazgo('aviso', 'RFC en minúsculas', r,
                    'Viene como «' + celda('rfc') + '»; en los archivos va como «' + r.rfc + '». Conviene corregirlo en el origen.'));
            }

            if (!/^\d{8}$/.test(r.fecInicio) || !/^\d{8}$/.test(r.fecFin)) {
                hallazgos.push(hallazgo('error', 'Periodo ilegible', r,
                    'FEC_INICIO / FEC_FIN deben venir como AAAAMMDD; trae «' + r.fecInicio + '» / «' + r.fecFin + '».'));
            }

            const q = /^(\d{1,2})_(\d{4})/.exec(r.qna);
            const clave = q ? Number(q[1]) + '/' + q[2] : r.qna + '/' + r.anio;

            quincenas.set(clave, (quincenas.get(clave) || 0) + 1);
            registros.push(r);
        }

        if (!registros.length) {
            throw new Error('La hoja «' + hoja.nombre + '» no trae ningún renglón de nómina.');
        }

        // Un archivo, una quincena. Si se mezclan, el prefijo del producto y
        // la fecha de pago no tendrían de dónde salir.
        if (quincenas.size > 1) {
            throw new Error('La hoja mezcla quincenas (' + Array.from(quincenas.keys()).join(', ')
                + '). Carga una sola quincena por corrida.');
        }

        const [qq, aaaa] = Array.from(quincenas.keys())[0].split('/');
        const quincena = Number(qq);
        const anio = Number(aaaa) || Number(registros[0].anio);

        if (!(quincena >= 1 && quincena <= 24) || !(anio > 2000)) {
            throw new Error('No se pudo leer la quincena de la columna QNA («' + registros[0].qna + '»).');
        }

        const descNomina = Array.from(new Set(registros.map(r => r.descNomina)));

        if (descNomina.length > 1) {
            hallazgos.push({
                severidad: 'aviso', tipo: 'Tipo de nómina mezclado', renglon: '', rfc: '', nombre: '',
                mensaje: 'La primera columna TIPO DE NOMINA trae varios valores (' + descNomina.join(', ')
                    + '); el encabezado del archivo lleva «' + descNomina[0] + '».'
            });
        }

        return {
            hoja: hoja.nombre,
            quincena,
            anio,
            descNomina: descNomina[0] || 'ORDINARIA',
            registros,
            hallazgos
        };
    }

    /**
     * Lee uno o varios DETALLE EMPLEADOS de la quincena anterior.
     *
     * De ahí salen dos cosas que la nómina no tiene: los nombres tal como se
     * han venido corrigiendo a mano en el catálogo (34 diferencias en la q17,
     * como «KARLOS HUMBRETO» → «CARLOS HUMBERTO») y el orden de la cola.
     */
    function leerDetalleAnterior(libros) {
        const porRfc = new Map();
        const columnas = {
            num: { encabezado: 'numero de empleado' },
            paterno: { encabezado: 'apellido paterno' },
            materno: { encabezado: 'apellido materno' },
            nombre: { encabezado: 'nombre (s)' },
            rfc: { encabezado: 'filiacion' }
        };

        libros.forEach(({ libro, archivo }) => {
            let leidos = 0;

            libro.hojas.forEach(h => {
                const u = ubicarColumnas(h.filas, columnas);

                if (!u) {
                    return;
                }

                for (let i = u.renglon + 1; i < h.filas.length; i++) {
                    const fila = h.filas[i] || [];
                    const celda = k => String(fila[u.mapa[k]] === undefined ? '' : fila[u.mapa[k]]).trim();
                    const rfc = celda('rfc').toUpperCase();
                    const num = Number(celda('num'));

                    if (!rfc) {
                        continue;
                    }

                    leidos++;

                    // Un RFC con retroactivos sale varias veces; manda el
                    // número más bajo, que es su lugar en el orden.
                    const previo = porRfc.get(rfc);

                    if (!previo || num < previo.num) {
                        porRfc.set(rfc, {
                            num,
                            paterno: celda('paterno'),
                            materno: celda('materno'),
                            nombre: celda('nombre')
                        });
                    }
                }
            });

            if (!leidos) {
                throw new Error(archivo + ': no trae las columnas de un DETALLE EMPLEADOS '
                    + '(numero de empleado, apellido paterno, filiacion…).');
            }
        });

        return { porRfc };
    }

    /**
     * ¿Qué salida es este libro? 'detalle', 'prod' o null.
     *
     * Lo natural es soltar los cuatro archivos de la quincena anterior juntos,
     * pero solo los detalles traen nombres. El prod_pago se reconoce para
     * decir «no se usa» en vez de tratarlo como un archivo equivocado.
     */
    function clasificarSalida(libro) {
        const detalle = { num: { encabezado: 'numero de empleado' }, rfc: { encabezado: 'filiacion' } };
        const prod = { num: { encabezado: 'num empl.' }, rfc: { encabezado: 'rfc' }, sdi: { encabezado: 'sdi' } };

        if (libro.hojas.some(h => ubicarColumnas(h.filas, detalle))) {
            return 'detalle';
        }

        return libro.hojas.some(h => ubicarColumnas(h.filas, prod)) ? 'prod' : null;
    }

    /* ── Generación ──────────────────────────────────────────────────────── */

    function hallazgo(severidad, tipo, r, mensaje) {
        return { severidad, tipo, renglon: r.renglon, rfc: r.rfc, nombre: r.nombre, mensaje };
    }

    function grupoDe(r) {
        const p = r.descPrograma.toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
        const enCola = PROGRAMAS_COLA.indexOf(p);

        if (enCola >= 0) {
            return { cola: true, indice: 0 };
        }

        const i = ORDEN_PROGRAMAS.indexOf(p);

        return { cola: false, indice: i >= 0 ? i : ORDEN_PROGRAMAS.length, conocido: i >= 0 };
    }

    /**
     * Genera los dos juegos.
     *
     * @param entrada   resultado de leerEntrada
     * @param anterior  resultado de leerDetalleAnterior, o null
     * @param opciones  { fechaPago: 'AAAAMMDD' } — opcional; por omisión, el
     *                  último día de la quincena
     */
    function generar(entrada, anterior, opciones) {
        const o = opciones || {};
        const qq = String(entrada.quincena).padStart(2, '0');
        const aa = String(entrada.anio).slice(-2);
        const fechaPago = o.fechaPago || finDeQuincena(entrada.anio, entrada.quincena);
        const hallazgos = entrada.hallazgos.slice();
        const previos = anterior ? anterior.porRfc : new Map();

        // ── Orden y número de empleado ──
        const conGrupo = entrada.registros.map((r, i) => ({ r, i, g: grupoDe(r) }));
        const programasRaros = new Set();

        conGrupo.forEach(x => {
            if (!x.g.cola && !x.g.conocido) {
                programasRaros.add(x.r.descPrograma);
            }
        });

        programasRaros.forEach(p => hallazgos.push({
            severidad: 'aviso', tipo: 'Programa nuevo', renglon: '', rfc: '', nombre: '',
            mensaje: '«' + p + '» no es un programa conocido. Va en eventuales y se numera después de los '
                + 'conocidos, antes de la cola; confirma que así corresponde.'
        }));

        const principal = conGrupo.filter(x => !x.g.cola).sort((a, b) => a.g.indice - b.g.indice || a.i - b.i);
        const lugarPrevio = x => {
            const p = previos.get(x.r.rfc);

            return p ? p.num : Infinity;
        };
        const cola = conGrupo.filter(x => x.g.cola).sort((a, b) => {
            const pa = lugarPrevio(a);
            const pb = lugarPrevio(b);

            return pa === pb ? a.i - b.i : pa < pb ? -1 : 1;
        });

        if (cola.length && !previos.size) {
            hallazgos.push({
                severidad: 'aviso', tipo: 'Orden de la cola', renglon: '', rfc: '', nombre: '',
                mensaje: 'Sin el detalle anterior, CUOTAS, REGULACIÓN SANITARIA y GUARDIAS se numeran en el '
                    + 'orden de la entrada, que puede no ser el de siempre.'
            });
        }

        const ordenados = principal.concat(cola).map((x, k) => Object.assign({ num: k + 1 }, x.r));

        // ── Renglones por juego ──
        const juegos = {};

        Object.keys(JUEGOS).forEach(k => {
            juegos[k] = Object.assign({}, JUEGOS[k], { registros: [] });
        });

        ordenados.forEach(r => {
            const esSanas = normalizar(r.descPrograma) === 'sanas';

            juegos[esSanas ? 'SA' : 'EV'].registros.push(r);
        });

        let nombresDeAnterior = 0;
        const nombresArmados = [];
        const altas = [];

        Object.keys(juegos).forEach(k => {
            const j = juegos[k];
            const prefijo = j.clave + qq + aa;
            const totales = { percepciones: 0, deducciones: 0, netoEntrada: 0, porClave: {} };

            j.prod = [];
            j.detalle = [];
            j.retroactivos = [];

            j.registros.forEach(r => {
                // Importes → casillas. Un importe sin clave para este juego
                // detiene el lote: no se inventa una clave que nadie ha visto.
                const casillas = { P: [], D: [] };
                let percepciones = 0;
                let deducciones = 0;

                CONCEPTOS.forEach(c => {
                    const monto = r.importes[c.campo];

                    if (!monto) {
                        return;
                    }

                    const regla = c[k];

                    if (!regla) {
                        hallazgos.push(hallazgo('error', 'Concepto sin clave', r,
                            'Trae ' + c.etiqueta + ' = ' + monto.toFixed(2) + ' y ese concepto no tiene clave '
                            + 'conocida para ' + j.etiqueta + '. Hay que darla de alta antes de generar.'));

                        return;
                    }

                    casillas[c.tipo][regla.casilla] = { clave: regla.clave, monto };

                    if (c.tipo === 'P') {
                        percepciones += monto;
                    } else {
                        deducciones += monto;
                    }

                    const t = totales.porClave[regla.clave] || (totales.porClave[regla.clave] = {
                        clave: regla.clave, concepto: c.etiqueta, tipo: c.tipo, registros: 0, importe: 0
                    });

                    t.registros++;
                    t.importe += monto;
                });

                const neto = centavos(percepciones - deducciones);

                if (Math.abs(neto - r.neto) > 0.009) {
                    hallazgos.push(hallazgo('aviso', 'Neto no cuadra', r,
                        'Percepciones menos deducciones dan ' + neto.toFixed(2) + ' y la columna NETO dice '
                        + r.neto.toFixed(2) + '.'));
                }

                totales.percepciones += percepciones;
                totales.deducciones += deducciones;
                totales.netoEntrada += r.neto;

                if (r.tipoNomina === '22') {
                    j.retroactivos.push(r);
                }

                // ── prod_pago ──
                const fila = new Array(COL_FIN + 1).fill(null);

                fila.splice(0, 31, prefijo, r.num, '0', '0', r.descPrograma, r.rfc, r.cr, r.codigo, 'CON',
                    j.contrato, '0', 'DIURNA', r.fecInicio, r.fecFin, '0', '0', r.importes.c07,
                    '0', '0', '0', '0', '0', '0', '0', '0', '0', 0, 0, 0, 'NO', null);

                let trailers = 0;

                ['P', 'D'].forEach(tipo => {
                    const base = tipo === 'P' ? COL_PERCEPCIONES : COL_DEDUCCIONES;

                    casillas[tipo].forEach((c, i) => {
                        if (c) {
                            fila[base + 3 * i] = c.clave;
                            fila[base + 3 * i + 1] = c.monto;
                            trailers++;
                        }
                    });
                });

                const n = FILA_DATOS + j.prod.length;

                fila[30] = { f: 'COUNT(AF' + n + ':DK' + n + ')', v: trailers };
                j.prod.push(fila);

                // ── detalle ──
                let partes = previos.get(r.rfc);

                if (partes) {
                    nombresDeAnterior++;
                } else {
                    const p = partirNombre(r.nombre);

                    if (!p) {
                        hallazgos.push(hallazgo('aviso', 'Nombre sin formato', r,
                            'No viene como PATERNO,MATERNO/NOMBRE; va completo en «nombre (s)». Corrígelo en el detalle.'));
                    } else if (p.irregular) {
                        hallazgos.push(hallazgo('aviso', 'Nombre sin formato', r,
                            'Viene como PATERNO,MATERNO,NOMBRE (coma en vez de diagonal); se separó así.'));
                    }

                    partes = p
                        ? { paterno: limpiarNombre(p.paterno), materno: limpiarNombre(p.materno), nombre: limpiarNombre(p.nombre) }
                        : { paterno: '0', materno: '0', nombre: limpiarNombre(r.nombre) };

                    // Con retroactivos la persona trae varios renglones; se lista una vez.
                    if (!nombresArmados.some(a => a.rfc === r.rfc)) {
                        nombresArmados.push({ rfc: r.rfc, original: r.nombre, juego: j.etiqueta,
                            paterno: partes.paterno, materno: partes.materno, nombre: partes.nombre });
                    }
                }

                if (previos.size && !previos.has(r.rfc) && !altas.some(a => a.rfc === r.rfc)) {
                    altas.push({ rfc: r.rfc, nombre: r.nombre, juego: j.etiqueta, programa: r.descPrograma,
                        subprograma: r.descSubprograma, fingreso: r.fingreso });
                }

                j.detalle.push([r.num, partes.paterno, partes.materno, partes.nombre, r.rfc, r.curp, '0',
                    r.fingreso, '0', r.rama]);
            });

            Object.keys(totales.porClave).forEach(c => {
                totales.porClave[c].importe = centavos(totales.porClave[c].importe);
            });

            j.totales = {
                registros: j.registros.length,
                personas: new Set(j.registros.map(r => r.rfc)).size,
                percepciones: centavos(totales.percepciones),
                deducciones: centavos(totales.deducciones),
                neto: centavos(totales.percepciones - totales.deducciones),
                netoEntrada: centavos(totales.netoEntrada),
                porClave: Object.values(totales.porClave)
            };
            j.prefijo = prefijo;
            j.archivos = nombresArchivos(j, qq, entrada.anio);
            j.filasProd = filasProdPago(j, entrada, fechaPago);
            j.filasDetalle = [ENCABEZADO_DETALLE].concat(j.detalle);
        });

        // ── Bajas: estaban en el detalle anterior y ya no vienen ──
        const actuales = new Set(entrada.registros.map(r => r.rfc));
        const bajas = [];

        previos.forEach((p, rfc) => {
            if (!actuales.has(rfc)) {
                bajas.push({ rfc, nombre: [p.paterno, p.materno].join(' ') + ' / ' + p.nombre, num: p.num });
            }
        });

        bajas.sort((a, b) => a.num - b.num);

        return {
            quincena: entrada.quincena,
            anio: entrada.anio,
            fechaPago,
            descNomina: entrada.descNomina,
            hoja: entrada.hoja,
            juegos,
            hallazgos,
            errores: hallazgos.filter(h => h.severidad === 'error').length,
            conAnterior: previos.size > 0,
            nombresDeAnterior,
            nombresArmados,
            altas,
            bajas
        };
    }

    /**
     * Las tres filas de arriba del prod_pago más los datos.
     *
     *   Fila 1: rótulos; en AH1 la suma de percepciones y en BL1 la de
     *           deducciones.
     *   Fila 2: producto, año, quincena, fecha de pago, registros, neto
     *           (=AH1-BL1) y tipo de nómina; encima de cada «Importe exento»,
     *           el subtotal de su «Importe gravado».
     *   Fila 3: encabezado de columnas.
     *
     * Es la misma disposición de las plantillas, con las fórmulas escritas
     * sobre su rango exacto: las de antes sumaban rangos corridos que solo
     * daban bien porque esas columnas siempre van vacías.
     */
    function filasProdPago(j, entrada, fechaPago) {
        const L = EV.xlsx.letraColumna;
        const ultima = FILA_DATOS + j.prod.length - 1;
        const fila1 = new Array(COL_FIN).fill(null);
        const fila2 = new Array(COL_FIN).fill(null);
        const suma = col => centavos(j.prod.reduce((s, f) => s + (typeof f[col] === 'number' ? f[col] : 0), 0));
        const subtotales = { P: 0, D: 0 };

        ['P', 'D'].forEach(tipo => {
            const base = tipo === 'P' ? COL_PERCEPCIONES : COL_DEDUCCIONES;
            const casillas = tipo === 'P' ? CASILLAS_PERCEPCION : CASILLAS_DEDUCCION;

            for (let i = 0; i < casillas; i++) {
                const gravado = base + 3 * i + 1;
                const v = suma(gravado);

                fila2[gravado + 1] = j.prod.length
                    ? { f: 'SUM(' + L(gravado) + FILA_DATOS + ':' + L(gravado) + ultima + ')', v }
                    : 0;
                subtotales[tipo] += v;
            }
        });

        const colP = COL_PERCEPCIONES + 2; // AH
        const colD = COL_DEDUCCIONES + 2; // BL

        subtotales.P = centavos(subtotales.P);
        subtotales.D = centavos(subtotales.D);

        ['PROD_PAGO', 'AÑO', 'QNA', 'FECHA DE PAGO', 'No. Reg.', 'totales netos', 'desc_nomina']
            .forEach((t, i) => { fila1[i] = t; });
        fila1[colP] = { f: 'SUM(' + L(colP) + '2:' + L(COL_DEDUCCIONES - 1) + '2)', v: subtotales.P };
        fila1[colD] = { f: 'SUM(' + L(colD) + '2:' + L(COL_FIN - 1) + '2)', v: subtotales.D };

        [j.prefijo, entrada.anio, String(entrada.quincena).padStart(2, '0'), fechaPago, j.prod.length,
            { f: L(colP) + '1-' + L(colD) + '1', v: centavos(subtotales.P - subtotales.D) }, entrada.descNomina]
            .forEach((v, i) => { fila2[i] = v; });

        const encabezado = ENCABEZADO_PROD.slice();

        encabezado[0] = j.primeraCelda;

        return [fila1, fila2, encabezado].concat(j.prod);
    }

    /**
     * Los nombres de siempre, sin los errores de dedo que traían («20226»,
     * «(1)»). El «1807» se repite en todas las quincenas y nadie sabe qué es,
     * así que se conserva tal cual.
     */
    function nombresArchivos(j, qq, anio) {
        const s = j.sufijo === 'S' ? 'S ' + anio : 'E';

        return {
            prod: 'detalle prod_pago con 1807 FED ' + qq + s + '.xlsx',
            detalle: 'DETALLE EMPLEADOS CON 1807 FED' + qq + s + '.xlsx'
        };
    }

    EV.nomina = {
        leerEntrada,
        leerDetalleAnterior,
        clasificarSalida,
        generar,
        finDeQuincena,
        limpiarNombre,
        partirNombre,
        CONCEPTOS,
        ENCABEZADO_PROD,
        ENCABEZADO_DETALLE
    };
})(window.EV = window.EV || {});
