/**
 * Resumen: avance de timbrado de la quincena y acumulado del año.
 * Las piezas compartidas (anillos, formato, la regla del avance) viven en
 * js/comun.js.
 */
(function () {
    'use strict';

    let periodo = null;
    let datos = null;

    document.addEventListener('DOMContentLoaded', () => {
        periodo = montarPeriodo(p => { periodo = p; cargar(); });
        cargar();
    });

    // Los anillos y las barras llevan el color en el atributo, no en una clase:
    // al cambiar de tema hay que repintarlos.
    document.addEventListener('tema-cambiado', () => { if (datos) pintar(); });

    async function cargar() {
        let respuesta;

        try {
            respuesta = await pedir('ajax/datos.php', periodo, { etiqueta: 'Cargando resumen' });
        } catch (e) {
            respuesta = null;
        }

        if (!respuesta || respuesta.error || !respuesta.quincena) {
            return fallar(respuesta && respuesta.error
                ? respuesta.error
                : 'No se pudieron cargar los datos del resumen.');
        }

        datos = respuesta;
        document.getElementById('error').hidden = true;
        document.getElementById('contenido').hidden = false;
        pintar();
    }

    /** Si la consulta falla hay que decirlo: el resumen vacío se leería como "cero timbres". */
    function fallar(mensaje) {
        document.getElementById('contenido').hidden = true;
        const aviso = document.getElementById('error');
        aviso.hidden = false;
        aviso.textContent = mensaje;
    }

    function pintar() {
        const c = paleta();

        // ── Quincena (la métrica principal)
        const q = datos.quincena;
        const qb = base(q);

        document.getElementById('q-title').textContent = `Quincena ${periodo.quincena} · ${periodo.anio}`;
        document.getElementById('q-pct').textContent = qb.pctTimbrado;
        document.getElementById('q-total').textContent = fmt(qb.total);

        // Sobre el vino van los colores luminosos; los --*-dot ahí no se leen.
        // Los invisibles no son segmento: quedan fuera de la base.
        pintarAnillo('q-ring', [
            { pct: qb.pct.imp, color: c.heroImp },
            { pct: qb.pct.act, color: c.heroAct },
            { pct: qb.pct.can, color: c.heroCan }
        ], 41, { gap: 1.2, ancho: 9 });

        document.getElementById('q-legend').innerHTML = [
            { label: 'Impresos', valueFmt: fmt(qb.imp), pct: qb.pct.imp, color: c.heroImp },
            { label: 'Pendientes por timbrar', valueFmt: fmt(qb.act), pct: qb.pct.act, color: c.heroAct },
            { label: 'Cancelados', valueFmt: fmt(qb.can), pct: qb.pct.can, color: c.heroCan }
        ].map(lg => lineaLeyenda(lg, { sobreHero: true })).join('')
            + lineaExcluida(qb.inv, { sobreHero: true });

        // ── Año
        const yb = base(datos.anio);

        document.getElementById('y-title').textContent = `Acumulado ${periodo.anio}`;
        document.getElementById('y-pct').textContent = yb.pctTimbrado;
        pintarAnillo('y-ring', [
            { pct: yb.pct.imp, color: c.imp },
            { pct: yb.pct.act, color: c.act },
            { pct: yb.pct.can, color: c.can }
        ], 42, { gap: 1.2, ancho: 8 });

        document.getElementById('y-legend').innerHTML = [
            { label: 'Impresos', valueFmt: fmt(yb.imp), color: c.imp },
            { label: 'Pendientes', valueFmt: fmt(yb.act), color: c.act },
            { label: 'Cancelados', valueFmt: fmt(yb.can), color: c.can }
        ].map(lg => lineaLeyenda(lg)).join('') + lineaExcluida(yb.inv);

        // ── KPI. El delta sale de la tendencia: su penúltima entrada es la
        // quincena anterior a la seleccionada.
        const trend = datos.trend || [];
        const previa = trend.length >= 2 ? trend[trend.length - 2] : null;
        const delta = previa ? Math.round((qb.pctTimbrado - previa.pct) * 10) / 10 : null;

        const tiles = [
            {
                label: 'Timbrables en la quincena',
                value: fmt(qb.total),
                color: c.wineSoft,
                hint: qb.inv ? `${fmt(qb.inv)} invisibles fuera del cálculo` : 'timbres del periodo'
            },
            { label: 'Falta timbrar', value: fmt(qb.act), color: c.act, hint: `${qb.pct.act}% de los timbrables` },
            { label: 'Cancelados', value: fmt(qb.can), color: c.can, hint: `${qb.pct.can}% de los timbrables` },
            {
                label: 'Vs. quincena previa',
                value: delta === null ? '—' : (delta > 0 ? `+${delta}` : `${delta}`) + ' pts',
                color: c.imp,
                hint: delta === null ? 'sin referencia' : (delta >= 0 ? 'más avance' : 'menos avance')
            }
        ];

        document.getElementById('tiles').innerHTML = tiles.map((t, i) => `
            <div class="card lift rise-in" style="--i:${i + 2};border-radius:var(--r-tile);padding:20px 22px">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
                <span style="width:7px;height:7px;border-radius:50%;background:${t.color};flex:0 0 auto"></span>
                <span class="eyebrow" style="letter-spacing:1.1px">${esc(t.label)}</span>
              </div>
              <div class="figure" style="font-size:27px">${esc(t.value)}</div>
              <div style="font-size:12px;color:var(--ink-muted);margin-top:8px">${esc(t.hint)}</div>
            </div>`).join('');

        // ── Tendencia: la quincena activa en vino, el resto en gris cálido.
        document.getElementById('trend').innerHTML = trend.map((tr, i) => {
            const activa = tr.q === periodo.quincena;
            const tinta = activa ? 'var(--ink)' : 'var(--ink-faint)';
            return `
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:10px;height:100%;justify-content:flex-end">
              <div style="font-size:12px;font-weight:600;color:${tinta}">${tr.pct}%</div>
              <div style="width:100%;max-width:46px;height:100%;background:var(--track);border-radius:999px;display:flex;align-items:flex-end;overflow:hidden">
                <div class="bar-grow" style="--i:${i};width:100%;border-radius:999px;background:${activa ? c.wine : c.barIdle};height:${Math.max(8, tr.pct)}%;min-height:8px;transition:height .4s cubic-bezier(.2,.8,.2,1)"></div>
              </div>
              <div style="font-size:11.5px;color:${tinta};font-weight:${activa ? 600 : 500}">Q${tr.q}</div>
            </div>`;
        }).join('');
    }
})();
