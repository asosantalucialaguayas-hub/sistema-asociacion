<?php
/**
 * xlsx_generator.php
 * Generador XLSX nativo — sin librerías externas.
 * Incluir con: require "xlsx_generator.php";
 *
 * Uso:
 *   $xls = new XlsxGenerator();
 *   $xls->addStyle([...]);       // opcional
 *   $sh = $xls->addSheet("LPA");
 *   $sh->setColWidth('A', 12);
 *   $sh->setRowHeight(1, 40);
 *   $sh->writeCell('A1', 'Texto', $styleIdx);
 *   $sh->writeCell('B1', 123.45, $styleIdx, 'n');
 *   $sh->merge('A1:C1');
 *   $xls->download("archivo.xlsx");
 */

class XlsxSheet {
    public  string $name;
    private array  $cells    = [];   // 'A1' => [val, si, type]
    private array  $merges   = [];
    private array  $colW     = [];
    private array  $rowH     = [];
    private int    $maxRow   = 0;
    private int    $maxCol   = 0;
    private array  $strings;         // referencia al pool de strings compartidos

    public function __construct(string $name, array &$strings) {
        $this->name    = $name;
        $this->strings = &$strings;
    }

    public function setColWidth(string $col, float $w): void {
        $this->colW[strtoupper($col)] = $w;
    }
    public function setRowHeight(int $row, float $h): void {
        $this->rowH[$row] = $h;
    }
    public function merge(string $range): void {
        $this->merges[] = $range;
    }

    /**
     * @param string $ref   'A1', 'B3', 'AA5' ...
     * @param mixed  $val   string o número
     * @param int    $si    índice de estilo (0 si no hay)
     * @param string $type  's'=string  'n'=número
     */
    public function writeCell(string $ref, $val, int $si = 0, string $type = 's'): void {
        [$col, $row] = $this->parseRef($ref);
        if ($row > $this->maxRow) $this->maxRow = $row;
        $colNum = self::col2num($col);
        if ($colNum > $this->maxCol) $this->maxCol = $colNum;
        $this->cells[$row][$col] = [$val, $si, $type];
    }

    public function buildXml(bool $freeze = true, string $freezeCell = 'A9'): string {
        // ColWidths
        $cwXml = '';
        if ($this->colW) {
            $map = [];
            foreach ($this->colW as $c => $w) { $map[self::col2num($c)] = $w; }
            ksort($map);
            $cwXml = '<cols>';
            foreach ($map as $i => $w) {
                $cwXml .= sprintf('<col min="%d" max="%d" width="%.2f" customWidth="1"/>', $i, $i, $w);
            }
            $cwXml .= '</cols>';
        }

        // Freeze pane
        $freezeXml = '';
        if ($freeze && $this->maxRow >= 9) {
            $freezeXml = '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
                . '<pane ySplit="8" topLeftCell="A9" activePane="bottomLeft" state="frozen"/>'
                . '</sheetView></sheetViews>';
        } else {
            $freezeXml = '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>';
        }

        // SheetData
        $sdXml = '<sheetData>';
        for ($r = 1; $r <= $this->maxRow; $r++) {
            $ht = isset($this->rowH[$r])
                ? sprintf(' ht="%.1f" customHeight="1"', $this->rowH[$r])
                : '';
            $sdXml .= "<row r=\"{$r}\"{$ht}>";
            if (!empty($this->cells[$r])) {
                ksort($this->cells[$r]);
                foreach ($this->cells[$r] as $col => [$val, $si, $type]) {
                    $ref = $col . $r;
                    $s   = " s=\"{$si}\"";
                    if ($type === 'n') {
                        $num = is_numeric($val) ? $val : 0;
                        $sdXml .= "<c r=\"{$ref}\"{$s} t=\"n\"><v>{$num}</v></c>";
                    } else {
                        // sharedString
                        $str = (string)$val;
                                                if (!isset($this->strings[$str])) {
                            $this->strings[$str] = count($this->strings);
                        }
                        $idx = $this->strings[$str];
                        $sdXml .= "<c r=\"{$ref}\"{$s} t=\"s\"><v>{$idx}</v></c>";
                    }
                }
            }
            $sdXml .= '</row>';
        }
        $sdXml .= '</sheetData>';

        // Merges
        $mergeXml = '';
        if ($this->merges) {
            $mergeXml = '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) { $mergeXml .= "<mergeCell ref=\"{$m}\"/>"; }
            $mergeXml .= '</mergeCells>';
        }

        // AutoFilter — solo si hay datos
        $afXml = '';
        if ($this->maxRow > 8) {
            $lastCol = self::num2col($this->maxCol);
            $afXml   = "<autoFilter ref=\"A7:{$lastCol}{$this->maxRow}\"/>";
        }

        // PageSetup
        $psXml = '<pageSetup orientation="landscape" paperSize="8" fitToWidth="1" fitToHeight="0"/>'
               . '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.3" footer="0.3"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $freezeXml . $cwXml . $sdXml . $mergeXml . $afXml . $psXml
            . '</worksheet>';
    }

