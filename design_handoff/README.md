# Handoff: Timbrado — rediseño premium

Rediseño visual del módulo de monitoreo de timbrado de nómina. **La base funcional ya existe** (`monitor/home.php`, `monitor/empleados.php`, `monitor/checkid.php`, el corrector SQL y los endpoints de `monitor/ajax/`); lo que cambia es la capa visual y la organización de las vistas.

## Qué cambió respecto a la implementación actual

1. **Paleta nueva** — el vino salió de las superficies y quedó solo como acento. Superficies en neutros cálidos (marfil/blanco), líneas hairline, y una sola tarjeta "hero" en oxblood profundo. Esto es lo que separa la identidad visual de los sitios de gobierno sin perder el vino institucional. Ver `theme.css`.
2. **Tipografía a dos voces** — Roboto Serif para cifras y títulos, Roboto Flex para UI, micro-etiquetas en mayúsculas con tracking amplio, cifras tabulares en todas las tablas.
3. **Gráficas en SVG** — los anillos ahora son arcos SVG con segmentos separados y extremos redondeados, no `conic-gradient`. Se ven finos y permiten el hueco entre segmentos.
4. **El detalle de registros salió del dashboard** — vive en su propia vista para no romper la armonía visual del resumen.
5. **Productos** — conserva la gráfica de distribución (peso de cada producto en el total de la quincena) y el desglose de conceptos por tarjeta, más un anillo de 4 estados y badge COMPLETO / EN PROCESO por producto.
6. **CheckID** — pasó de tarjetas sueltas a una **ficha de identidad**: cabecera vino con retrato de iniciales + nombre en serif, franja de identificadores primarios, y secciones agrupadas abajo. Se quitó el filtro de "datos a consultar" (siempre se consulta todo).
7. **Jerarquía quincena > año** — la quincena es el hero; el año es una tarjeta secundaria deliberadamente más discreta.

## Sobre el archivo de diseño

`Dashboard Nomina.dc.html` es una **referencia visual en HTML** — prototipo de alta fidelidad con datos de ejemplo, no código de producción. La tarea es trasladar el lenguaje visual a la implementación PHP existente, reutilizando sus endpoints reales.

Todo el HTML del prototipo usa estilos inline (viene de un editor visual). Al portarlo, usa `theme.css` + las clases utilitarias que ahí se definen (`.eyebrow`, `.figure`, `.card`, `.hero`, `.chip-estado`, `.tabla`, `.btn-primary`, `.field`) en lugar de copiar los inline styles.

**Los datos del prototipo son mock generados de forma determinista.** Hay que sustituirlos por los endpoints reales que ya existen.

## Vistas (6, navegación por rail lateral de 96px)

### 1. Resumen (`monitor/home.php`)
La métrica principal es **% timbrado = impresos / total** de la quincena seleccionada.

- Selector de periodo como pill: `Q{n}` + año, dentro de una cápsula con la etiqueta "PERIODO"; al lado el rango de fechas en texto plano.
- Grid `repeat(auto-fit, minmax(440px, 1fr))` — se apila a 1 columna en pantallas angostas (importante: con `1.5fr 1fr` fijo el texto de la leyenda se cortaba).
  - **Hero (quincena)**: fondo `--hero-bg`, anillo SVG de 196px con 4 segmentos, % grande en serif al centro, total de timbres y leyenda de 4 estados con valor y %.
  - **Acumulado del año**: `.card`, anillo de 116px, misma estructura pero tipografía menor. Suma de las 24 quincenas.
- **4 tiles KPI**: Total quincena · Falta timbrar (act+inv, con su %) · Cancelados · Vs. quincena previa (delta en puntos).
- **Tendencia**: barras verticales del % de impresos en las últimas 6 quincenas, con la quincena activa en `--wine` y el resto en gris; track de fondo redondeado.
- Dos accesos a Productos y Detalle.

### 2. Productos del periodo
La quincena se divide en productos (`EV16xx`), cada uno con sus 4 estados.

