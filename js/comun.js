/**
 * Piezas que comparten los módulos: formato, la regla del avance, los anillos
 * SVG, el velo de carga y el selector de periodo.
 *
 * Cada pantalla es su propio módulo con su propio JS; lo que vive aquí es lo
 * que se repetiría idéntico en todos, y sobre todo la regla del avance, que
 * tiene que ser una sola en todo el sistema.
 */

/* ── Formato ─────────────────────────────────────────────────────────────── */

const fmt = n => Math.round(n).toLocaleString('es-MX');
const pct = (n, t) => t ? Math.round(n / t * 1000) / 10 : 0;

const ESC = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ESC[c]);

/**
 * Porcentaje que no miente por redondeo. 10,196 de 10,198 es 99.98%, que
 * redondeado se pinta "100%" con dos pendientes vivos; al revés, 2 de 10,198
 * se pinta "0%" como si no hubiera ninguno.
 *
 * Así que 100% solo aparece cuando de verdad no falta nada, y 0% solo cuando
 * de verdad no hay nada.
 */
function pctVivo(n, t) {
  const v = pct(n, t);

  if (v >= 100 && n < t) return 99.9;
  if (v <= 0 && n > 0) return 0.1;

  return v;
}

/**
 * La regla del avance, en un solo lugar porque la usan el resumen, el año y
 * cada producto.
 *
 * Los invisibles no se timbran nunca: si contaran en la base, el avance jamás
 * llegaría a 100% y la gráfica arrastraría siempre esa mancha. Así que la base
 * son impresos + activos + cancelados, y lo único que falta por hacer son los
 * activos — un cancelado ya pasó por el timbrado. Estar al 100% es no tener
 * ningún activo.
 */
function base(counts) {
  const imp = counts.imp || 0, act = counts.act || 0;
  const can = counts.can || 0, inv = counts.inv || 0;
  const total = imp + act + can;

  return {
    imp, act, can, inv, total,
    pct: { imp: pctVivo(imp, total), act: pctVivo(act, total), can: pctVivo(can, total) },
    pctTimbrado: pctVivo(imp + can, total)
  };
}

/* ── Colores ─────────────────────────────────────────────────────────────── */

/* Los colores viven en css/theme.css. Aquí solo se leen, para que cambiar el
   tema (o los tokens) no obligue a tocar el JS. */
const token = n => getComputedStyle(document.body).getPropertyValue('--' + n).trim();

function paleta() {
  return {
    imp: token('imp-dot'), act: token('act-dot'), can: token('can-dot'), inv: token('inv-dot'),
    heroImp: token('hero-imp'), heroAct: token('hero-act'), heroCan: token('hero-can'), heroInv: token('hero-inv'),
    wine: token('wine'), wineSoft: token('wine-soft'), barIdle: token('bar-idle'),
    dist: [1, 2, 3, 4, 5, 6, 7, 8].map(i => token('dist-' + i))
  };
}

/* ── Anillos ─────────────────────────────────────────────────────────────── */

/**
 * Arcos de un anillo. No son conic-gradient: son <circle> concéntricos con
 * stroke-dasharray sobre la circunferencia, con un hueco entre segmentos y
 * extremos redondeados. Se ve mucho más fino y permite separar los estados.
 *
 * parts: [{pct, color}] · r: radio en el viewBox de 100x100
 */
function segmentos(parts, r, gapPct = 1.4) {
  const C = 2 * Math.PI * r;
  let acc = 0;
  const out = [];
  parts.forEach(p => {
    const start = acc;
    acc += p.pct;
    // Solo se omite lo que vale cero. Un estado con un puñado de registros pesa
    // una fracción de por ciento, pero si se descarta el anillo se ve cerrado y
    // contradice al número: se le da el arco mínimo y se nota.
    if (p.pct <= 0) return;
    const len = Math.max(C * (p.pct - gapPct) / 100, C * 0.012);
    out.push({ color: p.color, dash: `${len.toFixed(2)} ${(C - len).toFixed(2)}`, offset: (-C * start / 100).toFixed(2) });
  });
  return out;
}

