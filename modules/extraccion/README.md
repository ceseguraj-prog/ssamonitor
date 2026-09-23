# Extracción — Regularizados y Eventuales IMSS

Automatiza el cierre que se hacía a mano cada quincena: sacar los UUID de los
timbres que van a extracción, cuadrar cuántos salieron por producto y anotar en
las notas a quién se le retuvo.

**El módulo no escribe nada.** Todas sus consultas son `SELECT` contra BPM. Lo
único que produce son archivos temporales en `storage/`, que se borran solos a
la hora, y texto que el navegador copia al portapapeles.

## Qué entrega

| Archivo | Qué trae |
|:---|:---|
| `UUID_Reg_QNA17_26.txt` | Un UUID por línea, los impresos de Regularizados |
| `UUID_IMSS_QNA17_26.txt` | Un UUID por línea, los impresos de IMSS Bienestar |
| `RegYEvenQNA17_26.xlsx` | Resumen por unidad, desglose por producto y los RFC retenidos |
| `RegYEvenQNA17_26.md` | La nota de la quincena, en el formato de siempre |

Los cuatro van en un zip, pero cada uno se puede bajar suelto, y el markdown
además se copia al portapapeles con un botón: es lo que más se usa.

## Los dos universos

Cada grupo se define por un criterio sobre `detalle_nomina` y, cuando hace
falta, por la familia de producto donde ese criterio cuenta:

| Grupo | Criterio | Familia |
|:---|:---|:---|
| REGULARIZADOS | `unidad = 'REG'` | solo productos de base (`PRD*`) |
| IMSS | `clavep LIKE '%IMSS%'` | cualquiera |

### Por qué los REG se acotan a `PRD*`

Los productos de eventuales **también traen filas con `unidad = 'REG'`** — en la
Q17 de 2026 son 12 impresos en `EV1726`. No son parte de este cierre: son
*regularizados eventuales*, otro trámite. El límite a `PRD*` es exactamente el
filtro que se ponía a mano al escribir el `IN`, y está codificado como tal en
`ExtraccionRepository::GRUPOS` (clave `prefijos`).

Esos productos no desaparecen de la pantalla: se listan aparte, apagados y sin
casilla, bajo «Fuera del cierre». Contarlos sería un error, pero esconderlos
haría imposible distinguir un filtro puesto adrede de un producto que nadie
miró.

El filtro se aplica también en `ajax/generar.php`, no solo en la vista: esconder
una casilla no es una restricción, y un producto de eventuales no debe poder
entrar a Regularizados ni pidiéndolo a mano en la URL.

### Los productos no se escriben a mano

Se leen de `producto_nomina` para el periodo elegido y se propone marcado el que
tenga al menos una fila del grupo. Ese era el otro paso que había que dar a ojo:
cuáles de los `PRD*` de la quincena traen `REG`, que cambia de quincena en
quincena.

Las casillas quedan a la vista porque proponer no es decidir. Lo que llega del
navegador se cruza contra los productos que `producto_nomina` tiene para ese
periodo y contra la familia del grupo antes de tocar el SQL: una clave
inventada no entra, una de otra quincena tampoco, y un `EV*` en Regularizados
menos.

## Retenciones

Los invisibles (`inv`) son las retenciones: no se timbran nunca, así que son
los renglones que quedan fuera de la entrega y los que hay que poder justificar
después. Por eso van con RFC y no solo como número, tanto en la nota como en la
segunda hoja del Excel.

Van sin repetir: un trabajador con dos conceptos retenidos aparece dos veces en
el detalle, y en la lista lo que interesa es a quién se le retuvo. La columna
`Registros` del Excel conserva cuántas veces.

## Cuadre

El conteo de líneas de cada `.txt` sale de una consulta distinta a la de la
columna `IMP`, así que compararlos es gratis. Si no coinciden, es que hay un
impreso sin UUID: la pantalla lo avisa arriba y en rojo, porque si no, faltaría
un timbre en la entrega y nadie se enteraría hasta que allá lo reclamaran.

## Archivos

```
ExtraccionRepository.php   las consultas; todo SELECT
Xlsx.php                   escritor mínimo de .xlsx (ZipArchive + OOXML)
ajax/inventario.php        qué productos toca cada grupo en el periodo
ajax/generar.php           arma los .txt, el Excel, la nota y el zip
ajax/descargar.php         entrega el zip o uno de sus archivos, por token
```

`Xlsx.php` existe porque el proyecto no tiene Composer, y traer PhpSpreadsheet
solo para escribir dos hojas de números obligaría a montar un autoloader y a
versionar unos cuantos miles de archivos de vendor. A cambio hace lo justo:
varias hojas, texto y números, seis estilos y ancho de columna. Si alguna vez
hacen falta fórmulas o fechas, ahí sí conviene la librería de verdad.

## Permisos

`modulo.php` trae lista blanca (`usuarios`), porque equivocarse de periodo aquí
cuesta una extracción entera. Para abrirlo a cualquier usuario autenticado,
deja el arreglo vacío.
