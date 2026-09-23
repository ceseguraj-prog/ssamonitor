/**
 * Cierre de Regularizados y Eventuales IMSS.
 *
 * Dos pasos y en ese orden: el inventario propone qué productos entran en cada
 * grupo y se pinta con casillas; al generar, el servidor vuelve a consultar con
 * lo que quedó marcado y devuelve el paquete.
 *
 * Se vuelve a consultar a propósito en vez de reutilizar lo que ya está en
 * pantalla: entre abrir el módulo y pulsar Generar puede haberse timbrado algo,
 * y los archivos tienen que contar lo mismo que el Excel.
 */

const ESTADOS = ['act', 'can', 'inv', 'imp'];

/* Lo último que devolvió el inventario, que es de donde salen las casillas. */
let inventario = null;

/* El paquete generado: se guarda para armar los enlaces de descarga. */
let paquete = null;

const $ = id => document.getElementById(id);

/* ── Inventario ──────────────────────────────────────────────────────────── */

async function cargarInventario(periodo) {
    ocultarAviso();
    $('resultado').hidden = true;
    paquete = null;

    const datos = await pedir('ajax/inventario.php', periodo, { etiqueta: 'Consultando la quincena' });

    if (datos.error) {
        inventario = null;
        $('grupos').innerHTML = '';
        actualizarSeleccion();
        return avisar(datos.error, true);
    }

    inventario = datos;
    pintarGrupos();
}

function pintarGrupos() {
    $('grupos').innerHTML = inventario.grupos.map((grupo, i) => {
        const productos = grupo.productos.length
            ? grupo.productos.map(p => filaProducto(grupo, p)).join('')
            : `<div class="ext-vacio">Ningún producto de la quincena tiene filas con
                 <code>${esc(grupo.criterio)}</code>.</div>`;

        return `<section class="card ext-grupo rise-in" style="--i:${i + 1}">
            <header class="ext-grupo__cabeza">
                <div>
                    <div class="eyebrow">${esc(grupo.etiqueta)}</div>
                    <div class="ext-grupo__criterio">
                        <code>${esc(grupo.criterio)}</code>${familia(grupo)}
                    </div>
                </div>
                <div class="ext-grupo__total">
                    <span class="figure">${fmt(grupo.totales.imp)}</span>
                    <span class="ext-grupo__pie">impresos a extraer</span>
                </div>
            </header>
            <div class="ext-productos">${productos}</div>
            ${descartados(grupo)}
        </section>`;
    }).join('');

    $('grupos').querySelectorAll('input[type=checkbox]')
        .forEach(c => c.addEventListener('change', actualizarSeleccion));

    actualizarSeleccion();
}

/** Los chips de estado de un producto; los estados en cero no se pintan. */
function chipsEstado(counts) {
    return ESTADOS
        .filter(e => counts[e] > 0)
        .map(e => `<span class="chip-estado ${e}"><span class="dot"></span>${e.toUpperCase()} ${fmt(counts[e])}</span>`)
        .join('');
}

function filaProducto(grupo, producto) {
    return `<label class="ext-producto">
        <input type="checkbox" data-grupo="${esc(grupo.id)}" value="${esc(producto.clave)}" checked>
        <span class="ext-producto__clave">${esc(producto.clave)}</span>
        <span class="ext-producto__chips">${chipsEstado(producto.counts)}</span>
        <span class="ext-producto__total">${fmt(producto.total)}</span>
    </label>`;
}

/** En qué familia de producto vale el criterio, cuando está acotado. */
function familia(grupo) {
    if (!grupo.prefijos || !grupo.prefijos.length) return '';

    return ` <span class="ext-familia">solo en ${grupo.prefijos.map(p => esc(p) + '*').join(', ')}</span>`;
}

/**
 * Los productos que cumplen el criterio pero están fuera de la familia del
 * grupo: las filas REG de un producto de eventuales son regularizados
 * eventuales, que son otro trámite.
 *
 * Se muestran sin casilla y sin contar, pero se muestran: es la diferencia
 * entre un filtro puesto adrede y un producto que nadie miró.
 */
function descartados(grupo) {
    if (!grupo.descartados || !grupo.descartados.length) return '';

    const filas = grupo.descartados.map(p => `<div class="ext-descartado">
        <span class="ext-producto__clave">${esc(p.clave)}</span>
        <span class="ext-producto__chips">${chipsEstado(p.counts)}</span>
        <span class="ext-producto__total">${fmt(p.total)}</span>
    </div>`).join('');

    return `<div class="ext-descartados">
        <div class="ext-descartados__titulo"
             title="Cumplen el criterio, pero en un producto donde no cuenta para este cierre">
            Fuera del cierre — cumplen el criterio pero no son producto de base
        </div>
        ${filas}
    </div>`;
}

/** Qué quedó marcado, por grupo. */
function seleccion() {
    const elegidos = {};

    $('grupos').querySelectorAll('input[type=checkbox]:checked').forEach(c => {
        const grupo = c.dataset.grupo;
        if (!elegidos[grupo]) elegidos[grupo] = [];
        elegidos[grupo].push(c.value);
    });

    return elegidos;
}

