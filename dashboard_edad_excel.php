<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header('HTTP/1.1 403 Forbidden'); exit; }
require __DIR__ . "/config/conexion.php";

function xe($v) { return htmlspecialchars((string)$v, ENT_XML1,'UTF-8'); }

// ── Consulta detalle completo con edad calculada ───────────────────────
try {
    $stmt = $pdo->query("
        SELECT
            UPPER(TRIM(COALESCE(sexo,'')))             AS sexo,
            COALESCE(NULLIF(TRIM(nombre_completo),''),
                CONCAT(COALESCE(nombres,''),' ',COALESCE(apellidos,''))) AS nombre,
            identificacion,
            COALESCE(telefono,'')                      AS telefono,
            COALESCE(fecha_nacimiento,'')              AS fecha_nacimiento,
            CASE
                WHEN fecha_nacimiento IS NULL OR TRIM(fecha_nacimiento)='' OR fecha_nacimiento='0000-00-00' THEN NULL
                ELSE (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d')))
            END AS edad,
            CASE
                WHEN fecha_nacimiento IS NULL OR TRIM(fecha_nacimiento)='' OR fecha_nacimiento='0000-00-00' THEN 'Sin fecha'
                WHEN (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d'))) BETWEEN 18 AND 35 THEN 'Jóvenes (18-35)'
                WHEN (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d'))) BETWEEN 36 AND 70 THEN 'Adultos (36-70)'
                WHEN (YEAR(CURDATE()) - YEAR(fecha_nacimiento)
                     - (DATE_FORMAT(CURDATE(),'%m%d') < DATE_FORMAT(fecha_nacimiento,'%m%d'))) >= 71 THEN 'Adultos Mayores (71+)'
                ELSE 'Sin fecha'
            END AS rango
        FROM socios
        ORDER BY rango, sexo, nombre ASC
    ");
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die('Error BD: '.$e->getMessage()); }

// Agrupar
$grupos = ['Jóvenes (18-35)'=>[],'Adultos (36-70)'=>[],'Adultos Mayores (71+)'=>[],'Sin fecha'=>[]];
foreach ($todos as $r) {
    $g = $r['rango'];
    if (!isset($grupos[$g])) $grupos[$g] = [];
    $grupos[$g][] = $r;
}

$fecha = date('d/m/Y H:i');
$user  = htmlspecialchars($_SESSION['usuario']);

// ── Estilos XLSX ──────────────────────────────────────────────────────
function estilosEdad() {
return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="5">
    <font><sz val="10"/><name val="Arial"/></font>
    <font><b/><sz val="10"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
    <font><b/><sz val="13"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>
    <font><i/><sz val="9"/><name val="Arial"/><color rgb="FF888888"/></font>
    <font><b/><sz val="10"/><name val="Arial"/></font>
  </fonts>
  <fills count="14">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF6366F1"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF3B82F6"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF59E0B"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF6B7280"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFADBD8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD6EAF8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF9FAFB"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCCCCCC"/></left>
      <right style="thin"><color rgb="FFCCCCCC"/></right>
      <top style="thin"><color rgb="FFCCCCCC"/></top>
      <bottom style="thin"><color rgb="FFCCCCCC"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="14">
    <xf numFmtId="0" fontId="0" fillId="0"  borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="2" fillId="2"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="3"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="4"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="5"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="3" fillId="0"  borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="3"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="4"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="12" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0"  borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="12" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0"  borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="1" fillId="13" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';
}

// ── Hoja detalle por rango ─────────────────────────────────────────────
// sXfTitle: 1=violeta(jóvenes) 2=azul(adultos) 3=naranja(mayores) 4=gris(sd)
// sXfHeader: 6,7,8,4
function hojaRango($rows, $titulo, $sTitle, $sHeader, $sAltFill) {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
    $xml .= '<cols>';
    $xml .= '<col min="1" max="1" width="6"  customWidth="1"/>';
    $xml .= '<col min="2" max="2" width="36" customWidth="1"/>';
    $xml .= '<col min="3" max="3" width="16" customWidth="1"/>';
    $xml .= '<col min="4" max="4" width="15" customWidth="1"/>';
    $xml .= '<col min="5" max="5" width="14" customWidth="1"/>';
    $xml .= '<col min="6" max="6" width="8"  customWidth="1"/>';
    $xml .= '</cols>';
    $xml .= '<sheetData>';

    $xml .= '<row r="1" ht="28" customHeight="1">';
    $xml .= '<c r="A1" s="'.$sTitle.'" t="inlineStr"><is><t>'.xe($titulo).'</t></is></c>';
    $xml .= '</row>';
    $xml .= '<row r="2" ht="15" customHeight="1">';
    $xml .= '<c r="A2" s="5" t="inlineStr"><is><t>Exportado el '.xe($fecha).' por '.xe($user).'</t></is></c>';
    $xml .= '</row>';
    $xml .= '<row r="3" ht="6" customHeight="1"></row>';

    // Encabezados
    $xml .= '<row r="4" ht="22" customHeight="1">';
    foreach (['N°','Nombre Completo','Cédula','Teléfono','F. Nacimiento','Edad'] as $ci => $h) {
        $col = chr(65+$ci);
        $xml .= '<c r="'.$col.'4" s="'.$sHeader.'" t="inlineStr"><is><t>'.xe($h).'</t></is></c>';
    }
    $xml .= '</row>';

    // Separador hombres
    $hombres = array_filter($rows, fn($r)=>$r['sexo']==='M');
    $mujeres = array_filter($rows, fn($r)=>$r['sexo']==='F');
    $otros   = array_filter($rows, fn($r)=>$r['sexo']!=='M'&&$r['sexo']!=='F');

    $fila = 5;
    $bloques = [
        ['🚹 HOMBRES', $hombres, 7],
        ['🚺 MUJERES', $mujeres, 6],
    ];
    if(count($otros)>0) $bloques[] = ['❓ SIN GÉNERO', $otros, 4];

    foreach ($bloques as [$subtitulo, $bloque, $sBloque]) {
        // Sub-encabezado
        $xml .= '<row r="'.$fila.'" ht="20" customHeight="1">';
        $xml .= '<c r="A'.$fila.'" s="'.$sBloque.'" t="inlineStr"><is><t>'.xe($subtitulo).'</t></is></c>';
        for($ci=1;$ci<6;$ci++) {
            $col = chr(65+$ci);
            $xml .= '<c r="'.$col.$fila.'" s="'.$sBloque.'" t="inlineStr"><is><t></t></is></c>';
        }
        $xml .= '</row>';
        $fila++;

        foreach (array_values($bloque) as $i => $r) {
            $sA = ($i%2===0) ? 9 : 10;
            $sC = ($i%2===0) ? 11 : 12;
            $xml .= '<row r="'.$fila.'" ht="18" customHeight="1">';
            $xml .= '<c r="A'.$fila.'" s="'.$sC.'" t="n"><v>'.($i+1).'</v></c>';
            $xml .= '<c r="B'.$fila.'" s="'.$sA.'" t="inlineStr"><is><t>'.xe($r['nombre']).'</t></is></c>';
            $xml .= '<c r="C'.$fila.'" s="'.$sC.'" t="inlineStr"><is><t>'.xe($r['identificacion']).'</t></is></c>';
            $xml .= '<c r="D'.$fila.'" s="'.$sC.'" t="inlineStr"><is><t>'.xe($r['telefono']).'</t></is></c>';
            $xml .= '<c r="E'.$fila.'" s="'.$sC.'" t="inlineStr"><is><t>'.xe($r['fecha_nacimiento']).'</t></is></c>';
            $xml .= '<c r="F'.$fila.'" s="'.$sC.'" t="'.($r['edad']!==null?'n':'inlineStr').'">';
            $xml .= $r['edad']!==null ? '<v>'.xe($r['edad']).'</v>' : '<is><t>-</t></is>';
            $xml .= '</c>';
            $xml .= '</row>';
            $fila++;
        }
        // Sub-total
        $xml .= '<row r="'.$fila.'" ht="18" customHeight="1">';
        $xml .= '<c r="A'.$fila.'" s="13" t="inlineStr"><is><t>Subtotal</t></is></c>';
        $xml .= '<c r="B'.$fila.'" s="13" t="inlineStr"><is><t></t></is></c>';
        $xml .= '<c r="C'.$fila.'" s="13" t="n"><v>'.count($bloque).'</v></c>';
        for($ci=3;$ci<6;$ci++) { $col=chr(65+$ci); $xml .= '<c r="'.$col.$fila.'" s="13" t="inlineStr"><is><t></t></is></c>'; }
        $xml .= '</row>';
        $fila++;
    }

    $xml .= '</sheetData>';
    $xml .= '<mergeCells count="'.((count($bloques)*2)).'">'; 
    // merges A1:F1, A2:F2, y sub-encabezados (simplificado: solo título y subtítulo)
    $xml .= '<mergeCell ref="A1:F1"/>';
    $xml .= '<mergeCell ref="A2:F2"/>';
    $xml .= '</mergeCells>';
    $xml .= '</worksheet>';
    return $xml;
}

// ── Hoja Resumen ──────────────────────────────────────────────────────
function hojaResumenEdad($todos, $fecha, $user) {
    $total = count($todos);
    $rangos = ['Jóvenes (18-35)'=>['M'=>0,'F'=>0],'Adultos (36-70)'=>['M'=>0,'F'=>0],'Adultos Mayores (71+)'=>['M'=>0,'F'=>0],'Sin fecha'=>['M'=>0,'F'=>0]];
    foreach($todos as $r) {
        $g=$r['rango']; $s=$r['sexo'];
        if(!isset($rangos[$g])) $rangos[$g]=['M'=>0,'F'=>0];
        if($s==='M') $rangos[$g]['M']++;
        elseif($s==='F') $rangos[$g]['F']++;
        else $rangos[$g]['M']++;
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
    $xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="13" customWidth="1"/><col min="3" max="3" width="13" customWidth="1"/><col min="4" max="4" width="13" customWidth="1"/><col min="5" max="5" width="13" customWidth="1"/></cols>';
    $xml .= '<sheetData>';
    $xml .= '<row r="1" ht="28" customHeight="1"><c r="A1" s="13" t="inlineStr"><is><t>RESUMEN — DISTRIBUCIÓN POR EDAD Y GÉNERO</t></is></c></row>';
    $xml .= '<row r="2" ht="15" customHeight="1"><c r="A2" s="5" t="inlineStr"><is><t>Exportado el '.xe($fecha).' por '.xe($user).'</t></is></c></row>';
    $xml .= '<row r="3" ht="6" customHeight="1"></row>';
    $xml .= '<row r="4" ht="22" customHeight="1">';
    foreach(['Rango de Edad','🚹 Hombres','🚺 Mujeres','Total','% del Total'] as $ci=>$h) {
        $col=chr(65+$ci);
        $xml .= '<c r="'.$col.'4" s="13" t="inlineStr"><is><t>'.xe($h).'</t></is></c>';
    }
    $xml .= '</row>';

    $sColors = [1,2,3,4]; $ri=0;
    $tmH=0; $tmF=0;
    foreach($rangos as $nombre=>$vals) {
        $r=5+$ri; $sH=$sColors[$ri]; $t=$vals['M']+$vals['F'];
        $pct=$total>0?round($t/$total*100,1):0;
        $tmH+=$vals['M']; $tmF+=$vals['F'];
        $xml .= '<row r="'.$r.'" ht="20" customHeight="1">';
        $xml .= '<c r="A'.$r.'" s="'.$sH.'" t="inlineStr"><is><t>'.xe($nombre).'</t></is></c>';
        $xml .= '<c r="B'.$r.'" s="'.$sH.'" t="n"><v>'.xe($vals['M']).'</v></c>';
        $xml .= '<c r="C'.$r.'" s="'.$sH.'" t="n"><v>'.xe($vals['F']).'</v></c>';
        $xml .= '<c r="D'.$r.'" s="'.$sH.'" t="n"><v>'.xe($t).'</v></c>';
        $xml .= '<c r="E'.$r.'" s="'.$sH.'" t="inlineStr"><is><t>'.xe($pct).'%</t></is></c>';
        $xml .= '</row>';
        $ri++;
    }
    // Total
    $xml .= '<row r="9" ht="22" customHeight="1">';
    $xml .= '<c r="A9" s="13" t="inlineStr"><is><t>TOTAL</t></is></c>';
    $xml .= '<c r="B9" s="13" t="n"><v>'.xe($tmH).'</v></c>';
    $xml .= '<c r="C9" s="13" t="n"><v>'.xe($tmF).'</v></c>';
    $xml .= '<c r="D9" s="13" t="n"><v>'.xe($total).'</v></c>';
    $xml .= '<c r="E9" s="13" t="inlineStr"><is><t>100%</t></is></c>';
    $xml .= '</row>';
    $xml .= '</sheetData>';
    $xml .= '<mergeCells count="2"><mergeCell ref="A1:E1"/><mergeCell ref="A2:E2"/></mergeCells>';
    $xml .= '</worksheet>';
    return $xml;
}

// ── Build XLSX ────────────────────────────────────────────────────────
$tmpFile = sys_get_temp_dir().'/edad_'.session_id().'_'.time().'.xlsx';
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet5.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Resumen"           sheetId="1" r:id="rId1"/>
    <sheet name="Jóvenes 18-35"     sheetId="2" r:id="rId2"/>
    <sheet name="Adultos 36-70"     sheetId="3" r:id="rId3"/>
    <sheet name="Ad. Mayores 71+"   sheetId="4" r:id="rId4"/>
    <sheet name="Sin Fecha"         sheetId="5" r:id="rId5"/>
  </sheets>
</workbook>');

$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet5.xml"/>
  <Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"    Target="styles.xml"/>
</Relationships>');

$zip->addFromString('xl/styles.xml', estilosEdad());
$zip->addFromString('xl/worksheets/sheet1.xml', hojaResumenEdad($todos, $fecha, $user));
$zip->addFromString('xl/worksheets/sheet2.xml', hojaRango($grupos['Jóvenes (18-35)'],     'JÓVENES — 18 a 35 años',       1, 6, 0));
$zip->addFromString('xl/worksheets/sheet3.xml', hojaRango($grupos['Adultos (36-70)'],     'ADULTOS — 36 a 70 años',       2, 7, 0));
$zip->addFromString('xl/worksheets/sheet4.xml', hojaRango($grupos['Adultos Mayores (71+)'],'ADULTOS MAYORES — 71+ años',  3, 8, 0));
$zip->addFromString('xl/worksheets/sheet5.xml', hojaRango($grupos['Sin fecha'],           'SIN FECHA DE NACIMIENTO',      4, 4, 0));
$zip->close();

$fn = 'Socios_por_Edad_'.date('Ymd').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fn.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: max-age=0');
readfile($tmpFile);
@unlink($tmpFile);
exit;
