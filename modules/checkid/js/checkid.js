/**
 * Módulo CheckID: manda la consulta al servidor y pinta el resultado.
 * La clave de la API nunca llega aquí; vive solo en el .env del servidor.
 */
(function () {
    'use strict';

    const form = document.getElementById('ck-form');
    const entrada = document.getElementById('ck-termino');
    const boton = document.getElementById('ck-buscar');
    const ayuda = document.getElementById('ck-ayuda');
    const velo = document.getElementById('loader');
    const cajaError = document.getElementById('ck-error');
    const resultado = document.getElementById('ck-resultado');
    const vacio = document.getElementById('ck-vacio');

    const RE_RFC = /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/;
    const RE_CURP = /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/;

    if (!form) {
        return;
    }

    // Se fuerza mayúscula al teclear: la API las espera así y evita un rebote
    // por algo que el usuario no ve.
    entrada.addEventListener('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/\s+/g, '');
        this.setSelectionRange(pos, pos);
        validar(false);
    });

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        if (!validar(true)) {
            return;
        }

        // Ya no se eligen secciones: la ficha se arma con todo lo que devuelva
        // la API, y sin secciones el servidor las pide todas.
        const cuerpo = new FormData();
        cuerpo.append('termino', entrada.value.trim());

        cargando(true);

        try {
            const respuesta = await fetch('ajax/buscar.php', { method: 'POST', body: cuerpo });
            const datos = await respuesta.json().catch(() => null);

            if (!respuesta.ok || !datos) {
                throw new Error((datos && datos.error) || 'El servidor respondió ' + respuesta.status + '.');
            }
            if (datos.error) {
                throw new Error(datos.error);
            }

            pintar(datos);
        } catch (e) {
            mostrarError(e.message);
        } finally {
            cargando(false);
        }
    });

    /** Valida el formato antes de gastar una consulta de la API. */
    function validar(alEnviar) {
        const valor = entrada.value.trim();

        if (valor === '') {
            ayuda.textContent = 'RFC de 12 o 13 caracteres, o CURP de 18.';
            ayuda.classList.remove('mal', 'ok');
            return false;
        }

        const valido = RE_RFC.test(valor) || RE_CURP.test(valor);

        if (valido) {
            ayuda.textContent = RE_CURP.test(valor) ? 'Formato de CURP válido.' : 'Formato de RFC válido.';
            ayuda.classList.remove('mal');
            ayuda.classList.add('ok');
            return true;
        }

        ayuda.classList.remove('ok');

        // Mientras escribe no se le regaña; solo al intentar enviar.
        if (alEnviar) {
            ayuda.textContent = 'No parece un RFC ni una CURP válidos. Revisa antes de consultar.';
            ayuda.classList.add('mal');
        } else {
            ayuda.textContent = 'RFC de 12 o 13 caracteres, o CURP de 18.';
            ayuda.classList.remove('mal');
        }

        return false;
    }

    function cargando(activo) {
        boton.disabled = activo;
        if (velo) velo.hidden = !activo;

        if (activo) {
            cajaError.hidden = true;
            resultado.hidden = true;
            vacio.hidden = true;
        }
    }

    function mostrarError(mensaje) {
        cajaError.textContent = mensaje;
        cajaError.hidden = false;
        resultado.hidden = true;
        vacio.hidden = true;
    }

    /* ── Ficha de identidad ───────────────────────────────────────────────
       El resultado ya no son tarjetas sueltas por sección: la API devuelve
       campos sueltos y aquí se reordenan en una sola ficha — cabecera con el
       retrato y el nombre, franja de identificadores y secciones agrupadas. */

    /** Busca el valor de un campo por etiqueta dentro de una sección. */
    function campo(secciones, seccion, etiqueta) {
        const s = (secciones || {})[seccion];
        const encontrado = ((s && s.campos) || []).find(c => c.etiqueta === etiqueta);

        return encontrado ? String(encontrado.valor || '').trim() : '';
    }

    /** Primer valor no vacío entre varias combinaciones sección/etiqueta. */
    function primero(secciones, pares) {
        for (const [sec, et] of pares) {
            const v = campo(secciones, sec, et);
            if (v) return v;
        }

        return '';
    }

    /** Edad cumplida a partir de una fecha dd/mm/aaaa; vacío si no se entiende. */
    function edad(fechaTexto) {
        const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(fechaTexto.trim());
        if (!m) return '';

        const nacido = new Date(+m[3], +m[2] - 1, +m[1]);
        const hoy = new Date();
        let anios = hoy.getFullYear() - nacido.getFullYear();
        if (hoy.getMonth() < nacido.getMonth()
            || (hoy.getMonth() === nacido.getMonth() && hoy.getDate() < nacido.getDate())) {
            anios--;
        }

        return anios >= 0 && anios < 130 ? anios + ' años' : '';
    }

    function pintar(datos) {
        const sec = datos.secciones || {};

        if (!Object.keys(sec).length) {
            mostrarError(datos.error || 'La API no devolvió datos para esa clave.');
            return;
        }

        // Nombre: de la CURP salen las partes; el RFC trae la razón social.
        const nombres = campo(sec, 'curp', 'Nombres');
        const ap1 = campo(sec, 'curp', 'Primer apellido');
        const ap2 = campo(sec, 'curp', 'Segundo apellido');
        const nombre = [nombres, ap1, ap2].filter(Boolean).join(' ')
            || campo(sec, 'rfc', 'Nombre / Razón social')
            || datos.termino;

        const iniciales = ((nombres || nombre)[0] || '') + ((ap1 || '')[0] || '');

        const nacimiento = campo(sec, 'curp', 'Fecha de nacimiento');
        const anios = edad(nacimiento);

        const regimen = campo(sec, 'regimenFiscal', 'Régimen fiscal');
        const mRegimen = /^(\d{3})\s*[-–]?\s*(.*)$/.exec(regimen);

        const primarios = [
            ['RFC', primero(sec, [['rfc', 'RFC'], ['curp', 'RFC']]) || (datos.tipo === 'RFC' ? datos.termino : '')],
            ['CURP', primero(sec, [['curp', 'CURP'], ['rfc', 'CURP']]) || (datos.tipo === 'CURP' ? datos.termino : '')],
            ['NSS', campo(sec, 'nss', 'NSS')]
        ].filter(([, v]) => v);

        // Insignia de la situación fiscal: la API la manda como aviso.
        const avisosRfc = (sec.rfc && sec.rfc.avisos) || [];
        const avisos69 = (sec.estado69o69B && sec.estado69o69B.avisos) || [];

        const bloques = [
            {
                titulo: 'Situación fiscal',
                avisos: avisosRfc,
                campos: (sec.rfc && sec.rfc.campos || []).filter(c => ['RFC', 'CURP'].indexOf(c.etiqueta) === -1)
                    .concat(sec.codigoPostal && sec.codigoPostal.campos || [])
                    .concat(sec.regimenFiscal && sec.regimenFiscal.campos || []),
                error: sec.rfc && sec.rfc.error
            },
            {
                titulo: 'Datos personales',
                avisos: [],
                campos: (sec.curp && sec.curp.campos || []).filter(c => c.etiqueta !== 'CURP'),
                error: sec.curp && sec.curp.error
            },
            {
                titulo: 'Listas 69 / 69-B',
                avisos: avisos69,
                campos: (sec.estado69o69B && sec.estado69o69B.campos) || [],
                error: sec.estado69o69B && sec.estado69o69B.error
            }
        ].filter(b => b.campos.length || b.avisos.length || b.error);

        const meta = [
            campo(sec, 'curp', 'Sexo') ? '<span class="ck-pastilla">' + escapar(campo(sec, 'curp', 'Sexo')) + '</span>' : '',
            nacimiento ? '<span>' + escapar(anios ? anios + ' · ' + nacimiento : nacimiento) + '</span>' : '',
            campo(sec, 'curp', 'Entidad') ? '<span class="ck-sep"></span><span>' + escapar(campo(sec, 'curp', 'Entidad')) + '</span>' : ''
        ].filter(Boolean).join('');

        // Lo que uno acaba pegando en otro sistema: la ficha completa en texto.
        const paraCopiar = [nombre]
            .concat(primarios.map(([e, v]) => e + ': ' + v))
            .concat(bloques.flatMap(b => b.campos.map(c => c.etiqueta + ': ' + c.valor)))
            .join('\n');

        resultado.innerHTML =
            '<div class="ck-ficha">'
            + '<div class="ck-ficha-cabecera">'
            + '  <div class="ck-ficha-identidad">'
            + '    <div class="ck-retrato">' + escapar(iniciales.toUpperCase()) + '</div>'
            + '    <div style="flex:1;min-width:260px">'
            + '      <div class="ck-nombre">' + escapar(nombre) + '</div>'
            + '      <div class="ck-meta">' + meta + '</div>'
            + '    </div>'
            + (regimen
                ? '    <div class="ck-regimen">'
                  + '      <div class="eyebrow" style="color:var(--hero-ink-faint)">Régimen</div>'
                  + '      <div class="valor">' + escapar(mRegimen ? mRegimen[1] : regimen) + '</div>'
                  + (mRegimen && mRegimen[2]
                        ? '      <div style="font-size:11.5px;color:var(--hero-ink-soft);margin-top:3px">' + escapar(mRegimen[2]) + '</div>'
                        : '')
                  + '    </div>'
                : '')
            + '  </div>'
            + (primarios.length
                ? '  <div class="ck-primarios">'
                  + primarios.map(([etiqueta, valor]) =>
                        '<div class="ck-primario"><div class="etiqueta">' + escapar(etiqueta) + '</div>'
                        + '<div class="valor">' + escapar(valor) + '</div></div>').join('')
                  + '  </div>'
                : '')
            + '</div>'
            + '<div class="ck-ficha-cuerpo">'
            + bloques.map(bloque).join('')
            + '  <div class="ck-pie">'
            + '    <div class="fuente">Consultado el ' + escapar(new Date().toLocaleDateString('es-MX')) + ' · fuente SAT / RENAPO</div>'
            + '    <div class="acciones">'
            + '      <button type="button" class="btn-ghost ck-copiar" data-copiar="' + escapar(paraCopiar) + '">Copiar ficha</button>'
            + '      <a class="btn-primary" style="text-decoration:none;display:inline-flex;align-items:center" '
            + '         href="../empleados/index.php">Ver timbres de este RFC</a>'
            + '    </div>'
            + '  </div>'
            + '</div>'
            + '</div>';

        resultado.hidden = false;
        vacio.hidden = true;
        cajaError.hidden = true;

        conectarCopiar(resultado);
    }

    /** Una sección de la ficha: título con punto, insignia opcional y campos. */
    function bloque(b) {
        const insignias = b.avisos.map(a =>
            '<span class="ck-insignia ' + (a.tono === 'ok' ? 'ok' : 'mal') + '">'
            + escapar(a.texto) + '</span>').join('');

        const campos = b.campos.map(c =>
            '<div><div class="ck-campo-etiqueta">' + escapar(c.etiqueta) + '</div>'
            + '<div class="ck-campo-valor">' + escapar(c.valor) + '</div></div>').join('');

        return '<div class="ck-seccion">'
            + '<div class="ck-seccion-titulo"><span class="punto"></span>'
            + '<span class="eyebrow" style="letter-spacing:1.3px">' + escapar(b.titulo) + '</span>'
            + insignias + '</div>'
            + (b.error ? '<div class="ck-seccion-error">' + escapar(b.error) + '</div>' : '')
            + (campos ? '<div class="ck-campos">' + campos + '</div>' : '')
            + '</div>';
    }

    function conectarCopiar(contenedor) {
        contenedor.querySelectorAll('.ck-copiar').forEach(function (boton) {
            boton.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(this.dataset.copiar);
                    const antes = this.textContent;
                    this.textContent = 'Copiado';
                    setTimeout(() => { this.textContent = antes; }, 1400);
                } catch (e) {
                    this.textContent = 'No se pudo';
                }
            });
        });
    }

    function escapar(texto) {
        const div = document.createElement('div');
        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML.replace(/"/g, '&quot;');
    }
})();
