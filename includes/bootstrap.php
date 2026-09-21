<?php

declare(strict_types=1);

session_start();

/**
 * Nombre del sistema. Sale en la pestaña de todas las pantallas y en la lámina
 * del login. Vive aquí porque estaba repetido a mano en quince archivos y
 * renombrarlo obligaba a cazarlos uno por uno.
 *
 * Ojo: esto es solo el rótulo. La clave de tema en localStorage
 * ('monitor-tema'), la base de datos y la carpeta del proyecto se llaman
 * 'monitor' y no se tocan al cambiar este texto.
 */
const APP_NOMBRE = 'SSA Monitor';

/**
 * Área a la que pertenece quien usa el sistema; se pinta junto al nombre en la
 * cabecera de todas las páginas. Vive aquí y no en cada plantilla porque son
 * cuatro cabeceras iguales y el dato es el mismo para todas.
 */
const AREA_USUARIO = 'Sistematización del pago';

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../Classes/Database.php';
require_once __DIR__ . '/../Classes/NominaRepository.php';
require_once __DIR__ . '/../Classes/EmpleadosRepository.php';
require_once __DIR__ . '/../Classes/EmpleadosIndex.php';
require_once __DIR__ . '/../Classes/ConceptosRepository.php';
require_once __DIR__ . '/../Classes/TercerosRepository.php';
require_once __DIR__ . '/../Classes/UsuariosRepository.php';
require_once __DIR__ . '/../Classes/CatalogoConceptosRepository.php';
