/**
 * Layouts de las dos fuentes de entrada y los catálogos que las traducen al
 * TG-7. Son datos, no código: cuando ISSSTE publique una revisión se corrige
 * la tabla y el lector de `anchoFijo.js` no se toca.
 *
 * Cada campo lleva la evidencia con la que se confirmó su posición, para que
 * nadie tenga que volver a deducirla.
 */
(function (TG7) {
    'use strict';

    /* ── Nómina de la pagaduría (SIPE-SIC, 260 posiciones) ──────────────────
     *
     * ORIGEN: especificación oficial «SIPE-SIC / INFORMACIÓN DE NÓMINA»
     * (`formaciónDeNómina_260 posiciones.pdf`, julio 2009), 260 posiciones,
     * 32 campos, llave R.F.C. Las posiciones de la especificación son 1-based
     * inclusivas; aquí se guardan como `inicio` 0-based + `largo`.
     *
     * Las 32 posiciones se verificaron una por una contra esa especificación y
     * contra los 10 242 registros reales de la quincena 18. La prueba de
     * regresión es aritmética y la define la propia spec (campo 25):
     *
     *   SUMANDO == SER_MED + FON_PREST + OTROS + P.C.P. + A.S.M. + I.H. + I.S.H.
     *
     * Cierra en 10 242 / 10 242.
     */
    const NOMINA_260 = {
        nombre: 'Nómina de pagaduría SIPE-SIC (260)',
        ancho: 260,
        campos: [
            // ── Identificación
            { nombre: 'ramo', inicio: 0, largo: 3, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 01 (1-3). Constante 022 en los 10 242 registros; coincide con las órdenes de descuento.' },

            { nombre: 'pagaduria', inicio: 3, largo: 5, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 02 (4-9): 6 posiciones = clave de 5 + un 0 de relleno, textual en la spec. Aquí se leen las 5 útiles.' },
            { nombre: 'pagaduriaRelleno', inicio: 8, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'El 0 final de PAGAD. Constante en los 10 242 registros (S12120, S12160, …).' },

            { nombre: 'numeroIssste', inicio: 9, largo: 9, tipo: 'numero', confirmado: true,
              nota: 'Spec campo 03 (10-18), 9 posiciones. Viene en ceros en 6 662 de 10 242: la orden de descuento sí lo trae.' },

            { nombre: 'rfc', inicio: 18, largo: 13, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 04 (19-31) y llave del registro. Estructura válida en 10 242 / 10 242. Llave del cruce contra las órdenes.' },

            { nombre: 'sueldo', inicio: 31, largo: 12, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 05 (32-43), SUELDO básico topado a 10 salarios mínimos. Es el Salario Básico de Cotización del TG-7, NO el SAL_SAR.' },

            { nombre: 'nombreCompleto', inicio: 43, largo: 40, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 06 (44-83). La spec fija el orden: PATERNO, MATERNO, NOMBRE(S), sin separador. La Ñ llega como espacio: falla del origen.' },

            { nombre: 'claveCobro', inicio: 83, largo: 30, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 07 (84-113). COBRO: distribución física de cheques o número de empleado.' },

            // ── Clasificación
            { nombre: 'tipoNombramientoSIPE', inicio: 113, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 08 (114). Catálogo completo en TIPO_NOMBRAMIENTO_SIPE; se traduce al del TG-7 con tipoNombramientoTG7().' },

            { nombre: 'tipoNominaSIPE', inicio: 114, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 09 (115). Coincide con la extensión del archivo en 10 225 / 10 225. Confirmó que .RET = extraordinaria.' },

            { nombre: 'relleno116', inicio: 115, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 10 (116), FILLER. En blanco en los 10 242 registros.' },

            // ── Periodo
            { nombre: 'fechaInicio', inicio: 116, largo: 8, tipo: 'fecha', confirmado: true,
              nota: 'Spec campo 11 (117-124), AAAAMMDD.' },
            { nombre: 'fechaFin', inicio: 124, largo: 8, tipo: 'fecha', confirmado: true,
              nota: 'Spec campo 11 (125-132), AAAAMMDD.' },

            { nombre: 'aportacionesRamo', inicio: 132, largo: 9, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 12 (133-141). APOR OR: indicadores de aportación del ramo. No es deducción del trabajador — no entra en el SUMANDO.' },

            // ── Deducciones. El SUMANDO cierra al 100 % con estas siete.
            { nombre: 'servicioMedico', inicio: 141, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 13 (142-147). SER MED, 3.375 % del sueldo (2.75 % + 0.625 %): coincide en 10 190 / 10 242.' },

            { nombre: 'fondoPrestaciones', inicio: 147, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 14 (148-153). FON PREST. La spec de 2009 dice 5.15 %; en los datos de 2026 es 7.25 % en 9 941 / 10 242 — la tasa cambió, la posición no.' },

            { nombre: 'creditoAdicional', inicio: 153, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 15 (154-159). OTROS: descuento por crédito adicional. CERO en los 10 242 registros de la quincena 18.' },

            { nombre: 'relleno160', inicio: 159, largo: 4, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 16 (160-163), FILLER. En blanco en los 10 242 registros.' },

            { nombre: 'descuentoPrestamo', inicio: 163, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 17 (164-169). P.C.P. — el descuento de préstamo personal. Coincide con el importe de las órdenes en 26 de 26 cruzados de la quincena 13.' },

            { nombre: 'descuentoServicioMedico', inicio: 169, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 18 (170-175). A.S.M.: responsiva de servicio médico. CERO en los 10 242 registros de la quincena 18.' },

            { nombre: 'claveHabitacion', inicio: 175, largo: 2, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 19 (176-177). T.H.: catálogo en CLAVES_HABITACION. Observado: 00 → 8 258, 64 → 1 978, 55 → 6.' },

            { nombre: 'importeHabitacion', inicio: 177, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 20 (178-183). I.H.: importe del descuento de vivienda. Es el Descuento FOVISSSTE del TG-7 SOLO si T.H. es una clave FOVISSSTE.' },

            { nombre: 'claveSeguroHabitacion', inicio: 183, largo: 2, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 21 (184-185). T.S.H.: 65 = Seguro de daños. Observado: 00 → 8 265, 65 → 1 977.' },

            { nombre: 'importeSeguroHabitacion', inicio: 185, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 22 (186-191). I.S.H. Constante 8.50 en los 1 977 que lo traen.' },

            // ── Identificación secundaria
            { nombre: 'curp', inicio: 191, largo: 18, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 23 (192-209). Estructura válida en 10 242 / 10 242. Base del algoritmo de separación de nombre.' },

            { nombre: 'relleno210', inicio: 209, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 24 (210), FILLER. En blanco en los 10 242 registros.' },

            { nombre: 'sumaDescuentos', inicio: 210, largo: 7, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 25 (211-217). SUMANDO: la spec enumera los 7 conceptos que lo forman. Cuadra en 10 242 / 10 242. Es la prueba de regresión del layout.' },

            { nombre: 'quincenaProceso', inicio: 217, largo: 6, tipo: 'numero', confirmado: true,
              nota: 'Spec campo 26 (218-223). REP_QUIN, AAAAQQ. Constante 202618 en los archivos de la quincena 18.' },

            { nombre: 'fechaProceso', inicio: 223, largo: 8, tipo: 'fecha', confirmado: true,
              nota: 'Spec campo 27 (224-231). FECH_REP: fecha de generación del archivo.' },

            { nombre: 'entidadPago', inicio: 231, largo: 2, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 28 (232-233): «entidad federativa en la que tiene radicado el pago». NO es la del aportante, así que no tiene por qué coincidir con la del encabezado. Constante 19.' },

            { nombre: 'nss', inicio: 233, largo: 11, tipo: 'numero', confirmado: true,
              nota: 'Spec campo 29 (234-244). Dato real: el año de nacimiento que trae coincide con el de la CURP en 9 056 / 9 115 (99.4 %). 11 % viene en ceros.' },

            { nombre: 'salarioSAR', inicio: 244, largo: 12, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Spec campo 30 (245-256). SAL_SAR: base de cotización del SAR. NO es el salario del TG-7 — ése es SUELDO.' },

            { nombre: 'relleno257', inicio: 256, largo: 3, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 31 (257-259), FILLER. En blanco en los 10 242 registros.' },

            { nombre: 'tipoRegistro', inicio: 259, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'Spec campo 32 (260), TIP_REG constante = 1. Vale 1 en los 10 242. Buen canario para detectar un archivo desalineado.' }
        ]
    };

    /** Los siete campos cuya suma debe dar `sumaDescuentos`. Base de la regresión. */
    const CAMPOS_DEL_SUMANDO = [
        'servicioMedico',
        'fondoPrestaciones',
        'creditoAdicional',
        'descuentoPrestamo',
        'descuentoServicioMedico',
        'importeHabitacion',
        'importeSeguroHabitacion'
    ];

    /* ── Órdenes de descuento emitidas por ISSSTE (180 posiciones) ──────────
     *
     * Archivos `1{ramo}{pagaduria}_{folio}.txt`. Esta es la fuente del NÚMERO
     * DE PRÉSTAMO, que la nómina de la pagaduría no trae: el TG-7 solo se
     * puede armar cruzando ambas.
     *
     * No hay especificación oficial de este archivo; las posiciones se
     * dedujeron de 87 registros reales (quincenas 13 y 19) y cumplen el patrón
     * en 87/87. La cabecera (ramo 3 + pagaduría 6 + número ISSSTE 9 = 18) se
     * alinea con la del SIPE-SIC, incluido el 0 de relleno de la pagaduría.
     */
    const ORDENES_180 = {
        nombre: 'Órdenes de descuento (180)',
        ancho: 180,
        campos: [
            { nombre: 'ramo', inicio: 0, largo: 3, tipo: 'texto', confirmado: true },

            { nombre: 'pagaduria', inicio: 3, largo: 5, tipo: 'texto', confirmado: true },
            { nombre: 'pagaduriaRelleno', inicio: 8, largo: 1, tipo: 'texto', confirmado: true,
              nota: 'Mismo 0 de relleno que la nómina: es "0" en los 87/87 registros. Leerlo aparte evita arrastrarlo como primer dígito del número ISSSTE.' },

            { nombre: 'numeroIssste', inicio: 9, largo: 9, tipo: 'numero', confirmado: true,
              nota: '9 posiciones, igual que el campo 03 del SIPE. Aquí SÍ viene poblado, a diferencia de la nómina.' },

            { nombre: 'rfc', inicio: 18, largo: 13, tipo: 'texto', confirmado: true,
              nota: '86/87 cumplen la estructura de RFC. Llave del cruce contra la nómina.' },

            { nombre: 'nombreCompleto', inicio: 31, largo: 40, tipo: 'texto', confirmado: true,
              nota: 'Orden NOMBRES PATERNO MATERNO — inverso al de la nómina. No se usa: manda el de la nómina, que sí tiene orden documentado.' },

            { nombre: 'claveCobro', inicio: 71, largo: 30, tipo: 'texto', confirmado: true },

            { nombre: 'plazoQuincenas', inicio: 103, largo: 2, tipo: 'numero', confirmado: true,
              nota: 'Coincide con la columna «Pzo.Qna.» del reporte impreso (48, 36, 24…).' },

            { nombre: 'periodoDesde', inicio: 105, largo: 6, tipo: 'numero', confirmado: true,
              nota: 'Formato QQAAAA. Es el PLAZO DEL PRÉSTAMO, no el periodo de la nómina: no va al TG-7. Ver cruce.js.' },
            { nombre: 'periodoHasta', inicio: 111, largo: 6, tipo: 'numero', confirmado: true,
              nota: 'Formato QQAAAA, igual que periodoDesde.' },

            { nombre: 'concepto', inicio: 117, largo: 2, tipo: 'numero', confirmado: false },

            { nombre: 'importeDescuento', inicio: 120, largo: 6, tipo: 'importe', escala: 2, confirmado: true,
              nota: 'Coincide con el P.C.P. de la nómina en 26 de 26 registros cruzados de la quincena 13.' },

            { nombre: 'curp', inicio: 146, largo: 18, tipo: 'texto', confirmado: true,
              nota: '87/87 cumplen la estructura de CURP.' },

            { nombre: 'numeroPrestamo', inicio: 166, largo: 11, tipo: 'numero', confirmado: true,
              nota: 'CLAVE PARA EL TG-7. 87/87 con 11 dígitos. No existe en la nómina de la pagaduría.' }
        ]
    };

    /* ── Catálogos ──────────────────────────────────────────────────────── */

    /** Sufijo del archivo de nómina → pagaduría. Constante dentro de cada archivo. */
    const PAGADURIA_POR_SUFIJO = {
        FE: 'S1212',
        HO: 'S1213',
        RG: 'S1214',
        F1: 'S1215',
        F2: 'S1216',
        F3: 'S1217'
    };

    /**
     * Extensión del archivo → tipo de nómina del encabezado TG-7.
     *
     * Confirmado contra el campo TIPO NOMI (posición 115), que el propio
     * archivo declara: coincide en 10 225 de 10 225 registros.
     * `.RET` era una pregunta abierta: sus 17 registros declaran TIPO NOMI = 2,
     * o sea que el sistema los trata como extraordinaria.
     */
    const TIPO_NOMINA_POR_EXTENSION = {
        ORD: '1',
        EXT: '2',
        CAN: '3',
        RET: '2'
    };

    /** Spec campo 19 (T.H.). Solo 55, 56 y 64 son FOVISSSTE; el resto es crédito ISSSTE. */
    const CLAVES_HABITACION = {
        '00': { nombre: 'Sin crédito', fovissste: false },
        '06': { nombre: 'Hipotecario', fovissste: false },
        '08': { nombre: 'Hipotecario avalado', fovissste: false },
        '10': { nombre: 'Rentas ISSSTE (Multifamiliares)', fovissste: false },
        '46': { nombre: 'Adquisición Departamento Tlatelolco', fovissste: false },
        '55': { nombre: 'Hipotecario FOVISSSTE constante', fovissste: true },
        '56': { nombre: 'Hipotecario FOVISSSTE creciente', fovissste: true },
        '64': { nombre: 'Hipotecario FOVISSSTE salarios mínimos', fovissste: true }
    };

    /**
     * Catálogo del campo 08 del SIPE (TIPO NOM), textual de la especificación.
     *
     * Durante meses el valor `6` se creyó indocumentado —es el 70 % de la
     * plantilla— y bloqueó la entrega. Está en la spec: significa «Otros».
     */
    const TIPO_NOMBRAMIENTO_SIPE = {
        '1': 'Base o Plantel',
        '2': 'Confianza o Supernumerario',
        '3': 'Interino o Provisional',
        '4': 'Lista de raya o base',
        '5': 'Lista de raya eventual honorarios',
        '6': 'Otros'
    };

    /**
     * SIPE (1 dígito) → TG-7 (2 dígitos).
     *
     * La correspondencia es por NOMBRE, no aritmética: coincide con «×10» en
     * cuatro de los seis valores solo porque los nombres de esos cuatro son
     * idénticos en ambos catálogos.
     *
     *   1 Base o Plantel              → 10 Base
     *   2 Confianza o Supernumerario  → 20 Confianza
     *   4 Lista de raya o base        → 40 Base / Lista de Raya
     *   6 Otros                       → 60 Otros
     *
     * Los otros dos no tienen equivalente en el catálogo del TG-7 y NO se
     * adivinan: el registro se rechaza con el motivo puesto. Son los únicos
     * dos casos que siguen necesitando una respuesta de ISSSTE.
     *
     *   3 Interino o Provisional      → ¿35 Eventual? ¿60 Otros?
     *   5 Lista de raya eventual      → ¿50 Lista de Raya? ¿25 Honorarios?
     *
     * En la quincena 18: 1 → 2 967, 2 → 61, 3 → 10, 6 → 7 204. O sea que la
     * duda afecta a 10 registros de 10 242 y los demás ya se pueden emitir.
     */
    const TIPO_NOMBRAMIENTO_SIPE_A_TG7 = {
        '1': '10',
        '2': '20',
        '4': '40',
        '6': '60'
    };

    /** Por qué una clave del SIPE no se puede traducir. Vacío = sí se puede. */
    const TIPO_NOMBRAMIENTO_SIN_EQUIVALENCIA = {
        '3': 'El SIPE lo declara «Interino o Provisional» y el catálogo del TG-7 no tiene ese tipo. Falta que ISSSTE indique si va como 35 (Eventual) o 60 (Otros).',
        '5': 'El SIPE lo declara «Lista de raya eventual honorarios» y en el TG-7 eso se parte en 50 (Lista de Raya) y 25 (Honorarios). Falta que ISSSTE indique cuál.'
    };

    /**
     * Traduce el tipo de nombramiento del SIPE al del TG-7.
     * Devuelve `{ valor, motivo }`: si `valor` viene vacío, `motivo` explica
     * por qué, y el registro se rechaza sin inventar una clave.
     */
    function tipoNombramientoTG7(claveSIPE) {
        const clave = String(claveSIPE || '').trim();

        if (clave === '') {
            return { valor: '', motivo: 'La nómina no trae tipo de nombramiento (posición 114 vacía).' };
        }

        const equivalente = TIPO_NOMBRAMIENTO_SIPE_A_TG7[clave];

        if (equivalente) {
            return { valor: equivalente, motivo: '' };
        }

        const conocido = TIPO_NOMBRAMIENTO_SIN_EQUIVALENCIA[clave];

        return {
            valor: '',
            motivo: conocido || ('El valor «' + clave + '» no está en el catálogo de tipo de nombramiento del SIPE (1 a 6).')
        };
    }

    /** `132026` (QQAAAA) → `202613` (AAAAQQ), que es lo que pide el TG-7. */
    function aPeriodoTG7(qqaaaa) {
        if (!/^\d{6}$/.test(qqaaaa)) {
            return '';
        }

        return qqaaaa.slice(2) + qqaaaa.slice(0, 2);
    }

    /** `ISSSTE18F2.ORD` → `{ quincena: '18', pagaduria: 'S1216', tipoNomina: '1' }` */
    function interpretarNombreArchivo(nombre) {
        const m = /^ISSSTE(\d{2})([A-Z0-9]{2})\.([A-Z]{3})$/i.exec(String(nombre).trim());

        if (!m) {
            return null;
        }

        const sufijo = m[2].toUpperCase();
        const extension = m[3].toUpperCase();

        return {
            quincena: m[1],
            sufijo,
            pagaduria: PAGADURIA_POR_SUFIJO[sufijo] || null,
            extension,
            tipoNomina: TIPO_NOMINA_POR_EXTENSION[extension] || null
        };
    }

    TG7.layouts = {
        NOMINA_260,
        ORDENES_180,
        CAMPOS_DEL_SUMANDO,
        PAGADURIA_POR_SUFIJO,
        TIPO_NOMINA_POR_EXTENSION,
        CLAVES_HABITACION,
        TIPO_NOMBRAMIENTO_SIPE,
        TIPO_NOMBRAMIENTO_SIPE_A_TG7,
        TIPO_NOMBRAMIENTO_SIN_EQUIVALENCIA,
        tipoNombramientoTG7,
        aPeriodoTG7,
        interpretarNombreArchivo
    };
})(window.TG7 = window.TG7 || {});
