<?php

namespace App\Modules\CheckId;

use RuntimeException;

/**
 * Cliente de la API de CheckID (https://www.checkid.mx/api/Busqueda).
 *
 * Consulta por RFC o CURP y devuelve la respuesta ya normalizada, porque la
 * original trae varias rarezas que no conviene esparcir por la interfaz:
 *
 *  - Cada sección (rfc, curp, nss…) tiene su propio `exitoso` y `error`, y una
 *    puede fallar mientras el resto responde bien.
 *  - `codigoPostal.error` llega como la cadena "False" en vez de null cuando
 *    NO hay error. Tomarla al pie de la letra pintaría un error inexistente.
 *  - Los booleanos del SAT vienen invertidos en significado: `suspendido` y
 *    `conProblema` en true son malas noticias, `valido` en true es buena.
 *
 * La clave nunca sale del servidor: vive en .env y solo la usa esta clase.
 */
class CheckIdClient
{
    /**
     * URL de producción. CHECKID_ENDPOINT la puede sobrescribir para apuntar a
     * un ambiente de pruebas; si no está definida se usa esta.
     */
    private const ENDPOINT = 'https://www.checkid.mx/api/Busqueda';

    /** Secciones que se pueden pedir: nombre interno => bandera de la API. */
    private const BANDERAS = [
        'rfc'            => 'ObtenerRFC',
        'curp'           => 'ObtenerCURP',
        'estado69o69B'   => 'Obtener69o69B',
        'nss'            => 'ObtenerNSS',
        'regimenFiscal'  => 'ObtenerRegimenFiscal',
        'codigoPostal'   => 'ObtenerCP',
    ];

    /**
     * Valores que la API usa para decir "sin error". Se filtran para no
     * mostrar un error que no existe.
     */
    private const ERRORES_VACIOS = ['', 'false', 'null', 'none', '0'];

    /**
     * Consulta un RFC o CURP.
     *
     * @param list<string>|null $secciones Claves de BANDERAS a pedir; null = todas.
     * @return array<string,mixed> Respuesta normalizada.
     */
    public static function buscar(string $termino, ?array $secciones = null): array
    {
        $termino = strtoupper(trim($termino));

        if (!self::esRfc($termino) && !self::esCurp($termino)) {
            throw new RuntimeException('El término debe ser un RFC (12-13 caracteres) o una CURP (18 caracteres).');
        }

        $secciones = $secciones ?? array_keys(self::BANDERAS);
        $secciones = array_values(array_intersect($secciones, array_keys(self::BANDERAS)));

        if ($secciones === []) {
            throw new RuntimeException('Hay que pedir al menos un dato.');
        }

        $cuerpo = ['ApiKey' => envRequerida('CHECKID_API_KEY'), 'TerminoBusqueda' => $termino];

        // Las banderas se mandan siempre todas: la API espera el objeto
        // completo y las no pedidas van explícitamente en false.
        foreach (self::BANDERAS as $clave => $bandera) {
            $cuerpo[$bandera] = in_array($clave, $secciones, true);
        }

        $crudo = self::enviar($cuerpo);

        return self::normalizar($crudo, $termino, $secciones);
    }

    /**
     * POST con el cuerpo en JSON. Aislado para poder probar la normalización
     * sin salir a la red.
     *
     * @param array<string,mixed> $cuerpo
     * @return array<string,mixed>
     */
    private static function enviar(array $cuerpo): array
    {
        $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('No se pudo preparar la petición.');
        }

