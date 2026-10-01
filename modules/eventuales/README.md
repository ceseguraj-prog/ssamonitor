# Módulo: Nómina de eventuales (`eventuales`)

Toma el Excel de la nómina de eventuales de una quincena y arma los cuatro
archivos de carga que antes se hacían a mano:

| Juego | prod_pago | Detalle de empleados |
|---|---|---|
| Eventuales (`E`) | `detalle prod_pago con 1807 FED {QQ}E.xlsx` | `DETALLE EMPLEADOS CON 1807 FED{QQ}E.xlsx` |
| SaNAS (`S`) | `detalle prod_pago con 1807 FED {QQ}S {AAAA}.xlsx` | `DETALLE EMPLEADOS CON 1807 FED{QQ}S {AAAA}.xlsx` |

SaNAS va separado de eventuales: su propio prefijo de producto, su propio
contrato y sus propias claves.

## Los datos no salen de esta computadora

Igual que el TG-7, todo se procesa en el navegador: **no hay endpoint, no se
sube nada y no se guarda nada**. Los archivos traen RFC, CURP y sueldo de unas
2 700 personas. Por eso la lógica vive en `js/core/` y no en PHP, y el módulo no
tiene `ajax/` ni `storage/`.

## Acceso

`usuarios` es lista blanca (`csegura`) en [`modulo.php`](modulo.php): el módulo
produce los archivos con los que se paga, no es una consulta. Para abrirlo a
alguien más se le da desde el módulo Permisos.

## Estructura

```
modules/eventuales/
├── modulo.php          descriptor que lee includes/modulos.php
├── index.php           página (solo el armazón: no procesa nada)
├── css/eventuales.css
└── js/
    ├── core/           núcleo puro: sin DOM, sin red, sin PHP
    │   ├── xlsx.js         lector y escritor de .xlsx, empaquetador .zip
    │   └── nomina.js       reglas: entrada → prod_pago y detalle
    └── eventuales.js   interfaz: carga archivos, pinta, descarga
```

`xlsx.js` no usa librerías. Lee con `DecompressionStream` y escribe con
`CompressionStream` más un CRC-32 propio. Las dos APIs vienen en el navegador y
en Node 18+. El lector es pariente del de `tg7/js/core/padronXlsx.js`, pero lee
todas las hojas por nombre, porque la hoja de eventuales no siempre es la
primera.

## Entradas

1. **La nómina de la quincena** (obligatoria). Es el Excel con la hoja
   `2026_{QQ}_event…`. Los de la q16 y la q17 traen primero la hoja `SIAP`
   (nómina federal, no se usa) y el de la q18 trae además `pensiones` (tampoco
   se usa: la columna PENSION ya trae el importe). Se toma la primera hoja que
   tenga las columnas de eventuales, ubicadas por el texto del encabezado.
2. **Los dos DETALLE EMPLEADOS de la quincena anterior** (recomendados). Dan
   dos cosas que la nómina no tiene: los nombres corregidos a mano y el orden de
   la cola. Ver abajo.

## Las reglas

Salieron de comparar las entradas contra las salidas armadas a mano de las
quincenas 16 y 17. Viven como constantes al principio de `nomina.js`.

### Juego

`DESC. PROGRAMA` = `SaNAS` → archivos `S`. Todo lo demás → archivos `E`.

### Claves

Cada clave va siempre en la misma casilla, aunque las anteriores vengan vacías:

| Columna | Eventuales | SaNAS | Casilla |
|---|---|---|---|
| `07` | `10200` | `102SA` | percepción 1 |
| `06` | `106EE` | — | percepción 2 |
| `37` | `105CG` | — | percepción 3 |
| `ISR` | `20102` | `201HA` | deducción 1 |
| `PENSION` | `262DE` | — | deducción 2 |
| `FALTAS` | `21700` | — | deducción 3 |
| `DESCUENTOS` | `246OM` | — | deducción 4 |

**`29+`, `SE` y `DES.MERCANTIL` no tienen clave.** Vinieron en cero en las
quincenas 16, 17 y 18, así que nunca se vio cuál les toca. Si un día traen
importe, el lote se detiene con error en vez de inventar una. Pasa lo mismo con
cualquier concepto distinto de sueldo e ISR en SaNAS. Para darla de alta se
agrega `EV: { clave, casilla }` o `SA: …` al concepto en `CONCEPTOS`.

### Renglón del prod_pago

- `sdi` = columna `07`, `puesto` = `CODIGO`, `centro de trabajo` = `CEN.RESP.`.
- El periodo es el `FEC_INICIO`/`FEC_FIN` de cada renglón. Un **retroactivo**
  (tipo 22) es un renglón más de la misma persona, con su propio periodo y
  dentro del mismo producto. En la q16 hubo 5 de eventuales y 10 de SaNAS, y
  así venían en la salida a mano.
- Lo demás son constantes: `CON`, `EVENTUAL`/`HONORARIOS`, `DIURNA`, `NO` y
  ceros.

### Número de empleado

Es un consecutivo que **se rehace cada quincena** y que comparten los dos juegos
(por eso los de SaNAS caen en medio):

1. El bloque principal: IMSS BIENESTAR → IMSS BIENESTAR ADMINISTRATIVOS → OTROS
   PROGRAMAS → SaNAS. Dentro de cada programa va en el orden de la nómina.
