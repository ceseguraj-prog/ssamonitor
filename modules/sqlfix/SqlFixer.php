<?php

namespace App\Modules\SqlFix;

/**
 * Corrige archivos .sql generados por CONCAT (volcados de UPDATE de una
 * sentencia por línea) que llegan con dos defectos típicos:
 *
 *   1. Apóstrofes sin escapar dentro de los valores (YOIC'S) que rompen la
 *      sentencia. Se convierten al escape estándar de SQL: YOIC''S.
 *   2. Texto con la codificación corrompida (mojibake), p. ej. 'AG‚àö√∫ERO'
 *      en vez de 'AGÜERO', por pasar bytes UTF-8 a través de CP1252 o
 *      Mac-Roman una o más veces.
 *
 * La clase no toca la red, la sesión ni la base de datos: recibe rutas de
 * archivo y devuelve un reporte. Eso la deja probable de forma aislada y
 * reutilizable desde CLI si algún día hace falta.
 */
class SqlFixer
{
    /** Tope de correcciones detalladas que se guardan en el reporte. */
    private const MAX_DETALLE = 500;

    /** Tope de avisos estructurales detallados. */
    private const MAX_AVISOS = 100;

    /**
     * Caracteres no-ASCII que sí son legítimos en nombres en español. Todo lo
     * demás fuera de ASCII se considera sospechoso de mojibake.
     */
    private const ACENTOS_VALIDOS = 'ÁÉÍÓÚÜÑáéíóúüñÇç«»°ªº';

    /**
     * Codificaciones intermedias por las que suele colarse el texto. Se
     * prueban en este orden para deshacer el mojibake.
     */
    private const CODIFICACIONES = ['CP1252', 'MACINTOSH', 'ISO-8859-1'];

    /**
     * Procesa $rutaEntrada y escribe la versión corregida en $rutaSalida
     * (si se indica). Devuelve el reporte de lo encontrado.
     *
     * Trabaja línea por línea para no cargar en memoria archivos de decenas
     * de miles de sentencias.
     *
     * @return array<string,mixed>
     */
    public static function procesar(string $rutaEntrada, ?string $rutaSalida = null): array
    {
        $entrada = @fopen($rutaEntrada, 'rb');
        if ($entrada === false) {
            throw new \RuntimeException('No se pudo abrir el archivo de entrada.');
        }

        $formato = self::detectarFormato($rutaEntrada);
        $salto = $formato['salto'];

        $salida = null;
        if ($rutaSalida !== null) {
            $salida = @fopen($rutaSalida, 'wb');
            if ($salida === false) {
                fclose($entrada);
                throw new \RuntimeException('No se pudo crear el archivo de salida.');
            }
        }

        $reporte = [
            'lineas'            => 0,
            'lineasCorregidas'  => 0,
            'comillasEscapadas' => 0,
            'lineasRecodificadas' => 0,
            'correcciones'      => [],
            'avisos'            => [],
            'totalAvisos'       => 0,
            'noAscii'           => [],
            'bomOriginal'       => $formato['bom'],
            'salto'             => $salto === "\r\n" ? 'CRLF' : 'LF',
            'finalConSalto'     => $formato['finalConSalto'],
            'bytesEntrada'      => $formato['bytes'],
        ];

        // El BOM se salta al leer y no se reescribe: MySQL trataría esos tres
        // bytes como parte de la primera sentencia y fallaría.
        if ($formato['bom']) {
            fseek($entrada, 3);
        }

        $numero = 0;
        $primera = true;

        while (($cruda = fgets($entrada)) !== false) {
            $numero++;

            // fgets conserva el salto de línea; lo quitamos para trabajar y lo
            // reponemos nosotros al escribir, respetando el formato original.
            $linea = rtrim($cruda, "\r\n");

            // Una línea final vacía por el salto del último renglón no cuenta.
            if ($linea === '' && feof($entrada) && $cruda !== $linea) {
                continue;
            }

            $reporte['lineas']++;

            $resultado = self::corregirLinea($linea);

            if ($resultado['cambios'] !== []) {
                $reporte['lineasCorregidas']++;
                $reporte['comillasEscapadas'] += $resultado['comillas'];
                if ($resultado['recodificada']) {
                    $reporte['lineasRecodificadas']++;
                }

                if (count($reporte['correcciones']) < self::MAX_DETALLE) {
                    $reporte['correcciones'][] = [
                        'linea'   => $numero,
                        'tipos'   => $resultado['cambios'],
                        'antes'   => $linea,
                        'despues' => $resultado['linea'],
                    ];
                }
            }

            foreach ($resultado['avisos'] as $aviso) {
                $reporte['totalAvisos']++;
                if (count($reporte['avisos']) < self::MAX_AVISOS) {
                    $reporte['avisos'][] = [
                        'linea'   => $numero,
                        'motivo'  => $aviso,
                        'texto'   => $resultado['linea'],
                    ];
                }
            }

            self::contarNoAscii($resultado['linea'], $reporte['noAscii']);

            if ($salida !== null) {
                if (!$primera) {
                    fwrite($salida, $salto);
                }
                fwrite($salida, $resultado['linea']);
                $primera = false;
            }
        }

        // Si el original terminaba con salto de línea, lo reponemos.
        if ($salida !== null && $formato['finalConSalto'] && $reporte['lineas'] > 0) {
            fwrite($salida, $salto);
        }

        fclose($entrada);
        if ($salida !== null) {
            fclose($salida);
        }

        arsort($reporte['noAscii']);

        return $reporte;
    }