    private function parseRef(string $ref): array {
        preg_match('/^([A-Z]+)(\d+)$/i', strtoupper($ref), $m);
        return [$m[1], (int)$m[2]];
    }
    public static function col2num(string $col): int {
        $col = strtoupper($col); $n = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $n = $n * 26 + (ord($col[$i]) - 64);
        }
        return $n;
    }
    public static function num2col(int $n): string {
        $s = '';
        while ($n > 0) { $n--; $s = chr(65 + $n % 26) . $s; $n = intdiv($n, 26); }
        return $s;
    }
}

class XlsxGenerator {
    private array $sheets  = [];
    private array $strings = [];   // pool sharedStrings
    private array $styles  = [];   // índices de estilo

    // ── Estilos predefinidos ─────────────────────────────────────────────────
    // Cada estilo: [fontBold, fontSize, fontColor, bgARGB, hAlign, vAlign, wrap, numFmtId, italic]
    public array $styleMap = [];

    public function __construct() {
        // Estilos base — orden importa (índice 0,1,2...)
        $this->styleMap = [
            // 0  normal blanco centro
            [false, 9,  '000000', 'FFFFFFFF', 'center', 'center', false, 0,   false],
            // 1  normal crema centro
            [false, 9,  '000000', 'FFFAFAF0', 'center', 'center', false, 0,   false],
            // 2  normal blanco izquierda
            [false, 9,  '000000', 'FFFFFFFF', 'left',   'center', false, 0,   false],
            // 3  normal crema izquierda
            [false, 9,  '000000', 'FFFAFAF0', 'left',   'center', false, 0,   false],
            // 4  encabezado info dorado claro negro negrita izq
            [true,  9,  '000000', 'FFC4AF6E', 'left',   'center', false, 0,   false],
            // 5  sub-encabezado columnas dorado medio negro negrita centro wrap
            [true,  8,  '000000', 'FFD4C98A', 'center', 'center', true,  0,   false],
            // 6  fila LPA amarillo negrita centro
            [true,  9,  '000000', 'FFF5E6A3', 'center', 'center', false, 0,   false],
            // 7  fila unidades crema pequeño gris centro
            [false, 7,  '555555', 'FFFAF5E4', 'center', 'center', false, 0,   false],
            // 8  texto FLO-CERT grande marrón
            [true,  14, '5A3A1A', 'FFF0EEC0', 'center', 'center', false, 0,   false],
            // 9  decimal blanco
            [false, 9,  '000000', 'FFFFFFFF', 'center', 'center', false, 164, false],
            // 10 decimal crema
            [false, 9,  '000000', 'FFFAFAF0', 'center', 'center', false, 164, false],
            // 11 separador crema
            [false, 8,  '000000', 'FFF0EEC0', 'center', 'center', false, 0,   false],
            // 12 logo verde
            [false, 9,  '000000', 'FF8FAF6C', 'center', 'center', false, 0,   false],
            // 13 logo naranja
            [false, 9,  '000000', 'FFE8927C', 'center', 'center', false, 0,   false],
            // 14 logo azul
            [false, 9,  '000000', 'FFA7B8D0', 'center', 'center', false, 0,   false],
            // 15 logo crema
            [false, 9,  '000000', 'FFF0EEC0', 'center', 'center', false, 0,   false],
            // 16 logo camel
            [false, 9,  '000000', 'FFC8A878', 'center', 'center', false, 0,   false],
            // 17 logo salmón
            [false, 9,  '000000', 'FFD4A880', 'center', 'center', false, 0,   false],
            // 18 encabezado consolidado azul oscuro blanco negrita centro
            [true,  10, 'FFFFFF', 'FF1F3A5F', 'center', 'center', false, 0,   false],
            // 19 encabezado consolidado fila sub azul medio blanco negrita centro wrap
            [true,  9,  'FFFFFF', 'FF2563EB', 'center', 'center', true,  0,   false],
            // 20 total gris negrita
            [true,  9,  '000000', 'FFF3F4F6', 'right',  'center', false, 164, false],
            // 21 total label gris negrita derecha
            [true,  9,  '000000', 'FFF3F4F6', 'right',  'center', false, 0,   false],
        ];
    }

    public function addSheet(string $name): XlsxSheet {
        $sh = new XlsxSheet($name, $this->strings);
        $this->sheets[] = $sh;
        return $sh;
    }

