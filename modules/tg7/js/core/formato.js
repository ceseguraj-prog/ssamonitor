/**
 * Formato TG-7 — Nómina de Préstamos Personales (SERICA 2026).
 *
 * Tipos y escritor del archivo de salida. Núcleo puro: no toca el DOM ni la
 * red, para poder probarse solo y, si algún día hiciera falta, traducirse a
 * PHP sin reescribir las reglas.
 *
 * Reglas verificadas byte a byte contra el archivo oficial de referencia
 * `NOMPPR-52009001Q202613101.txt` (36 502 registros):
 *   - separador de campos `|`, presente incluso cuando el campo va vacío
 *   - fin de línea CRLF
 *   - SIN salto de línea al final del archivo
 *   - los 5 importes siempre con punto y exactamente 2 decimales
 *   - los identificadores NO llevan ceros a la izquierda (`1009035`, no `01009035`)
 */
(function (TG7) {
    'use strict';

    const SEPARADOR = '|';
    const FIN_LINEA = '\r\n';

    /** Catálogo del campo 17 del detalle (PDF «Formato Nómina PP (TG-7)», pág. 6). */
    const TIPOS_NOMBRAMIENTO = {
        '10': 'Base',
        '20': 'Confianza',
        '25': 'Honorarios',
        '30': 'Pensionado',
        '35': 'Eventual',
        '40': 'Base / Lista de Raya',
        '50': 'Lista de Raya',
        '60': 'Otros',
        '65': 'Becario',
        '70': 'Voluntariado'
    };

    const TIPOS_NOMINA = {
        '1': 'Ordinaria',
        '2': 'Extraordinaria',
        '3': 'Cancelación'
    };

    const TIPOS_PAGO = {
        '1': 'Descuento de nómina',
        '5': 'Liquidación por indemnización global',
        '9': 'Liquidación por retiro voluntario o finiquito'
    };

    /** Orden exacto de los 19 campos del detalle. No reordenar. */
    const CAMPOS_DETALLE = [
        'pagaduria',
        'numeroIssste',
        'numeroPrestamo',
        'nombres',
        'apellidoPaterno',
        'apellidoMaterno',
        'rfc',
        'curp',
        'nss',
        'tipoPago',
        'salarioBase',
        'descuentoPrestamo',
        'descuentoFovissste',
        'pensionAlimenticia',
        'otrasDeducciones',
        'clabe',
        'tipoNombramiento',
        'periodoDesde',
        'periodoHasta'
    ];

    /** Los 5 campos monetarios, que siempre se emiten con 2 decimales. */
    const CAMPOS_IMPORTE = [
        'salarioBase',
        'descuentoPrestamo',
        'descuentoFovissste',
        'pensionAlimenticia',
        'otrasDeducciones'
    ];

    /**
     * Campos del TG-7 cuyo origen NO se ha localizado en ninguna fuente.
     *
     * Se emiten vacíos y `validaciones.js` los reporta. El objetivo es que sea
     * imposible entregar un archivo que aparente estar completo: un valor
     * adivinado que pase el catálogo es peor que un campo vacío, porque nadie
     * lo revisa.
     *
     * La CLABE es AVISO y no error: el layout oficial del TG-7 deja la columna
     * «Validación» en blanco para ese campo, mientras que en todos los demás
     * campos obligatorios dice explícitamente «Que el campo no este vacío».
     * Además la nota general del formato admite campos nulos («separados por el
     * carácter Pipe, incluso cuando el campo sea nulo»). Mientras ISSSTE no lo
     * confirme por escrito, se emite vacío y se avisa en cada registro.
     */
    const CAMPOS_SIN_FUENTE = {
        clabe: {
            severidad: 'aviso',
            motivo: 'Sin origen: las 260 posiciones del layout SIPE-SIC están asignadas y ninguna es una cuenta bancaria; tampoco viene en las órdenes de 180. El formato TG-7 no la marca como obligatoria, así que se emite vacía.'
        }
    };

    /**
     * Campos que se emiten en 0.00 porque el layout de origen no tiene un campo
     * equivalente. No bloquean —se reportan como aviso—, pero conviene confirmarlos.
     */
    const CAMPOS_SIN_CAMPO_EN_ORIGEN = {
        pensionAlimenticia:
            'El layout SIPE-SIC de 260 no tiene campo de pensión alimenticia, y la prueba del SUMANDO cierra al 100 % con las otras siete deducciones: no hay un campo oculto. Se emite 0.00.'
    };

    /**
     * Importe con punto y exactamente 2 decimales.
     * Un campo vacío o nulo se reporta como `0.00`, nunca en blanco.
     */
    function formatearImporte(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '0.00';
        }

        const n = typeof valor === 'number'
            ? valor
            : Number(String(valor).replace(/[\s,$]/g, ''));

        return Number.isFinite(n) ? n.toFixed(2) : '0.00';
    }

    /** Texto plano, sin el separador y sin espacios en los extremos. */
    function formatearTexto(valor) {
        if (valor === null || valor === undefined) {
            return '';
        }

        return String(valor).replace(/\|/g, ' ').trim();
    }

    function lineaEncabezado(e) {
        return [
            e.tipoNomina,
            String(e.version).padStart(2, '0'),
            e.periodicidad,
            e.periodo,
            e.organismo,
            e.entidad,
            e.municipio,
            String(e.ramoCredito).padStart(3, '0')
        ].join(SEPARADOR);
    }

    function lineaDetalle(r) {
        return CAMPOS_DETALLE.map(campo => (
            CAMPOS_IMPORTE.indexOf(campo) !== -1
                ? formatearImporte(r[campo])
                : formatearTexto(r[campo])
        )).join(SEPARADOR);
    }

    /**
     * Arma el contenido completo del archivo.
     * CRLF entre líneas y sin salto final, tal como el archivo de referencia.
     */
    function escribirTG7(encabezado, registros) {
        return [lineaEncabezado(encabezado)]
            .concat(registros.map(lineaDetalle))
            .join(FIN_LINEA);
    }

    /**
     * NOMPPR-{organismo}{entidad}{municipio}{periodicidad}{periodo}{tipoNomina}{version}.txt
     * Ejemplo real: NOMPPR-52009001Q202613101.txt
     */
    function nombreArchivo(e) {
        const claveAportante = String(e.organismo) + String(e.entidad) + String(e.municipio);
        const version = String(e.version).padStart(2, '0');

        return 'NOMPPR-' + claveAportante + e.periodicidad + e.periodo + e.tipoNomina + version + '.txt';
    }

    TG7.formato = {
        SEPARADOR,
        FIN_LINEA,
        TIPOS_NOMBRAMIENTO,
        TIPOS_NOMINA,
        TIPOS_PAGO,
        CAMPOS_DETALLE,
        CAMPOS_IMPORTE,
        CAMPOS_SIN_FUENTE,
        CAMPOS_SIN_CAMPO_EN_ORIGEN,
        formatearImporte,
        formatearTexto,
        lineaEncabezado,
        lineaDetalle,
        escribirTG7,
        nombreArchivo
    };
})(window.TG7 = window.TG7 || {});
