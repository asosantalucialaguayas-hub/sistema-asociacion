<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { die('No autorizado'); }

require "config/conexion.php";
require "vendor/autoload.php"; // PhpSpreadsheet via composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$id_periodo = intval($_GET['id_periodo'] ?? 0);
$anio       = intval($_GET['anio'] ?? date('Y'));

// ── CONSTANTES FAIRTRADE ──────────────────────────────────────────────
define('FAIRTRADE_DIVISOR', 22.046);
define('FAIRTRADE_PRIMA',   240);
define('FAIRTRADE_BASE',    275);
define('ORG_ES',  'ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS');
define('ORG_EN_LPA', 'SANTA LUCIA COROTU');
define('ORG_EN_EST', 'ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS');
define('ORG_PLAN', 'ASOCIACIÓN DE TRABAJADORES AGRÍCOLAS AUTONÓMOS SANTA LUCIA COROTÚ');

// ── ESTILOS ───────────────────────────────────────────────────────────
$AMARILLO = 'FFE699';
$AZUL_HDR = '1F3A5F';
$BLANCO   = 'FFFFFF';
$NEGRO    = '000000';

function bordeFinoStyle(): array {
    $thin = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']];
    return ['borders' => ['allBorders' => $thin]];
}

function headerStyle(string $bg = 'FFE699', int $size = 9, bool $wrap = true): array {
    return [
        'font'      => ['bold' => true, 'size' => $size, 'name' => 'Arial', 'color' => ['argb' => 'FF000000']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$bg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => $wrap],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ];
}

