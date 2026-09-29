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
 *
 * EL PADRÓN
 * ISSSTE también entrega el padrón de préstamos vigentes en .xlsx
 * (padronXlsx.js). Es un corte —el de septiembre de 2026 llega a la q16—, así
 * que se carga junto con las órdenes de las quincenas posteriores y aquí se
 * tratan igual: el número de préstamo es la llave y los repetidos cuentan una
 * vez.
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
     * Normaliza un archivo de órdenes a una lista plana, con los periodos ya
     * en AAAAQQ (`desde`/`hasta`).
     *
     * Acepta las tres formas en que ISSSTE entrega el dato:
     *   - el `.txt` de ancho fijo (`contenido`), periodos en QQAAAA;
     *   - el reporte `.docx` ya interpretado por reporteOrdenes.js
     *     (`ordenes`), periodos en QQAAAA;
     *   - el padrón `.xlsx` ya interpretado por padronXlsx.js (`ordenes` con
     *     `desde`/`hasta`), periodos YA en AAAAQQ. Voltearlos otra vez los
     *     dejaría todos fuera de vigencia.
     *
     * Los avisos que el lector dejó en el archivo (renglones ilegibles) pasan
     * aquí a las incidencias, para que no se pierdan en silencio.
     */
    function normalizar(archivo, incidencias) {
        (archivo.avisos || []).forEach(a => incidencias.push(a));

        if (Array.isArray(archivo.ordenes)) {
            return archivo.ordenes.map(o => ({
                rfc: o.rfc,
                curp: o.curp || '',
                tipoOrden: L.TIPO_ORDEN[o.tipoOrden] || '',
                numeroPrestamo: identificador(o.numeroPrestamo),
                numeroIssste: identificador(o.numeroIssste),
                importe: o.importe,
                desde: o.desde !== undefined ? o.desde : L.aPeriodoTG7(o.periodoDesde),
                hasta: o.hasta !== undefined ? o.hasta : L.aPeriodoTG7(o.periodoHasta),
                linea: o.linea
            }));
        }

        const lectura = AF.leerAnchoFijo(archivo.contenido, L.ORDENES_180);

        lectura.errores.forEach(e => {
            incidencias.push({ origen: archivo.nombre, linea: e.linea, rfc: '', mensaje: e.mensaje });
        });

        return lectura.registros.map(r => ({
            rfc: AF.texto(r, 'rfc'),
            curp: AF.texto(r, 'curp'),
            tipoOrden: L.TIPO_ORDEN[AF.texto(r, 'tipoOrden')] || '',
            numeroPrestamo: identificador(AF.texto(r, 'numeroPrestamo')),
            numeroIssste: identificador(AF.texto(r, 'numeroIssste')),
            importe: AF.numero(r, 'importeDescuento'),
            desde: L.aPeriodoTG7(AF.texto(r, 'periodoDesde')),
            hasta: L.aPeriodoTG7(AF.texto(r, 'periodoHasta')),
            linea: r.linea
        }));
    }

    function agregar(mapa, clave, valor) {
        if (!clave) {
            return;
        }

        if (!mapa.has(clave)) {
            mapa.set(clave, []);
        }

        mapa.get(clave).push(valor);
    }

    /**
     * Registro acumulado de préstamos, indexado por RFC, por CURP y por RFC
     * sin homoclave.
     *
     * Se queda solo con los que siguen vigentes en el periodo que se declara
     * (`desde <= periodo <= hasta`). Un préstamo que ya terminó no tiene por
     * qué aparecer, y uno que empieza después tampoco.
     *
     * Las BAJAS (tipo de orden B) no son préstamos: terminan uno desde su
     * quincena. Se descartan como fuente y, si su quincena ya llegó, dejan
     * fuera al préstamo aunque el padrón lo siga listando.
     *
     * El orden de los pasos importa. Primero vigencia, DESPUÉS repetidos: si
     * se descartaran repetidos antes, ganaría el primer archivo cargado, y una
     * orden de la q20 cargada antes que el padrón le quitaba el préstamo
     * vigente a un trabajador de la q18. Entre repetidos vigentes gana la
     * orden más reciente (`desde` mayor): un cambio corrige al alta.
     *
     * Los índices secundarios existen porque el padrón y la nómina no siempre
     * traen el mismo RFC: en la q18, 88 trabajadores solo se encontraron por
     * CURP (homoclave distinta) y 4 por los 10 primeros caracteres (72 RFC
     * del padrón vienen sin homoclave).
     */
    function registroDeOrdenes(archivos, periodo, incidencias) {
        const leidas = [];

        archivos.forEach(archivo => {
            normalizar(archivo, incidencias).forEach(r => leidas.push(Object.assign({ origen: archivo.nombre }, r)));
        });

        // El número de préstamo es único en todo ISSSTE, así que es la llave:
        // el mismo préstamo llega repetido cuando se cargan el padrón y las
        // órdenes, o dos quincenas que se traslapan, y a veces con el RFC
        // escrito distinto.
        const claveDe = r => (r.numeroPrestamo !== '' ? r.numeroPrestamo : r.rfc + '|');

        // Entre dos copias vigentes del mismo préstamo manda la más reciente;
        // a igual quincena, un cambio (C) sobre un alta (A) y un alta sobre el
        // padrón, que no trae tipo. Así no depende del orden de carga.
        const PESO_TIPO = { C: 2, A: 1 };
        const masReciente = (a, b) => {
            if ((a.desde || '') !== (b.desde || '')) {
                return (a.desde || '') > (b.desde || '');
            }

            return (PESO_TIPO[a.tipoOrden] || 0) > (PESO_TIPO[b.tipoOrden] || 0);
        };

        const bajas = new Map();

        leidas.forEach(r => {
            if (r.tipoOrden === 'B' && r.desde) {
                const previa = bajas.get(claveDe(r));

                if (!previa || r.desde < previa) {
                    bajas.set(claveDe(r), r.desde);
                }
            }
        });

        const elegidas = new Map();
        let duplicadas = 0;
        let dadosDeBaja = 0;

        leidas.forEach(r => {
            if (r.tipoOrden === 'B') {
                return;
            }

            if (r.desde && r.hasta && (periodo < r.desde || periodo > r.hasta)) {
                return;
            }

            const clave = claveDe(r);
            const baja = bajas.get(clave);

            if (baja && baja <= periodo) {
                if (!elegidas.has(clave + '|baja')) {
                    // Marca, no préstamo: solo evita contar la baja dos veces.
                    elegidas.set(clave + '|baja', null);
                    dadosDeBaja++;
                }

                return;
            }

            const previa = elegidas.get(clave);

            if (previa) {
                duplicadas++;

                // Los datos se toman de la orden más reciente, pero el
                // préstamo se puede encontrar por CUALQUIER RFC o CURP con que
                // haya venido: el padrón puede traer la homoclave con letra O
                // (`…MVO`) donde la nómina y el .docx traen cero (`…MV0`), y el .docx no trae
                // CURP. Quedarse con una sola copia hacía que el resultado
                // dependiera del orden de carga.
                previa.rfcs.add(r.rfc);
                previa.curps.add(r.curp);

                if (masReciente(r, previa.r)) {
                    previa.r = r;
                }

                return;
            }

            elegidas.set(clave, { r, rfcs: new Set([r.rfc]), curps: new Set([r.curp]) });
        });

        const porRfc = new Map();
        const porCurp = new Map();
        const porRfc10 = new Map();
        let vigentes = 0;

        elegidas.forEach(e => {
            if (!e) {
                return;
            }

            vigentes++;

            const r = e.r;
            const p = {
                numeroPrestamo: r.numeroPrestamo,
                numeroIssste: r.numeroIssste,
                rfc: Array.from(e.rfcs).filter(Boolean).join(','),
                importe: r.importe,
                desde: r.desde,
                hasta: r.hasta,
                origen: r.origen,
                linea: r.linea
            };

            e.rfcs.forEach(rfc => agregar(porRfc, rfc, p));
            e.curps.forEach(curp => agregar(porCurp, curp, p));
            new Set(Array.from(e.rfcs, rfc => String(rfc || '').slice(0, 10))).forEach(k => agregar(porRfc10, k, p));
        });

        return { porRfc, porCurp, porRfc10, total: leidas.length, vigentes, duplicadas, dadosDeBaja };
    }

    function cuadra(a, b) {
        return Number.isFinite(a) && Number.isFinite(b) && Math.abs(a - b) < 0.005;
    }

    /**
     * Préstamos vigentes de un trabajador de la nómina.
     *
     * Por RFC exacto y por CURP, JUNTOS: la CURP identifica a la persona
     * aunque la homoclave cambie, y un trabajador puede tener un préstamo
     * registrado con cada RFC. Quedarse con lo del RFC dejaba fuera el otro,
     * y con él la posibilidad de ver que era una renovación.
     *
     * Por último, RFC sin homoclave, pero solo si algún importe cuadra con lo
     * retenido: diez caracteres pueden repetirse entre dos personas, y el
     * importe es lo que confirma que es la misma.
     */
    function buscarPrestamos(registro, rfc, curp, pcp) {
        const exacto = registro.porRfc.get(rfc) || [];
        const porCurp = (curp && registro.porCurp.get(curp)) || [];
        const unidos = exacto.concat(porCurp.filter(p => exacto.indexOf(p) === -1));

        if (unidos.length) {
            return { prestamos: unidos, via: exacto.length ? 'rfc' : 'curp' };
        }

        const porRfc10 = registro.porRfc10.get(String(rfc || '').slice(0, 10));

        if (porRfc10 && porRfc10.some(p => cuadra(p.importe, pcp))) {
            return { prestamos: porRfc10, via: 'rfc10' };
        }

        return { prestamos: [], via: '' };
    }

    /**
     * Qué préstamos se declaran contra lo retenido (`pcp`).
     *
     *   - La suma de todos cuadra: van todos, cada uno con su importe.
     *   - Varios, y solo UNO cuadra con lo retenido: va ese. Es una
     *     renovación — el préstamo anterior se liquidó con el nuevo, pero el
     *     padrón lo sigue listando hasta su quincena final original. Medido en
     *     la q18: 152 de 152 trabajadores con dos o más préstamos «vigentes»,
     *     y en los 152 el que cuadra es el más reciente.
     *   - Uno solo que no cuadra: va ese número con lo que SÍ se retuvo. El
     *     TG-7 declara la retención efectiva; se avisa, porque puede ser una
     *     renovación posterior al corte del padrón.
     *   - Varios y ninguno cuadra: van todos con su importe y se avisa. No hay
     *     con qué decidir cuál sí, y no se adivina.
     */
    function elegirPrestamos(prestamos, pcp) {
        if (!prestamos.length) {
            return { lineas: [{ numeroPrestamo: '', numeroIssste: '', importe: pcp }], descartados: [], descuadre: false };
        }

        const aLinea = (p, importe) => ({ numeroPrestamo: p.numeroPrestamo, numeroIssste: p.numeroIssste, importe });
        const suma = prestamos.reduce((s, p) => s + (Number.isFinite(p.importe) ? p.importe : 0), 0);

        if (cuadra(suma, pcp)) {
            return { lineas: prestamos.map(p => aLinea(p, p.importe)), descartados: [], descuadre: false };
        }

        if (prestamos.length === 1) {
            return { lineas: [aLinea(prestamos[0], pcp)], descartados: [], descuadre: true, suma };
        }

        const exactos = prestamos.filter(p => cuadra(p.importe, pcp));

        if (exactos.length === 1) {
            return {
                lineas: [aLinea(exactos[0], exactos[0].importe)],
                descartados: prestamos.filter(p => p !== exactos[0]),
                descuadre: false
            };
        }

        return { lineas: prestamos.map(p => aLinea(p, p.importe)), descartados: [], descuadre: true, suma };
    }

    /**
     * Tipo de nómina de un registro: el que declara el propio registro
     * (posición 115) y, si viene vacío, el de la extensión del archivo.
     */
    function tipoNominaDe(fila, archivo) {
        const propio = AF.texto(fila, 'tipoNominaSIPE');

        if (/^[123]$/.test(propio)) {
            return propio;
        }

        const info = L.interpretarNombreArchivo(archivo.nombre);

        return info && info.tipoNomina ? info.tipoNomina : '';
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
        let otroTipoNomina = 0;
        let pcpIlegible = 0;
        let porIdentidad = 0;
        let renovacionesDescartadas = 0;

        const vistos = new Set();

        archivosNomina.forEach(archivo => {
            const lectura = AF.leerAnchoFijo(archivo.contenido, L.NOMINA_260);
            let otroTipoEnArchivo = 0;

            // El ramo del encabezado sale de la nómina, y SERICA valida ramo +
            // pagaduría de cada registro. Si se capturó otro, hay que saberlo.
            const ramosDistintos = new Set(lectura.registros
                .map(r => AF.texto(r, 'ramo'))
                .filter(ramo => Number(ramo) !== Number(encabezado.ramoCredito)));

            if (ramosDistintos.size) {
                incidencias.push({
                    origen: archivo.nombre,
                    linea: '',
                    rfc: '',
                    mensaje: 'La nómina trae el ramo ' + Array.from(ramosDistintos).join(', ')
                        + ' y el encabezado dice ' + encabezado.ramoCredito
                        + ': SERICA valida ramo + pagaduría, así que deberían coincidir'
                });
            }

            lectura.errores.forEach(e => {
                incidencias.push({ origen: archivo.nombre, linea: e.linea, rfc: '', mensaje: e.mensaje });
            });

            lectura.registros.forEach(fila => {
                totalNomina++;

                const rfc = AF.texto(fila, 'rfc');
                const pcp = AF.numero(fila, 'descuentoPrestamo');

                // Un P.C.P. ilegible no es «sin retención»: es un archivo que
                // puede venir desalineado. Se reporta en vez de descartarlo
                // como si fuera cero.
                if (!Number.isFinite(pcp)) {
                    pcpIlegible++;
                    incidencias.push({
                        origen: archivo.nombre,
                        linea: fila.linea,
                        rfc,
                        mensaje: 'El P.C.P. no es numérico: no se puede saber si hubo retención. El registro no se emite'
                    });

                    return;
                }

                // Sin retención efectiva no va en el archivo. Es el criterio
                // del propio formato, y descarta ~7 de cada 10 registros.
                if (pcp <= 0) {
                    return;
                }

                // Cada archivo TG-7 es de UN tipo de nómina. Una cancelación
                // o una extraordinaria no son retención ordinaria, y sin este
                // filtro además desplazaban al registro del .ORD: en la q18,
                // .CAN y .EXT van antes que .ORD en orden alfabético.
                if (tipoNominaDe(fila, archivo) !== encabezado.tipoNomina) {
                    otroTipoNomina++;
                    otroTipoEnArchivo++;

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

                const curp = AF.texto(fila, 'curp');
                const hallado = buscarPrestamos(registro, rfc, curp, pcp);
                const prestamos = hallado.prestamos;

                if (prestamos.length) {
                    conPrestamo++;
                } else {
                    sinPrestamo.push({
                        rfc,
                        curp,
                        nombre: AF.texto(fila, 'nombreCompleto'),
                        pagaduria: AF.texto(fila, 'pagaduria'),
                        importe: pcp,
                        linea: fila.linea,
                        origen: archivo.nombre
                    });
                }

                if (hallado.via === 'curp' || hallado.via === 'rfc10') {
                    porIdentidad++;
                }

                // Un trabajador puede tener más de un préstamo vigente, y cada
                // uno es su propio registro TG-7 con su propio importe. El
                // P.C.P. de la nómina es lo retenido en total; contra eso se
                // decide qué préstamos se declaran. Ver elegirPrestamos().
                const eleccion = elegirPrestamos(prestamos, pcp);
                const lineas = eleccion.lineas;
                const descuadre = eleccion.descuadre;

                if (descuadre) {
                    importeDiscrepante++;
                }

                if (eleccion.descartados.length) {
                    renovacionesDescartadas++;
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
                            mensaje: lineas.length === 1
                                ? 'El importe del préstamo no cuadra con el P.C.P. retenido; se declara lo retenido. Puede ser una renovación posterior al padrón: conviene confirmar el número de préstamo'
                                : 'La suma de los préstamos vigentes no cuadra con el P.C.P. retenido y ninguno coincide solo; podría faltar un alta o sobrar un préstamo ya liquidado',
                            valor: 'préstamos ' + eleccion.suma.toFixed(2) + ' · nómina ' + pcp.toFixed(2)
                        });
                    }

                    if (eleccion.descartados.length) {
                        salida.motivos.push({
                            campo: 'numeroPrestamo',
                            severidad: 'aviso',
                            mensaje: 'Varios préstamos vigentes y solo uno cuadra con lo retenido: se declara ese. Los demás se dan por liquidados en una renovación',
                            valor: 'se omite ' + eleccion.descartados.map(p => p.numeroPrestamo + ' (' + Number(p.importe).toFixed(2) + ')').join(', ')
                        });
                    }

                    if (hallado.via === 'curp' || hallado.via === 'rfc10') {
                        salida.motivos.push({
                            campo: 'rfc',
                            severidad: 'aviso',
                            mensaje: hallado.via === 'curp'
                                ? 'El préstamo se encontró por CURP: el RFC del padrón es distinto al de la nómina. Se declara el de la nómina'
                                : 'El préstamo se encontró por RFC sin homoclave y con importe idéntico. Se declara el RFC de la nómina',
                            valor: 'padrón ' + prestamos.map(p => p.rfc).filter((x, i, a) => a.indexOf(x) === i).join(',') + ' · nómina ' + rfc
                        });
                    }

                    registros.push(salida.registro);
                    motivos.push(salida.motivos);
                });
            });

            if (otroTipoEnArchivo) {
                incidencias.push({
                    origen: archivo.nombre,
                    linea: '',
                    rfc: '',
                    mensaje: otroTipoEnArchivo + ' registro(s) con retención son de otro tipo de nómina que el del encabezado ('
                        + encabezado.tipoNomina + '): no van en este archivo. Genéralos en su propia corrida'
                });
            }
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
                prestamosDadosDeBaja: registro.dadosDeBaja,
                trabajadoresConPrestamo: conPrestamo,
                trabajadoresSinPrestamo: sinPrestamo.length,
                emitidos: registros.length,
                rfcRepetidoEnNomina,
                nombresARevisar,
                eniesPerdidas,
                sinTipoNombramiento,
                numeroIsssteDiscrepante,
                importeDiscrepante,
                otroTipoNomina,
                pcpIlegible,
                porIdentidad,
                renovacionesDescartadas
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
                mensaje: 'No hay préstamo vigente para este trabajador ni en el padrón ni en las órdenes cargadas: se emite sin número de préstamo. SERICA lo rechazará; hay que pedir el alta a ISSSTE',
                valor: 'P.C.P. ' + Number(linea.importe).toFixed(2)
            });
        }

        // El número ISSSTE es único y permanente según la spec del SIPE. La
        // nómina lo trae en ceros en 2 de cada 3 registros, así que manda el de
        // la orden cuando existe; si ambos vienen y difieren, hay que saberlo:
        // SERICA valida la combinación número de ISSSTE + préstamo.
        //
        // `000000000` sin ceros a la izquierda queda en `0`: eso es «no lo
        // trae», no un número que difiera del de la orden.
        const numeroNomina = identificador(AF.texto(fila, 'numeroIssste'));
        const nominaLoTrae = numeroNomina !== '' && numeroNomina !== '0';
        const numeroOrden = linea.numeroIssste || '';
        const numeroIssste = numeroOrden !== '' ? numeroOrden : numeroNomina;
        const numeroDiscrepante = numeroOrden !== '' && nominaLoTrae && numeroOrden !== numeroNomina;

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

    /** CSV de los trabajadores con retención cuyo préstamo no se encontró. */
    function sinPrestamoCSV(filas) {
        const q = s => '"' + String(s === undefined || s === null ? '' : s).replace(/"/g, '""') + '"';
        const salida = [['rfc', 'curp', 'nombre', 'pagaduria', 'importe_retenido', 'archivo', 'linea'].join(',')];

        filas.forEach(f => {
            salida.push([
                q(f.rfc),
                q(f.curp),
                q(f.nombre),
                q(f.pagaduria),
                Number(f.importe).toFixed(2),
                q(f.origen),
                f.linea
            ].join(','));
        });

        return salida.join('\r\n');
    }

    TG7.cruce = { cruzar, registroDeOrdenes, normalizar, identificador, elegirPrestamos, sinPrestamoCSV };
})(window.TG7 = window.TG7 || {});
