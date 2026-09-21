/**
 * Validaciones del formato TG-7.
 *
 * Criterio de diseño: NUNCA abortar el proceso completo. Con 10 000+ registros,
 * detener todo por un dato malo es inservible. Cada registro se valida por
 * separado y los hallazgos se acumulan en una bitácora; el operador corrige el
 * origen y reprocesa. Solo los registros con ERROR quedan fuera del archivo;
 * los avisos se entregan.
 *
 * Las validaciones contra catálogo (aportante válido, ramo + pagaduría,
 * préstamo vigente, salario entre 1 salario mínimo y 10 UMAs) NO están aquí:
 * requieren datos que solo tiene SERICA. Ver README.md.
 */
(function (TG7) {
    'use strict';

    const F = TG7.formato;

    const RE_RFC = /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/;

    /**
     * CURP. Las 32 claves de entidad de RENAPO más `NE` (nacido en el
     * extranjero), en orden alfabético y sin repetir.
     *
     * Ojo con `NT`: es NAYARIT, no un error de dedo. Faltaba en una versión
     * anterior de esta expresión y rechazaba como «estructura inválida» a
     * todos los nacidos en Nayarit — 3 registros en la quincena 18, gente con
     * retención real que se quedaba fuera del archivo sin motivo.
     */
    const ENTIDADES_CURP = 'AS|BC|BS|CC|CH|CL|CM|CS|DF|DG|GR|GT|HG|JC|MC|MN|MS|NE|NL|NT|OC|PL|QR|QT|SL|SP|SR|TC|TL|TS|VZ|YN|ZS';
    const RE_CURP = new RegExp(
        '^[A-Z][AEIOUX][A-Z]{2}\\d{6}[HM](?:' + ENTIDADES_CURP + ')[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\\d$'
    );
    const RE_IMPORTE = /^\d+\.\d{2}$/;
    const RE_PERIODO = /^\d{6}$/;

    /** Longitudes máximas del layout TG-7 (campo → caracteres). */
    const LONGITUDES = {
        pagaduria: 5,
        numeroIssste: 8,
        numeroPrestamo: 11,
        nombres: 60,
        apellidoPaterno: 30,
        apellidoMaterno: 30,
        rfc: 13,
        curp: 18,
        nss: 11,
        clabe: 18,
        tipoNombramiento: 2
    };

    /**
     * Campos que el validador deja pasar vacíos.
     *
     * `apellidoMaterno` y `nss` lo son en el propio formato.
     *
     * `clabe` está aquí porque el layout oficial deja su columna de validación
     * en blanco, a diferencia de todos los demás campos obligatorios. Ver
     * CAMPOS_SIN_FUENTE en formato.js.
     *
     * `numeroPrestamo` NO es opcional en el formato: es obligatorio y SERICA
     * valida que la combinación número de ISSSTE + préstamo exista en su tabla.
     * Está aquí por una decisión explícita de operación: cuando no hay orden de
     * descuento cargada para un trabajador que sí trae retención, se prefiere
     * emitir la línea sin el número y que SERICA la rebote, en vez de callarla.
     * El cruce levanta un aviso por cada caso y la pantalla los lista aparte
     * para poder reclamar las altas faltantes a ISSSTE.
     */
    const OPCIONALES = new Set(['apellidoMaterno', 'nss', 'clabe', 'numeroPrestamo']);

    /** Valida el periodo AAAAPP contra la periodicidad. */
    function periodoValido(periodo, periodicidad) {
        if (!RE_PERIODO.test(periodo)) {
            return false;
        }

        const anio = Number(periodo.slice(0, 4));
        const pp = Number(periodo.slice(4));
        const max = periodicidad === 'M' ? 12 : 24;

        return anio >= 1900 && anio <= 2100 && pp >= 1 && pp <= max;
    }

    /**
     * CLABE: 18 dígitos con dígito verificador módulo 10 ponderado 3,7,1.
     * Es la única validación estructural que atrapa un dígito mal capturado.
     */
    function clabeValida(clabe) {
        if (!/^\d{18}$/.test(clabe)) {
            return false;
        }

        const pesos = [3, 7, 1];
        let suma = 0;

        for (let i = 0; i < 17; i++) {
            suma += (Number(clabe[i]) * pesos[i % 3]) % 10;
        }

        return (10 - (suma % 10)) % 10 === Number(clabe[17]);
    }

    /**
     * Dígito verificador del NSS (algoritmo de Luhn sobre los 11 dígitos).
     *
     * En los datos reales de la quincena 18 solo cuadra en el 64.4 % de los NSS
     * poblados. El campo SÍ es real —su año de nacimiento coincide con el de la
     * CURP en 99.4 %—, así que un Luhn fallido se reporta como AVISO y no
     * excluye el registro. Sirve para saber cuántos podría rebotar SERICA.
     */
    function nssLuhnValido(nss) {
        if (!/^\d{11}$/.test(nss)) {
            return false;
        }

        let suma = 0;
        let alterno = false;

        for (let i = nss.length - 1; i >= 0; i--) {
            let d = Number(nss[i]);

            if (alterno) {
                d *= 2;
                if (d > 9) {
                    d -= 9;
                }
            }

            suma += d;
            alterno = !alterno;
        }

        return suma % 10 === 0;
    }

    function validarEncabezado(e) {
        const h = [];
        const err = (campo, mensaje, valor) =>
            h.push({ linea: 1, campo, severidad: 'error', mensaje, valor });

        if (['1', '2', '3'].indexOf(e.tipoNomina) === -1) {
            err('tipoNomina', 'Debe ser 1 (ordinaria), 2 (extraordinaria) o 3 (cancelación)', e.tipoNomina);
        }

        if (!Number.isInteger(e.version) || e.version < 1 || e.version > 99) {
            err('version', 'Debe ser un entero entre 1 y 99', String(e.version));
        }

        if (e.periodicidad !== 'Q' && e.periodicidad !== 'M') {
            err('periodicidad', 'Debe ser Q (quincenal) o M (mensual)', e.periodicidad);
        }

        if (!periodoValido(e.periodo, e.periodicidad)) {
            err('periodo', 'Formato AAAAPP inválido para periodicidad ' + e.periodicidad, e.periodo);
        }

        if (!/^\d{3}$/.test(e.organismo)) {
            err('organismo', 'Debe ser de 3 dígitos', e.organismo);
        }

        if (!/^\d{2}$/.test(e.entidad)) {
            err('entidad', 'Debe ser de 2 dígitos', e.entidad);
        }

        if (!/^\d{3}$/.test(e.municipio)) {
            err('municipio', 'Debe ser de 3 dígitos', e.municipio);
        }

        if (!/^\d{1,3}$/.test(String(e.ramoCredito))) {
            err('ramoCredito', 'Debe ser numérico de hasta 3 dígitos (se rellena con ceros)', String(e.ramoCredito));
        }

        return h;
    }

    /**
     * Valida un registro de detalle. `linea` es su posición en el archivo final
     * (el encabezado ocupa la 1, así que el primer detalle es la 2).
     *
     * `motivos` son los hallazgos que el cruce ya detectó para este registro
     * (tipo de nombramiento sin equivalencia, por ejemplo); llegan aquí para
     * que la bitácora salga en un solo lugar y con la línea del archivo final.
     */
    function validarRegistro(r, linea, e, motivos) {
        const h = [];
        const add = (campo, severidad, mensaje, valor) =>
            h.push({ linea, campo, severidad, mensaje, valor });
        const err = (campo, mensaje, valor) => add(campo, 'error', mensaje, valor);

        // Lo que el cruce ya sabía de este registro. Cuando el cruce ya explicó
        // por qué un campo quedó vacío —de qué clave del SIPE no se pudo
        // traducir, por ejemplo— ese campo no se vuelve a reportar abajo como
        // «obligatorio vacío»: sería el mismo problema contado tres veces y el
        // motivo genérico taparía al específico.
        const yaExplicados = new Set();

        (motivos || []).forEach(m => {
            add(m.campo, m.severidad, m.mensaje, m.valor);

            if (m.severidad === 'error') {
                yaExplicados.add(m.campo);
            }
        });

        // Campos sin origen localizado: se reportan SIEMPRE y con el motivo
        // concreto, para que ningún archivo salga aparentando estar completo.
        Object.keys(F.CAMPOS_SIN_FUENTE).forEach(campo => {
            const d = F.CAMPOS_SIN_FUENTE[campo];
            add(campo, d.severidad, d.motivo, String(r[campo] === undefined ? '' : r[campo]));
        });

        // Campos que el layout de origen simplemente no tiene: avisan, no bloquean.
        Object.keys(F.CAMPOS_SIN_CAMPO_EN_ORIGEN).forEach(campo => {
            add(campo, 'aviso', F.CAMPOS_SIN_CAMPO_EN_ORIGEN[campo], String(r[campo] === undefined ? '' : r[campo]));
        });

        // Obligatoriedad y longitud máxima. Los campos sin fuente ya se
        // reportaron arriba con su motivo real; repetirlos como «obligatorio
        // vacío» solo ensucia la bitácora.
        F.CAMPOS_DETALLE.forEach(campo => {
            if (Object.prototype.hasOwnProperty.call(F.CAMPOS_SIN_FUENTE, campo) || yaExplicados.has(campo)) {
                return;
            }

            const valor = String(r[campo] === undefined || r[campo] === null ? '' : r[campo]);

            if (valor === '' && !OPCIONALES.has(campo)) {
                err(campo, 'Campo obligatorio vacío');

                return;
            }

            const max = LONGITUDES[campo];

            if (max && valor.length > max) {
                err(campo, 'Excede la longitud máxima de ' + max + ' caracteres', valor);
            }
        });

        // Formato de los 5 importes: punto y exactamente 2 decimales.
        F.CAMPOS_IMPORTE.forEach(campo => {
            if (!RE_IMPORTE.test(F.formatearImporte(r[campo]))) {
                err(campo, 'No se pudo formatear como importe con 2 decimales', String(r[campo]));
            }
        });

        if (r.rfc && !RE_RFC.test(r.rfc)) {
            err('rfc', 'Estructura de RFC inválida', r.rfc);
        }

        if (r.curp && !RE_CURP.test(r.curp)) {
            err('curp', 'Estructura de CURP inválida', r.curp);
        }

        if (r.nss && !/^\d{11}$/.test(r.nss)) {
            err('nss', 'Si se reporta, debe tener 11 dígitos', r.nss);
        } else if (r.nss && !nssLuhnValido(r.nss)) {
            add('nss', 'aviso', 'El dígito verificador del NSS no cuadra; SERICA podría rechazarlo', r.nss);
        }

        if (['1', '5', '9'].indexOf(r.tipoPago) === -1) {
            err('tipoPago', 'Debe ser 1 (nómina), 5 (indemnización) o 9 (retiro/finiquito)', r.tipoPago);
        }

        // El catálogo es obligatorio. Si viene vacío y el cruce no dijo por qué
        // (porque se validó un lote armado a mano, sin `motivos`), se reporta
        // aquí para que no pase inadvertido.
        if (r.tipoNombramiento === '') {
            if (!yaExplicados.has('tipoNombramiento')) {
                err('tipoNombramiento', 'Campo obligatorio vacío: no se pudo traducir el tipo de nombramiento del SIPE');
            }
        } else if (!Object.prototype.hasOwnProperty.call(F.TIPOS_NOMBRAMIENTO, r.tipoNombramiento)) {
            err('tipoNombramiento', 'No existe en el catálogo de tipos de nombramiento', r.tipoNombramiento);
        }

        if (r.clabe && !/^\d{18}$/.test(r.clabe)) {
            err('clabe', 'Si se reporta, debe tener exactamente 18 dígitos', r.clabe);
        } else if (r.clabe && !clabeValida(r.clabe)) {
            add('clabe', 'aviso', 'El dígito verificador de la CLABE no cuadra', r.clabe);
        }

        // Importes.
        const descuento = Number(r.descuentoPrestamo);

        if (!Number.isFinite(descuento) || descuento <= 0) {
            err('descuentoPrestamo', 'Debe ser mayor a 0.00; sin retención efectiva el registro no va en el TG-7', String(r.descuentoPrestamo));
        }

        const salario = Number(r.salarioBase);

        if (!Number.isFinite(salario) || salario <= 0) {
            err('salarioBase', 'Debe ser mayor a 0.00', String(r.salarioBase));
        }

        // Periodos. El formato es explícito: «Para nómina ordinaria, el periodo
        // debe de coincidir con el del encabezado. Nunca un periodo posterior.
        // Para nómina extraordinaria y cancelaciones puede ser igual al del
        // encabezado o anterior, nunca posterior.»
        if (!periodoValido(r.periodoDesde, e.periodicidad)) {
            err('periodoDesde', 'Formato AAAAPP inválido', r.periodoDesde);
        }

        if (!periodoValido(r.periodoHasta, e.periodicidad)) {
            err('periodoHasta', 'Formato AAAAPP inválido', r.periodoHasta);
        }

        if (RE_PERIODO.test(r.periodoDesde) && RE_PERIODO.test(r.periodoHasta)) {
            if (r.periodoDesde > r.periodoHasta) {
                err('periodoDesde', '«desde» no puede ser posterior a «hasta»', r.periodoDesde + ' > ' + r.periodoHasta);
            }

            if (RE_PERIODO.test(e.periodo)) {
                if (r.periodoDesde > e.periodo) {
                    err('periodoDesde', 'Nunca puede ser posterior al periodo del encabezado (' + e.periodo + ')', r.periodoDesde);
                }

                if (r.periodoHasta > e.periodo) {
                    err('periodoHasta', 'Nunca puede ser posterior al periodo del encabezado (' + e.periodo + ')', r.periodoHasta);
                }

                if (e.tipoNomina === '1' && (r.periodoDesde !== e.periodo || r.periodoHasta !== e.periodo)) {
                    err('periodoDesde', 'En nómina ordinaria ambos periodos deben coincidir con el del encabezado (' + e.periodo + ')',
                        r.periodoDesde + '–' + r.periodoHasta);
                }
            }
        }

        return h;
    }

    /** Valida el lote completo separando lo emitible de lo que hay que corregir. */
    function validarLote(e, registros, motivosPorRegistro) {
        const hallazgos = validarEncabezado(e);
        const encabezadoInvalido = hallazgos.length > 0;
        const validos = [];
        const rechazados = [];

        registros.forEach((registro, i) => {
            const linea = i + 2; // +1 por índice base 0, +1 por el encabezado
            const propios = validarRegistro(registro, linea, e, (motivosPorRegistro || [])[i]);

            hallazgos.push.apply(hallazgos, propios);

            const errores = propios.filter(x => x.severidad === 'error');

            if (errores.length > 0) {
                rechazados.push({ registro, linea, errores });
            } else {
                validos.push(registro);
            }
        });

        return { hallazgos, validos, rechazados, encabezadoInvalido };
    }

    /** Bitácora en CSV, para que el operador corrija el origen. */
    function bitacoraCSV(hallazgos) {
        const filas = [['linea', 'campo', 'severidad', 'mensaje', 'valor'].join(',')];

        hallazgos.forEach(h => {
            const celdas = [String(h.linea), h.campo, h.severidad, h.mensaje, h.valor === undefined ? '' : h.valor];

            // Una celda que empieza con = + - @ la interpreta Excel como fórmula.
            // Se le antepone un apóstrofe para que se lea como texto.
            filas.push(celdas.map(c => {
                const s = String(c);
                const seguro = /^[=+\-@]/.test(s) ? "'" + s : s;

                return '"' + seguro.replace(/"/g, '""') + '"';
            }).join(','));
        });

        return filas.join('\r\n');
    }

    TG7.validaciones = {
        RE_RFC,
        RE_CURP,
        ENTIDADES_CURP,
        RE_IMPORTE,
        RE_PERIODO,
        LONGITUDES,
        OPCIONALES,
        periodoValido,
        clabeValida,
        nssLuhnValido,
        validarEncabezado,
        validarRegistro,
        validarLote,
        bitacoraCSV
    };
})(window.TG7 = window.TG7 || {});
