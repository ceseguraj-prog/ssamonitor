# Reporte QNA (`qna`)

Arma el reporte quincenal de nómina ordinaria. Antes salía del «Generador QNA»
de pintarUUID a partir de seis .txt que mandaban cada quincena (más
`ENCABEZADO.txt` y un Excel de referencia). Aquí sale **directo de las seis
tablas de `catalogos`**: se elige ejercicio y quincena y se descarga
`QNA_{QQ}_{AAAA}.xlsx`, **idéntico al que salía de los .txt** (ver Verificación).

## Solo lectura

Solo `SELECT`. No se crea ni se escribe nada en la base, y tampoco en disco: el
libro se arma en un temporal del sistema, viaja a la pantalla en la misma
respuesta (base64) y el temporal se borra. No hace falta carpeta `storage/`.

Necesita la extensión `zip` de PHP (la usa `Classes/Xlsx.php`, igual que
Extracción). En el XAMPP local venía comentada en `php.ini`.

## Acceso

`usuarios` es lista blanca (`csegura`) en [`modulo.php`](modulo.php): el reporte
trae CURP, RFC y sueldo de unas diez mil personas, y `federal` es MyISAM (una
lectura larga bloquea escrituras). Para abrirlo a alguien más se le da desde el
módulo Permisos.

## Qué hace

1. De `federal`, `formalizados`, `formalizados2015`, `formalizados2016`,
   `homologados` y `regularizados` (en ese orden, el de los .txt) toma la
   quincena con `TIPO = '11'` (ordinaria) y `UR <> '610'`. `QNA` se busca en
   sus dos formas (`'01'` y `'1'`), como en Conceptos.
2. La UR `416` sale como `411`, también dentro de la clave presupuestal.
3. Suma los slots `TR{n}TCP`/`TR{n}IM` en cuatro columnas (`QnaRepository::IMPORTES`):

   | Columna | Claves |
   |---|---|
   | CPTO 07 | 10700 |
   | CPTO 30 | 130AR, 130MR, 130BR |
   | QUINQUENIO | 1A100 a 1A500 |
   | AHORRO SOLIDARIO | 2AS10, 2AS20, 2AS00, 2AS-2, **2AS03** |

   El .txt no tenía columna para `2AS03` y la metía en la de `2AS20`, así que
   el ahorro solidario sí la incluía. Una clave de esas familias (`107`, `130`,
   `1A`, `2AS`) que no esté en la tabla no se suma, pero la pantalla la lista.
4. Las descripciones: la del CR sale de `indeteccr` **por CLUES** y la del
   puesto de `cat_puesto` (`CAT_PUESTO → DESC_PUEST`). Esta última reemplaza al
   Excel de referencia, que no era un catálogo sino el reporte de una quincena
   anterior.

Columnas: `UR`, `FECHA DE INGRESO` (`AHISA1`), `CURP`, `RFC`, `NOMBRE` (`NOMB`),
`CLUES` (`SPC`), `CR`, `DESCRIPCIÓN DEL CR`, `CLABE PRESUPUESTAL`
(`AA`+`UR`+`PTDA`+` `+`COD`+`PGAI`+`NUM`), `CODIGO` (`COD`), `DESCRIPCION` y los
cuatro importes.

El libro tiene la pinta del que se entregaba: una hoja `Sheet1`, sin estilos
ni anchos, con autofiltro, todo como texto salvo `CPTO 30`, `QUINQUENIO` y
`AHORRO SOLIDARIO`. **`CPTO 07` va como texto**: el generador leía los .txt como
texto y esa columna solo la renombraba.

## Dos modos

Los .txt eran un volcado de estas mismas tablas cruzado con un **INNER JOIN
contra `indeteccr` por CLUES**, y el reporte heredaba tres defectos:

- **Renglones repetidos.** `indeteccr` tiene 35 CLUES repetidos (casi siempre
  la misma unidad con y sin el municipio), y cada uno repetía al trabajador
  completo, importes incluidos. En la q16: 11,260 renglones para 10,188 pagos,
  y el CPTO 07 sumaba **76.1 millones en vez de 68.7**.
