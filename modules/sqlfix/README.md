# Módulo: Corrector SQL (`sqlfix`)

Repara volcados `.sql` de una sentencia por línea, del tipo que genera un
`SELECT CONCAT(...)` sobre `empleados`. Arregla dos defectos:

1. **Apóstrofes sin escapar** dentro de los valores (`nombre='YOIC'S'`), que
   rompen la sentencia. Se convierten al escape estándar de SQL: `YOIC''S`, que
   al ejecutarse guarda `YOIC'S` en la base.
2. **Codificación corrompida** (mojibake): `AG‚àö√∫ERO` → `AGÜERO`,
   `ALARC‚àö√¨N` → `ALARCÓN`, `CASTAÃ‘ON` → `CASTAÑON`.

El archivo original nunca se modifica: se descarga una copia corregida.

## Acceso

Restringido a los logins listados en `usuarios` dentro de [`modulo.php`](modulo.php).
Hoy: `csegura`. Para cambiarlo se edita solo ese arreglo; dejarlo vacío (`[]`)
abre el módulo a cualquier usuario autenticado.

Ese arreglo es el permiso **por omisión**: si a alguien se le configura acceso
desde el módulo [Permisos](../permisos/README.md), manda lo que diga ahí.

La restricción se aplica en el servidor en las tres entradas (página, `procesar.php`
y `descargar.php`) vía `requireModulo('sqlfix')`. Ocultar el enlace del menú es
solo cosmético y no se usa como control.

## Estructura

```
modules/sqlfix/
├── modulo.php          descriptor que lee includes/modulos.php (nombre, url, permisos)
├── index.php           página
├── SqlFixer.php        lógica pura: recibe rutas, devuelve reporte. Sin sesión ni BD.
├── ajax/
│   ├── procesar.php    POST archivo → JSON con el reporte + token
│   └── descargar.php   GET token → descarga el resultado
├── css/sqlfix.css
├── js/sqlfix.js
└── storage/            resultados temporales, se borran solos tras 1 hora
```

`SqlFixer` no depende de nada del resto del proyecto, así que se puede usar
desde CLI o cubrir con pruebas sin levantar el sitio.

## Cómo decide qué corregir

**Apóstrofes.** Un `'` dentro de un valor es la comilla de cierre solo si
después viene coma, punto y coma, paréntesis o una palabra clave
(`WHERE` / `AND` / `OR` / `LIMIT` / `ORDER`). En `'YOIC'S'` el primer `'` va
seguido de `S`, así que es un apóstrofe literal y se escapa; el segundo va
seguido de coma, así que cierra. Un `''` ya escapado se respeta.

**Codificación.** Se vuelven a codificar los caracteres a la codificación
intermedia sospechosa (CP1252, Mac-Roman, ISO-8859-1) y esos bytes se
reinterpretan como UTF-8. El resultado se acepta solo si es UTF-8 válido **y**
tiene estrictamente menos caracteres sospechosos que antes. Por eso un
`CASTAÑON` sano nunca se toca: su puntaje ya es cero. El proceso se repite
hasta cuatro veces, porque el mojibake puede venir en varias capas.

Son "sospechosos" los caracteres fuera de ASCII imprimible que tampoco son
acentos válidos del español (`ÁÉÍÓÚÜÑ áéíóúüñ Çç`).

## Salida

Se preserva el formato del original: mismo salto de línea (CRLF o LF), mismo
final (con o sin salto) y **UTF-8 sin BOM**. Lo del BOM importa: si se
escribiera, MySQL leería esos tres bytes como parte de la primera sentencia y
fallaría.

## Límites

- El tamaño máximo lo fijan `upload_max_filesize` y `post_max_size` de PHP. La
  página muestra los valores vigentes y el endpoint devuelve un error claro
  cuando se rebasan.
- El proceso es por streaming: un archivo de ~41 000 líneas (6.5 MB) tarda
  ~1.8 s con un pico de ~26 MB de memoria.
- El reporte detalla las primeras 500 correcciones y 100 avisos; los conteos
  totales sí son exactos y el archivo descargable lleva todas las correcciones.
- Las líneas cuya estructura no se reconoce **no** se modifican: se listan como
  avisos para revisarlas a mano.

## Pendiente conocido

El módulo no ejecuta nada contra la base de datos: solo entrega el archivo
corregido para que se cargue a mano.
