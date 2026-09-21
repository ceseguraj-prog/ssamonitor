/**
 * Separación de nombre completo en apellido paterno / materno / nombres.
 *
 * Los archivos de nómina traen el nombre en UN solo campo de 40 caracteres, en
 * el orden que fija la spec del SIPE (`PATERNO MATERNO NOMBRES`) y sin
 * separador; el TG-7 los exige en tres campos distintos.
 *
 * El corte se deduce de la CURP, que codifica:
 *   [0] primera letra del apellido paterno   (ignorando partículas: DE, LA, DEL…)
 *   [1] primera vocal interna del paterno
 *   [2] primera letra del apellido materno
 *   [3] primera letra del primer nombre      (ignorando MARIA / MA / JOSE / J
 *                                             cuando hay más de un nombre)
 *
 * Medido sobre los 10 242 registros reales de la quincena 18: 10 233 (99.9 %)
 * se resuelven con aval de la CURP; los 9 restantes salen a la bitácora.
 */
(function (TG7) {
    'use strict';

    /** Partículas que la CURP no toma en cuenta al formar las iniciales. */
    const PARTICULAS = new Set([
        'DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'MC', 'MAC', 'VAN', 'VON', 'DA', 'DAS', 'DI', 'DU'
    ]);

    /** Nombres que la CURP omite cuando el trabajador tiene más de un nombre. */
    const NOMBRES_OMITIDOS = new Set(['MARIA', 'MA', 'MA.', 'JOSE', 'J', 'J.']);

    const VOCALES = new Set(['A', 'E', 'I', 'O', 'U']);

    /** Primera letra significativa de un apellido, saltando partículas. */
    function inicialApellido(palabras) {
        for (const p of palabras) {
            if (!PARTICULAS.has(p)) {
                return p[0] || '';
            }
        }

        return (palabras[0] && palabras[0][0]) || '';
    }

    /** Primera vocal DESPUÉS de la inicial del apellido paterno. */
    function vocalInterna(palabras) {
        for (const p of palabras) {
            if (PARTICULAS.has(p)) {
                continue;
            }

            for (const letra of p.slice(1)) {
                if (VOCALES.has(letra)) {
                    return letra;
                }
            }

            return '';
        }

        return '';
    }

    /**
     * Compara una inicial contra la CURP tolerando la `X`.
     *
     * RENAPO sustituye letras por `X` cuando el apellido no tiene vocal interna
     * y cuando la combinación resulta altisonante: `MEDINA` puede aparecer como
     * `MX..` en vez de `ME..`.
     */
    function coincide(esperado, enCurp) {
        return enCurp === 'X' || esperado === enCurp;
    }

    /** Primera letra del nombre de pila que la CURP toma en cuenta. */
    function inicialNombre(palabras) {
        const utiles = palabras.filter(p => !PARTICULAS.has(p));

        if (utiles.length > 1) {
            const primero = utiles.find(p => !NOMBRES_OMITIDOS.has(p));

            if (primero) {
                return primero[0];
            }
        }

        return (utiles[0] && utiles[0][0]) || '';
    }

    /**
     * Separa `nombreCompleto` (orden PATERNO MATERNO NOMBRES) usando la CURP.
     * Prueba cada corte posible y se queda con el que reproduce las 4 iniciales.
     */
    function separarNombre(nombreCompleto, curp) {
        const palabras = String(nombreCompleto || '').trim().split(/\s+/).filter(Boolean);
        const c = String(curp || '').toUpperCase();

        if (palabras.length === 0) {
            return {
                apellidoPaterno: '', apellidoMaterno: '', nombres: '',
                confiable: false, advertencia: 'Nombre vacío'
            };
        }

        if (palabras.length < 3) {
            // Sin material suficiente para tres campos: el TG-7 permite materno vacío.
            return {
                apellidoPaterno: palabras[0] || '',
                apellidoMaterno: '',
                nombres: palabras.slice(1).join(' '),
                confiable: false,
                advertencia: 'El nombre tiene menos de 3 palabras; no se puede confirmar el corte'
            };
        }

        const candidatos = [];

        if (c.length >= 4) {
            // i = fin del paterno, j = fin del materno. Ambos apellidos no vacíos,
            // y queda al menos una palabra para los nombres.
            for (let i = 1; i < palabras.length - 1; i++) {
                for (let j = i + 1; j < palabras.length; j++) {
                    const paterno = palabras.slice(0, i);
                    const materno = palabras.slice(i, j);
                    const nombres = palabras.slice(j);

                    // Una partícula siempre se une a la palabra que le sigue, así que no
                    // puede quedar al final de un apellido: "HERNANDEZ DE" es un corte
                    // imposible, el apellido materno es "DE JESUS".
                    if (PARTICULAS.has(paterno[paterno.length - 1]) || PARTICULAS.has(materno[materno.length - 1])) {
                        continue;
                    }

                    if (
                        coincide(inicialApellido(paterno), c[0]) &&
                        coincide(vocalInterna(paterno), c[1]) &&
                        coincide(inicialApellido(materno), c[2]) &&
                        coincide(inicialNombre(nombres), c[3])
                    ) {
                        candidatos.push({
                            partes: {
                                apellidoPaterno: paterno.join(' '),
                                apellidoMaterno: materno.join(' '),
                                nombres: nombres.join(' '),
                                confiable: true
                            },
                            palabrasApellidos: j
                        });
                    }
                }
            }
        }

        if (candidatos.length === 1) {
            return candidatos[0].partes;
        }

        if (candidatos.length > 1) {
            // Varios cortes cuadran con la CURP. Es lo normal en nombres como
            // "MEZA SANCHEZ JOSE CAMILO", donde "SANCHEZ JOSE" también pasa la prueba.
            // Desempate: los apellidos más cortos, que es la lectura natural.
            const minimo = Math.min.apply(null, candidatos.map(x => x.palabrasApellidos));
            const mejores = candidatos.filter(x => x.palabrasApellidos === minimo);

            if (mejores.length === 1) {
                return mejores[0].partes;
            }

            return Object.assign({}, mejores[0].partes, {
                confiable: false,
                advertencia: 'La CURP admite más de un corte con la misma cantidad de apellidos'
            });
        }

        // Respaldo: las dos primeras palabras como apellidos.
        return {
            apellidoPaterno: palabras[0],
            apellidoMaterno: palabras[1],
            nombres: palabras.slice(2).join(' '),
            confiable: false,
            advertencia: c.length >= 4 ? 'El corte no coincide con la CURP' : 'CURP ausente o incompleta'
        };
    }

    /**
     * Detecta nombres donde la `Ñ` se perdió y quedó como espacio.
     *
     * Los archivos de nómina llegan en ASCII puro: `MUÑOZ` viene como `MU OZ`,
     * `OCAÑA` como `OCA A`, `AÑORVE` como `A ORVE`. No es corregible de forma
     * confiable desde el archivo —hay que reclamarlo al área que genera la
     * nómina—, así que solo se marca.
     *
     * El fragmento suelto se busca también en la PRIMERA palabra: `AÑORVE` es
     * un apellido común y, partido, deja una `A` al inicio. Solo se descarta el
     * fragmento final, porque ahí sí puede ser una inicial legítima
     * ("JUAN PEREZ N").
     */
    function detectarEniePerdida(nombreCompleto) {
        const palabras = String(nombreCompleto || '').trim().split(/\s+/).filter(Boolean);

        return palabras.some((p, i) =>
            i < palabras.length - 1 &&
            p.length <= 2 &&
            !PARTICULAS.has(p) &&
            !NOMBRES_OMITIDOS.has(p)
        );
    }

    TG7.nombres = { separarNombre, detectarEniePerdida };
})(window.TG7 = window.TG7 || {});