    /**
     * Aplica las dos correcciones a una línea y la valida estructuralmente.
     *
     * @return array{linea:string,cambios:list<string>,comillas:int,recodificada:bool,avisos:list<string>}
     */
    public static function corregirLinea(string $linea): array
    {
        $cambios = [];
        $avisos = [];

        // Todo el análisis posterior asume UTF-8 válido.
        $linea = self::asegurarUtf8($linea);

        $recodificada = self::repararCodificacion($linea);
        if ($recodificada !== $linea) {
            $linea = $recodificada;
            $cambios[] = 'codificacion';
        }

        $comillas = 0;
        $escapada = self::escaparApostrofes($linea, $comillas, $avisos);
        if ($comillas > 0) {
            $linea = $escapada;
            $cambios[] = 'comillas';
        }

        foreach (self::validarEstructura($linea) as $aviso) {
            $avisos[] = $aviso;
        }

        return [
            'linea'        => $linea,
            'cambios'      => $cambios,
            'comillas'     => $comillas,
            'recodificada' => in_array('codificacion', $cambios, true),
            'avisos'       => $avisos,
        ];
    }

    /**
     * Escapa los apóstrofes que están dentro de un valor entrecomillado
     * duplicándolos ('' es el escape estándar de SQL).
     *
     * Distingue un apóstrofe literal de la comilla que cierra el valor por lo
     * que viene después: una comilla de cierre siempre va seguida de coma,
     * punto y coma, paréntesis o una palabra clave (WHERE / AND / OR / LIMIT).
     * En "nombre='YOIC'S'", la comilla tras YOIC va seguida de 'S', así que es
     * literal; la de después de la S va seguida de coma, así que cierra.
     *
     * @param list<string> $avisos
     */
    private static function escaparApostrofes(string $linea, int &$escapados, array &$avisos): string
    {
        $salida = '';
        $largo = strlen($linea);
        $dentro = false;
        $i = 0;

        while ($i < $largo) {
            $caracter = $linea[$i];

            if (!$dentro) {
                $salida .= $caracter;
                // Un valor abre con ='  (permitiendo espacios: = ')
                if ($caracter === "'" && self::abreValor($linea, $i)) {
                    $dentro = true;
                }
                $i++;
                continue;
            }

            if ($caracter !== "'") {
                $salida .= $caracter;
                $i++;
                continue;
            }

            // Ya venía escapada: se respeta tal cual y se avanzan ambas.
            if (($linea[$i + 1] ?? '') === "'") {
                $salida .= "''";
                $i += 2;
                continue;
            }

            if (self::esComillaDeCierre($linea, $i)) {
                $salida .= "'";
                $dentro = false;
                $i++;
                continue;
            }

            $salida .= "''";
            $escapados++;
            $i++;
        }

        if ($dentro) {
            $avisos[] = 'La línea termina con un valor entrecomillado sin cerrar.';
        }

        return $salida;
    }

    /**
     * ¿La comilla en $pos abre un valor? Lo hace si viene después de un '='
     * (tolerando espacios intermedios).
     */
    private static function abreValor(string $linea, int $pos): bool
    {
        for ($j = $pos - 1; $j >= 0; $j--) {
            if ($linea[$j] === ' ') {
                continue;
            }
            return $linea[$j] === '=';
        }

        return false;
    }

    /**
     * ¿La comilla en $pos cierra el valor? Sí cuando lo que sigue es un
     * separador o una palabra clave, y no más texto del propio valor.
     */
    private static function esComillaDeCierre(string $linea, int $pos): bool
    {
        $resto = substr($linea, $pos + 1);

        return (bool) preg_match('/^\s*(,|;|\)|WHERE\b|AND\b|OR\b|LIMIT\b|ORDER\b|$)/i', $resto);
    }