/** Pinta los segmentos en un <svg> que ya trae su círculo de fondo. */
function pintarAnillo(svgId, parts, r, opciones = {}) {
  const svg = document.getElementById(svgId);
  if (!svg) return;

  const { gap = 1.4, ancho = 9, cap = 'round' } = opciones;

  // El primer <circle> es la pista; los demás son segmentos de un render previo.
  while (svg.children.length > 1) svg.removeChild(svg.lastChild);

  segmentos(parts, r, gap).forEach(sg => {
    const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    c.setAttribute('cx', '50');
    c.setAttribute('cy', '50');
    c.setAttribute('r', String(r));
    c.setAttribute('fill', 'none');
    c.setAttribute('stroke', sg.color);
    c.setAttribute('stroke-width', String(ancho));
    c.setAttribute('stroke-linecap', cap);
    c.setAttribute('stroke-dasharray', sg.dash);
    c.setAttribute('stroke-dashoffset', sg.offset);
    svg.appendChild(c);
  });
}

/** Markup de un anillo completo (pista + segmentos) para tarjetas generadas por JS. */
function anilloHtml(parts, r, { tamano, ancho, gap = 1.4, cap = 'round', pista = 'var(--track)' }) {
  const arcos = segmentos(parts, r, gap).map(sg =>
    `<circle cx="50" cy="50" r="${r}" fill="none" stroke="${sg.color}" stroke-width="${ancho}" stroke-linecap="${cap}" stroke-dasharray="${sg.dash}" stroke-dashoffset="${sg.offset}"/>`
  ).join('');
  return `<svg width="${tamano}" height="${tamano}" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
    <circle cx="50" cy="50" r="${r}" fill="none" stroke="${pista}" stroke-width="${ancho}"/>${arcos}
  </svg>`;
}

/** Una línea de leyenda: punto, etiqueta, valor y (opcionalmente) porcentaje. */
function lineaLeyenda(lg, { sobreHero = false } = {}) {
  const tinta = sobreHero ? 'var(--hero-ink-soft)' : 'var(--ink-muted)';
  const valor = sobreHero ? 'var(--hero-ink)' : 'var(--ink)';
  const tam = sobreHero ? 13 : 12.5;
  const porcentaje = lg.pct === undefined
    ? ''
    : `<span style="font-size:12px;color:${sobreHero ? 'var(--hero-ink-faint)' : 'var(--ink-faint)'};flex:0 0 46px;text-align:right;white-space:nowrap">${lg.pct}%</span>`;

  return `<div style="display:flex;align-items:center;gap:${sobreHero ? 11 : 9}px;min-height:18px">
    <span style="width:${sobreHero ? 9 : 8}px;height:${sobreHero ? 9 : 8}px;border-radius:50%;background:${lg.color};flex:0 0 auto"></span>
    <span style="font-size:${tam}px;color:${tinta};flex:1;white-space:nowrap">${esc(lg.label)}</span>
    <span style="font-size:${tam}px;font-weight:600;color:${valor};flex:0 0 auto;white-space:nowrap">${lg.valueFmt}</span>
    ${porcentaje}
  </div>`;
}

/**
 * Renglón de los invisibles. Va aparte de la leyenda y sin porcentaje: no son
 * parte del avance, pero esconderlos haría que los números no cuadraran con el
 * detalle.
 */