2. Al final, la **cola** de CUOTAS, REGULACIÓN SANITARIA y GUARDIAS. Son
   siempre las mismas ~31 personas en un orden fijo, que no es el de la nómina.
   En la q16 venían revueltas y aun así salieron en el mismo orden que en la
   q17. Ese orden se toma del número que tenían en el detalle anterior. Sin
   detalle anterior van en el orden de la nómina, y se avisa.

Un programa que no esté en ninguna de las dos listas va en eventuales, después
de los conocidos y antes de la cola, con aviso.

### Detalle de empleados

El nombre viene como `PATERNO,MATERNO/NOMBRE` y se limpia: sin acentos, la `Ñ`
como `N`, sin puntos (`MA.` → `MA`) y sin espacios de más. Un apellido vacío se
escribe `0`.

**Pero el detalle no se rehace desde la nómina.** Es un catálogo que se ha ido
corrigiendo a mano: en la q17, 34 nombres no salen de la nómina («KARLOS
HUMBRETO» → «CARLOS HUMBERTO», Ñ que en unos se conserva y en otros no). Por eso,
a quien ya estaba en el detalle anterior se le copia el nombre de ahí, y solo a
las altas se les arma desde la nómina. La pantalla lista esas altas con el
nombre como va a quedar, para revisarlo.

### Encabezado del prod_pago

Es la misma disposición de las plantillas: rótulos en la fila 1, producto
(`EV1826`), año, quincena, fecha de pago, número de renglones, neto y tipo de
nómina en la fila 2, y el encabezado de columnas en la fila 3.

Los totales van en las mismas celdas: percepciones en `AH1`, deducciones en
`BL1`, neto en `F2` y, encima de cada «Importe exento», el subtotal de su
«Importe gravado». Las fórmulas de las plantillas sumaban rangos corridos (una
sumaba una sola celda) y daban bien solo porque esas columnas van vacías. Aquí
suman exactamente su columna, y cada una lleva su valor ya calculado, para que el
archivo se lea bien aunque quien lo abra no recalcule.

La **fecha de pago** es, por omisión, el último día de la quincena (el 15 en
las nones, fin de mes en las pares), como en la q16, la q17 y la q18. Se puede
cambiar en pantalla.

### Lo que se corrige al pasar

- El RFC se pasa a mayúsculas. Uno vino con la homoclave en minúsculas en la q16, la q17 y la
  q18, y la salida a mano lo dejó igual. Se avisa.
- Los nombres de archivo no llevan los errores de dedo de las plantillas
  (`20226`, `(1)`). El «1807» se conserva: se repite en todas las quincenas y
  nadie sabe qué significa.
- El «QNA» del encabezado del 16S decía `15`. Aquí sale siempre de la nómina.

## Verificación

El núcleo se prueba sin levantar el sitio, cargando `js/core/` en un contexto
de Node 18+:

```js
const sandbox = { window: {}, console, TextDecoder, TextEncoder, Blob, Response,
                  DecompressionStream, CompressionStream };
vm.createContext(sandbox);
for (const f of ['xlsx.js', 'nomina.js']) {
  vm.runInContext(readFileSync(`modules/eventuales/js/core/${f}`, 'utf8'), sandbox, { filename: f });
}
const { xlsx, nomina } = sandbox.window.EV;
const entrada = nomina.leerEntrada(await xlsx.leerLibro(buffer));
const r = nomina.generar(entrada, nomina.leerDetalleAnterior([{ libro, archivo }]));
```

Resultados contra los archivos reales, celda por celda con openpyxl:

| Prueba | Resultado |
|---|---|
| q17 con el detalle de la q16: los 4 archivos | **idénticos**, salvo el RFC en minúsculas (1 celda en prod y 1 en detalle) |
| q17: nombres del detalle | 2 641 / 2 641, incluidos los 34 corregidos a mano |
| q16 con el detalle de la q17 como referencia del orden | **idénticos**, salvo el mismo RFC; la cola, revuelta en la entrada, sale en su orden |
| q16 sin detalle anterior | prod_pago idéntico salvo el orden de la cola (31 renglones), que es lo esperado |
| Totales del encabezado q16 y q17 (E y S) | percepciones, deducciones y neto **iguales** a los de las plantillas |
| q18: neto generado vs. columna NETO | E 13 962 143.61 = 13 962 143.61 · S 1 671 504.63 = 1 671 504.63 |
| q18 en el navegador (Chrome) | 2 642 renglones, 7 altas, 8 bajas, 4 retroactivos, 1 aviso; los archivos bajados son **byte por byte** los de Node |
| Importe en DES.MERCANTIL (eventuales) o PENSION (SaNAS) | error por renglón y **no se genera nada** |
| Fin de quincena: q04 2026, q04 2028 (bisiesto), q17 | 20260228 · 20280229 · 20260915 |

Tiempos: leer y generar la q18 toma ~220 ms y escribir los 4 .xlsx ~350 ms.

## Límites

- La lista de hallazgos muestra los primeros 300. Los conteos sí son exactos.
- Si se carga solo uno de los dos detalles anteriores, las personas del otro
  juego salen como altas (con el nombre armado desde la nómina) y las bajas
  quedan incompletas.
- El orden de los renglones dentro de cada archivo es el del número de
  empleado. En las salidas a mano no seguía ninguna regla reproducible, y para la
  carga no importa.
