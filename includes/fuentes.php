<?php

declare(strict_types=1);

/**
 * Sello de procedencia: de dónde sale lo que se está viendo en pantalla.
 *
 * Importa porque no todo viene del mismo lado — BPM es de una empresa externa,
 * CheckID es una API que se cobra por consulta y hay herramientas que no tocan
 * ninguna base. Quien lee un número debe poder saber de quién es el dato sin
 * preguntarle a nadie.
 *
 * Para dar de alta una fuente nueva (Catálogos ya está lista) se agrega aquí y
 * la página la invoca con su clave: no hay que tocar estilos ni plantillas.
 */

/** Cilindro de base de datos, el ícono base de las fuentes que son una BD. */
const ICONO_BD = '<ellipse cx="12" cy="6" rx="7" ry="2.7"/>'
    . '<path d="M5 6v12c0 1.5 3.1 2.7 7 2.7s7-1.2 7-2.7V6"/>'
    . '<path d="M5 12c0 1.5 3.1 2.7 7 2.7s7-1.2 7-2.7"/>';

function fuentesDisponibles(): array
{
    return [
        // La base de nómina; la opera un tercero y aquí solo se lee.
        'bpm' => [
            'nombre' => 'BPM',
            'detalle' => 'Base de datos de BPM, proveedor externo. Consulta de solo lectura.',
            'icono' => ICONO_BD,
        ],
        'catalogos' => [
            'nombre' => 'Catálogos',
            'detalle' => 'Base de datos de catálogos institucionales.',
            'icono' => '<rect x="3.4" y="4" width="17.2" height="16" rx="2.6"/>'
                . '<path d="M8.4 4v16"/><path d="M11.8 8.8h5.4"/><path d="M11.8 12.6h5.4"/><path d="M11.8 16.4h3.2"/>',
        ],
        'checkid' => [
            'nombre' => 'API CheckID',
            'detalle' => 'Servicio externo CheckID, que a su vez consulta al SAT y a RENAPO.',
            'icono' => '<path d="M6.6 19.4a4.4 4.4 0 0 1-.5-8.77 5.9 5.9 0 0 1 11.3-1.5 4.2 4.2 0 0 1 .3 8.37"/>'
                . '<path d="M9.4 15.2 11.4 17.2 15 13.2"/>',
        ],
        // Herramientas que no leen ninguna base: el insumo lo pone el usuario.
        'archivo' => [
            'nombre' => 'Archivo del usuario',
            'detalle' => 'No consulta ninguna base de datos: trabaja sobre el archivo que se sube.',
            'icono' => '<path d="M6 3.4h7.4L19 8.8v11.8H6z"/><path d="M13.2 3.6V9h5.4"/>'
                . '<path d="M9 13.4h6"/><path d="M9 16.8h4"/>',
        ],
        'navegador' => [
            'nombre' => 'Archivo local',
            'detalle' => 'Se lee en tu navegador: no se sube al servidor ni se guarda en ninguna base.',
            'icono' => '<path d="M6 3.4h7.4L19 8.8v11.8H6z"/><path d="M13.2 3.6V9h5.4"/>'
                . '<path d="M9.4 14.6 11.4 16.6 15 12.6"/>',
        ],
    ];
}

/**
 * Pinta el sello. Va al inicio de .content-area, pegado a la derecha y bajo la
 * cabecera; nunca dentro de la vista, para que no choque con los filtros ni con
 * los contadores que ya viven a la derecha de esa primera fila.
 */
function fuenteDatos(string $clave): string
{
    $fuente = fuentesDisponibles()[$clave] ?? null;

    if ($fuente === null) {
        return '';
    }

    return '<div class="fuente-barra"><span class="fuente" title="'
        . htmlspecialchars((string) $fuente['detalle']) . '">'
        . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
        . $fuente['icono'] . '</svg>'
        . '<span class="fuente__texto">'
        . '<span class="fuente__etiqueta">Fuente</span>'
        . '<span class="fuente__nombre">' . htmlspecialchars((string) $fuente['nombre']) . '</span>'
        . '</span></span></div>';
}