    public function download(string $filename): void {
        $xlsx = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: max-age=0');
        echo $xlsx;
        exit;
    }

    private function build(): string {
        $buf = '';
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // _rels/.rels
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        // Content_Types
        $ctXml  = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        foreach ($this->sheets as $i => $sh) {
            $ctXml .= '<Override PartName="/xl/worksheets/sheet' . ($i+1) . '.xml" '
                   . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ctXml .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ctXml);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $sh) {
            $wbRels .= '<Relationship Id="rId' . ($i+1) . '" '
                    . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                    . 'Target="worksheets/sheet' . ($i+1) . '.xml"/>';
        }
        $n = count($this->sheets);
        $wbRels .= '<Relationship Id="rId' . ($n+1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbRels .= '<Relationship Id="rId' . ($n+2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $wbRels .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $wbXml  = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets>';
        foreach ($this->sheets as $i => $sh) {
            $wbXml .= '<sheet name="' . htmlspecialchars($sh->name, ENT_XML1) . '" sheetId="' . ($i+1) . '" r:id="rId' . ($i+1) . '"/>';
        }
        $wbXml .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $wbXml);

        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        // xl/worksheets/sheet*.xml  (PRIMERO las hojas para popular sharedStrings)
        $sheetXmls = [];
        foreach ($this->sheets as $i => $sh) {
            $sheetXmls[$i] = $sh->buildXml();
        }
        foreach ($sheetXmls as $i => $xml) {
            $zip->addFromString('xl/worksheets/sheet' . ($i+1) . '.xml', $xml);
        }

        // xl/sharedStrings.xml  (después de buildXml para tener todos los strings)
        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStrings());

        $zip->close();
        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }

    private function buildSharedStrings(): string {
        $count = count($this->strings);
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$count}\" uniqueCount=\"{$count}\">";
        // Ordenar por índice
        $sorted = array_flip($this->strings); // idx => string
        ksort($sorted);
        foreach ($sorted as $str) {
            $esc = htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= "<si><t xml:space=\"preserve\">{$esc}</t></si>";
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function buildStyles(): string {
        $fonts = []; $fills = []; $borders = []; $xfs = [];
        $brd = 'FFC8B870';

        foreach ($this->styleMap as $s) {
            [$bold, $sz, $fc, $bg, $ha, $va, $wrap, $nfId, $italic] = array_pad($s, 9, false);
            $b    = $bold   ? '<b/>'   : '';
            $it   = $italic ? '<i/>'   : '';
            $fKey = "{$bold}|{$italic}|{$sz}|{$fc}";
            if (!array_key_exists($fKey, $fonts)) {
                $fonts[$fKey] = "<font><sz val=\"{$sz}\"/>{$b}{$it}<color rgb=\"FF{$fc}\"/><name val=\"Arial\"/></font>";
            }
            $fIdx = array_search($fKey, array_keys($fonts));

            if (!array_key_exists($bg, $fills)) {
                $fills[$bg] = "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"{$bg}\"/></patternFill></fill>";
            }
            $bgIdx = array_search($bg, array_keys($fills)) + 2;

            $bKey = count($borders);
            $borders[] = "<border>"
                . "<left style=\"thin\"><color rgb=\"{$brd}\"/></left>"
                . "<right style=\"thin\"><color rgb=\"{$brd}\"/></right>"
                . "<top style=\"thin\"><color rgb=\"{$brd}\"/></top>"
                . "<bottom style=\"thin\"><color rgb=\"{$brd}\"/></bottom>"
                . "</border>";

            $wt  = $wrap ? 'wrapText="1" ' : '';
            $xfs[] = "<xf numFmtId=\"{$nfId}\" fontId=\"{$fIdx}\" fillId=\"{$bgIdx}\" borderId=\"{$bKey}\" "
                   . "xfId=\"0\" applyFont=\"1\" applyFill=\"1\" applyBorder=\"1\" applyAlignment=\"1\">"
                   . "<alignment horizontal=\"{$ha}\" vertical=\"{$va}\" {$wt}/>"
                   . "</xf>";
        }

        $nF  = count($fonts);
        $nFi = count($fills) + 2;
        $nB  = count($borders);
        $nX  = count($xfs);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts>'
            . "<fonts count=\"{$nF}\">" . implode('', array_values($fonts)) . '</fonts>'
            . "<fills count=\"{$nFi}\"><fill><patternFill patternType=\"none\"/></fill><fill><patternFill patternType=\"gray125\"/></fill>" . implode('', array_values($fills)) . '</fills>'
            . "<borders count=\"{$nB}\">" . implode('', $borders) . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . "<cellXfs count=\"{$nX}\">" . implode('', $xfs) . '</cellXfs>'
            . '</styleSheet>';
    }
}
