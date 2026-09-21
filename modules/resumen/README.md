# Pantallas de timbrado (`resumen`, `productos`, `detalle`, `empleados`)

Las cuatro pantallas del avance de timbrado. Cada una es un módulo propio bajo
`modules/`, igual que CheckID o el Corrector SQL: se dan de alta solas al existir
su `modulo.php` y el rail las ordena por la clave `orden`.

## Acceso

`usuarios` vacío: las ve cualquiera que haya iniciado sesión. Las herramientas
(CheckID, Errores, Corrector SQL) sí tienen lista blanca.

## El periodo se comparte

Resumen, Productos y Detalle trabajan sobre la misma quincena. Al ser páginas
distintas, el periodo se guarda en la sesión desde
[`includes/periodo.php`](../../includes/periodo.php): si eliges Q14 en Resumen y
entras a Productos, sigues en Q14, y los enlaces del rail se quedan limpios.

Se resuelve en este orden:

1. lo que venga en la petición (el usuario acaba de cambiarlo),
2. lo último elegido en la sesión,
3. la última quincena con timbres en la base — no la del calendario, que suele
   estar en curso y todavía vacía.

## La regla del avance

Vive en `base()` dentro de [`js/comun.js`](../../js/comun.js) y se repite en el
endpoint del resumen para la tendencia:

- Los **invisibles no se timbran**, así que no entran en la base. Si contaran, el
  avance nunca llegaría a 100%.
- La base es impresos + activos + cancelados; lo pendiente son los **activos**,
  porque un cancelado ya pasó por el timbrado.
- **100% = ningún activo.** Por eso `pctVivo()` nunca redondea a 100 si queda algo
  vivo (se queda en 99.9%) ni a 0 si existe algo (0.1%).

## De dónde salen los datos

De **BPM**, la base del proveedor externo, en consultas de solo lectura. La
búsqueda de empleados va contra el índice local (`EmpleadosIndex`) porque en BPM
no hay índice por nombre y un LIKE por tecleo obligaba a escanear la tabla
completa.
