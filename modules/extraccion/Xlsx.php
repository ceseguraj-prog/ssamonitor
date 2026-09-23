<?php

declare(strict_types=1);

namespace App;

use ZipArchive;

/**
 * Escritor mínimo de archivos .xlsx.
 *
 * Un .xlsx es un zip con unos cuantos XML dentro, y ZipArchive ya se usa en el
 * proyecto (el paquete de Terceros). Eso es más barato que traer PhpSpreadsheet:
 * el proyecto no tiene Composer y meterlo solo para escribir dos hojas de
 * números obligaría a montar un autoloader y a versionar unos cuantos miles de
 * archivos de vendor.
 *
 * A cambio, esto hace lo justo y nada más: varias hojas, texto y números, seis
 * estilos y ancho de columna. No hay fórmulas, ni imágenes, ni fechas — si
 * alguna vez hacen falta, ahí sí conviene la librería de verdad.
 *
 * Las cadenas van como inlineStr en vez de la tabla compartida de cadenas: en
 * una hoja de resumen casi nada se repite, así que la tabla no ahorraría nada y
 * sí obligaría a un XML más y a un índice.
 *
 * Uso:
 *   $xlsx = new Xlsx();
 *   $xlsx->hoja('Resumen', [28, 10, 10], [
 *       [['RegYEvenQNA17_26', 'titulo']],
 *       [],
 *       [['Unidad', 'encabezado'], ['IMP', 'encabezado']],
 *       ['REGULARIZADOS', [1731, 'numero']],
 *   ]);
 *   $xlsx->guardar($ruta);
 */
class Xlsx
{
    /** Índices de cellXfs en styles.xml, por nombre. */
    private const ESTILOS = [
        ''            => 0,
        'titulo'      => 1,
        'encabezado'  => 2,
        'numero'      => 3,
        'total'       => 4,
        'totalNumero' => 5,
        'fuerte'      => 6,
    ];

    /** @var list<array{nombre:string,anchos:list<int>,filas:list<array>}> */
    private array $hojas = [];

    /**
     * Agrega una hoja.
     *
     * @param string     $nombre nombre de la pestaña; Excel lo limita a 31 caracteres
     * @param list<int>  $anchos ancho de cada columna, en caracteres
     * @param list<array> $filas  cada fila es una lista de celdas; una celda es un
     *                            escalar o [valor, estilo]. Una fila vacía es un
     *                            renglón en blanco.
     */
    public function hoja(string $nombre, array $anchos, array $filas): void
    {
        $this->hojas[] = [
            'nombre' => self::nombreDeHoja($nombre),
            'anchos' => $anchos,
            'filas'  => $filas,
        ];
    }

    /** Escribe el libro en disco. */
    public function guardar(string $ruta): void
    {
        if (!$this->hojas) {
            throw new \RuntimeException('Un libro necesita al menos una hoja.');
        }

        $zip = new ZipArchive();

        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', self::relsRaiz());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());

        foreach ($this->hojas as $i => $hoja) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', self::sheet($hoja));
        }

        $zip->close();
    }

    /* ── Piezas del paquete ─────────────────────────────────────────────── */

    private function contentTypes(): string
    {
        $overrides = '';

        foreach (array_keys($this->hojas) as $i) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return self::CABECERA_XML
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '<Override PartName="/xl/styles.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsRaiz(): string
    {
        return self::CABECERA_XML
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
            . ' Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';

        foreach ($this->hojas as $i => $hoja) {
            $sheets .= '<sheet name="' . self::esc($hoja['nombre']) . '" sheetId="' . ($i + 1)
                . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return self::CABECERA_XML
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $base = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        $rels = '';

        foreach (array_keys($this->hojas) as $i) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="' . $base . 'worksheet"'
                . ' Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }

        // Los estilos van después de las hojas para no pisarles el rId.
        $rels .= '<Relationship Id="rId' . (count($this->hojas) + 1) . '" Type="' . $base . 'styles"'
            . ' Target="styles.xml"/>';

        return self::CABECERA_XML
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels . '</Relationships>';
    }

    /**
     * Los seis estilos del libro. El orden de cellXfs es el que mapea
     * self::ESTILOS, así que agregar uno nuevo es añadirlo al final de los dos
     * sitios: si se intercalan, todas las celdas ya escritas cambian de pinta.
     */
    private static function styles(): string
    {
        return self::CABECERA_XML
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF6B1F33"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF6B1F33"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3E7EB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top style="thin"><color rgb="FFBFA6AE"/></top><bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"'
            . ' applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1"'
            . ' applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** @param array{nombre:string,anchos:list<int>,filas:list<array>} $hoja */
    private static function sheet(array $hoja): string
    {
        $cols = '';

        foreach ($hoja['anchos'] as $i => $ancho) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="'
                . (float) $ancho . '" customWidth="1"/>';
        }

        if ($cols !== '') {
            $cols = '<cols>' . $cols . '</cols>';
        }

        $filas = '';
        $numero = 0;

        foreach ($hoja['filas'] as $fila) {
            $numero++;

            // Una fila vacía se omite del XML: el renglón existe igual en la
            // hoja y así no se escriben celdas que nadie va a leer.
            if (!$fila) {
                continue;
            }

            $celdas = '';

            foreach (array_values($fila) as $columna => $celda) {
                $celdas .= self::celda(self::columna($columna) . $numero, $celda);
            }

            $filas .= '<row r="' . $numero . '">' . $celdas . '</row>';
        }

        return self::CABECERA_XML
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols . '<sheetData>' . $filas . '</sheetData></worksheet>';
    }

    /** @param mixed $celda escalar, o [valor, estilo] */
    private static function celda(string $ref, $celda): string
    {
        $estilo = '';

        if (is_array($celda)) {
            $estilo = (string) ($celda[1] ?? '');
            $celda = $celda[0] ?? '';
        }

        $s = self::ESTILOS[$estilo] ?? 0;
        $atributos = ' r="' . $ref . '"' . ($s !== 0 ? ' s="' . $s . '"' : '');

        if ($celda === null || $celda === '') {
            return '<c' . $atributos . '/>';
        }

        if (is_int($celda) || is_float($celda)) {
            return '<c' . $atributos . '><v>' . $celda . '</v></c>';
        }

        return '<c' . $atributos . ' t="inlineStr"><is><t xml:space="preserve">'
            . self::esc((string) $celda) . '</t></is></c>';
    }

    /** Letra de columna desde un índice base cero: 0 → A, 26 → AA. */
    private static function columna(int $indice): string
    {
        $letra = '';

        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letra = chr(65 + ($n - 1) % 26) . $letra;
        }

        return $letra;
    }

    /**
     * Nombre de pestaña que Excel acepta: sin los caracteres reservados y de
     * 31 caracteres como mucho. Si se pasa, Excel no avisa: se niega a abrir
     * el archivo entero.
     */
    private static function nombreDeHoja(string $nombre): string
    {
        $limpio = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '-', $nombre);

        return mb_substr($limpio, 0, 31) ?: 'Hoja';
    }

    private static function esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private const CABECERA_XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
}
