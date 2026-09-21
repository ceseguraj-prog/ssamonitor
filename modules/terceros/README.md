# Módulo: Terceros (`terceros`)

Cierre quincenal de **descuentos a terceros**: lo que se le retiene al trabajador
y se le entrega a alguien más (aseguradora, FOVISSSTE, sindicato, comercializadora).

Entrega dos cosas a la vez:

- en pantalla, el **concentrado** — importe sumado por grupo, UR, rama, tipo,
  banco y concepto, que es lo que se lee para cuadrar la quincena;
- en un zip, los **dos CSV** que consume el resto del trámite: el detalle
  registro por registro y el mismo concentrado.

Portado del sistema `TercerosCesarVersion` (`TercerosSalud.php`,
`TercerosSaludRapido.php`, `generarCSV.php`). De allá **solo** se trajo Terceros:
Timbrado ya vive en este proyecto, Conceptos se va a rehacer distinto y
Concentrados no se ocupa.

## Acceso

`usuarios` vacío en [`modulo.php`](modulo.php): cualquier usuario autenticado
entra. Se aplica en el servidor en las tres entradas (página, `generar.php` y
`descargar.php`) vía `requireModulo('terceros')`.

Ojo si eso cambia: una corrida recorre las seis tablas de nómina del periodo y
`federal` es MyISAM, donde un escaneo toma READ lock. Por eso nada corre solo —
ni al cargar la página ni al cambiar un selector, únicamente con el botón.

## Estructura

```
modules/terceros/
├── modulo.php          descriptor que lee includes/modulos.php
├── index.php           página
├── ajax/
│   ├── generar.php     GET periodo+grupo → JSON concentrado + token del zip
│   └── descargar.php   GET token → descarga el zip
├── css/terceros.css
├── js/terceros.js
└── storage/            paquetes temporales, se borran solos tras 1 hora
```

La lógica vive en [`Classes/TercerosRepository.php`](../../Classes/TercerosRepository.php),
junto a los demás repositorios, no dentro del módulo: consulta nómina como
`ConceptosRepository` y comparte con él la forma de las tablas.

## Cómo se arma un grupo

Cada fila de nómina guarda hasta 50 conceptos en columnas `TR{n}TCP` (clave de 5
caracteres) y `TR{n}IM` (importe). Un slot sin usar trae el centinela `00000`.

Un grupo de terceros es un conjunto de **prefijos de 3 dígitos**:

| Grupo | Prefijos |
|---|---|
| `COMERCIALIZADORAS_46` | 246 |
| `POTENCIACION_50` | 250 |
| `AXA_SEGUROS_74` | 274 |
| `SEGURO_RETIRO_77` | 277 |
| `SEG_INDV_METLIFE_51_57` | 251, 257 |
| `FOVISSTE_55_56_64_65` | 255, 256, 264, 265 |
| `SINAISSA_267` | 267 |
| `AUXILIO_POR_DEFUNCION_270` | 270 |
| `CUOTAS_SINDICALES_258` | 258 |
| `FEGAC_221` | 221 |

El orden de la tabla es el orden en que salen los grupos en los archivos. Un
grupo nuevo se agrega **al final**: así sus renglones se pegan al final de los
dos CSV y no mueven ni un byte de los que ya estaban. Verificado al dar de alta
FEGAC.

Esa tabla es el catálogo **de fábrica**, el que vive en `TercerosRepository::GRUPOS`.
Encima se aplica lo que se dé de alta desde la pantalla (ver abajo), así que para
saber qué grupos hay de verdad se consulta `TercerosRepository::catalogo()`.

## Dar de alta grupos sin tocar código

El botón **Grupos…** de la pantalla abre el catálogo. Ahí se crea un grupo, se
le cambian los prefijos a uno existente, o se quita.

Se guarda en **`config/terceros-grupos.json`**, no en la base: `catalogos` es de
solo lectura para este sistema. Está git-ignorado, como `config.php`; sin él
quedan solo los grupos de fábrica, que es un arranque seguro.

