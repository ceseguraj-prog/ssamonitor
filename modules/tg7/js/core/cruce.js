/**
 * Cruce de las fuentes para armar el TG-7.
 *
 * QUIÉN VA EN EL ARCHIVO
 * El formato lo define sin ambigüedad: «el archivo a declarar solo deberá de
 * contener registros de trabajadores o pensionados a los que se le retuvo el
 * concepto de prestamo personal». Ese dato está en la NÓMINA, en el campo
 * P.C.P. (posiciones 164-169): si trae importe, hubo retención efectiva y el
 * trabajador va en el TG-7.
 *
 * Por eso el universo es la nómina, y las órdenes de descuento son un
 * complemento, no un filtro. Es la corrección de fondo respecto de la primera
 * versión: las órdenes NO son el padrón de préstamos vigentes.
 *
 * QUÉ SON LAS ÓRDENES
 * ISSSTE manda cada quincena solo las ALTAS de esa quincena, y el préstamo se
 * sigue descontando hasta su `periodoHasta`. Medido: de las 28 órdenes de la
 * quincena 13, 27 seguían retenidas en la quincena 18 con el importe idéntico;
 * de las 59 de la quincena 19, apenas 6 aparecían en la 18 y ninguna con el
 * mismo importe (esos 6 tenían otro crédito anterior).
 *
 * Consecuencia práctica: para resolver el número de préstamo hay que cargar
 * TODAS las órdenes acumuladas, de todas las quincenas, no solo las de la
 * quincena que se declara. El número de préstamo no está en ningún otro lado:
 * se buscó cada uno de los 27 préstamos conocidos dentro de la línea completa
 * de 260 caracteres de su trabajador y no aparece en ninguno.
 */
