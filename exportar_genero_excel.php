<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header('HTTP/1.1 403 Forbidden'); exit('No autorizado'); }
require __DIR__ . "/config/conexion.php";

// ════════════════════════════════════════════════════════════════════
//  Generador XLSX puro en PHP — sin librerías externas, sin Python
//  Usa ZipArchive (disponible en casi todos los hostings compartidos)
// ════════════════════════════════════════════════════════════════════

try {
    $mujeres = $pdo->query("
        SELECT identificacion, nombre_completo, COALESCE(telefono,'') AS telefono
        FROM socios WHERE UPPER(TRIM(sexo))='F' ORDER BY nombre_completo ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $hombres = $pdo->query("
        SELECT identificacion, nombre_completo, COALESCE(telefono,'') AS telefono
        FROM socios WHERE UPPER(TRIM(sexo))='M' ORDER BY nombre_completo ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $otros = $pdo->query("
        SELECT identificacion, nombre_completo, COALESCE(telefono,'') AS telefono
        FROM socios WHERE UPPER(TRIM(sexo)) NOT IN ('F','M') OR sexo IS NULL OR TRIM(sexo)=''
        ORDER BY nombre_completo ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die('Error BD: '.$e->getMessage()); }

$totalM = count($mujeres);
$totalH = count($hombres);
$total  = $totalM + $totalH + count($otros);
$totalO = count($otros);
$pctM   = $total ? round($totalM/$total*100,1) : 0;
$pctH   = $total ? round($totalH/$total*100,1) : 0;
$pctO   = $total ? round($totalO/$total*100,1) : 0;
$fecha  = date('d/m/Y H:i');
$user   = htmlspecialchars($_SESSION['usuario']);

// ── Helpers XML ──────────────────────────────────────────────────────
function xe($v) { return htmlspecialchars((string)$v, ENT_XML1, 'UTF-8'); }

// ── Estilos (índices) ─────────────────────────────────────────────────
// 0=normal, 1=header_rojo, 2=header_azul, 3=header_resumen, 4=titulo_rojo,
// 5=titulo_azul, 6=titulo_resumen, 7=alt_rosa, 8=alt_celeste, 9=subtitulo,
// 10=total_rojo, 11=total_azul, 12=total_res_muj, 13=total_res_hom, 14=total_res_tot

function buildStyles() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="6">
    <font><sz val="10"/><name val="Arial"/></font>
    <font><b/><sz val="10"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
    <font><b/><sz val="14"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
    <font><i/><sz val="9"/><name val="Arial"/><color rgb="FF888888"/></font>
    <font><b/><sz val="10"/><name val="Arial"/></font>
    <font><b/><sz val="11"/><name val="Arial"/></font>
  </fonts>
  <fills count="14">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFC0392B"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFADBD8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD6EAF8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0F0F0"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFDEDEC"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEBF5FB"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFE0E0"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE0EEFF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE8F8F5"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEAFAF1"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="19">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="9" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="9" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="5" fillId="7" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
}

// ── Construir hoja detalle (Mujeres o Hombres) ────────────────────────
// sXfTitle=4 rojo / 5 azul | sXfHeader=1 rojo / 2 azul | sXfAlt=8 rosa / 9 celeste | sXfTotal=12 rojo / 13 azul
function buildSheetDetalle($rows, $titulo, $sXfTitle, $sXfHeader, $sXfAlt, $sXfTotal, $fecha, $user) {
    $sharedIdx = 0; // no usamos sharedStrings, todo inline
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetFormatPr defaultRowHeight="18" customHeight="1"/>';
    $xml .= '<cols>';
    $xml .= '<col min="1" max="1" width="6"  customWidth="1"/>';
    $xml .= '<col min="2" max="2" width="17" customWidth="1"/>';
    $xml .= '<col min="3" max="3" width="38" customWidth="1"/>';
    $xml .= '<col min="4" max="4" width="15" customWidth="1"/>';
    $xml .= '</cols>';
    $xml .= '<sheetData>';

    // Fila 1: Título
    $xml .= '<row r="1" ht="28" customHeight="1">';
    $xml .= '<c r="A1" s="'.$sXfTitle.'" t="s"><v>0</v></c>';
    $xml .= '</row>';

    // Fila 2: Subtítulo
    $xml .= '<row r="2" ht="16" customHeight="1">';
    $xml .= '<c r="A2" s="7" t="inlineStr"><is><t>Exportado el '.xe($fecha).' por '.xe($user).'</t></is></c>';
    $xml .= '</row>';

    // Fila 3: vacía separadora
    $xml .= '<row r="3" ht="6" customHeight="1"></row>';

    // Fila 4: Encabezados
    $xml .= '<row r="4" ht="22" customHeight="1">';
    $xml .= '<c r="A4" s="'.$sXfHeader.'" t="inlineStr"><is><t>N°</t></is></c>';
    $xml .= '<c r="B4" s="'.$sXfHeader.'" t="inlineStr"><is><t>Cédula</t></is></c>';
    $xml .= '<c r="C4" s="'.$sXfHeader.'" t="inlineStr"><is><t>Nombre Completo</t></is></c>';
    $xml .= '<c r="D4" s="'.$sXfHeader.'" t="inlineStr"><is><t>Teléfono</t></is></c>';
    $xml .= '</row>';

    // Datos
    foreach ($rows as $i => $row) {
        $r   = $i + 5;
        $alt = ($i % 2 === 1);
        $sN  = $alt ? ($sXfHeader == 1 ? 10 : 11) : 17;   // número centrado
        $sT  = $alt ? ($sXfHeader == 1 ? 8  : 9)  : 18;   // texto izq
        $xml .= '<row r="'.$r.'" ht="18" customHeight="1">';
        $xml .= '<c r="A'.$r.'" s="'.$sN.'" t="n"><v>'.($i+1).'</v></c>';
        $xml .= '<c r="B'.$r.'" s="'.$sN.'" t="inlineStr"><is><t>'.xe($row['identificacion']).'</t></is></c>';
        $xml .= '<c r="C'.$r.'" s="'.$sT.'" t="inlineStr"><is><t>'.xe($row['nombre_completo']).'</t></is></c>';
        $xml .= '<c r="D'.$r.'" s="'.$sN.'" t="inlineStr"><is><t>'.xe($row['telefono']).'</t></is></c>';
        $xml .= '</row>';
    }

    // Fila Total
    $rTot = count($rows) + 5;
    $xml .= '<row r="'.$rTot.'" ht="20" customHeight="1">';
    $xml .= '<c r="A'.$rTot.'" s="'.$sXfTotal.'" t="inlineStr"><is><t>TOTAL</t></is></c>';
    $xml .= '<c r="B'.$rTot.'" s="'.$sXfTotal.'" t="inlineStr"><is><t></t></is></c>';
    $xml .= '<c r="C'.$rTot.'" s="14" t="n"><v>'.count($rows).'</v></c>';
    $xml .= '<c r="D'.$rTot.'" s="14" t="inlineStr"><is><t></t></is></c>';
    $xml .= '</row>';

    $xml .= '</sheetData>';

    // Merge Cells: A1:D1 y A2:D2
    $xml .= '<mergeCells count="2">';
    $xml .= '<mergeCell ref="A1:D1"/>';
    $xml .= '<mergeCell ref="A2:D2"/>';
    $xml .= '</mergeCells>';

    $xml .= '</worksheet>';
    return $xml;
}

// ── Construir hoja Resumen ────────────────────────────────────────────
function buildSheetResumen($totalM, $totalH, $totalO, $total, $pctM, $pctH, $pctO, $fecha, $user) {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
    $xml .= '<cols>';
    $xml .= '<col min="1" max="1" width="22" customWidth="1"/>';
    $xml .= '<col min="2" max="2" width="14" customWidth="1"/>';
    $xml .= '<col min="3" max="3" width="14" customWidth="1"/>';
    $xml .= '</cols>';
    $xml .= '<sheetData>';

    // Fila 1: título
    $xml .= '<row r="1" ht="28" customHeight="1">';
    $xml .= '<c r="A1" s="6" t="inlineStr"><is><t>RESUMEN POR GÉNERO</t></is></c>';
    $xml .= '</row>';

    // Fila 2: subtítulo
    $xml .= '<row r="2" ht="16" customHeight="1">';
    $xml .= '<c r="A2" s="7" t="inlineStr"><is><t>Exportado el '.xe($fecha).' por '.xe($user).'</t></is></c>';
    $xml .= '</row>';

    // Fila 3: encabezados
    $xml .= '<row r="3" ht="22" customHeight="1">';
    $xml .= '<c r="A3" s="3" t="inlineStr"><is><t>Categoría</t></is></c>';
    $xml .= '<c r="B3" s="3" t="inlineStr"><is><t>N° Socios</t></is></c>';
    $xml .= '<c r="C3" s="3" t="inlineStr"><is><t>% del Total</t></is></c>';
    $xml .= '</row>';

    // Fila 4: Mujeres
    $xml .= '<row r="4" ht="22" customHeight="1">';
    $xml .= '<c r="A4" s="15" t="inlineStr"><is><t>Mujeres</t></is></c>';
    $xml .= '<c r="B4" s="15" t="n"><v>'.xe($totalM).'</v></c>';
    $xml .= '<c r="C4" s="15" t="inlineStr"><is><t>'.xe($pctM).'%</t></is></c>';
    $xml .= '</row>';

    // Fila 5: Hombres
    $xml .= '<row r="5" ht="22" customHeight="1">';
    $xml .= '<c r="A5" s="16" t="inlineStr"><is><t>Hombres</t></is></c>';
    $xml .= '<c r="B5" s="16" t="n"><v>'.xe($totalH).'</v></c>';
    $xml .= '<c r="C5" s="16" t="inlineStr"><is><t>'.xe($pctH).'%</t></is></c>';
    $xml .= '</row>';

    // Fila 6: Sin Definir
    $xml .= '<row r="6" ht="22" customHeight="1">';
    $xml .= '<c r="A6" s="17" t="inlineStr"><is><t>Sin Género Definido</t></is></c>';
    $xml .= '<c r="B6" s="17" t="n"><v>'.xe($totalO).'</v></c>';
    $xml .= '<c r="C6" s="17" t="inlineStr"><is><t>'.xe($pctO).'%</t></is></c>';
    $xml .= '</row>';

    // Fila 7: Total
    $xml .= '<row r="7" ht="22" customHeight="1">';
    $xml .= '<c r="A7" s="14" t="inlineStr"><is><t>TOTAL</t></is></c>';
    $xml .= '<c r="B7" s="14" t="n"><v>'.xe($total).'</v></c>';
    $xml .= '<c r="C7" s="14" t="inlineStr"><is><t>100%</t></is></c>';
    $xml .= '</row>';

    $xml .= '</sheetData>';
    $xml .= '<mergeCells count="2">';
    $xml .= '<mergeCell ref="A1:C1"/>';
    $xml .= '<mergeCell ref="A2:C2"/>';
    $xml .= '</mergeCells>';
    $xml .= '</worksheet>';
    return $xml;
}

// ── Armar el ZIP / XLSX ───────────────────────────────────────────────
$tmpFile = sys_get_temp_dir().'/socios_genero_'.session_id().'_'.time().'.xlsx';

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('No se pudo crear el archivo temporal');
}

// [Content_Types].xml
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet4.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"                ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

// _rels/.rels
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

// xl/workbook.xml — Resumen primero, luego Mujeres, luego Hombres
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Resumen" sheetId="1" r:id="rId1"/>
    <sheet name="Mujeres" sheetId="2" r:id="rId2"/>
    <sheet name="Hombres" sheetId="3" r:id="rId3"/>
    <sheet name="Sin Definir" sheetId="4" r:id="rId4"/>
  </sheets>
</workbook>');

// xl/_rels/workbook.xml.rels
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"    Target="styles.xml"/>
</Relationships>');

// xl/styles.xml
$zip->addFromString('xl/styles.xml', buildStyles());

// Hojas
$zip->addFromString('xl/worksheets/sheet1.xml', buildSheetResumen($totalM, $totalH, $totalO, $total, $pctM, $pctH, $pctO, $fecha, $user));
$zip->addFromString('xl/worksheets/sheet2.xml', buildSheetDetalle($mujeres, 'SOCIAS — MUJERES', 4, 1, 8, 12, $fecha, $user));
$zip->addFromString('xl/worksheets/sheet3.xml', buildSheetDetalle($hombres, 'SOCIOS — HOMBRES', 5, 2, 9, 13, $fecha, $user));
$zip->addFromString('xl/worksheets/sheet4.xml', buildSheetDetalle($otros, 'SIN GÉNERO DEFINIDO', 3, 3, 0, 3, $fecha, $user));

$zip->close();

// ── Enviar al navegador ───────────────────────────────────────────────
$filename = 'Socios_por_Genero_'.date('Ymd').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: max-age=0');
readfile($tmpFile);
@unlink($tmpFile);
exit;