# Módulo: Errores de timbrado (`errores`)

Lee los archivos que devuelve el PAC cuando un comprobante no se timbra y los
presenta desglosados: RFC y CURP del trabajador, código de error, serie, folio
y el motivo en texto legible, con el reparto por código de error.

## Acceso

Restringido a los logins listados en `usuarios` dentro de [`modulo.php`](modulo.php).
Hoy: `csegura`. Se aplica en el servidor con `requireModulo('errores')`.

## El archivo no sale de la máquina

No hay endpoint: el archivo se lee **en el navegador** con `FileReader` y
`DOMParser`. No se sube, no se escribe en disco del servidor y no toca ninguna
base de datos. Al recargar la página no queda nada.

Por lo mismo, el CSV de descarga y el "Copiar RFC" se arman en el navegador con
lo que ya está en pantalla.

## Formato que espera

Una línea por comprobante rechazado. Cada línea trae un prefijo con los
identificadores y después la respuesta del PAC en XML:

```
RFC,CURP RFC - <?xml version="1.0" encoding="UTF-8"?><CFDICertificacion>…
```

Del XML se leen dos niveles:

- `CFDICertificacion > mensaje` y `> status` — el resultado de la
  **autenticación** con el PAC (normalmente `200 OK`).
- `CFDIResultadoCertificacion > mensaje` y `> status` — el del **comprobante**,
  que es el que interesa (`301` cuando se rechazó).

Del mensaje del comprobante se sacan, por texto:

| Dato | De dónde sale |
| --- | --- |
| Código | `[Error #CFDI40148]` → `CFDI40148` |
| Tipo | lo que va antes del corchete (`Error en complemento Nómina`) |
| Folio | `Folio: 600804284.` |
| Serie | `Serie: 411.` |
| Motivo | el resto del mensaje, sin folio ni serie |

Si una línea no trae XML o el XML no se puede leer, no se descarta en silencio:
se cuenta y se avisa con el número de línea al pie de la tabla.

## Codificación

Los archivos declaran `encoding="UTF-8"` en el XML pero vienen en
**Windows-1252** (los acentos llegan como un byte suelto, `Autenticaci\xF3n`).
El lector intenta UTF-8 en modo estricto y, si truena, decodifica como 1252
— por eso los acentos se ven bien sin tener que convertir el archivo antes.

## Códigos vistos hasta ahora

- `NOM9` — el RFC del receptor no está en la lista de RFC inscritos no
  cancelados en el SAT.
- `CFDI40148` — el `DomicilioFiscalReceptor` no corresponde al registrado en la
  LRFC para ese RFC.
- `CFDI40145` — el `Nombre` del receptor no corresponde al registrado en la LRFC.

No están codificados en ningún lado: el módulo agrupa por el código que venga en
el archivo, así que uno nuevo aparece solo.
