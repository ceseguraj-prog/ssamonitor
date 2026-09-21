/**
 * Productos: cuánto aporta cada producto a la quincena y cómo va cada uno.
 * Las piezas compartidas viven en js/comun.js.
 */
(function () {
    'use strict';

    let periodo = null;
    let productos = null;

    document.addEventListener('DOMContentLoaded', () => {
        periodo = montarPeriodo(p => { periodo = p; cargar(); });
        cargar();
    });

    document.addEventListener('tema-cambiado', () => { if (productos) pintar(); });

    async function cargar() {
        let datos;

        try {
            datos = await pedir('ajax/datos.php', periodo, { etiqueta: 'Cargando productos' });
        } catch (e) {
            datos = null;
        }

        if (!datos || datos.error) {
            document.getElementById('grid').innerHTML =
                '<div class="emp-vacio">' + esc((datos && datos.error) || 'No se pudieron cargar los productos.') + '</div>';
            return;
        }

        productos = datos.productos || [];
        pintar();
    }

    function pintar() {
        const c = paleta();
        const grid = document.getElementById('grid');

        if (!productos.length) {
            grid.innerHTML = '<div class="emp-vacio">Esta quincena no tiene productos registrados.</div>';
            return;
        }

        // Cada producto se mide con la misma regla que la quincena: los
        // invisibles quedan fuera de la base.
        const bases = productos.map(p => base(p.counts || {}));
        const grandTotal = bases.reduce((suma, b) => suma + b.total, 0);
        const invTotal = bases.reduce((suma, b) => suma + b.inv, 0);

        grid.innerHTML = tarjetaDistribucion(bases, grandTotal, invTotal, c)
            + productos.map((p, i) => tarjetaProducto(p, bases[i], i, c)).join('');
    }

    /** Primera tarjeta: cuánto pesa cada producto dentro del total del periodo. */
    function tarjetaDistribucion(bases, grandTotal, invTotal, c) {
        const dist = productos.map((p, i) => ({
            codigo: p.codigo,
            valor: bases[i].total,
            pct: pct(bases[i].total, grandTotal || 1),
            color: c.dist[i % c.dist.length]
        }));

        return `
        <div class="card rise-in" style="padding:24px;display:flex;flex-direction:column;gap:18px">
          <div class="eyebrow" style="letter-spacing:1.2px">Distribución por producto</div>
          <div style="display:flex;justify-content:center">
            <div class="ring-in" style="--i:1;width:178px;height:178px;position:relative">
              ${anilloHtml(dist.map(d => ({ pct: d.pct, color: d.color })), 41, { tamano: 178, ancho: 11, gap: 0.9, cap: 'butt' })}
              <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 22px">
                <div class="figure" style="font-size:27px;letter-spacing:-0.6px">${fmt(grandTotal)}</div>
                <div style="font-size:10.5px;color:var(--ink-muted);margin-top:6px;line-height:1.3">timbrables en la quincena</div>
              </div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:9px;border-top:1px solid var(--border-soft);padding-top:16px">
            ${dist.map(d => `
              <div style="display:flex;align-items:center;gap:9px">
                <span style="width:8px;height:8px;border-radius:50%;background:${d.color};flex:0 0 auto"></span>
                <span style="font-size:12px;color:var(--ink-muted);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(d.codigo)}">${esc(d.codigo)}</span>
                <span style="font-size:12px;font-weight:600;flex:0 0 auto">${fmt(d.valor)}</span>
                <span style="font-size:11.5px;color:var(--ink-faint);flex:0 0 44px;text-align:right">${d.pct}%</span>
              </div>`).join('')}
            ${lineaExcluida(invTotal)}
          </div>
        </div>`;
    }

    /** Una tarjeta por producto: estados, total y desglose de conceptos. */
    function tarjetaProducto(p, b, i, c) {
        // Completo es no tener ningún activo, no un porcentaje redondeado.
        const completo = b.act === 0 && b.total > 0;
        const conceptos = Object.entries(p.unidades || {}).slice(0, 6);

        return `
        <div class="card lift rise-in" style="--i:${i + 1};padding:24px;display:flex;flex-direction:column;gap:20px">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
            <div style="min-width:0">
              <div style="font-size:15px;font-weight:600;letter-spacing:0.2px">${esc(p.codigo)}</div>
              <div style="font-size:12.5px;color:var(--ink-muted);margin-top:3px">${esc(p.nombre)}</div>
            </div>
            <span class="chip-estado ${completo ? 'imp' : 'act'}" style="flex:0 0 auto;white-space:nowrap">
              <span class="dot"></span>${completo ? 'COMPLETO' : 'EN PROCESO'}
            </span>
          </div>

          <div style="display:flex;align-items:center;gap:20px">
            <div style="width:90px;height:90px;position:relative;flex:0 0 auto">
              ${anilloHtml([
                  { pct: b.pct.imp, color: c.imp },
                  { pct: b.pct.act, color: c.act },
                  { pct: b.pct.can, color: c.can }
              ], 42, { tamano: 90, ancho: 9, gap: 2 })}
              <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
                <div class="figure" style="font-size:18px">${b.pctTimbrado}<span style="font-size:11px">%</span></div>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:7px;flex:1;min-width:0">
              ${[
                  { label: 'Impresos', v: b.imp, color: c.imp },
                  { label: 'Pendientes', v: b.act, color: c.act },
                  { label: 'Cancelados', v: b.can, color: c.can }
              ].map(lg => `
                <div style="display:flex;align-items:center;gap:7px">
                  <span style="width:7px;height:7px;border-radius:50%;background:${lg.color};flex:0 0 auto"></span>
                  <span style="font-size:11.5px;color:var(--ink-muted);flex:1;white-space:nowrap">${lg.label}</span>
                  <span style="font-size:11.5px;font-weight:600;flex:0 0 auto">${fmt(lg.v)}</span>
                </div>`).join('')}
            </div>
          </div>

          <div style="border-top:1px solid var(--border-soft);padding-top:14px">
            <div style="font-size:11.5px;color:var(--ink-faint);letter-spacing:0.2px;margin-bottom:13px">
              ${fmt(b.total)} timbrables en el periodo${b.inv ? ` · ${fmt(b.inv)} invisibles fuera` : ''}
            </div>
            ${conceptos.length ? `<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px 10px">${
              conceptos.map(([unidad, cuantos]) => `
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:0.9px;color:var(--ink-faint)">${esc(unidad)}</div>
                  <div style="font-size:13px;font-weight:600;margin-top:3px">${pct(cuantos, p.total || 0)}%</div>
                </div>`).join('')
            }</div>` : ''}
          </div>
        </div>`;
    }
})();