- **Primera tarjeta = gráfica de distribución**: anillo de 178px donde cada segmento es un producto (serie `--dist-1..8`), con el total de timbres de la quincena al centro y leyenda de código + valor + %.
- **Una tarjeta por producto**: código + nombre, badge COMPLETO (≥99% impreso) o EN PROCESO, anillo de 90px con los 4 estados, leyenda de 4 líneas con conteos, total del periodo y **desglose de conceptos** (411, FO2, FOR, REG…) en grid de 3 columnas con su %.

### 3. Detalle de registros (`monitor/ajax/detalle.php`)
Auditoría fila por fila, separada a propósito del resumen. Buscador por RFC + filtros de producto y estado + contador. Tabla con Producto, Estado (chip con punto), RFC, Total 1, Total 2, Inicio, Fin. Scroll interno, `max-height: 540px`.

### 4. Empleados (`monitor/empleados.php`)
**Solo lectura** — sin editar, sin eliminar.

- **Lista**: buscador por nombre/apellido/RFC; filas con avatar de iniciales, nombre completo, CURP y C.P., y el RFC en chip.
- **Detalle por RFC**: cabecera hero con iniciales, nombre, RFC/CURP y el conteo de timbres a la derecha; debajo la tabla de timbres (#, Clave de pago, CLUES, Código, Inicio, Fin, Percepciones, Deducciones, Estado). Equivale a `.../rh/comprobantes/empleado/pago/{RFC}` pero sin acciones de edición.

### 5. CheckID (`monitor/checkid.php`)
Consulta de RFC/CURP ante SAT/RENAPO, presentada como **ficha de identidad**:

- Campo de captura (auto-mayúsculas) con validación de formato en vivo — el hint cambia de color según si el patrón de RFC es válido — y botón Consultar.
- **Cabecera de ficha** (`--hero-bg`): barra vertical de 5px con gradiente verde→ámbar al borde izquierdo, retrato de 96px con iniciales en serif, nombre en serif 29px, chips de sexo / edad + fecha / entidad, y el régimen fiscal (605) en grande a la derecha.
- **Franja de identificadores primarios**: RFC · CURP · NSS en bloques separados por hairlines de 1px.
- **Secciones de detalle** en grid etiqueta-valor: Situación fiscal (con badge RFC VÁLIDO ANTE EL SAT), Datos personales, Listas 69 / 69-B (badge SIN COINCIDENCIAS).
- Pie con fecha de consulta + acciones (Copiar ficha, Ver timbres de este RFC).

Nota: se quitó el filtro de campos a consultar — siempre se traen todos.

### 6. Corrector SQL
Repara volcados de una sentencia por línea: escapa apóstrofes sueltos (`YOIC'S → YOIC''S`) y corrige codificación corrompida (`AGÜERO → AGÜERO`). El archivo original no se modifica.

- Zona de archivo con borde punteado en `--wine-soft` + botón "Analizar y corregir". Límite 40 MB.
- Panel de resultado: badge "Corregido", nombre del archivo, 3 estadísticas en cifra serif (líneas procesadas, apóstrofes escapados, textos recodificados) y botón de descarga.

## Movimiento y estados de carga

Toda consulta y todo cambio de vista pasa por un loader. En el prototipo lo centraliza `withLoader(label, ms, next)`; en la implementación real se sustituye por el `then/finally` del fetch, conservando la etiqueta.

- **Velo**: cubre solo el área de contenido — el rail y la cabecera (título, subtítulo, selector de periodo) siguen legibles. `background: var(--veil)` + `backdrop-filter: blur(7px)`, entra con un fade de 220ms.
- **Spinner**: arco de `--wine` de 74px que gira 1.5s lineal mientras su `stroke-dasharray` "respira" (`omArc`, 1.9s) — por eso no se siente mecánico. Dentro, un segundo arco de 48px en `--wine-soft` contrarrota a 2.4s, un halo radial pulsa (`omGlow`) y hay un punto fijo al centro.
- **Etiqueta contextual** en micro-mayúsculas, distinta por operación: Cargando resumen · Cargando registros · Cargando productos · Cargando empleados · Abriendo CheckID · Abriendo corrector · Consultando periodo · Consultando SAT / RENAPO · Analizando y corrigiendo.
- **Barra de avance** de 132×3px con un gradiente de vino que la recorre (`omSweep`, 1.25s) más tres puntos en cascada (`omDot`, desfase de 160ms).
- Duraciones simuladas: 520–700ms navegación, 640ms cambio de periodo, 1.1s CheckID, 1.4s corrector SQL.

**Entradas escalonadas.** Al montar cada vista los bloques suben 14px con fade (`omRiseIn`, 500ms `cubic-bezier(.2,.8,.2,1)`) en cascada: hero → tiles (desfase 60ms) → tendencia → accesos; las tarjetas de producto escalonan 50ms cada una. Los anillos entran con un pop suave (`omPop`, escala .86→1), las barras de tendencia crecen desde la base (`omGrow`, `transform-origin: bottom`) y un destello recorre el hero una sola vez al montar (`omSheen`). Las tarjetas de producto levantan 3px en hover con sombra.

Los delays se calculan en la lógica (`delay` por ítem), no se escriben a mano en el markup.

## Comportamiento

- El rail cambia de vista sin recargar. Ítem activo: fondo `#F3DCE1` (claro) / `#4A1226` (oscuro), icono en `--wine`, label en `--ink` y peso 600.
- Cambiar año o quincena recalcula todo lo derivado. En producción es async — considera skeleton, no spinner de pantalla completa.
- Entrar a Empleados desde el rail siempre resetea a la lista; "Volver" conserva el término de búsqueda.
- Switch claro/oscuro al pie del rail (sol/luna). En la implementación real, persiste la preferencia.
- Hover de filas y de ítems del rail: `--hover`. Foco de campos: `border-color: --wine`, sin outline del navegador.
- Transiciones de 140–180ms `ease` en hover/estado; la altura de las barras de tendencia anima a 400ms `cubic-bezier(.2,.8,.2,1)`.

## Estado

```
theme: 'light' | 'dark'
view: 'dashboard' | 'productos' | 'detalle' | 'empleados' | 'checkid' | 'sqlfix'
anio, quincena                                     // filtros de resumen/productos/detalle
searchDetalle, filtroProductoDetalle, filtroEstadoDetalle
searchEmpleados, selectedRFC                       // null = mostrar lista
checkidInput, checkidResult
sqlFile, sqlResult
```

## Detalle técnico: anillos SVG

Los anillos no son `conic-gradient`. Son `<circle>` concéntricos con `stroke-dasharray` calculado sobre la circunferencia, `stroke-linecap: round` y un hueco de ~1.2–2% entre segmentos. El `<svg>` va rotado `-90deg` para que el primer segmento arranque arriba.

```js
// parts: [{pct, color}], r = radio en el viewBox de 100x100
function segments(parts, r, gapPct = 1.4) {
  const C = 2 * Math.PI * r;
  let acc = 0;
  return parts.flatMap(p => {
    const start = acc; acc += p.pct;
    if (p.pct <= 0.05) return [];                       // omite slivers
    const len = Math.max(C * (p.pct - gapPct) / 100, C * 0.012);
    return [{ color: p.color,
              dash: `${len.toFixed(2)} ${(C - len).toFixed(2)}`,
              offset: (-C * start / 100).toFixed(2) }];
  });
}
```

Tamaños: 196px (hero quincena, r=41) · 178px (distribución, r=41) · 116px (año, r=42) · 90px (producto, r=42).

## Accesibilidad

- Los 3 niveles de tinta pasan 4.5:1 sobre `--card` en ambos temas — no bajes `--ink-faint` de #6B6360 en claro ni de #A79D9A en oscuro.
- Los colores de estado sobre el hero (`--hero-*`) son más luminosos a propósito; no uses los `--*-dot` sobre el vino.
- El estado nunca se comunica solo por color: siempre chip con punto **y** texto (IMP/ACT/CAN/INV).

## Archivos

- `theme.css` — tokens y primitivas listos para reemplazar el `theme.css` actual (incluye `--veil` y `--glow` del loader, los `@keyframes` y `.loader`).
- `Dashboard Nomina.dc.html` — prototipo completo de las 6 vistas. Ábrelo en el navegador; el JS embebido (`class Component`) documenta el cálculo de porcentajes, los segmentos de los anillos y la generación de mocks a sustituir.