        $ch = curl_init(env('CHECKID_ENDPOINT') ?? self::ENDPOINT);

        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar la conexión.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            // La API consulta SAT, RENAPO e IMSS en vivo; con las seis
            // secciones puede pasarse de un minuto. El timeout de conexión sí
            // es corto: si no conecta rápido, no va a conectar.
            CURLOPT_TIMEOUT        => (int) (env('CHECKID_TIMEOUT') ?? 90),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $errorRed = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new RuntimeException(
                    'CheckID tardó más de ' . (env('CHECKID_TIMEOUT') ?? 90) . ' s en responder. '
                    . 'Intenta con menos datos seleccionados, o sube CHECKID_TIMEOUT en el .env.'
                );
            }

            throw new RuntimeException('No se pudo contactar a CheckID: ' . $errorRed);
        }

        // Una clave inválida o vencida no devuelve un error JSON: la API
        // redirige (302) a su pantalla de login con el cuerpo vacío. Sin este
        // caso el fallo se reportaría como "respuesta que no es JSON".
        if ($codigo >= 300 && $codigo < 400) {
            throw new RuntimeException(
                'CheckID rechazó la petición y redirigió a su pantalla de acceso. '
                . 'Suele significar que CHECKID_API_KEY es inválida o está vencida.'
            );
        }

        if ($codigo >= 400) {
            throw new RuntimeException("CheckID respondió HTTP $codigo.");
        }

        if (trim((string) $respuesta) === '') {
            throw new RuntimeException('CheckID respondió vacío (HTTP ' . $codigo . ').');
        }

        $datos = json_decode((string) $respuesta, true);

        if (!is_array($datos)) {
            throw new RuntimeException('CheckID devolvió una respuesta que no es JSON válido.');
        }

        return $datos;
    }

    /**
     * Aplana la respuesta a algo que la interfaz pueda pintar directo.
     *
     * @param array<string,mixed> $crudo
     * @param list<string> $secciones
     * @return array<string,mixed>
     */
    public static function normalizar(array $crudo, string $termino, array $secciones): array
    {
        $resultado = is_array($crudo['resultado'] ?? null) ? $crudo['resultado'] : [];

        $salida = [
            'exitoso'  => (bool) ($crudo['exitoso'] ?? false),
            'error'    => self::limpiarError($crudo['error'] ?? null),
            'codigo'   => $crudo['codigoError'] ?? null,
            'termino'  => $termino,
            'tipo'     => self::esCurp($termino) ? 'CURP' : 'RFC',
            'secciones' => [],
        ];

        foreach ($secciones as $clave) {
            $bloque = is_array($resultado[$clave] ?? null) ? $resultado[$clave] : null;

            if ($bloque === null) {
                $salida['secciones'][$clave] = [
                    'exitoso' => false,
                    'error'   => 'La API no devolvió esta sección.',
                    'campos'  => [],
                    'avisos'  => [],
                ];
                continue;
            }

            $salida['secciones'][$clave] = [
                'exitoso' => (bool) ($bloque['exitoso'] ?? false),
                'error'   => self::limpiarError($bloque['error'] ?? null),
                'campos'  => self::campos($clave, $bloque),
                'avisos'  => self::avisos($clave, $bloque),
            ];
        }

        return $salida;
    }

    /**
     * Campos a mostrar por sección, en orden. Los vacíos se descartan para no
     * llenar la pantalla de renglones en blanco.
     *
     * @param array<string,mixed> $b
     * @return list<array{etiqueta:string,valor:string}>
     */
    private static function campos(string $seccion, array $b): array
    {
        $mapas = [
            'rfc' => [
                'rfc'                => 'RFC',
                'razonSocial'        => 'Nombre / Razón social',
                'curp'               => 'CURP',
                'validoHastaText'    => 'Válido hasta',
                'emailContacto'      => 'Correo de contacto',
                'rfcRepresentante'   => 'RFC del representante',
                'curpRepresentante'  => 'CURP del representante',
            ],
            'curp' => [
                'curp'                => 'CURP',
                'nombres'             => 'Nombres',
                'primerApellido'      => 'Primer apellido',
                'segundoApellido'     => 'Segundo apellido',
                'sexo'                => 'Sexo',
                'fechaNacimientoText' => 'Fecha de nacimiento',
                'entidad'             => 'Entidad',
                'nacionalidad'        => 'Nacionalidad',
                'municipioRegistro'   => 'Municipio de registro',
            ],
            'codigoPostal'  => ['codigoPostal'      => 'Código postal'],
            'regimenFiscal' => ['regimenesFiscales' => 'Régimen fiscal'],
            'nss'           => ['nss'               => 'NSS'],
            'estado69o69B'  => ['detalles'          => 'Detalles'],
        ];

        $campos = [];

        foreach ($mapas[$seccion] ?? [] as $llave => $etiqueta) {
            $valor = $b[$llave] ?? null;

            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            $campos[] = [
                'etiqueta' => $etiqueta,
                'valor'    => is_array($valor) ? implode(', ', $valor) : (string) $valor,
            ];
        }

        return $campos;
    }

    /**
     * Señales de estado que merecen destacarse en vez de ir como un campo más:
     * si el RFC es válido, si está suspendido, si aparece en las listas 69/69-B.
     *
     * @param array<string,mixed> $b
     * @return list<array{texto:string,tono:string}>
     */
    private static function avisos(string $seccion, array $b): array
    {
        $avisos = [];

        if ($seccion === 'rfc') {
            if (array_key_exists('valido', $b)) {
                $avisos[] = $b['valido']
                    ? ['texto' => 'RFC válido ante el SAT', 'tono' => 'ok']
                    : ['texto' => 'RFC NO válido ante el SAT', 'tono' => 'mal'];
            }
            if (!empty($b['suspendido'])) {
                $avisos[] = ['texto' => 'Contribuyente suspendido', 'tono' => 'mal'];
            }
        }

        if ($seccion === 'estado69o69B') {
            // conProblema = aparece en las listas de EFOS/EDOS del artículo 69-B.
            $avisos[] = !empty($b['conProblema'])
                ? ['texto' => 'Aparece en las listas 69 / 69-B', 'tono' => 'mal']
                : ['texto' => 'Sin problemas en listas 69 / 69-B', 'tono' => 'ok'];
        }

        return $avisos;
    }

    /**
     * Normaliza el campo de error: la API usa null, "" y hasta la cadena
     * "False" para decir "sin error".
     */
    private static function limpiarError(mixed $valor): ?string
    {
        if ($valor === null || is_bool($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return in_array(strtolower($texto), self::ERRORES_VACIOS, true) ? null : $texto;
    }

    public static function esRfc(string $v): bool
    {
        return (bool) preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $v);
    }

    public static function esCurp(string $v): bool
    {
        return (bool) preg_match('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/u', $v);
    }

    /** Etiquetas de las secciones, para la interfaz. */
    public static function etiquetasSecciones(): array
    {
        return [
            'rfc'           => 'RFC',
            'curp'          => 'CURP',
            'nss'           => 'NSS',
            'codigoPostal'  => 'Código postal',
            'regimenFiscal' => 'Régimen fiscal',
            'estado69o69B'  => 'Listas 69 / 69-B',
        ];
    }

    /** ¿Está configurada la clave? La interfaz lo avisa antes de dejar buscar. */
    public static function configurada(): bool
    {
        return env('CHECKID_API_KEY') !== null;
    }
}