function actualizarSeleccion() {
    const elegidos = seleccion();
    const cuantos = Object.values(elegidos).reduce((n, l) => n + l.length, 0);

    $('generar').disabled = cuantos === 0;
    $('seleccion').textContent = cuantos === 0
        ? 'Marca al menos un producto'
        : `${cuantos} producto${cuantos === 1 ? '' : 's'} marcado${cuantos === 1 ? '' : 's'}`;
}

/* ── Generar ─────────────────────────────────────────────────────────────── */

async function generar(periodo) {
    ocultarAviso();

    const elegidos = seleccion();
    const params = { ...periodo };
    Object.keys(elegidos).forEach(g => { params[g] = elegidos[g].join(','); });

    const datos = await pedir('ajax/generar.php', params, { etiqueta: 'Armando el paquete' });

    if (datos.error) {
        $('resultado').hidden = true;
        return avisar(datos.error, true);
    }

    paquete = datos;
    pintarResultado(datos);
    $('resultado').hidden = false;
}

function pintarResultado(datos) {
    /* Un impreso sin UUID no llegaría al archivo de extracción y nadie se
       enteraría hasta que allá faltara un timbre. Se avisa aquí, arriba. */
    const huecos = datos.grupos.filter(g => g.uuidsFaltantes > 0);

    if (huecos.length) {
        avisar(huecos.map(g =>
            `${g.nombre}: ${g.uuidsFaltantes} timbre(s) impresos sin UUID, así que no entraron al .txt.`
        ).join(' '), true);
    }

    pintarMetricas(datos);
    pintarArchivos(datos);

    $('markdown').textContent = datos.markdown;
    $('descargar-zip').href = `ajax/descargar.php?token=${encodeURIComponent(datos.token)}`;
    $('descargar-zip').setAttribute('download', datos.nombreZip);

    $('detalle').innerHTML = datos.grupos.flatMap(grupo =>
        grupo.desglose.map(fila => `<div class="tabla__row ext-grid">
            <div>${esc(grupo.nombre)}</div>
            <div class="ext-clave">${esc(fila.clave)}</div>
            ${ESTADOS.map(e => `<div class="ext-num ${fila.counts[e] ? '' : 'ext-cero'}">${fmt(fila.counts[e])}</div>`).join('')}
            <div class="ext-num ext-fuerte">${fmt(fila.total)}</div>
        </div>`)
    ).join('');
}

function pintarMetricas(datos) {
    const tiles = datos.grupos.filter(g => g.productos.length).map(grupo => {
        const t = grupo.totales;

        return `<div class="card ext-tile">
            <div class="eyebrow">${esc(grupo.etiqueta)}</div>
            <div class="figure">${fmt(grupo.uuids)}</div>
            <div class="ext-tile__pie">UUID en el archivo</div>
            <div class="ext-tile__chips">
                ${ESTADOS.filter(e => t[e] > 0).map(e =>
                    `<span class="chip-estado ${e}"><span class="dot"></span>${e.toUpperCase()} ${fmt(t[e])}</span>`
                ).join('')}
            </div>
        </div>`;
    }).join('');

    $('metricas').innerHTML = tiles;
}

function pintarArchivos(datos) {
    $('archivos').innerHTML = datos.archivos.map(archivo => {
        const url = `ajax/descargar.php?token=${encodeURIComponent(datos.token)}`
            + `&archivo=${encodeURIComponent(archivo.nombre)}`;

        const pie = archivo.cuantos === null
            ? ''
            : `<span class="ext-archivo__pie">${fmt(archivo.cuantos)} líneas</span>`;

        return `<a class="ext-archivo" href="${url}" download="${esc(archivo.nombre)}">
            <span class="ext-archivo__nombre">${esc(archivo.nombre)}</span>${pie}
        </a>`;
    }).join('');
}

/* ── Copiar la nota ──────────────────────────────────────────────────────── */

async function copiarMarkdown() {
    if (!paquete) return;

    const boton = $('copiar');
    const original = boton.textContent;

    try {
        await navigator.clipboard.writeText(paquete.markdown);
        boton.textContent = 'Copiado';
    } catch (e) {
        /* El portapapeles necesita https o localhost; en http plano el
           navegador lo niega. Ahí lo que funciona es seleccionar el bloque y
           dejar que el usuario haga Ctrl+C. */
        seleccionar($('markdown'));
        boton.textContent = 'Selecciona y Ctrl+C';
    }

    setTimeout(() => { boton.textContent = original; }, 2200);
}

function seleccionar(nodo) {
    const rango = document.createRange();
    rango.selectNodeContents(nodo);

    const seleccion = window.getSelection();
    seleccion.removeAllRanges();
    seleccion.addRange(rango);
}

/* ── Avisos ──────────────────────────────────────────────────────────────── */

function avisar(texto, esError) {
    const caja = $('aviso');
    caja.textContent = texto;
    caja.classList.toggle('ext-aviso--error', !!esError);
    caja.hidden = false;
}

function ocultarAviso() {
    $('aviso').hidden = true;
}

/* ── Arranque ────────────────────────────────────────────────────────────── */

const periodo = montarPeriodo(p => cargarInventario(p));

$('generar').addEventListener('click', () => generar(periodo));
$('copiar').addEventListener('click', copiarMarkdown);

cargarInventario(periodo);
