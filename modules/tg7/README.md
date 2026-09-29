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
hiciera falta moverla al servidor, esos archivos se traducen sin arrastrar nada
del DOM.

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
    │   ├── padronXlsx.js   lector del padrón de préstamos vigentes en .xlsx
    │   └── cruce.js        unión de nómina + préstamos → registros TG-7
    └── tg7.js          interfaz: carga archivos, pinta, descarga
```

Los archivos de `js/core/` se cargan en ese orden y se registran en
`window.TG7`. Cada uno depende solo de los anteriores.

## Las fuentes

| | Nómina de la pagaduría | Padrón de préstamos vigentes | Órdenes de descuento |
|---|---|---|---|
| Nombre | `ISSSTE{QQ}{PAG}.ORD` / `.EXT` / `.CAN` / `.RET` | `RAMO 022 PRESTAMOS VIGENTES {MES} {AAAA}.xlsx` | `1{ramo}{pagaduría}_{folio}.txt` o `RAMO 022 OD {QQAAAA}.docx` |
| Formato | ancho fijo, 260 | Excel, una hoja | ancho fijo, 180 / tabla de Word |
| Trae | trabajador, sueldo, **retención (P.C.P.)**, CURP, NSS | **número de préstamo** de todo lo vigente al corte | **número de préstamo** de las altas de una quincena |
| Periodos | — | `AAAAQQ` | `QQAAAA` |
| Llave | R.F.C. | R.F.C., y CURP de respaldo | R.F.C., y CURP de respaldo (solo el `.txt`) |
| ¿Obligatoria? | **sí** | no, pero es la que da casi todos los números | no; cubren lo posterior al corte del padrón |

Cada archivo TG-7 es de **un** tipo de nómina. Se pueden soltar los cuatro
archivos de la quincena juntos: solo entran los registros cuyo `TIPO NOMI`
(posición 115) coincide con el del encabezado, y los demás se reportan por
archivo. Antes entraban todos, y en la q18 eso metía 4 cancelaciones y 1
extraordinaria al archivo ordinario —y, como `.CAN` y `.EXT` van antes que
`.ORD` en orden alfabético, desplazaban 2 registros del `.ORD`.

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

### El padrón de préstamos vigentes

ISSSTE entregó en septiembre de 2026 el padrón: `RAMO 022 PRESTAMOS VIGENTES
SEPTIEMBRE 2026.xlsx`, 3 220 préstamos. Es lo que faltaba — contra la nómina
`.ORD` de la q18 da número de préstamo a **2 796 de 2 947** trabajadores con
retención; con todas las órdenes de 2026 sin él, solo a 1 179.

Lo lee `padronXlsx.js` con el mismo descompresor que los `.docx`, ubicando las
columnas por su encabezado. Tres cosas que conviene saber:

- **Es un corte.** El de septiembre llega hasta la quincena inicial 202616. Las
  altas de las quincenas siguientes no están: se cargan sus órdenes junto con el
  padrón. La lista de archivos muestra hasta qué quincena llega.
- **Los periodos vienen en `AAAAQQ`**, no en `QQAAAA` como las órdenes. El lector
  los entrega ya normalizados; tratarlos como órdenes los voltearía y dejaría
  todos los préstamos fuera de vigencia.
- **El RFC no siempre coincide con el de la nómina.** 72 vienen sin homoclave y
  otros con homoclave distinta. Si el RFC no aparece se busca por CURP (88 en la
  q18) y, como último recurso, por los 10 primeros caracteres siempre que el
  importe cuadre exacto (4). Se declara el RFC de la nómina y se avisa.
- **No refleja las bajas.** Contra las órdenes q01-q15 el padrón coincide en
  1 191 de 1 198 altas y cambios, pero sigue listando como vigentes **22
  préstamos que ya tienen orden de baja**. Las bajas son reales: en ninguno de
  los 27 casos la nómina q18 sigue reteniendo el importe dado de baja (15
  renovaron con un préstamo nuevo, 10 ya no tienen retención, 2 ya no están).
  Por eso las bajas mandan sobre el padrón. También le faltan 7 altas vigentes
  al corte; 4 tienen retención en la q18, pero ninguna con el mismo importe.

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

Con el padrón de septiembre y las órdenes de q01 a q15, q19, q20 y q21, el
hueco en la q18 es de **148 de 2 947** trabajadores ($288 487.25 retenidos):
préstamos que empezaron después del corte del padrón. Se cierran con las
órdenes de las quincenas **16, 17 y 18**, que son las que faltan.

El CSV lleva RFC, CURP, nombre y pagaduría, y la bitácora trae el RFC en cada
hallazgo, para poder buscar a cada trabajador.

## Un trabajador, varios préstamos

El `P.C.P.` de la nómina es un solo importe: lo retenido en total. Contra eso se
decide qué se declara (`elegirPrestamos()` en `cruce.js`):

| Préstamos vigentes | Qué se emite |
|---|---|
| Ninguno | una línea sin número, con el `P.C.P.` |
| La suma cuadra con el `P.C.P.` | una línea por préstamo, cada una con su importe |
| Varios, y **solo uno** cuadra | solo ese; los demás se avisan como liquidados |
| Uno solo que no cuadra | ese número, con el `P.C.P.` como importe, y aviso |
| Varios y ninguno cuadra | todos con su importe, y aviso: no hay con qué decidir |

La tercera fila es la que más pesa. El padrón lista como vigente un préstamo
hasta su quincena final original aunque se haya liquidado al **renovar**: en la
q18, 152 trabajadores tienen dos o más préstamos «vigentes» y en los 152 solo el
más reciente cuadra con lo retenido. Declararlos todos inflaría el archivo.

La cuarta fila declara lo retenido porque eso es lo que el TG-7 informa. El
número puede estar desactualizado si hubo una renovación después del corte del
padrón: son 33 casos en la q18 y van con aviso.

## Altas, bajas y cambios

Las órdenes traen un tipo (columna TPOD del reporte; posiciones 102-103 del
`.txt`, `10`/`20`/`30`): **A** alta, **B** baja, **C** cambio. Una baja viene con
importe 0 y plazo 1 y **termina** el préstamo desde su quincena, así que no se
declara: se descarta como fuente y deja fuera a ese préstamo aunque el padrón lo
siga listando. Entre q14 y q20 hay 9 bajas.

Cuando el mismo préstamo llega en varias fuentes, primero se filtra la vigencia
y después se quitan los repetidos, quedándose con la orden más reciente. El
préstamo se puede encontrar por cualquiera de los RFC y CURP con que vino: el
padrón trae RFC con `O` donde la nómina trae `0`, y el `.docx` no trae CURP. Así
el archivo sale idéntico sin importar el orden en que se carguen los archivos
(`notas/verificacion/orden_carga.mjs`).

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

- **El organismo de la clave de aportante.** ISSSTE confirmó entidad `12`
   (Guerrero) y municipio `029` (Chilpancingo de los Bravo); el `520` del
   organismo viene del archivo de ejemplo y la pantalla lo marca como pendiente.
   El ramo de crédito (`022`) se toma de la nómina.
0. **Las altas posteriores al corte del padrón.** El padrón ya llegó, pero es un
   corte: para cada quincena que se declare hacen falta las órdenes de las
   quincenas entre el corte y ella, o un padrón más reciente.
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

**El número de ISSSTE no siempre coincide** entre el padrón y la nómina: 51
casos en la quincena 18, algunos con valores imposibles en la nómina (`3`). La
spec del SIPE lo declara único y permanente, así que no deberían diferir. Se usa
el del padrón o la orden —la nómina lo trae en ceros en 6 662 de 10 242
registros, y eso cuenta como «no lo trae», no como discrepancia— y se levanta
aviso, porque SERICA valida la combinación número + préstamo contra su tabla.

## Verificación

El núcleo se prueba contra datos reales sin levantar el sitio, cargando los
archivos de `js/core/` en un contexto de Node (18 o posterior, que ya trae
`DecompressionStream` para los `.docx` y `.xlsx`):

```js
const sandbox = { window: {}, console, TextDecoder, Blob, Response, DecompressionStream };
vm.createContext(sandbox);
for (const f of ['formato.js','anchoFijo.js','layouts.js','nombres.js','validaciones.js',
                 'reporteOrdenes.js','padronXlsx.js','cruce.js']) {
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
| Lector de `.xlsx` vs. openpyxl sobre el padrón de septiembre | **3 220 / 3 220** préstamos, 0 omitidos |
| Q18, los 19 archivos + padrón + órdenes q01-q15, q19-q21 (104 archivos) | 5 de otro tipo apartados · **2 947 emitidos, 2 799 con número**, 0 rechazados |
| `.txt` vs `.docx` de la misma quincena, q01-q12 y q19-q21 | **15 / 15 quincenas idénticas** |
| Mismo lote en tres órdenes de carga distintos | archivo **idéntico** |
| …ningún préstamo ni RFC repetido en el archivo | suma declarada = `P.C.P.` retenido, **$5 327 627.87** |
| Las 6 claves del SIPE + una inválida | un error por causa, con su motivo |

## Límites

- El proceso bloquea el hilo del navegador mientras cruza. Con los 19 archivos
  de una quincena completa (10 242 registros) tarda ~200 ms; el velo de carga se
  pinta antes de empezar para que no parezca colgado.
- Las tablas de la pantalla listan los primeros 200 renglones de cada bloque.
  Los conteos sí son exactos y los CSV llevan todo.
- Volver a seleccionar archivos en una zona **reemplaza** la selección anterior,
  no la suma. El padrón y las órdenes se sueltan en una sola selección.
- Un trabajador sin préstamo cuya nómina trae el número de ISSSTE en ceros sale
  con `0` en ese campo (5 en la q18). Esas líneas SERICA las rechaza de todos
  modos por el número de préstamo vacío.

## Una trampa ya pagada

La expresión que valida la CURP no incluía `NT`, que es **Nayarit** — se parece
a un error de dedo y no lo es. Rechazaba como «estructura inválida» a todos los
nacidos en ese estado: 3 trabajadores con retención real en la quincena 18, que
se quedaban fuera del archivo sin motivo. Las 33 claves están ahora en
`ENTIDADES_CURP`, en una sola constante, para que se puedan contar.
