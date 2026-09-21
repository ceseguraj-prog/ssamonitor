# Módulo: Permisos (`permisos`)

Matriz **usuario × módulo**: quién entra a qué. Los usuarios salen de la tabla
`users` de `catalogos` (la misma contra la que autentica el login) y los permisos
se guardan en un archivo del proyecto.

## Acceso

Restringido a los logins de `usuarios` en [`modulo.php`](modulo.php). Hoy:
`csegura`.

Ese arreglo es **el único que esta pantalla no puede sobrescribir**, y es a
propósito. Esta es la única puerta para volver a conceder accesos: si se pudiera
quitar desde aquí, una casilla mal picada dejaría el sistema sin nadie capaz de
repararlo salvo editando el JSON a mano en el servidor. Para cambiar quién
administra permisos se edita ese archivo, no la pantalla.

En la matriz la columna *Permisos* aparece en gris y con la casilla deshabilitada,
para que se vea que existe y que está fija.

## Dónde se guarda

En **`config/permisos.json`**, no en la base de datos. `catalogos` es de solo
lectura para este sistema y no se le crean tablas; el módulo no ejecuta un solo
`INSERT`.

```json
{
  "version": 1,
  "usuarios": {
    "u.damian": ["resumen", "productos", "tg7"],
    "j.tavira": []
  }
}
```

El archivo está git-ignorado igual que `config.php`: es estado del despliegue,
cada servidor tiene el suyo. Un clon nuevo arranca sin él, que es un arranque
seguro (ver abajo).

**El servidor web necesita permiso de escritura sobre `config/`.** Si no lo
tiene, la pantalla lo avisa arriba y deshabilita todas las casillas en vez de
dejarte picar y fallar al guardar.

## Cómo se resuelve un permiso

Tres estados por usuario, y la diferencia entre los dos últimos es la que se
presta a confusión:

| Estado | Qué significa |
|---|---|
| **Por omisión** (sin entrada en el JSON) | Manda el `usuarios` de cada `modulo.php`, como funcionaba todo antes de esta pantalla. Vacío = abierto a cualquiera con sesión. |
| **Configurado** con slugs | Exactamente esos módulos, sin importar lo que diga `modulo.php`. |
| **Configurado** con lista vacía | Ningún módulo. **No** es lo mismo que estar por omisión: es que alguien le quitó todo a propósito. |

*Restablecer* borra la entrada y devuelve al usuario a **por omisión**.

Ese respaldo es lo que hizo que estrenar el módulo no moviera a nadie de sitio:
mientras el archivo esté vacío, el sistema se comporta exactamente igual que
antes. La migración es opcional y por usuario.

Un JSON ausente, ilegible o corrupto se trata como vacío en vez de reventar:
todo el mundo cae en los permisos por omisión. Un archivo roto no debe tumbar el
sitio ni encerrar a nadie.

## Estructura

```
modules/permisos/
├── modulo.php          descriptor; su 'usuarios' es el que manda aquí
├── index.php           la matriz
├── ajax/guardar.php    POST usuario + modulos[] → JSON
├── css/permisos.css
└── js/permisos.js
```

La lógica de resolución **no** vive en el módulo sino en
[`includes/permisos.php`](../../includes/permisos.php), porque la consulta
`puedeVerModulo()` en cada página, no solo esta pantalla. La lectura de `users`
está en [`Classes/UsuariosRepository.php`](../../Classes/UsuariosRepository.php).

## Detalles de implementación

- **Se guarda por renglón**, no toda la matriz de golpe: dos ediciones no se
  pisan y un error en un usuario no arrastra a los demás. El renglón con cambios
  sin guardar se marca con una banda vino a la izquierda.
- **Escritura atómica bajo candado**: se reescribe el JSON completo a un temporal
  y se renombra encima, con `flock` sobre un `.lock` aparte (bloquear el archivo
  que se va a sustituir no sirve de nada). Nadie llega a leer un JSON a medias.
  Antes de escribir se relee del disco, no del caché: entre que cargaste la
  página y le diste guardar, alguien más pudo haber tocado otra fila.
- **Los slugs se validan en el servidor** contra los módulos instalados y se
  descarta `permisos`. Un POST a mano no puede meter basura en el archivo ni
  concederse a sí mismo la administración.
- **El login se valida contra `users`** para que un typo no deje entradas
  muertas en el archivo.
- **Mayúsculas**: el login se compara sin distinguirlas, igual que en
  `usuarioEsAlguno()`. Guardar como `U.Damian` y leer como `u.damian` da lo mismo.
- **Las ocho filas basura de `users`** (ids 3, 9, 17, 20, 21, 22, 25, 26, sin
  login ni nombre) no se listan: nadie puede entrar con ellas. Quedan 18 usuarios.

## Lo que este módulo no hace

No crea, borra ni modifica usuarios, ni restablece contraseñas: solo concede y
quita acceso. Todo eso sería escribir en `users`, que está en `catalogos`.
