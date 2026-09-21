/**
 * Lector genérico de archivos de ancho fijo.
 *
 * Las dos fuentes de entrada (nómina de 260 caracteres y órdenes de descuento
 * de 180) se describen como datos —una lista de campos con posición y tipo—
 * en vez de código. Cuando ISSSTE publique una revisión del layout se corrige
 * la tabla de `layouts.js` y este archivo no se toca.
 */
(function (TG7) {
    'use strict';

    /**
     * Tipos de campo:
     *   texto   se le quitan los espacios de relleno
     *   numero  dígitos; se conservan tal cual, incluidos los ceros a la izquierda
     *   importe entero con decimales implícitos: `000000494750` escala 2 → 4947.50
     *   fecha   AAAAMMDD → AAAA-MM-DD
     */
    function extraer(linea, campo) {
        const bruto = linea.slice(campo.inicio, campo.inicio + campo.largo);

        switch (campo.tipo) {
            case 'texto':
            case 'numero':
                return bruto.trim();

            case 'importe': {
                const escala = campo.escala === undefined ? 2 : campo.escala;
                const digitos = bruto.trim() || '0';

                if (!/^\d+$/.test(digitos)) {
                    return Number.NaN;
                }

                return Number(digitos) / Math.pow(10, escala);
            }

            case 'fecha': {
                const d = bruto.trim();

                return /^\d{8}$/.test(d)
                    ? d.slice(0, 4) + '-' + d.slice(4, 6) + '-' + d.slice(6)
                    : '';
            }

            default:
                return bruto.trim();
        }
    }

    /** Parte el contenido en líneas, tolerando CRLF, LF y CR sueltos. */
    function dividirLineas(contenido) {
        return contenido.split(/\r\n|\n|\r/).filter(l => l.length > 0);
    }

    /**
     * Lee un archivo de ancho fijo. Una línea con ancho incorrecto se reporta
     * como error y se omite, sin detener la lectura del resto: con 10 000+
     * registros, abortar todo por una línea mala es inservible.
     */
    function leerAnchoFijo(contenido, layout) {
        const registros = [];
        const errores = [];

        dividirLineas(contenido).forEach((linea, i) => {
            const numero = i + 1;

            if (linea.length !== layout.ancho) {
                errores.push({
                    linea: numero,
                    mensaje: 'Se esperaban ' + layout.ancho + ' caracteres y la línea tiene ' + linea.length,
                    crudo: linea
                });

                return;
            }

            const valores = {};
            layout.campos.forEach(campo => {
                valores[campo.nombre] = extraer(linea, campo);
            });

            registros.push({ linea: numero, crudo: linea, valores });
        });

        return { registros, errores };
    }

    /** Texto de un campo ya leído, siempre como cadena. */
    function texto(registro, campo) {
        const v = registro.valores[campo];

        return v === null || v === undefined ? '' : String(v);
    }

    /**
     * Número de un campo ya leído. Devuelve NaN cuando el dato no es numérico,
     * y quien lo llama decide qué hacer: nunca se convierte NaN en 0 aquí,
     * porque un 0 inventado se entrega sin que nadie lo note.
     */
    function numero(registro, campo) {
        const v = registro.valores[campo];

        return typeof v === 'number' ? v : Number(v);
    }

    /** Rangos del layout que todavía no tienen ningún campo asignado. */
    function zonasSinMapear(layout) {
        const cubierto = new Array(layout.ancho).fill(false);

        layout.campos.forEach(c => {
            for (let i = c.inicio; i < c.inicio + c.largo && i < layout.ancho; i++) {
                cubierto[i] = true;
            }
        });

        const zonas = [];
        let inicio = null;

        for (let i = 0; i <= layout.ancho; i++) {
            if (i < layout.ancho && !cubierto[i]) {
                if (inicio === null) {
                    inicio = i;
                }
            } else if (inicio !== null) {
                zonas.push({ inicio, fin: i - 1 });
                inicio = null;
            }
        }

        return zonas;
    }

    TG7.anchoFijo = { dividirLineas, leerAnchoFijo, texto, numero, zonasSinMapear };
})(window.TG7 = window.TG7 || {});
