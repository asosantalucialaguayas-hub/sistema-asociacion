<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header('HTTP/1.1 403 Forbidden'); exit; }
require __DIR__ . "/config/conexion.php";

// ── Datos ──────────────────────────────────────────────────────────────
try {
    $zonas = $pdo->query("
        SELECT COALESCE(NULLIF(TRIM(l.zona),''),'Sin zona') AS nombre,
               COUNT(DISTINCT l.id_socio) AS total
        FROM tabla_lpa l
        INNER JOIN (SELECT id_socio, MAX(id_lpa) AS max_id FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio) mx
            ON l.id_socio=mx.id_socio AND l.id_lpa=mx.max_id
        GROUP BY nombre ORDER BY total DESC LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);

    $comunidades = $pdo->query("
        SELECT COALESCE(NULLIF(TRIM(l.comunidad_grupo),''),'Sin comunidad') AS nombre,
               COUNT(DISTINCT l.id_socio) AS total
        FROM tabla_lpa l
        INNER JOIN (SELECT id_socio, MAX(id_lpa) AS max_id FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio) mx
            ON l.id_socio=mx.id_socio AND l.id_lpa=mx.max_id
        GROUP BY nombre ORDER BY total DESC LIMIT 40
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die('Error BD: '.$e->getMessage()); }

$fecha = date('d/m/Y H:i');
$user  = htmlspecialchars($_SESSION['usuario']);

function xe($v) { return htmlspecialchars((string)$v, ENT_XML1,'UTF-8'); }

// ── Estilos ────────────────────────────────────────────────────────────
function estilos() {
return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="5">
    <font><sz val="10"/><name val="Arial"/></font>
    <font><b/><sz val="10"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
    <font><b/><sz val="13"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
    <font><i/><sz val="9"/><name val="Arial"/><color rgb="FF888888"/></font>
    <font><b/><sz val="10"/><name val="Arial"/></font>
  </fonts>
  <fills count="12">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF7ED"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2B0"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFB923C"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEA580C"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF7F1D1D"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF0F9FF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD0EBFF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF9FAFB"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="12">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="11" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0"  borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="3"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="4"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="5"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="6"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="7"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="2"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
}

// ── Hoja de datos con calor ────────────────────────────────────────────
function hojaCalor($rows, $titulo, $subtitulo, $colNombre, $colValor) {
    // Calcular máximo para color
    $maxVal = 0;
    foreach ($rows as $r) { if ((int)$r['total'] > $maxVal) $maxVal = (int)$r['total']; }

    // Función color (índice de estilo): 6=claro, 7=medio, 8=naranja, 9=rojo, 10=rojo oscuro
    $getStyle = function($val) use ($maxVal) {
        if ($maxVal == 0) return 4;
        $t = $val / $maxVal;
        if ($t < 0.20) return 6;
        if ($t < 0.40) return 7;
        if ($t < 0.60) return 8;
        if ($t < 0.80) return 9;
        return 10;
    };

    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
    $xml .= '<cols>';
    $xml .= '<col min="1" max="1" width="6"  customWidth="1"/>';
    $xml .= '<col min="2" max="2" width="32" customWidth="1"/>';
    $xml .= '<col min="3" max="3" width="14" customWidth="1"/>';
    $xml .= '<col min="4" max="4" width="14" customWidth="1"/>';
    $xml .= '</cols>';
    $xml .= '<sheetData>';

    // Fila 1: Título
    $xml .= '<row r="1" ht="28" customHeight="1">';
    $xml .= '<c r="A1" s="1" t="inlineStr"><is><t>'.xe($titulo).'</t></is></c>';
    $xml .= '</row>';
    // Fila 2: subtítulo
    $xml .= '<row r="2" ht="15" customHeight="1">';
    $xml .= '<c r="A2" s="2" t="inlineStr"><is><t>'.xe($subtitulo).'</t></is></c>';
    $xml .= '</row>';
    // Fila 3: espacio
    $xml .= '<row r="3" ht="6" customHeight="1"></row>';
    // Fila 4: encabezados
    $xml .= '<row r="4" ht="22" customHeight="1">';
    $xml .= '<c r="A4" s="3" t="inlineStr"><is><t>N°</t></is></c>';
    $xml .= '<c r="B4" s="3" t="inlineStr"><is><t>'.xe($colNombre).'</t></is></c>';
    $xml .= '<c r="C4" s="3" t="inlineStr"><is><t>N° Socios</t></is></c>';
    $xml .= '<c r="D4" s="3" t="inlineStr"><is><t>% del Total</t></is></c>';
    $xml .= '</row>';

    $totalSocios = array_sum(array_column($rows, 'total'));

    foreach ($rows as $i => $row) {
        $r    = $i + 5;
        $pct  = $totalSocios > 0 ? round((int)$row['total'] / $totalSocios * 100, 1) : 0;
        $sAlt = ($i % 2 === 0) ? 4 : 5;
        $sCol = $getStyle((int)$row['total']);
        $xml .= '<row r="'.$r.'" ht="20" customHeight="1">';
        $xml .= '<c r="A'.$r.'" s="'.$sAlt.'" t="n"><v>'.($i+1).'</v></c>';
        $xml .= '<c r="B'.$r.'" s="'.$sAlt.'" t="inlineStr"><is><t>'.xe($row['nombre']).'</t></is></c>';
        $xml .= '<c r="C'.$r.'" s="'.$sCol.'" t="n"><v>'.xe($row['total']).'</v></c>';
        $xml .= '<c r="D'.$r.'" s="'.$sAlt.'" t="inlineStr"><is><t>'.xe($pct).'%</t></is></c>';
        $xml .= '</row>';
    }

    // Fila total
    $rT = count($rows) + 5;
    $xml .= '<row r="'.$rT.'" ht="20" customHeight="1">';
    $xml .= '<c r="A'.$rT.'" s="11" t="inlineStr"><is><t>TOTAL</t></is></c>';
    $xml .= '<c r="B'.$rT.'" s="11" t="inlineStr"><is><t></t></is></c>';
    $xml .= '<c r="C'.$rT.'" s="11" t="n"><v>'.xe($totalSocios).'</v></c>';
    $xml .= '<c r="D'.$rT.'" s="11" t="inlineStr"><is><t>100%</t></is></c>';
    $xml .= '</row>';

    $xml .= '</sheetData>';
    $xml .= '<mergeCells count="2"><mergeCell ref="A1:D1"/><mergeCell ref="A2:D2"/></mergeCells>';

    // Leyenda color
    $xml .= '</worksheet>';
    return $xml;
}

// ── Construir ZIP/XLSX ─────────────────────────────────────────────────
$tmpFile = sys_get_temp_dir().'/mapa_calor_'.session_id().'_'.time().'.xlsx';
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$zip->addFromString('[Content_Types].xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

$zip->addFromString('_rels/.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

$zip->addFromString('xl/workbook.xml',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Por Zona"      sheetId="1" r:id="rId1"/>
    <sheet name="Por Comunidad" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels',
'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"    Target="styles.xml"/>
</Relationships>');

$zip->addFromString('xl/styles.xml', estilos());

$zip->addFromString('xl/worksheets/sheet1.xml',
    hojaCalor($zonas,       'SOCIOS POR ZONA',       'Exportado el '.$fecha.' por '.$user, 'Zona',       'N° Socios'));
$zip->addFromString('xl/worksheets/sheet2.xml',
    hojaCalor($comunidades, 'SOCIOS POR COMUNIDAD',  'Exportado el '.$fecha.' por '.$user, 'Comunidad',  'N° Socios'));

$zip->close();

$filename = 'Mapa_Calor_Socios_'.date('Ymd').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: max-age=0');
readfile($tmpFile);
@unlink($tmpFile);
exit;