```json
{
  "version": 1,
  "grupos": {
    "FONAC_262": ["262"],
    "FOVISSTE_55_56_64_65": ["255", "256", "264", "265", "266"]
  }
}
```

Dos tipos de entrada y la diferencia importa:

- **Clave nueva** → grupo nuevo, se agrega al final del catálogo.
- **Clave que ya existe en `GRUPOS`** → reemplaza sus prefijos **conservando su
  sitio** en el orden, para no mover los renglones de los demás.

*Quitar* borra un grupo agregado. En uno de fábrica el botón dice *Restablecer*
y lo devuelve a sus prefijos originales: el grupo sigue existiendo en el código.

### Quién puede

Solo los logins del arreglo `admin` de [`modulo.php`](modulo.php) — hoy
`csegura`. Consultar el reporte lo hace cualquiera; redefinir qué conceptos
entran en cada grupo mueve las cifras del cierre y de los dos CSV.

La restricción se aplica en el servidor en `ajax/grupos.php`, no solo escondiendo
el botón. A quien no es admin ni se le pinta el markup del panel.

### Lo que valida antes de guardar

- **Un prefijo no puede estar en dos grupos.** Es la comprobación que más
  importa: el detalle emite un renglón por cada grupo que reclame el concepto,
  así que el mismo importe se sumaría dos veces y el total del cierre saldría
  inflado sin que nada lo delate. El mensaje dice de qué grupo es ya.
- **Un prefijo son exactamente 3 caracteres** alfanuméricos, porque el código
  agrupa por `substr($concepto, 0, 3)`. Ni 2 ni 5.
- El nombre se normaliza a `MAYUSCULAS_CON_GUION_BAJO` (es parte del nombre del
  archivo CSV) y no pasa de 60 caracteres.
- El login se valida contra `users` y la escritura es atómica bajo candado,
  igual que en [Permisos](../permisos/README.md).

### Consultar prefijos antes de dar de alta

El botón **Consultar prefijos** dice cuántos registros y cuánto importe mueve
cada prefijo en el periodo elegido arriba, desglosado por clave completa, y
avisa si ya pertenece a un grupo.

Existe por lo que costó FEGAC. El número que se tenía a mano ("21" → 221) y el
nombre apuntaban a conceptos distintos: `cat_conceptos` describe el 21 como
FONAC y las tablas `fegac*` referencian 262. Sin ver los importes de cada uno no
había forma de notarlo. Ahora se comprueba antes de guardar, no después de
entregar un cierre mal.

### FEGAC es el 221 — no lo "corrijas" a 262

El prefijo de FEGAC es **221**, con las claves `221FA` y `22121`. Queda escrito
aquí porque la base da dos pistas falsas que invitan a cambiarlo:

- `cat_conceptos` describe el concepto 21 como **FONAC** ("DESCUENTO AL
  TRABAJADOR INSCRITO AL FONAC", "DEVOLUCIÓN DEL FONAC DEL CICLO ACTUAL"). Ese
  catálogo es federal y está viejo; no refleja cómo se usa el concepto aquí.
- Las tablas `fegac*` referencian `26201`, `16201`, `16202`, `16203`, y
  `fegac2021` tiene columnas con esos nombres. Son de procesos anteriores del
  fondo, no el concepto con el que hoy se descuenta en nómina.

El prefijo 262 existe y está sin grupo a propósito. Si algún día se ocupa, va
aparte.

Dos reglas que no se ven en el catálogo:

- **UR 610 se excluye siempre**, igual que en el reporte original.
- **Los regularizados llegan sin rama** (`''` o `'0'`). Se deduce de la CLUES con
  el criterio de OficiosQuincenales: `SPC` que empieza en `GRSSA` es rectoría
  (`REC`), cualquier otro es unidad médica (`UM`).

## Por qué corre en segundos

La primera versión tardaba minutos. Lo que se conservó del rescate:

1. `ANIO` y `QNA` son `varchar`. Comparados contra números MySQL castea la
   columna fila por fila y pierde el índice: recorre los 4.6 millones de
   renglones de `federal`. Contra cadenas la misma consulta baja de ~53 s a
   ~0.9 s. Además el dato está sucio y la quincena aparece como `'09'` y como
   `'9'`, así que se buscan las dos formas con `IN`.
2. Las seis tablas se recorren **una** vez para todos los grupos, no una por
   grupo: 12 consultas en lugar de 108.
3. Solo se piden las columnas `TR` que el periodo realmente usó, que es lo que
   mide `MAX(TTR)`.
4. El concentrado se acumula en un hash en una sola pasada.
5. Las filas se leen de una en una con `Database::stream()`. Un periodo completo
   de las seis tablas son cientos de miles de renglones de ~110 columnas;
   materializarlos todos en arreglos PHP a la vez se lleva cientos de MB.

## Los archivos

Los nombres y las columnas son **idénticos** a los del sistema anterior, porque
alguien los consume aguas abajo:

```
TercerosN2026Qna17_Todos.csv
ANIO,QNA,TIPO,RFC,UR,RAMA,BANCO,GRUPO,CPTO,IMPORTE,SPC

TercerosN2026Qna17_RPT.csv
ANIO,QNA,GRUPO,UR,RAMA,TIPO,BANCO,CPTO,IMPORTE
```

`_Todos` se cambia por el nombre del grupo cuando se genera uno solo. Sin
comillas, sin BOM y con fin de línea CRLF, como antes. Todas las columnas son
ASCII (no hay nombres de persona), así que forzar utf8mb4 en la conexión —cosa
que el sistema viejo no hacía— no cambia un byte.

El zip se llama `TercerosN2026Qna17.zip`. Ese nombre sí es nuevo: el anterior era
`productos.zip` para todos los reportes y no lo lee nadie, solo envuelve.

### Lo que el concentrado ya no pierde

En la versión vieja, los arreglos de UR / rama / tipo / banco eran **filtro**: lo
que no figurara en ellos se descartaba sin avisar. En la quincena 17 de 2026 eso
dejaba fuera el **60.7 % del importe** (UR 411, FO3, la rama `'0'` de los
regularizados y el tipo 66).

Aquí esos arreglos son **solo orden**: los valores conocidos salen primero y en
el orden de siempre, y cualquier valor nuevo aparece al final de su nivel en vez
de desaparecer. El total del concentrado siempre cuadra con el del detalle.

## Detalles heredados que conviene saber

- **`F03` con cero.** El catálogo de orden de UR trae `'F03'` donde debería decir
  `'FO3'` con o. Se conservó tal cual para no mover el orden de los renglones del
  archivo. Hoy solo hace que los formalizados 2016 salgan al final de su nivel en
  vez de en la sexta posición; no se pierde ningún importe. Corregirlo es cambiar
  esa cadena en `TercerosRepository::ORDEN`, sabiendo que cambia el orden del CSV.
- **Importes en notación científica.** Un renglón cuya suma no da exactamente
  cero por acumulación de flotantes (algo como `2.9e-11`) se escribe así en el
  CSV. Es el comportamiento del reporte original y se dejó igual; si un día
  estorba, el arreglo es redondear a dos decimales antes de comparar contra cero.
- **El detalle no viaja al navegador.** Son ~20 mil renglones por quincena y lo
  que se lee en pantalla es el concentrado (~1,700). El detalle completo va en el
  CSV, que es para lo que existe.

## Lo que se dejó fuera a propósito

El sistema original hacía `ALTER VIEW` sobre `catalogos` en cada corrida para
meterle el filtro de periodo a la vista. Eso es DDL contra una base de solo
lectura y es estado global: dos personas generando al mismo tiempo se pisan la
vista a media consulta. Aquí el periodo va en el `WHERE` de la consulta y no se
toca ningún objeto de la base.