(function (TG7) {
    'use strict';

    const AF = TG7.anchoFijo;
    const L = TG7.layouts;
    const N = TG7.nombres;

    /**
     * Identificador tal como lo quiere el TG-7: sin ceros a la izquierda.
     * Devuelve '' cuando el dato no es numérico, para que la validación lo
     * marque como campo vacío en vez de emitir la cadena "NaN", que pasaría
     * inadvertida por tener 3 caracteres y no estar en blanco.
     */
    function identificador(crudo) {
        const limpio = String(crudo || '').trim();

        if (!/^\d+$/.test(limpio)) {
            return '';
        }

        return String(Number(limpio));
    }

    /**
     * Registro acumulado de órdenes: RFC → lista de préstamos.
     *
     * Se queda solo con los que siguen vigentes en el periodo que se declara
     * (`desde <= periodo <= hasta`). Un préstamo que ya terminó no tiene por
     * qué aparecer, y uno que empieza después tampoco.
     */
    /**
     * Normaliza un archivo de órdenes a una lista plana.
     *
     * Acepta las dos formas en que ISSSTE entrega lo mismo: el `.txt` de ancho
     * fijo (`contenido`) y el reporte `.docx` ya interpretado por
     * reporteOrdenes.js (`ordenes`). Comparados renglón por renglón coinciden
     * en 58 de 58, así que de aquí para abajo da igual de cuál vinieron.
     */
    function normalizar(archivo, incidencias) {
        if (Array.isArray(archivo.ordenes)) {
            return archivo.ordenes.map(o => ({
                rfc: o.rfc,
                numeroPrestamo: identificador(o.numeroPrestamo),
                numeroIssste: identificador(o.numeroIssste),
                importe: o.importe,
                periodoDesde: o.periodoDesde,
                periodoHasta: o.periodoHasta,
                linea: o.linea
            }));
        }

        const lectura = AF.leerAnchoFijo(archivo.contenido, L.ORDENES_180);

        lectura.errores.forEach(e => {
            incidencias.push({ origen: archivo.nombre, linea: e.linea, rfc: '', mensaje: e.mensaje });
        });

        return lectura.registros.map(r => ({
            rfc: AF.texto(r, 'rfc'),
            numeroPrestamo: identificador(AF.texto(r, 'numeroPrestamo')),
            numeroIssste: identificador(AF.texto(r, 'numeroIssste')),
            importe: AF.numero(r, 'importeDescuento'),
            periodoDesde: AF.texto(r, 'periodoDesde'),
            periodoHasta: AF.texto(r, 'periodoHasta'),
            linea: r.linea
        }));
    }

    function registroDeOrdenes(archivos, periodo, incidencias) {
        const porRfc = new Map();
        const vistos = new Set();
        let total = 0;
        let vigentes = 0;
        let duplicadas = 0;

        archivos.forEach(archivo => {
            normalizar(archivo, incidencias).forEach(r => {
                total++;

                const rfc = r.rfc;
                const prestamo = r.numeroPrestamo;
                const clave = rfc + '|' + prestamo;

                // El mismo préstamo puede venir repetido si se cargan archivos
                // de quincenas que se traslapan. No es un error, pero contarlo
                // dos veces sí lo sería.
                if (vistos.has(clave)) {
                    duplicadas++;

                    return;
                }

                vistos.add(clave);

                const desde = L.aPeriodoTG7(r.periodoDesde);
                const hasta = L.aPeriodoTG7(r.periodoHasta);

                if (desde && hasta && (periodo < desde || periodo > hasta)) {
                    return;
                }

                vigentes++;

                if (!porRfc.has(rfc)) {
                    porRfc.set(rfc, []);
                }

                porRfc.get(rfc).push({
                    numeroPrestamo: prestamo,
                    numeroIssste: r.numeroIssste,
                    importe: r.importe,
                    desde,
                    hasta,
                    origen: archivo.nombre,
                    linea: r.linea
                });
            });
        });

        return { porRfc, total, vigentes, duplicadas };
    }

    /**
     * Une la nómina con el registro de órdenes y produce los registros TG-7.
     *
     * Devuelve, además, los `motivos` de cada registro: hallazgos que solo se
     * pueden ver aquí y que `validaciones.js` incorpora a la bitácora con la
     * línea del archivo final.
     */
    function cruzar(archivosNomina, archivosOrdenes, encabezado) {
        const incidencias = [];
        const registro = registroDeOrdenes(archivosOrdenes || [], encabezado.periodo, incidencias);

        const registros = [];
        const motivos = [];
        const sinPrestamo = [];

        let totalNomina = 0;
        let conRetencion = 0;
        let rfcRepetidoEnNomina = 0;
        let nombresARevisar = 0;
        let eniesPerdidas = 0;
        let sinTipoNombramiento = 0;
        let numeroIsssteDiscrepante = 0;
        let importeDiscrepante = 0;
        let conPrestamo = 0;

        const vistos = new Set();

        archivosNomina.forEach(archivo => {
            const lectura = AF.leerAnchoFijo(archivo.contenido, L.NOMINA_260);

            lectura.errores.forEach(e => {
                incidencias.push({ origen: archivo.nombre, linea: e.linea, rfc: '', mensaje: e.mensaje });
            });

            lectura.registros.forEach(fila => {
                totalNomina++;

                const rfc = AF.texto(fila, 'rfc');
                const pcp = AF.numero(fila, 'descuentoPrestamo');

                // Sin retención efectiva no va en el archivo. Es el criterio
                // del propio formato, y descarta ~7 de cada 10 registros.
                if (!Number.isFinite(pcp) || pcp <= 0) {
                    return;
                }

                conRetencion++;

                // Un RFC en dos archivos de nómina (dos pagadurías, o .ORD y
                // .RET de la misma quincena) es ambiguo: se conserva el primero
                // y se avisa, en vez de dejar que el último gane en silencio.
                if (vistos.has(rfc)) {
                    rfcRepetidoEnNomina++;
                    incidencias.push({
                        origen: archivo.nombre,
                        linea: fila.linea,
                        rfc,
                        mensaje: 'El RFC ya venía con retención en otro archivo de nómina del lote: se conserva el primero'
                    });

                    return;
                }

                vistos.add(rfc);

                const prestamos = registro.porRfc.get(rfc) || [];

                if (prestamos.length) {
                    conPrestamo++;
                } else {
                    sinPrestamo.push({ rfc, importe: pcp, linea: fila.linea, origen: archivo.nombre });
                }

                // Un trabajador puede tener más de un préstamo vigente, y cada
                // uno es su propio registro TG-7 con su propio importe. El
                // P.C.P. de la nómina es la SUMA, así que solo sirve para
                // cuadrar — y cuando no hay ninguna orden, para emitir una
                // línea con el total.
                const lineas = prestamos.length
                    ? prestamos.map(p => ({
                        numeroPrestamo: p.numeroPrestamo,
                        numeroIssste: p.numeroIssste,
                        importe: p.importe
                    }))
                    : [{ numeroPrestamo: '', numeroIssste: '', importe: pcp }];

                const sumaOrdenes = lineas.reduce((s, x) => s + (Number.isFinite(x.importe) ? x.importe : 0), 0);
                const descuadre = prestamos.length && Math.abs(sumaOrdenes - pcp) >= 0.005;

                if (descuadre) {
                    importeDiscrepante++;
                }

                lineas.forEach(linea => {
                    const salida = armarRegistro(fila, rfc, linea, encabezado);

                    if (!salida.partes.confiable) {
                        nombresARevisar++;
                    }

                    if (salida.enie) {
                        eniesPerdidas++;
                    }

                    if (salida.registro.tipoNombramiento === '') {
                        sinTipoNombramiento++;
                    }

                    if (salida.numeroDiscrepante) {
                        numeroIsssteDiscrepante++;
                    }

                    if (descuadre) {
                        salida.motivos.push({
                            campo: 'descuentoPrestamo',
                            severidad: 'aviso',
                            mensaje: 'La suma de las órdenes vigentes no cuadra con el P.C.P. retenido en la nómina; podría faltar un alta o sobrar un préstamo ya liquidado',
                            valor: 'órdenes ' + sumaOrdenes.toFixed(2) + ' · nómina ' + pcp.toFixed(2)
                        });
                    }

                    registros.push(salida.registro);
                    motivos.push(salida.motivos);
                });
            });
        });

        return {
            registros,
            motivos,
            incidencias,
            sinPrestamo,
            resumen: {
                registrosNomina: totalNomina,
                conRetencion,
                ordenesCargadas: registro.total,
                ordenesVigentes: registro.vigentes,
                ordenesDuplicadas: registro.duplicadas,
                trabajadoresConPrestamo: conPrestamo,
                trabajadoresSinPrestamo: sinPrestamo.length,
                emitidos: registros.length,
                rfcRepetidoEnNomina,
                nombresARevisar,
                eniesPerdidas,
                sinTipoNombramiento,
                numeroIsssteDiscrepante,
                importeDiscrepante
            }
        };
    }

    /** Arma una línea del TG-7 a partir de la fila de nómina y un préstamo. */
    function armarRegistro(fila, rfc, linea, encabezado) {
        const propios = [];
        const nombreCompleto = AF.texto(fila, 'nombreCompleto');
        const curp = AF.texto(fila, 'curp');
        const partes = N.separarNombre(nombreCompleto, curp);

        if (!partes.confiable) {
            propios.push({
                campo: 'nombres',
                severidad: 'aviso',
                mensaje: 'No se pudo separar el nombre con certeza: ' + partes.advertencia,
                valor: nombreCompleto
            });
        }

        const enie = N.detectarEniePerdida(nombreCompleto);

        if (enie) {
            propios.push({
                campo: 'nombres',
                severidad: 'aviso',
                mensaje: 'Posible «Ñ» perdida en el origen: el nombre saldrá incompleto mientras no se corrija la nómina',
                valor: nombreCompleto
            });
        }

        if (linea.numeroPrestamo === '') {
            propios.push({
                campo: 'numeroPrestamo',
                severidad: 'aviso',
                mensaje: 'No hay orden de descuento cargada para este RFC: se emite sin número de préstamo. SERICA lo rechazará; hay que pedir el alta a ISSSTE',
                valor: 'P.C.P. ' + Number(linea.importe).toFixed(2)
            });
        }

        // El número ISSSTE es único y permanente según la spec del SIPE. La
        // nómina lo trae en ceros en 2 de cada 3 registros, así que manda el de
        // la orden cuando existe; si ambos vienen y difieren, hay que saberlo:
        // SERICA valida la combinación número de ISSSTE + préstamo.
        const numeroNomina = identificador(AF.texto(fila, 'numeroIssste'));
        const numeroOrden = linea.numeroIssste || '';
        const numeroIssste = numeroOrden !== '' ? numeroOrden : numeroNomina;
        const numeroDiscrepante = numeroOrden !== '' && numeroNomina !== '' && numeroOrden !== numeroNomina;

        if (numeroDiscrepante) {
            propios.push({
                campo: 'numeroIssste',
                severidad: 'aviso',
                mensaje: 'El número de ISSSTE difiere entre la orden y la nómina; se usa el de la orden',
                valor: 'orden ' + numeroOrden + ' · nómina ' + numeroNomina
            });
        }

        const claveSIPE = AF.texto(fila, 'tipoNombramientoSIPE');
        const nombramiento = L.tipoNombramientoTG7(claveSIPE);

        if (nombramiento.valor === '') {
            propios.push({
                campo: 'tipoNombramiento',
                severidad: 'error',
                mensaje: nombramiento.motivo,
                valor: 'SIPE ' + claveSIPE
            });
        }

        // FOVISSSTE: el I.H. es el descuento de vivienda, pero solo es FOVISSSTE
        // cuando la clave T.H. lo es. Un hipotecario del propio ISSSTE o una
        // renta de multifamiliares no van en ese campo.
        const claveHabitacion = AF.texto(fila, 'claveHabitacion');
        const importeHabitacion = AF.numero(fila, 'importeHabitacion');
        const tipoHabitacion = L.CLAVES_HABITACION[claveHabitacion];
        let descuentoFovissste = 0;

        if (Number.isFinite(importeHabitacion) && importeHabitacion > 0) {
            if (tipoHabitacion && tipoHabitacion.fovissste) {
                descuentoFovissste = importeHabitacion;
            } else {
                propios.push({
                    campo: 'descuentoFovissste',
                    severidad: 'aviso',
                    mensaje: 'Hay descuento de vivienda con clave T.H. «' + claveHabitacion + '» ('
                        + (tipoHabitacion ? tipoHabitacion.nombre : 'clave no catalogada')
                        + '), que no es FOVISSSTE: se emite 0.00 en ese campo',
                    valor: String(importeHabitacion)
                });
            }
        }

        // Otras deducciones: OTROS (crédito adicional) + A.S.M. (responsiva de
        // servicio médico). Un campo ilegible no se convierte en 0.
        const creditoAdicional = AF.numero(fila, 'creditoAdicional');
        const servicioMedico = AF.numero(fila, 'descuentoServicioMedico');
        let otrasDeducciones = 0;

        if (Number.isFinite(creditoAdicional) && Number.isFinite(servicioMedico)) {
            otrasDeducciones = creditoAdicional + servicioMedico;
        } else {
            propios.push({
                campo: 'otrasDeducciones',
                severidad: 'error',
                mensaje: 'OTROS o A.S.M. no son numéricos en la nómina: el archivo podría venir desalineado',
                valor: AF.texto(fila, 'creditoAdicional') + ' · ' + AF.texto(fila, 'descuentoServicioMedico')
            });
        }

        return {
            partes,
            enie,
            numeroDiscrepante,
            motivos: propios,
            registro: {
                pagaduria: AF.texto(fila, 'pagaduria'),
                numeroIssste,
                numeroPrestamo: linea.numeroPrestamo,
                nombres: partes.nombres,
                apellidoPaterno: partes.apellidoPaterno,
                apellidoMaterno: partes.apellidoMaterno,
                rfc,
                curp,
                // El NSS viene en ceros cuando el trabajador no lo tiene
                // asignado; en el TG-7 es opcional, así que en ese caso va
                // vacío y no en 00000000000.
                nss: /^0*$/.test(AF.texto(fila, 'nss')) ? '' : AF.texto(fila, 'nss'),
                tipoPago: '1',
                salarioBase: AF.numero(fila, 'sueldo'),
                descuentoPrestamo: linea.importe,
                descuentoFovissste,
                // El layout de origen no tiene este campo. Ver CAMPOS_SIN_CAMPO_EN_ORIGEN.
                pensionAlimenticia: 0,
                otrasDeducciones,
                // Sin origen en ninguna de las fuentes; el formato no la marca
                // obligatoria, así que se emite vacía y se avisa.
                clabe: '',
                tipoNombramiento: nombramiento.valor,
                // El formato es explícito: en nómina ordinaria ambos periodos
                // son el del encabezado. El plazo del préstamo que traen las
                // órdenes es otra cosa y SERICA lo rechazaría por posterior.
                periodoDesde: encabezado.periodo,
                periodoHasta: encabezado.periodo
            }
        };
    }

    /** CSV de los trabajadores con retención cuya orden de descuento falta. */
    function sinPrestamoCSV(filas) {
        const salida = [['rfc', 'importe_retenido', 'archivo', 'linea'].join(',')];

        filas.forEach(f => {
            salida.push([
                '"' + f.rfc + '"',
                Number(f.importe).toFixed(2),
                '"' + String(f.origen).replace(/"/g, '""') + '"',
                f.linea
            ].join(','));
        });

        return salida.join('\r\n');
    }

    TG7.cruce = { cruzar, registroDeOrdenes, normalizar, identificador, sinPrestamoCSV };
})(window.TG7 = window.TG7 || {});