- **Personas fuera.** Un CLUES que no está en `indeteccr` tumbaba la fila: las
  mismas 5 personas en la q15 y en la q16, con pago ordinario normal.
- **La Ñ como «?».**

Por omisión el reporte los **reproduce**, porque lo que se pide es el mismo
archivo de siempre; la pantalla dice cuántos repetidos y quiénes quedaron fuera.
La casilla «Corregir» da cada pago una sola vez, con las 5 personas (con la
descripción del CR vacía) y con la Ñ.

## El orden de los renglones

Se dedujo de los .txt y se reproduce exacto:

1. Por tabla, en el orden de arriba.
2. Por **tanda del cruce**: el que armaba el .txt recorría `indeteccr` en tandas
   de 255 filas por `id` (0–254, 255–509, …), y cada tanda salía completa
   antes que la siguiente. Un trabajador repetido sale una vez en la tanda de
   cada una de sus filas del catálogo.
3. Dentro de la tanda, en el **orden físico de la tabla**: el de inserción. No
   hay columna que lo represente (`conse` coincidía en la q16 y no en la q15),
   así que la consulta va sin `ORDER BY` y filtra solo por `ANIO` y `QNA`; las
   seis tablas no tienen llave primaria y MySQL resuelve ese filtro con un
   `index_merge`, que entrega las filas en orden de inserción. TIPO y UR se
   filtran en PHP para no cambiar el plan, y con él el orden.

Si algún día alguien reconstruye una tabla (un `ALTER`, un respaldo
restaurado), el orden físico puede cambiar y con él el de los renglones; los
datos no.

## Verificación

Se corrió el generador original de pintarUUID sobre los .txt y reprodujo
**celda por celda** el reporte real `QNA_16_2026.xlsx`. Contra eso:

| Prueba | Resultado |
|---|---|
| q16, modo .txt, contra el reporte real | **idéntico**: 11,260 renglones, 0 celdas distintas en valor o tipo, mismo orden, misma hoja y filtro |
| q15, modo .txt, contra el generador con sus .txt | idéntico salvo la clave presupuestal de los 2,710 de federal (ver abajo) |
| q16, modo corregido | 10,193 renglones, sin repetidos, CPTO 07 68,784,842.47 |
| Campo por campo contra los .txt (q16, 11,402 renglones de todos los tipos, cruzados por RFC + cheque + tipo) | todos en la base |
| Descripción del puesto, `cat_puesto` contra el Excel de referencia | 115 de 115 iguales |

**La clave presupuestal de la q15.** El .txt de la q15 la traía con la UR 416
y el de la q16 ya con 411 (en la columna UR y dentro de la clave). Cambió del
lado de quien generaba los .txt y la base no dice cuándo; aquí sale siempre 411,
como en la q16.

**La q14 no se pudo comparar:** sus .txt vienen con una columna menos y el
generador original saca 0 renglones con ellos.

Tiempos: ~2.5–3.7 s para las seis tablas; el libro pesa ~1.1 MB.

## Estructura

```
modules/qna/
├── modulo.php          descriptor que lee includes/modulos.php
├── QnaRepository.php   la consulta, las reglas y el orden; todo SELECT
├── index.php           la pantalla: ejercicio, quincena, modo y el resumen
├── ajax/generar.php    arma el libro y lo devuelve con el resumen
├── css/qna.css
└── js/qna.js
```

## Límites

- Las columnas son fijas. El generador dejaba agregar cualquiera de las 226 del
  .txt con un checklist; aquí hace falta mapear cada una a su campo de la base,
  así que se agregan en `QnaRepository` (`CAMPOS`, `COLUMNAS` y la fila en
  `generar()`) cuando se necesiten.
- Los repetidos dependen de `indeteccr` *de hoy*: si alguien limpia o cambia el
  catálogo, el modo .txt repetirá distinto (o nada), igual que le habría pasado
  al .txt.
