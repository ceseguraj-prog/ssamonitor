# Conceptos — búsqueda de claves de pago

Localiza una clave de concepto en las seis tablas de nómina de `catalogos` y
devuelve **una fila por coincidencia**: si un RFC trae el concepto en tres slots,
salen tres filas. Reemplaza la consulta manual que se corría a mano con `SET
@anio_txt` / `SET @patron_busqueda`.

## Solo lectura

**Este módulo nunca escribe en la base.** No crea, altera ni borra tablas ni
registros: solo ejecuta `SELECT`. No hay tabla índice, ni materializada, ni ETL.
El filtrado, el orden, la paginación y el CSV se hacen sobre la respuesta que ya
está en memoria, así que ninguna de esas acciones vuelve a tocar la base.

## Acceso

`usuarios` tiene lista blanca a propósito: cada búsqueda escanea un ejercicio de
las seis tablas (~12 GB en total, y `federal` es MyISAM, donde un escaneo toma
READ lock y detiene escrituras). Para abrirlo a todos, vacía el arreglo en
[`modulo.php`](modulo.php).

## Cómo están guardados los conceptos

Cada fila guarda hasta 50 conceptos en grupos de **tres** columnas:

| Columna | Tipo | Qué es |
|---|---|---|
| `TR{n}TCP` | `varchar(5)` | concepto: 3 dígitos de clave + 2 de subclave (`26700`, `267CG`) |
| `TR{n}IM` | `double` | importe |
| `TR{n}AQ` | `varchar(6)` | año/quincena del concepto; solo se llena en retroactivos |

Un slot sin usar **no viene vacío ni `NULL`**: viene con el centinela `'00000'`,
importe `0` y AQ `'000000'`. Por eso el desglose descarta `'00000'` explícitamente
— un `TCP <> ''` no filtra nada.

## Por qué el unpivot se hace en PHP

La consulta original desglosaba con un `JOIN` contra una tabla de 50 números y un
`HAVING`. Eso multiplica por 50 las filas que pasaron el filtro, evalúa dos `CASE`
de 50 ramas sobre cada una y obliga a MySQL a materializar y ordenar todo eso en
una tabla temporal. Para 2,050 filas coincidentes son 102,500 filas intermedias
para entregar 2,282.

Aquí la base solo entrega las filas que ya coinciden y el desglose sale del lado de
PHP, en microsegundos. La base hace menos trabajo, no más.

Otros dos cambios respecto a la consulta original:

- **`LIKE 'prefijo%'` en vez de `REGEXP '^(251|257)'`.** Son 50 × número de códigos
  evaluaciones por fila (5 millones para un año de `federal`) y el motor de regex
  cuesta bastante más por fila que un prefijo.
- **`UR <> '610'` en vez de `UR NOT IN (610)`.** `UR` es `varchar(3)`; comparar
  contra un entero obliga a MySQL a castear la columna de cada fila a número, lo
  que anula el índice y convierte en `0` cualquier UR no numérica. Era un bug, no
  solo lentitud.

## El dato viene sucio

Dos cosas que hay que saber para no leer mal un total:

- **`QNA` tiene la misma quincena escrita de dos formas.** En 2026 la quincena 1
  aparece como `'01'` (13,093 filas) y también como `'1'` (2,542). Filtrar por una
  sola perdería el resto sin avisar, así que el filtro busca ambas con `IN` — y no
  con `CAST(QNA AS UNSIGNED)`, que inutilizaría el índice. También hay `'31'` y
  `'4'`, que no corresponden a ninguna quincena válida.
- **`ANIO` trae años imposibles**: `2124` (39,028 filas), `2030` (15,076), `2034`,
  `2224`. No se filtran de la lista: esto es una herramienta de auditoría y puede
  que esas filas sean justo lo que alguien busca. Lo que sí se hace es arrancar el
  selector en el año plausible más alto, para que la pantalla no abra en 2224.

Además, `TR{n}AQ` viene en `'000000'` incluso en slots llenos, así que se normaliza
a vacío y se muestra como `—`. Se devuelven **tanto** la `QNA` de la fila como el
`AQ` del slot: un concepto retroactivo se paga en una quincena pero corresponde a
otra, y con solo la `QNA` se reportaría en la equivocada.

## Rendimiento medido

Búsqueda de un ejercicio completo en las seis tablas, con `EXPLAIN` confirmando
`type: ref` sobre el índice `anio` en las seis (**no hay escaneos completos**):

| Escenario | Tiempo |
|---|---|
| `federal` · 2026 · Q1 · `251` | 41 ms |
| 6 tablas · 2026 · año · `251` | ~2.5 s |
| 6 tablas · 2025 · año · `251`+`257` — **caché frío** | ~13.8 s |
| 6 tablas · 2025 · año · `251`+`257` — **caché caliente** | ~900 ms |

El costo no son los `LIKE`: son las lecturas aleatorias de filas anchas (150
columnas) que el índice `anio` obliga a buscar una por una. De ahí la diferencia
entre frío y caliente.

Dos consecuencias prácticas:

