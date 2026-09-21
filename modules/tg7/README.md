# Módulo: Nómina de préstamos personales — TG-7 (`tg7`)

Toma la nómina de la pagaduría y las órdenes de descuento que emite ISSSTE, las
cruza y produce el archivo `NOMPPR-….txt` que se entrega en SERICA, más una
bitácora CSV de lo que hay que corregir.

**Entra en vigor la primera quincena de octubre de 2026.** Lo ordenan los
oficios DPESyC/0876/2026 y DIRI/741/2026 (18 de agosto de 2026), difundidos con
el DPESyC/SOC/251/2026. A partir de esa fecha el layout anterior deja de
recibirse.

## Los datos no salen de esta computadora

Todo el procesamiento ocurre en el navegador: **no hay endpoint, no se sube
nada y no se guarda nada**. Por eso el módulo no tiene carpeta `ajax/` ni
`storage/`, a diferencia del corrector SQL.

No es una decoración. Los archivos de entrada traen RFC, CURP, NSS, sueldo y
tipo de nombramiento de más de 10 000 trabajadores. Un archivo así no tiene por
qué tocar el disco del servidor ni los logs de acceso, así que no lo toca. El
sello de la pantalla lo dice (`fuente => 'navegador'`).

La consecuencia práctica: la lógica vive en `js/core/` y no en PHP. Si algún día
hiciera falta moverla al servidor, esos seis archivos se traducen sin arrastrar
nada del DOM.

## Acceso

`usuarios` va vacío en [`modulo.php`](modulo.php): **cualquier usuario
autenticado entra**. Toda pagaduría tiene que entregar su nómina de préstamos
personales, y el módulo no consulta ninguna base ni ningún servicio de paga, así
que no hay razón de costo ni de carga para restringirlo. Para cerrarlo se listan
logins en ese arreglo.

## Estructura

```
modules/tg7/
├── modulo.php          descriptor que lee includes/modulos.php
├── index.php           página (solo el armazón: no procesa nada)
├── css/tg7.css
└── js/
    ├── core/           núcleo puro: sin DOM, sin red, sin PHP
    │   ├── formato.js      escritor del archivo TG-7 y catálogos de salida
    │   ├── anchoFijo.js    lector genérico de archivos de ancho fijo
    │   ├── layouts.js      las dos fuentes como datos + catálogos del SIPE
    │   ├── nombres.js      separación de nombre apoyada en la CURP
    │   ├── validaciones.js validaciones del formato + bitácora
    │   ├── reporteOrdenes.js lector de los reportes de órdenes en .docx
    │   └── cruce.js        unión de nómina + órdenes → registros TG-7
    └── tg7.js          interfaz: carga archivos, pinta, descarga
```

Los archivos de `js/core/` se cargan en ese orden y se registran en
`window.TG7`. Cada uno depende solo de los anteriores.

## Las dos fuentes

| | Nómina de la pagaduría | Órdenes de descuento |
|---|---|---|
| Nombre | `ISSSTE{QQ}{PAG}.ORD` / `.EXT` / `.CAN` / `.RET` | `1{ramo}{pagaduría}_{folio}.txt` |
| Ancho | 260 caracteres | 180 caracteres |
| Trae | trabajador, sueldo, **retención (P.C.P.)**, CURP, NSS | **número de préstamo**, importe, plazo |
| Llave | R.F.C. | R.F.C. |
| ¿Obligatoria? | **sí** | no, pero sin ella no hay número de préstamo |

### Quién va en el archivo

Lo define el formato sin ambigüedad: *«el archivo a declarar solo deberá de
contener registros de trabajadores o pensionados a los que se le retuvo el
concepto de prestamo personal»*.

Ese dato está en la **nómina**, en el campo `P.C.P.` (posiciones 164-169). Si
trae importe, hubo retención efectiva y el trabajador va al TG-7. En la quincena
18 eso son **2 950 de 10 242** registros: los otros 7 292 no tienen préstamo y
no se declaran.

### Las órdenes son altas, no el padrón

Esto es lo que más fácil se malentiende, y equivocarse cuesta el archivo
completo. **ISSSTE manda cada quincena solo las altas de esa quincena**, y el
préstamo se sigue descontando hasta su `periodoHasta` — hasta 48 quincenas.
Medido sobre los archivos reales:

| | Siguen retenidas en la q18 | …con el mismo importe |
|---|---|---|
| 28 órdenes de la q13 | **27** | **27 de 27** |
| 59 órdenes de la q19 | 6 | 0 de 6 |