function lineaExcluida(inv, { sobreHero = false } = {}) {
  if (!inv) return '';

  const tinta = sobreHero ? 'var(--hero-ink-faint)' : 'var(--ink-faint)';
  const borde = sobreHero ? 'rgba(255,238,240,0.18)' : 'var(--border-soft)';

  return `<div style="display:flex;align-items:center;gap:9px;margin-top:4px;padding-top:9px;border-top:1px solid ${borde}">
    <span style="width:8px;height:8px;border-radius:50%;background:${sobreHero ? 'var(--hero-inv)' : 'var(--inv-dot)'};opacity:.55;flex:0 0 auto"></span>
    <span style="font-size:12px;color:${tinta};flex:1;white-space:nowrap">Invisibles (no se timbran)</span>
    <span style="font-size:12px;color:${tinta};flex:0 0 auto">${fmt(inv)}</span>
    <span style="font-size:12px;color:${tinta};flex:0 0 46px;text-align:right">fuera</span>
  </div>`;
}

/* ── Carga ───────────────────────────────────────────────────────────────── */

/* Se cuentan las peticiones en vuelo en vez de usar un booleano: una búsqueda
   puede encimar varias y la primera en terminar apagaría el velo mientras las
   demás siguen abiertas. */
let peticionesEnVuelo = 0;

function marcarCarga(delta, etiqueta) {
  peticionesEnVuelo = Math.max(0, peticionesEnVuelo + delta);

  const velo = document.getElementById('loader');
  const label = document.getElementById('loader-label');
  if (!velo) return;

  if (etiqueta && label) label.textContent = etiqueta;
  velo.hidden = peticionesEnVuelo === 0;
}

/**
 * Llama al endpoint del módulo. La ruta es relativa a la página, así que cada
 * módulo pide lo suyo sin saber dónde está montado el proyecto.
 */
async function pedir(ruta, params = {}, { signal, etiqueta = 'Cargando' } = {}) {
  const url = new URL(ruta, window.location.href);
  Object.keys(params).forEach(k => {
    if (params[k] !== null && params[k] !== undefined) url.searchParams.append(k, params[k]);
  });

  marcarCarga(1, etiqueta);
  try {
    const res = await fetch(url, { signal });
    return await res.json();
  } finally {
    marcarCarga(-1);
  }
}

/* ── Periodo ─────────────────────────────────────────────────────────────── */

/**
 * Cápsula de periodo. El servidor ya resolvió cuál mostrar y lo dejó en
 * data-anio / data-quincena; aquí solo se llenan los selectores y se avisa
 * cuando cambian.
 *
 * El periodo se recuerda en la sesión del lado del servidor, así que al pasar a
 * otra pantalla sigue el mismo sin arrastrar parámetros en los enlaces.
 */
function montarPeriodo(alCambiar) {
  const caja = document.getElementById('periodo');
  if (!caja) return null;

  const estado = {
    anio: Number(caja.dataset.anio),
    quincena: Number(caja.dataset.quincena)
  };

  const anios = JSON.parse(caja.dataset.anios || '[]');
  const selAnio = document.getElementById('periodo-anio');
  const selQuincena = document.getElementById('periodo-quincena');

  selAnio.innerHTML = anios.map(a =>
    `<option value="${a}" ${a == estado.anio ? 'selected' : ''}>${a}</option>`).join('');

  let opciones = '';
  for (let i = 1; i <= 24; i++) {
    opciones += `<option value="${i}" ${i === estado.quincena ? 'selected' : ''}>Q${i}</option>`;
  }
  selQuincena.innerHTML = opciones;

  const cambio = () => {
    estado.anio = Number(selAnio.value);
    estado.quincena = Number(selQuincena.value);
    pintarRango(estado);
    alCambiar(estado);
  };

  selAnio.addEventListener('change', cambio);
  selQuincena.addEventListener('change', cambio);

  return estado;
}

/** Rango de fechas de la quincena, en texto plano junto al selector. */
function pintarRango(periodo) {
  const salida = document.getElementById('periodo-rango');
  if (!salida) return;

  const mes = Math.ceil(periodo.quincena / 2);
  const impar = periodo.quincena % 2 === 1;
  const ultimo = new Date(periodo.anio, mes, 0).getDate();
  const f = (d) => `${periodo.anio}-${String(mes).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

  salida.textContent = `${f(impar ? 1 : 16)} al ${f(impar ? 15 : ultimo)}`;
}
