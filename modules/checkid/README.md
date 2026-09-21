# Módulo: CheckID (`checkid`)

Consulta la API de [CheckID](https://www.checkid.mx) por RFC o CURP y muestra,
en una sola pantalla: datos del RFC ante el SAT, datos de la CURP, NSS, código
postal, régimen fiscal y estatus en las listas 69 / 69-B.

## Acceso

Restringido a los logins listados en `usuarios` dentro de [`modulo.php`](modulo.php).
Hoy: `csegura`. La restricción se aplica en el servidor tanto en la página como
en `ajax/buscar.php`, vía `requireModulo('checkid')`.

## La clave de API

Vive en el archivo `.env` de la raíz del proyecto, que **está en `.gitignore`**.
Solo se versiona `.env.example` con las claves vacías.

```
CHECKID_API_KEY=tu-clave-aqui
CHECKID_TIMEOUT=20
```

La clave **nunca llega al navegador**: solo la usa `CheckIdClient` del lado del
servidor, y la respuesta que se manda al front está normalizada y no la
incluye. Sin clave configurada, la página lo avisa y deja el buscador
deshabilitado en vez de lanzar consultas que van a fallar.

Las variables reales del entorno (Apache `SetEnv`, variables del sistema) ganan
sobre el `.env`, para que un despliegue pueda inyectarlas sin tocar disco.

`CHECKID_ENDPOINT` es opcional y sobrescribe la URL de producción; sirve para
apuntar a un ambiente de pruebas.

## Estructura

```
modules/checkid/
├── modulo.php          descriptor que lee includes/modulos.php
├── index.php           página (usa css/theme.css y el rail compartido)
├── CheckIdClient.php   cliente + normalización. Sin sesión ni BD.
├── ajax/buscar.php     POST termino + secciones → JSON normalizado
├── css/checkid.css
└── js/checkid.js
```

## Rarezas de la API que el cliente absorbe

La respuesta cruda tiene trampas que no conviene esparcir por la interfaz:

- **`codigoPostal.error` llega como la cadena `"False"`** cuando NO hay error.
  Tomarla al pie de la letra pintaría un error inexistente. `limpiarError()`
  filtra `"False"`, `"null"`, `""` y compañía.
- **Cada sección trae su propio `exitoso` y `error`**: una puede fallar mientras
  el resto responde bien, así que se reportan por separado.
- **Los booleanos del SAT no se leen igual**: `valido` en true es buena noticia,
  pero `suspendido` y `conProblema` en true son malas. Se traducen a insignias
  verdes o rojas en vez de mostrar `true`/`false`.
- **Campos nulos** (`nacionalidad`, `municipioRegistro`, `rfcRepresentante`…)
  se omiten en vez de pintar renglones vacíos.

## Cuidado con el costo

Cada consulta a CheckID se cobra. Por eso:

- El formato de RFC/CURP se valida **antes** de salir a la red, en el navegador
  y otra vez en `CheckIdClient::buscar()`. Un término mal escrito nunca llega a
  la API.
- Los chips de "Datos a consultar" están a la vista para poder pedir solo lo que
  hace falta; las secciones no pedidas van explícitamente en `false`.
- La lista blanca de usuarios es la última barrera.

## Pendiente conocido

No hay caché: dos consultas seguidas del mismo RFC son dos cargos. Si el uso
crece, vale la pena guardar los resultados (la tabla `empleados` ya tiene RFC,
CURP, NSS y CP, así que podría alimentarse desde aquí).