Las de la q13 llevaban cinco quincenas descontándose sin que llegara ninguna
orden nueva. Las de la q19 casi no aparecen en la q18 porque todavía no
empezaban.

**Por eso hay que cargar todas las órdenes acumuladas, de todas las quincenas.**
El módulo se queda solo con las vigentes en el periodo que se declara
(`desde <= periodo <= hasta`) y descarta las vencidas y las futuras.

### Los dos formatos de la orden son el mismo dato

ISSSTE entrega las órdenes de dos formas y el módulo lee ambas, mezcladas:

| | |
|---|---|
| `1{ramo}{pagaduría}_{folio}.txt` | ancho fijo de 180 |
| `RAMO {ramo} OD {QQAAAA}.docx` | el reporte impreso, en tabla |

Son idénticas: de la quincena 19 existen las dos versiones y coinciden en los 7
campos en **58 de 58** renglones. Importa porque el `.docx` suele ser lo único
que se conserva de las quincenas viejas. Con los archivos que había en disco,
leer también los `.docx` sube la cobertura de **27 a 280** trabajadores con
número de préstamo.

El `.docx` es un ZIP con `word/document.xml`; se descomprime con
`DecompressionStream`, que ya trae el navegador — el proyecto no carga librerías
externas. Un renglón cuenta como dato si tiene 11 columnas y la segunda es un
RFC válido, así se descartan solos los encabezados y los subtotales. Si el mismo
préstamo viene en el `.txt` y en el `.docx`, se cuenta una sola vez.

### Y el número de préstamo no está en ningún otro lado

Verificado, no supuesto: se tomaron los 27 préstamos conocidos de la q13 y se
buscó cada número dentro de la línea completa de 260 caracteres de su
trabajador en la q18. **Aparece en 0 de 27.** El campo `claveCobro` tampoco lo
trae — tiene forma `999999I9999999 M99999999999999` en 2 425 de 2 597 casos: es
clave de plaza y adscripción.

Si falta la orden, la línea sale con el campo vacío. Ver *Cuando falta la
orden*, abajo.

El layout de 260 está en la especificación oficial *SIPE-SIC / Información de
Nómina* (julio 2009). **Las 32 posiciones se verificaron una por una contra ese
documento**, y la prueba de regresión la define la propia spec en su campo 25:

```
SUMANDO == SER_MED + FON_PREST + OTROS + P.C.P. + A.S.M. + I.H. + I.S.H.
```

Cierra en **10 242 / 10 242** registros reales de la quincena 18.

El de 180 no tiene especificación publicada; sus posiciones se dedujeron de 87
registros reales y cumplen el patrón en 87/87. Su cabecera se alinea con la del
SIPE (ramo 3 + pagaduría 6 + número ISSSTE 9), incluido el `0` de relleno de la
pagaduría.

## Cuando falta la orden

Un trabajador con `P.C.P. ≠ 0` cuya orden no se cargó **se emite igual, con el
número de préstamo vacío**. Es una decisión de operación, tomada a sabiendas:
el campo es obligatorio en el formato y SERICA valida que la combinación número
de ISSSTE + préstamo exista en su tabla, así que **esas líneas se van a
rechazar**. Se prefiere que reboten con nombre y apellido a que desaparezcan en
silencio del archivo.

Para que el hueco no pase inadvertido, el módulo:

- levanta un aviso por cada caso, con el importe retenido;
- los lista en un bloque propio de la pantalla, con el total en pesos;
- los exporta como `ordenes-faltantes-{periodo}.csv`, que es lo que se le
  reclama a ISSSTE.

Con los archivos que hay hoy en disco el hueco es casi total: de los 2 950
trabajadores con retención en la q18, solo **27** tienen orden cargada. No es un
defecto del módulo — es que el histórico de órdenes está incompleto. La salida
de fondo es pedirle a ISSSTE el **padrón de préstamos vigentes**, no solo las
altas de cada quincena; es lo que SERICA ya tiene del otro lado, porque contra
esa tabla valida.

## Un trabajador, varios préstamos

El `P.C.P.` de la nómina es un solo importe: la **suma** de lo retenido. Si el
registro de órdenes trae dos préstamos vigentes para ese RFC, se emite **una
línea por préstamo** con su propio importe, y se compara la suma contra el
`P.C.P.`. Si no cuadra, se avisa: puede faltar un alta o sobrar un préstamo ya
liquidado. Cuando no hay ninguna orden, se emite una sola línea con el `P.C.P.`
completo.

## Decisiones que conviene conocer