- **Filtrar por quincena no acelera.** No existe índice compuesto `(ANIO, QNA)`, así
  que MySQL usa uno o el otro. Medido, la búsqueda de Q1 en las seis tablas (3.7 s)
  salió *más lenta* que la del año completo (2.5 s). El filtro sirve para acotar el
  resultado, no para aliviar la base.
- El endpoint sube su `set_time_limit` a 180 s porque el caso frío queda al filo del
  límite de 30 s por omisión.

## Diseño de la pantalla

- **No busca al cargar ni al teclear.** A diferencia de Detalle, que rebota cada
  tecla, aquí solo se consulta con el botón: cada búsqueda cuesta un ejercicio.
- **El ejercicio es obligatorio** y no hay opción de "todos los años". Sin él la
  consulta pierde el índice `ANIO` y pasa de leer ~1/21 de las tablas a leer los
  12 GB completos.
- **Los códigos llevan de 3 a 5 caracteres.** La clave son 3 dígitos, así que un
  prefijo más corto no identifica nada (`2` traería medio ejercicio). Lo que se
  ignora se reporta en pantalla en vez de descartarse callando.
- **Tope de 20,000 coincidencias**, y cuando se alcanza la pantalla lo dice. Sin
  tope una búsqueda amplia se llevaría la memoria de PHP.
- **Una tabla sin coincidencias se atenúa pero no se esconde**: saber que se buscó
  ahí y salió vacía es parte del resultado.
- La tabla se pinta por tandas de 400 filas con "Mostrar más": 20,000 filas de golpe
  en el DOM congelan la pestaña.

## Catálogo de conceptos (el botón «Catálogo»)

Un **directorio de significados**: qué quiere decir cada clave. No lleva
importes, no consulta la nómina y no suma nada — para eso está la búsqueda de
arriba. Solo dice que `26430` es AMORTIZACION FOVISSSTE S M 30.

### Cómo se unifican siete tablas sin tocar la base

No existe una tabla de conceptos: hay siete, con nombres de columna distintos y
ninguna completa. Parecen incompatibles, pero todas guardan la misma clave
partida en tres pedazos:

```
TIPO + CONCEPTO + ANTECEDENTE  =  la clave de 5 que trae TR{n}TCP
```

| Tabla | Columnas | Compone |
|---|---|---|
| `cat_conceptos` | `tipocon` + `concepto` + `antecedente` | `20100` |
| `cat_conceptos_nvo` | `tipo` + `cpto` + `pa` | `20100` |
| `conceptospartida` | `tipo` + `con` + `ant` | `10700` |
| `cptosPartidas` | `tipo` + `concepto` + `partida` | `246VV` |
| `conceptos202224` | `TIPO` + `CONCEPTOENT` + `PARTIDAANTECEDENTEENT` | `13202` |
| `acum_conceptos` | `tipo` + `cpto` + `pa` | `202SR` |
| `conceptosTerceros` | ya viene compuesta | `246OM` |

La unificación es **en PHP al leer**, en
[`Classes/CatalogoConceptosRepository.php`](../../Classes/CatalogoConceptosRepository.php).
No se crea ninguna vista ni tabla: entre las siete suman ~1,700 filas y juntarlas
toma ~300 ms.

**Cobertura medida** contra la nómina real de 2026 Q17: las siete juntas
describen **136 de las 141 claves** que se usaron (96%). Las 5 que faltan son
subclaves de FOVISSSTE (26419, 26431, 26437, 26439, 26448) que suman 7,688.70.

### Cuando dos catálogos no coinciden

Pasa, y seguido. El módulo **muestra las dos** en vez de elegir en silencio: la
principal arriba y la otra como *también:*, con el nombre de la tabla de la que
salió. Esconder la discrepancia sería peor que enseñarla — es justo lo que hay
que revisar antes de fiarse de un nombre.

El orden de preferencia está en `FUENTES` y no es arbitrario:
`conceptosTerceros` primero en su terreno (24 filas curadas con el nombre real
de cada tercero), luego `cat_conceptos` (el general más completo, 92%), después
`conceptos202224` (cubre 96% pero es de un trienio concreto) y el resto rellena.

Ejemplo real: `246OG` sale como **COMERCIALIZADORA OFEM GUERRERO, S.A. DE C.V.**
(de `conceptosTerceros`) y debajo, como alternativa, *CREDITOS ADICIONALES
CREDITO MAESTRO* (de `cat_conceptos`).

### Otros detalles

- **Percepción o deducción** se deduce del primer carácter: `1` percepción, `2`
  deducción. Verificado contra los importes reales y contra la columna
  `perc_ded` de `cat_per_ded`.
- Se aceptan claves de 3 (la familia: `250`, `264`) y de 5 (la subclave:
  `26430`). Buscar `264` trae las dos cosas.
- El catálogo se trae **una vez** al abrir el modal y se filtra en el navegador:
  son 426 claves, no tiene sentido volver al servidor por cada tecla.
- **Clic en una clave** la pasa a la caja de búsqueda de la pantalla y cierra el
  modal, para encadenar «qué significa» con «quién lo tiene».
- Si una de las siete tablas desaparece, el catálogo pierde su aporte y sigue
  respondiendo con las demás.