    /**
     * Deshace el mojibake: toma los caracteres de la cadena, los vuelve a
     * codificar como la codificación intermedia sospechosa y reinterpreta esos
     * bytes como UTF-8. Repite mientras el resultado sea UTF-8 válido y
     * contenga estrictamente menos caracteres sospechosos que antes, así que
     * un texto ya sano (CASTAÑON) nunca se toca.
     */
    public static function repararCodificacion(string $texto): string
    {
        $mejor = $texto;
        $puntaje = self::puntajeSospecha($texto);

        // Cada vuelta deshace una capa; dos capas es lo más que se ha visto,
        // el tope de cuatro es holgura defensiva contra bucles.
        for ($vuelta = 0; $vuelta < 4 && $puntaje > 0; $vuelta++) {
            $mejoro = false;

            foreach (self::CODIFICACIONES as $codificacion) {
                $candidato = @iconv('UTF-8', $codificacion, $mejor);
                if ($candidato === false || $candidato === '') {
                    continue;
                }

                // Los bytes resultantes solo sirven si forman UTF-8 válido.
                if (!self::esUtf8Valido($candidato)) {
                    continue;
                }

                // Nunca aceptamos introducir caracteres de control.
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $candidato)) {
                    continue;
                }

                $nuevoPuntaje = self::puntajeSospecha($candidato);
                if ($nuevoPuntaje < $puntaje) {
                    $mejor = $candidato;
                    $puntaje = $nuevoPuntaje;
                    $mejoro = true;
                }
            }

            if (!$mejoro) {
                break;
            }
        }

        return $mejor;
    }

    /**
     * Cuenta caracteres fuera de ASCII imprimible que tampoco son acentos
     * legítimos del español. Cuanto más alto, más sospechosa la cadena.
     */
    private static function puntajeSospecha(string $texto): int
    {
        if (!self::esUtf8Valido($texto)) {
            return PHP_INT_MAX;
        }

        $total = 0;
        $validos = preg_split('//u', self::ACENTOS_VALIDOS, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $caracter) {
            if (strlen($caracter) === 1 && ord($caracter) >= 0x20 && ord($caracter) <= 0x7E) {
                continue;
            }
            if (in_array($caracter, $validos, true)) {
                continue;
            }
            $total++;
        }

        return $total;
    }

    /**
     * Comprueba que la línea siga teniendo forma de sentencia ejecutable.
     * No corrige nada: solo avisa para que la persona revise a mano.
     *
     * @return list<string>
     */
    private static function validarEstructura(string $linea): array
    {
        $avisos = [];
        $limpia = trim($linea);

        if ($limpia === '' || str_starts_with($limpia, '--') || str_starts_with($limpia, '#')) {
            return $avisos;
        }

        if (!preg_match('/^(UPDATE|INSERT|DELETE|REPLACE)\b/i', $limpia)) {
            $avisos[] = 'No empieza con UPDATE / INSERT / DELETE / REPLACE.';
        }

        if (!str_ends_with($limpia, ';')) {
            $avisos[] = 'No termina en punto y coma.';
        }

        return $avisos;
    }

    /** Acumula el inventario de caracteres no-ASCII para el reporte. */
    private static function contarNoAscii(string $linea, array &$acumulado): void
    {
        if (!preg_match_all('/[^\x00-\x7E]/u', $linea, $coincidencias)) {
            return;
        }

        foreach ($coincidencias[0] as $caracter) {
            $acumulado[$caracter] = ($acumulado[$caracter] ?? 0) + 1;
        }
    }

    /**
     * Detecta BOM, tipo de salto de línea y si el archivo cierra con salto,
     * para reproducir el mismo formato en la salida.
     *
     * @return array{bom:bool,salto:string,finalConSalto:bool,bytes:int}
     */
    private static function detectarFormato(string $ruta): array
    {
        $bytes = (int) filesize($ruta);
        $manejador = @fopen($ruta, 'rb');

        if ($manejador === false) {
            throw new \RuntimeException('No se pudo leer el archivo.');
        }

        $inicio = (string) fread($manejador, 8192);
        $bom = str_starts_with($inicio, "\xEF\xBB\xBF");

        // Si el primer salto viene precedido de \r, el archivo es CRLF.
        $salto = "\n";
        $posicion = strpos($inicio, "\n");
        if ($posicion !== false && $posicion > 0 && $inicio[$posicion - 1] === "\r") {
            $salto = "\r\n";
        }

        $finalConSalto = false;
        if ($bytes > 0) {
            fseek($manejador, -1, SEEK_END);
            $ultimo = (string) fread($manejador, 1);
            $finalConSalto = $ultimo === "\n" || $ultimo === "\r";
        }

        fclose($manejador);

        return [
            'bom'           => $bom,
            'salto'         => $salto,
            'finalConSalto' => $finalConSalto,
            'bytes'         => $bytes,
        ];
    }

    /**
     * Devuelve la cadena como UTF-8 válido. Si no lo es, asume CP1252, que es
     * de donde vienen casi todos los volcados de Windows.
     */
    private static function asegurarUtf8(string $texto): string
    {
        if (self::esUtf8Valido($texto)) {
            return $texto;
        }

        $convertido = @iconv('CP1252', 'UTF-8//IGNORE', $texto);

        return $convertido === false ? $texto : $convertido;
    }

    private static function esUtf8Valido(string $texto): bool
    {
        return $texto === '' || preg_match('//u', $texto) === 1;
    }
}