**Nunca se aborta el lote.** Con 10 000+ registros, detener todo por un dato
malo es inservible. Cada registro se valida por separado: los que tienen error
salen a la bitácora y los demás se emiten. Una línea con ancho incorrecto se
reporta y se omite, sin cortar la lectura del resto.

**Los layouts son datos, no código.** Están en `layouts.js` con la evidencia de
cada posición pegada al campo. Una revisión del layout se atiende corrigiendo
esa tabla, sin tocar el lector.

**No se inventa ninguna clave.** Cuando un dato no se puede derivar con
certeza, el registro se rechaza con el motivo escrito. Un valor adivinado que
pase el catálogo es peor que un campo vacío, porque nadie lo revisa.

**Los archivos se leen como latin1**, un byte por carácter. Leerlos como UTF-8
haría que cualquier byte alto corriera todas las posiciones del ancho fijo.

**La CLABE se maneja siempre como texto.** Son 18 dígitos; como número, JS
pierde precisión a partir de 2^53 y emite `9.87654321012345e+17`. El archivo de
referencia de ISSSTE trae `987654321012345000`, que es exactamente lo que deja
un `double`.

## Tipo de nombramiento: del SIPE al TG-7

Los dos catálogos no son el mismo y la traducción es **por nombre**, no
aritmética. Coincide con «×10» en cuatro de los seis valores solo porque los
nombres de esos cuatro son idénticos:

| SIPE (pos. 114) | → TG-7 | Quincena 18 |
|---|---|---|
| 1 Base o Plantel | **10** Base | 2 967 |
| 2 Confianza o Supernumerario | **20** Confianza | 61 |
| 3 Interino o Provisional | *sin equivalente* | 10 |
| 4 Lista de raya o base | **40** Base / Lista de Raya | 0 |
| 5 Lista de raya eventual honorarios | *sin equivalente* | 0 |
| 6 Otros | **60** Otros | 7 204 |

El valor `6` —el 70 % de la plantilla— está documentado en la spec del SIPE y
significa literalmente «Otros». Las claves 3 y 5 no tienen equivalente en el
catálogo del TG-7 y **no se adivinan**: esos registros se rechazan con el motivo
puesto. En la quincena 18 eso afecta a 10 registros de 10 242.

## Periodos

El formato es explícito: *«Para nómina ordinaria, el periodo debe de coincidir
con el del encabezado. Nunca un periodo posterior.»* El archivo oficial de
referencia lo confirma: `periodoDesde == periodoHasta == 202613` en **36 502 de
36 502** registros.

Por eso ambos campos salen del encabezado y **no** del plazo del préstamo que
traen las órdenes. Ese plazo (`202613`–`202812`, hasta 48 quincenas) es otra
cosa, y declararlo aquí sería un periodo cuatro años posterior al del
encabezado: rechazo inmediato en SERICA.

## Descuento FOVISSSTE

El importe de vivienda (`I.H.`) solo va en ese campo cuando la clave `T.H.` es
de FOVISSSTE: **55**, **56** o **64**. Las demás claves del catálogo — 06
Hipotecario, 08 Hipotecario avalado, 10 Rentas ISSSTE, 46 Tlatelolco — son
crédito del propio ISSSTE y no son lo mismo. Si aparece importe con una clave
que no es FOVISSSTE, se emite `0.00` y se levanta aviso.

## Lo que sigue abierto con ISSSTE

Ninguno de estos puntos impide generar el archivo hoy; los cuatro se entregan
con aviso o con rechazo acotado.

0. **El padrón de préstamos vigentes.** Es lo que más pesa hoy: las órdenes que
   llegan cada quincena son solo las altas, y sin el histórico completo el
   número de préstamo no se puede resolver para quien pidió su crédito hace
   quincenas. *¿Nos pueden entregar la tabla de préstamos vigentes por
   pagaduría, y no solo las altas del periodo?*
1. **CLABE interbancaria.** No existe en ninguna de las dos fuentes: las 260
   posiciones del SIPE están descritas una por una y ninguna es una cuenta
   bancaria. En el layout del TG-7 su columna de *Validación* está en blanco,
   mientras que todos los demás campos obligatorios dicen «Que el campo no este
   vacío», así que se emite vacía y se avisa en cada registro. **Confirmar por
   escrito que es opcional**, o pedir la tercera fuente de donde sale.
2. **Claves 3 y 5 del tipo de nombramiento.** ¿`3 Interino o Provisional` va
   como 35 (Eventual) o 60 (Otros)? ¿`5 Lista de raya eventual honorarios` va
   como 50 o como 25?