function infoStyle(string $bg = 'FFE699', bool $left = true): array {
    return [
        'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$bg]],
        'alignment' => ['horizontal' => $left ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ];
}

function datoStyle(bool $center = true, string $bg = 'FFFFFFFF'): array {
    return [
        'font'      => ['size' => 9, 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
        'alignment' => ['horizontal' => $center ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ];
}

function totalStyle(string $bg = 'FFE699'): array {
    return [
        'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$bg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ];
}

// ── CONSULTA DB ───────────────────────────────────────────────────────
try {
    $sql = "
        SELECT
            l.zona,
            l.comunidad_grupo,
            s.identificacion AS cedula,
            TRIM(COALESCE(NULLIF(s.nombre_completo,''),
                 CONCAT(COALESCE(s.apellidos,''),' ',COALESCE(s.nombres,'')))) AS nombre_completo,
            UPPER(COALESCE(l.sexo, s.sexo, ''))                    AS sexo,
            COALESCE(l.celular, s.telefono, '')                    AS celular,
            DATE_FORMAT(COALESCE(l.fecha_nacimiento,s.fecha_nacimiento),'%d/%m/%Y') AS fecha_nacimiento,
            DATE_FORMAT(COALESCE(l.fecha_ingreso,s.fecha_ingreso),'%d/%m/%Y')       AS fecha_ingreso,
            UPPER(COALESCE(l.en_acercamiento,'NO'))                AS en_acercamiento,
            UPPER(COALESCE(l.otra_org_fairtrade,'NO'))             AS otra_org_fairtrade,
            COALESCE(l.area_total_ha,0)                            AS area_total_ha,
            COALESCE(l.area_cacao_ha,0)                            AS area_cacao_ha,
            COALESCE(l.num_matas_ha,0)                             AS num_matas_ha,
            COALESCE(l.certificacion_organica,'NO')                AS cert_organica,
            COALESCE(l.volumen_produccion_estimado,0)              AS vol_produccion,
            COALESCE(l.volumen_entregado_org,0)                    AS vol_entregado,
            COALESCE(l.enero,0)      AS enero,
            COALESCE(l.febrero,0)    AS febrero,
            COALESCE(l.marzo,0)      AS marzo,
            COALESCE(l.abril,0)      AS abril,
            COALESCE(l.mayo,0)       AS mayo,
            COALESCE(l.junio,0)      AS junio,
            COALESCE(l.julio,0)      AS julio,
            COALESCE(l.agosto,0)     AS agosto,
            COALESCE(l.septiembre,0) AS septiembre,
            COALESCE(l.octubre,0)    AS octubre,
            COALESCE(l.noviembre,0)  AS noviembre,
            COALESCE(l.diciembre,0)  AS diciembre
        FROM tabla_lpa l
        INNER JOIN socios s ON s.id_socio = l.id_socio
        WHERE 1=1
        " . ($id_periodo ? "AND l.id_periodo = :id_periodo" : "") . "
        ORDER BY nombre_completo ASC
    ";

    $stmt = $pdo->prepare($sql);
    if ($id_periodo) $stmt->bindValue(':id_periodo', $id_periodo, PDO::PARAM_INT);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($datos);

} catch (Exception $e) {
    die("Error DB: " . $e->getMessage());
}

// ── FUNCIONES AUXILIARES ──────────────────────────────────────────────
$mesesCols = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];

/**
 * Construye una hoja LPA o Estimación de Cosecha.
 * $ws       = worksheet
 * $datos    = array de filas de la BD
 * $anio     = año
 * $orgEn    = nombre en inglés
 * $etiqueta = "LPA" o "ESTIMACIÓN DE COSECHA"
 */
function construirHojaLPA($ws, array $datos, int $anio, string $orgEn): void
{
    global $mesesCols;
    $total    = count($datos);
    $AMARILLO = 'FFE699';
    $AZUL     = '1F3A5F';

    // ── ANCHOS DE COLUMNA ─────────────────────────────────────────────
    $anchos = [5,10,18,14,34,6,12,12,16,14,18,10,10,10,10,10,12,
               6,6,6,6,6,6,6,6,6,6,6,6];
    $letras = range('A','Z');
    array_push($letras,'AA','AB','AC');
    foreach ($anchos as $i => $w) {
        $ws->getColumnDimension($letras[$i])->setWidth($w);
    }

    // ── FILA 1: Info socios ───────────────────────────────────────────
    $fila = 1;
    $ws->getRowDimension($fila)->setRowHeight(16);
    $ws->setCellValue("A$fila", "Lista de Productores Miembros:");
    $ws->setCellValue("F$fila", "$total SOCIOS");
    $ws->setCellValue("O$fila", "List of members producers:");
    $ws->setCellValue("T$fila", "$total SOCIOS");
    $ws->mergeCells("A$fila:E$fila");
    $ws->mergeCells("F$fila:M$fila");
    $ws->mergeCells("O$fila:S$fila");
    $ws->mergeCells("T$fila:AC$fila");
    $ws->getStyle("A$fila:M$fila")->applyFromArray(infoStyle());
    $ws->getStyle("O$fila:AC$fila")->applyFromArray(infoStyle());

    // ── FILA 2: Año ───────────────────────────────────────────────────
    $fila = 2;
    $ws->getRowDimension($fila)->setRowHeight(16);
    $ws->setCellValue("A$fila", "AÑO:");
    $ws->setCellValue("F$fila", $anio);
    $ws->setCellValue("O$fila", "YEAR:");
    $ws->setCellValue("T$fila", $anio);
    $ws->mergeCells("A$fila:E$fila");
    $ws->mergeCells("F$fila:M$fila");
    $ws->mergeCells("O$fila:S$fila");
    $ws->mergeCells("T$fila:AC$fila");
    $ws->getStyle("A$fila:M$fila")->applyFromArray(infoStyle());
    $ws->getStyle("O$fila:AC$fila")->applyFromArray(infoStyle());

    // ── FILA 3: Organización ──────────────────────────────────────────
    $fila = 3;
    $ws->getRowDimension($fila)->setRowHeight(16);
    $ws->setCellValue("A$fila", "Nombre de la Organización:");
    $ws->setCellValue("F$fila", ORG_ES);
    $ws->setCellValue("O$fila", "Name of Organisation:");
    $ws->setCellValue("T$fila", $orgEn);
    $ws->mergeCells("A$fila:E$fila");
    $ws->mergeCells("F$fila:M$fila");
    $ws->mergeCells("O$fila:S$fila");
    $ws->mergeCells("T$fila:AC$fila");
    $ws->getStyle("A$fila:M$fila")->applyFromArray(infoStyle());
    $ws->getStyle("O$fila:AC$fila")->applyFromArray(infoStyle());

    // ── FILA 4: Separador ─────────────────────────────────────────────
    $fila = 4;
    $ws->getRowDimension($fila)->setRowHeight(8);

    // ── FILA 5: Encabezados ───────────────────────────────────────────
    $fila = 5;
    $ws->getRowDimension($fila)->setRowHeight(60);
    $headers = [
        'A' => "N°",
        'B' => "Zona",
        'C' => "Comunidad o Grupo",
        'D' => "Cédula del Productor",
        'E' => "Apellidos y nombres productor/a",
        'F' => "Sexo\n(F/M)",
        'G' => "Celular",
        'H' => "Fecha de nacimiento",
        'I' => "Fecha de afiliación\na la organización",
        'J' => "En acercamiento\n(en proceso para\ningresar de socio)",
        'K' => "Socios que también son\nmiembros de otra\norganización certificada\nFairtrade SI/NO",
        'L' => "Área total de su\nunidad de produccion\n(Ha)",
        'M' => "Área cultivada\nde Cacao (Ha)",
        'N' => "Número de\nmatas por ha",
        'O' => "Estatus de\ncertificación\nOrgánica SI/NO",
        'P' => "Volumen de\nproducción de Cacao",
        'Q' => "Volumen de producción\nde Cacao entregado\na la organización",
        'R' => "E",  'S' => "F", 'T' => "M",  'U' => "A",
        'V' => "M",  'W' => "J", 'X' => "JL", 'Y' => "A",
        'Z' => "S", 'AA' => "O",'AB' => "N", 'AC' => "D",
    ];
    foreach ($headers as $col => $val) {
        $ws->setCellValue("$col$fila", $val);
        $ws->getStyle("$col$fila")->applyFromArray(headerStyle($AMARILLO, 8, true));
    }

    // ── FILA 6: Subfila 1000/has y LPA ───────────────────────────────
    $fila = 6;
    $ws->getRowDimension($fila)->setRowHeight(14);
    foreach (array_merge(range('A','AC'),[]) as $col) {
        // set empty + style
    }
    // estilo toda la fila
    $ws->getStyle("A$fila:AC$fila")->applyFromArray(headerStyle($AMARILLO, 8, false));
    $ws->setCellValue("N$fila", "1000/has");
    $ws->getStyle("N$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->setCellValue("R$fila", "LPA");
    $ws->mergeCells("R$fila:AC$fila");
    $ws->getStyle("R$fila")->getFont()->setBold(true)->setSize(10);
    $ws->getStyle("R$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // ── FILAS DE DATOS ────────────────────────────────────────────────
    $fila      = 7;
    $filaInicio = $fila;

    foreach ($datos as $idx => $row) {
        $ws->getRowDimension($fila)->setRowHeight(14);
        $matas = ($row['num_matas_ha'] > 0) ? $row['num_matas_ha'] . '/has' : '';

        // Columnas texto
        $ws->setCellValueExplicit("A$fila", $idx + 1, DataType::TYPE_NUMERIC);
        $ws->setCellValueExplicit("B$fila", $row['zona']           ?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("C$fila", $row['comunidad_grupo']?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("D$fila", $row['cedula']         ?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("E$fila", $row['nombre_completo']?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("F$fila", $row['sexo']           ?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("G$fila", $row['celular']        ?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("H$fila", $row['fecha_nacimiento']?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("I$fila", $row['fecha_ingreso']  ?? '', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("J$fila", $row['en_acercamiento']?? 'NO', DataType::TYPE_STRING);
        $ws->setCellValueExplicit("K$fila", $row['otra_org_fairtrade']?? 'NO', DataType::TYPE_STRING);

        // Columnas numéricas
        $ws->setCellValue("L$fila", (float)$row['area_total_ha']);
        $ws->setCellValue("M$fila", (float)$row['area_cacao_ha']);
        $ws->setCellValueExplicit("N$fila", $matas, DataType::TYPE_STRING);
        $ws->setCellValueExplicit("O$fila", $row['cert_organica'] ?? 'NO', DataType::TYPE_STRING);
        $ws->setCellValue("P$fila", (float)$row['vol_produccion']);
        $ws->setCellValue("Q$fila", (float)$row['vol_entregado']);

        // Meses
        $colsMeses = ['R','S','T','U','V','W','X','Y','Z','AA','AB','AC'];
        foreach ($mesesCols as $mi => $mes) {
            $v = (float)$row[$mes];
            $col = $colsMeses[$mi];
            if ($v > 0) {
                $ws->setCellValue("$col$fila", $v);
                $ws->getStyle("$col$fila")->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $ws->setCellValue("$col$fila", '');
            }
        }

        // Formatos numéricos
        foreach (['L','M','P','Q'] as $c) {
            $ws->getStyle("$c$fila")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Estilos
        $ws->getStyle("A$fila:AC$fila")->applyFromArray(datoStyle(true));
        $ws->getStyle("E$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("C$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $fila++;
    }

    $filaFin = $fila - 1;

    // ── FILA TOTALES ──────────────────────────────────────────────────
    $ws->getRowDimension($fila)->setRowHeight(16);
    $ws->getStyle("A$fila:AC$fila")->applyFromArray(totalStyle());

    // Suma Area Total, Area Cacao, Vol produccion, Vol entregado, meses
    foreach (['L','M','P','Q'] as $c) {
        $ws->setCellValue("$c$fila", "=SUM({$c}{$filaInicio}:{$c}{$filaFin})");
        $ws->getStyle("$c$fila")->getNumberFormat()->setFormatCode('#,##0.00');
    }
    $colsMeses = ['R','S','T','U','V','W','X','Y','Z','AA','AB','AC'];
    foreach ($colsMeses as $c) {
        $ws->setCellValue("$c$fila", "=SUM({$c}{$filaInicio}:{$c}{$filaFin})");
        $ws->getStyle("$c$fila")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    $filaTotales = $fila;
    $fila += 2;

    // ── CÁLCULOS FAIRTRADE ────────────────────────────────────────────
    $divisor = FAIRTRADE_DIVISOR;
    $prima   = FAIRTRADE_PRIMA;
    $base    = FAIRTRADE_BASE;

    // Toneladas
    $ws->setCellValue("N$fila", 'TONELADA');
    $ws->getStyle("N$fila")->getFont()->setBold(true)->setSize(9);
    $ws->setCellValue("O$fila", "DIVIDIDO A {$divisor} =");
    $ws->getStyle("O$fila")->getFont()->setSize(9);
    $ws->mergeCells("O$fila:P$fila");
    $ws->setCellValue("Q$fila", "=P{$filaTotales}/{$divisor}");
    $ws->getStyle("Q$fila")->getNumberFormat()->setFormatCode('#,##0.00');
    $ws->getStyle("Q$fila")->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF'.'1F3A5F');
    $filaTon = $fila;

    $fila++;
    // Prima × 240
    $ws->setCellValue("N$fila", 'PRIMA');
    $ws->getStyle("N$fila")->getFont()->setBold(true)->setSize(9);
    $ws->setCellValue("O$fila", "MULTIPLICADO * {$prima} =");
    $ws->mergeCells("O$fila:P$fila");
    $ws->setCellValue("Q$fila", "=Q{$filaTon}*{$prima}");
    $ws->getStyle("Q$fila")->getNumberFormat()->setFormatCode('#,##0.00');
    $ws->getStyle("Q$fila")->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF'.'1F3A5F');
    $filaPrima = $fila;

    $fila++;
    // Prima × 275
    $ws->setCellValue("O$fila", "MULTIPLICADO * {$base} =");
    $ws->mergeCells("O$fila:P$fila");
    $ws->setCellValue("Q$fila", "=Q{$filaTon}*{$base}");
    $ws->getStyle("Q$fila")->getNumberFormat()->setFormatCode('#,##0.00');
    $ws->getStyle("Q$fila")->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF'.'1F3A5F');
    $filaBase = $fila;

    $fila++;
    // Beneficio prima total
    $ws->setCellValue("O$fila", 'BENEFICIO PRIMA');
    $ws->mergeCells("O$fila:P$fila");
    $ws->getStyle("O$fila")->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FF'.'1F3A5F');
    $ws->setCellValue("Q$fila", "=Q{$filaPrima}+Q{$filaBase}");
    $ws->getStyle("Q$fila")->getNumberFormat()->setFormatCode('#,##0.00');
    $ws->getStyle("Q$fila")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FF'.'1F3A5F');
}

// ── CONSTRUIR WORKBOOK ────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0); // quitar hoja por defecto

// ════════════════════════════════════════════════════════════
//  HOJA 1 — LPA
// ════════════════════════════════════════════════════════════
$ws1 = $spreadsheet->createSheet();
$ws1->setTitle("LPA $anio");
$ws1->getSheetView()->setShowGridLines(false);
construirHojaLPA($ws1, $datos, $anio, ORG_EN_LPA);

// ════════════════════════════════════════════════════════════
//  HOJA 2 — ESTIMACIÓN DE COSECHA
//  Idéntica a LPA pero el nombre organización EN cambia
// ════════════════════════════════════════════════════════════
$ws2 = $spreadsheet->createSheet();
$ws2->setTitle("ESTIMACIÓN DE COSECHA");
$ws2->getSheetView()->setShowGridLines(false);
construirHojaLPA($ws2, $datos, $anio, ORG_EN_EST);

// ════════════════════════════════════════════════════════════
//  HOJA 3 — PLAN DE ABASTECIMIENTO
// ════════════════════════════════════════════════════════════
$ws3 = $spreadsheet->createSheet();
$ws3->setTitle("PLAN DE ABASTECIMIENTO");
$ws3->getSheetView()->setShowGridLines(false);

// Anchos
$ws3->getColumnDimension('A')->setWidth(5);
$ws3->getColumnDimension('B')->setWidth(55);
$ws3->getColumnDimension('C')->setWidth(16);
$ws3->getColumnDimension('D')->setWidth(14);
$colMeses3 = ['E','F','G','H','I','J','K','L','M','N','O','P'];
foreach ($colMeses3 as $c) $ws3->getColumnDimension($c)->setWidth(11);
$ws3->getColumnDimension('Q')->setWidth(16);
$ws3->getColumnDimension('R')->setWidth(14);

// Encabezados Hoja 3
$ws3->getRowDimension(1)->setRowHeight(44);
$hdrs3 = [
    'A' => '#',
    'B' => 'ASOCIACIÓN',
    'C' => 'FECHA DE CONTRATO',
    'D' => 'ESTIMADO ANUAL',
    'E' => 'ENERO',   'F' => 'FEBRERO',    'G' => 'MARZO',
    'H' => 'ABRIL',   'I' => 'MAYO',       'J' => 'JUNIO',
    'K' => 'JULIO',   'L' => 'AGOSTO',     'M' => 'SEPTIEMBRE',
    'N' => 'OCTUBRE', 'O' => 'NOVIEMBRE',  'P' => 'DICIEMBRE',
    'Q' => 'ENTREGA TOTAL QQ',
    'R' => 'ENTREGA TOTAL TM',
];
foreach ($hdrs3 as $col => $val) {
    $ws3->setCellValue("{$col}1", $val);
    $ws3->getStyle("{$col}1")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial', 'color' => ['argb' => 'FFFFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F3A5F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color'       => ['argb' => 'FF000000']]],
    ]);
}

// Fila de datos Hoja 3 — usa referencias a Hoja 1 para totales
// Calculamos totales desde $datos
$totalVol   = array_sum(array_column($datos, 'vol_produccion'));
$totMeses   = [];
foreach ($mesesCols as $mes) {
    $totMeses[] = array_sum(array_column($datos, $mes));
}

$ws3->getRowDimension(2)->setRowHeight(18);
$numPlan = 6; // número de asociación en el plan
$ws3->setCellValue('A2', $numPlan);
$ws3->setCellValueExplicit('B2', ORG_PLAN, DataType::TYPE_STRING);
// Fecha contrato: primer día del año
$ws3->setCellValue('C2', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
    mktime(0,0,0,1,1,$anio)
));
$ws3->getStyle('C2')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
$ws3->setCellValue('D2', $totalVol);
$ws3->getStyle('D2')->getNumberFormat()->setFormatCode('#,##0.00');

foreach ($colMeses3 as $mi => $col) {
    $v = $totMeses[$mi] ?? 0;
    $ws3->setCellValue("{$col}2", $v > 0 ? $v : 0);
    $ws3->getStyle("{$col}2")->getNumberFormat()->setFormatCode('#,##0.00');
}

// Totales con fórmulas
$ws3->setCellValue('Q2', '=SUM(E2:P2)');
$ws3->getStyle('Q2')->getNumberFormat()->setFormatCode('#,##0.00');
$divisor = FAIRTRADE_DIVISOR;
$ws3->setCellValue('R2', "=Q2/{$divisor}");
$ws3->getStyle('R2')->getNumberFormat()->setFormatCode('#,##0.00');

// Estilo fila 2
$ws3->getStyle('A2:R2')->applyFromArray(datoStyle(true));
$ws3->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
foreach (['A2','D2','E2','F2','G2','H2','I2','J2','K2','L2','M2','N2','O2','P2','Q2','R2'] as $cell) {
    $ws3->getStyle($cell)->getFont()->setName('Arial')->setSize(9);
}

// ── EXPORTAR ──────────────────────────────────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

$filename = "LPA_{$anio}_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;