3. **Código de pagaduría.** Los datos traen `S1212`–`S1217` (alfanumérico); el
   archivo de referencia trae `50002`, `15000`, `05000` (numérico). El formato
   dice `Alfanumérico(5)` y ambos caben, pero no son el mismo código. Confirmar
   cuál espera el catálogo de pagadurías de SERICA.
4. **Pensión alimenticia.** El layout de origen no tiene ese campo y el SUMANDO
   cierra al 100 % con las otras siete deducciones: no hay un campo oculto. Se
   emite `0.00` con aviso. Confirmar que es correcto para esta pagaduría.

Hay además validaciones que **no se pueden hacer aquí** porque requieren
catálogos que solo tiene SERICA: que organismo + entidad + municipio sea un
aportante válido, que ramo + pagaduría exista, que número de ISSSTE + préstamo
esté en la tabla de préstamos vigentes, y que el salario caiga entre un salario
mínimo y 10 UMAs.

## Calidad del dato en el origen

Dos cosas que el módulo detecta pero **no puede arreglar**; hay que reclamarlas
a quien genera la nómina.

**La `Ñ` se pierde.** Los archivos llegan en ASCII puro con la `Ñ` sustituida
por un espacio: `AYALA MU OZ` era `AYALA MUÑOZ`, `A ORVE` era `AÑORVE`. Se
detectan 72 casos en la quincena 18 y se marcan, pero el nombre saldrá
incompleto mientras el origen no se corrija.

**El número de ISSSTE no siempre coincide** entre la orden y la nómina: 6 de 61
comparables en la quincena 18. La spec del SIPE lo declara único y permanente,
así que no deberían diferir. Se usa el de la orden —la nómina lo trae en ceros
en 6 662 de 10 242 registros— y se levanta aviso, porque SERICA valida la
combinación número + préstamo contra su tabla de préstamos.

## Verificación

El núcleo se prueba contra datos reales sin levantar el sitio, cargando los seis
archivos de `js/core/` en un contexto de Node:

```js
const sandbox = { window: {}, console };
vm.createContext(sandbox);
for (const f of ['formato.js','anchoFijo.js','layouts.js','nombres.js','validaciones.js','cruce.js']) {
  vm.runInContext(readFileSync(`modules/tg7/js/core/${f}`, 'utf8'), sandbox, { filename: f });
}
const TG7 = sandbox.window.TG7;
```

Resultados contra los archivos reales:

| Prueba | Resultado |
|---|---|
| Escritor vs. archivo oficial de ISSSTE (36 502 registros) | **6 438 638 / 6 438 638 bytes, idéntico** |
| `SUMANDO` == suma de las 7 deducciones | **10 242 / 10 242** |
| Separación de nombre con aval de CURP | **10 233 / 10 242 (99.9 %)** |
| Quincena 18 completa, sin órdenes | 2 950 con retención → **2 950 emitidos, 0 rechazados**, 203 ms |
| Quincena 18 completa, con las 87 órdenes `.txt` | 28 vigentes, 27 con número de préstamo, **2 950 emitidos** |
| Quincena 18 con `.txt` + los 3 reportes `.docx` | 411 órdenes, 286 vigentes, **280 con número de préstamo** |
| Lector de `.docx` vs. el `.txt` de la misma quincena | **58 / 58 idénticos**, 0 diferencias |
| Las 6 claves del SIPE + una inválida | un error por causa, con su motivo |

## Límites

- El proceso bloquea el hilo del navegador mientras cruza. Con los 19 archivos
  de una quincena completa (10 242 registros) tarda ~200 ms; el velo de carga se
  pinta antes de empezar para que no parezca colgado.
- Las tablas de la pantalla listan los primeros 200 renglones de cada bloque.
  Los conteos sí son exactos y los CSV llevan todo.
- Cargar `.ORD` y `.RET` de la misma quincena hace que algún RFC con retención
  aparezca dos veces. Se conserva el primero y se avisa; si necesitas la
  extraordinaria por separado, genérala en su propia corrida con
  `Tipo de nómina = 2`.

## Una trampa ya pagada

La expresión que valida la CURP no incluía `NT`, que es **Nayarit** — se parece
a un error de dedo y no lo es. Rechazaba como «estructura inválida» a todos los
nacidos en ese estado: 3 trabajadores con retención real en la quincena 18, que
se quedaban fuera del archivo sin motivo. Las 33 claves están ahora en
`ENTIDADES_CURP`, en una sola constante, para que se puedan contar.
