<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { die('No autorizado'); }

require "config/conexion.php";

$id_periodo = intval($_GET['id_periodo'] ?? 0);
$anio       = intval($_GET['anio'] ?? date('Y'));

$DIVISOR = 22.046;
$PRIMA   = 240;
$BASE    = 275;

try {
    $sql = "
        SELECT
            l.zona,
            l.comunidad_grupo,
            s.identificacion AS cedula,
            TRIM(COALESCE(NULLIF(s.nombre_completo,''),
                 CONCAT(COALESCE(s.apellidos,''),' ',COALESCE(s.nombres,'')))) AS nombre_completo,
            UPPER(COALESCE(l.sexo, s.sexo, ''))                     AS sexo,
            COALESCE(l.celular, s.telefono, '')                    AS celular,
            DATE_FORMAT(COALESCE(l.fecha_nacimiento,s.fecha_nacimiento),'%d/%m/%Y') AS fecha_nacimiento,
            DATE_FORMAT(COALESCE(l.fecha_ingreso,s.fecha_ingreso),'%d/%m/%Y')       AS fecha_ingreso,
            UPPER(COALESCE(l.en_acercamiento,'NO'))                AS en_acercamiento,
            UPPER(COALESCE(l.otra_org_fairtrade,'NO'))             AS otra_org_fairtrade,
            COALESCE(l.area_total_ha,0)                            AS area_total_ha,
            COALESCE(l.area_cacao_ha,0)                            AS area_cacao_ha,
            COALESCE(l.num_matas_ha,0)                             AS num_matas_ha,
            COALESCE(l.certificacion_organica,'')                  AS cert_organica,
            COALESCE(l.volumen_produccion_estimado,0)              AS vol_produccion,
            COALESCE(l.volumen_entregado_org,'NO')                 AS vol_entregado,
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

    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $totAreaTotal = 0; $totAreaCacao = 0; $totVol = 0;
    $totMeses = array_fill(0, 12, 0);
    foreach ($datos as $r) {
        $totAreaTotal += (float)$r['area_total_ha'];
        $totAreaCacao += (float)$r['area_cacao_ha'];
        $totVol       += (float)$r['vol_produccion'];
        foreach ($meses as $mi => $mes) $totMeses[$mi] += (float)$r[$mes];
    }
    $totSumMeses = array_sum($totMeses);
    $toneladas   = $totVol / $DIVISOR;

} catch (Exception $e) {
    die("Error DB: " . $e->getMessage());
}

// ── Helpers ──────────────────────────────────────────────────────────
function xStr($v) {
    return '<ss:Data ss:Type="String">' . htmlspecialchars((string)$v, ENT_XML1) . '</ss:Data>';
}
function xNum($v) {
    $n = (float)$v;
    return '<ss:Data ss:Type="Number">' . $n . '</ss:Data>';
}
function xCell($data, $styleID = 'Dato') {
    return '<ss:Cell ss:StyleID="' . $styleID . '">' . $data . '</ss:Cell>';
}
function xCellNum($v, $styleID = 'DatoNum') {
    if ((float)$v == 0) return '<ss:Cell ss:StyleID="' . $styleID . '"><ss:Data ss:Type="Number">0</ss:Data></ss:Cell>';
    return '<ss:Cell ss:StyleID="' . $styleID . '"><ss:Data ss:Type="Number">' . (float)$v . '</ss:Data></ss:Cell>';
}
function xCellNumBlank($v, $styleID = 'DatoNum') {
    if ((float)$v == 0) return '<ss:Cell ss:StyleID="' . $styleID . '"/>';
    return '<ss:Cell ss:StyleID="' . $styleID . '"><ss:Data ss:Type="Number">' . (float)$v . '</ss:Data></ss:Cell>';
}

// ── Genera filas de datos (compartido entre hoja 1 y 2) ──────────────
function generarFilasDatos(array $datos, array $meses): string {
    $out = '';
    foreach ($datos as $i => $row) {
        $matas = $row['num_matas_ha'] > 0 ? $row['num_matas_ha'] . '/has' : '';
        $out .= '<ss:Row ss:Height="14">';
        $out .= xCell(xNum($i + 1), 'DatoC');
        $out .= xCell(xStr($row['zona'] ?? ''), 'DatoL');
        $out .= xCell(xStr($row['comunidad_grupo'] ?? ''), 'DatoL');
        $out .= xCell(xStr($row['cedula'] ?? ''), 'DatoC');
        $out .= xCell(xStr($row['nombre_completo'] ?? ''), 'DatoL');
        $out .= xCell(xStr($row['sexo'] ?? ''), 'DatoC');
        $out .= xCell(xStr($row['celular'] ?? ''), 'DatoC');
        $out .= xCell(xStr($row['fecha_nacimiento'] ?? ''), 'DatoC');
        $out .= xCell(xStr($row['fecha_ingreso'] ?? ''), 'DatoC');
        $out .= xCell(xStr($row['en_acercamiento'] ?? ''), 'DatoC');
        $out .= xCell(xStr($row['otra_org_fairtrade'] ?? ''), 'DatoC');
        $out .= xCellNum($row['area_total_ha']);
        $out .= xCellNum($row['area_cacao_ha']);
        $out .= xCell(xStr($matas), 'DatoC');
        $out .= xCell(xStr($row['cert_organica'] ?? ''), 'DatoC');
        $out .= xCellNum($row['vol_produccion']);
        $out .= xCell(xStr($row['vol_entregado'] ?? ''), 'DatoC');
        foreach ($meses as $mes) {
            $out .= xCellNumBlank($row[$mes]);
        }
        $out .= '</ss:Row>';
    }
    return $out;
}

// ── Genera hoja LPA o Estimacion ─────────────────────────────────────
function generarHojaLPA(string $nombre, array $datos, int $anio, int $total, string $orgEN,
                        float $totAreaTotal, float $totAreaCacao, float $totVol,
                        array $totMeses, float $toneladas, float $DIVISOR, int $PRIMA, int $BASE,
                        array $meses): string
{
    $out = '<ss:Worksheet ss:Name="' . htmlspecialchars($nombre, ENT_XML1) . '">';

    // Anchos de columna: N°, Zona, Comunidad, Cedula, Nombre, Sexo, Celular, FNac, FIngreso,
    // Acercamiento, OtraOrg, AreaTotal, AreaCacao, Matas, CertOrg, VolProd, VolEntregado,
    // E,F,M,A,M,J,JL,A,S,O,N,D
    $anchos = [40,90,120,110,250,70,95,95,120,110,130,95,95,95,95,95,95,
               45,45,45,45,45,45,45,45,45,45,45,45];
    $out .= '<ss:Table>';
    foreach ($anchos as $w) {
        $out .= '<ss:Column ss:Width="' . $w . '"/>';
    }

    // Fila imagen (vacía, altura reservada)
    $out .= '<ss:Row ss:Height="58"><ss:Cell ss:StyleID="Vacio" ss:MergeAcross="27"/></ss:Row>';

    // Info superior
    $out .= '<ss:Row ss:Height="16">';
    $out .= '<ss:Cell ss:StyleID="InfoL" ss:MergeAcross="4"><ss:Data ss:Type="String">Lista de Productores Miembros:</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="InfoV" ss:MergeAcross="7"><ss:Data ss:Type="String">' . $total . ' SOCIOS</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="Vacio" ss:MergeAcross="1"/>';
    $out .= '<ss:Cell ss:StyleID="InfoL" ss:MergeAcross="4"><ss:Data ss:Type="String">List of members producers:</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="InfoV" ss:MergeAcross="8"><ss:Data ss:Type="String">' . $total . ' SOCIOS</ss:Data></ss:Cell>';
    $out .= '</ss:Row>';

    $out .= '<ss:Row ss:Height="16">';
    $out .= '<ss:Cell ss:StyleID="InfoL" ss:MergeAcross="4"><ss:Data ss:Type="String">AÑO:</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="InfoV" ss:MergeAcross="7"><ss:Data ss:Type="Number">' . $anio . '</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="Vacio" ss:MergeAcross="1"/>';
    $out .= '<ss:Cell ss:StyleID="InfoL" ss:MergeAcross="4"><ss:Data ss:Type="String">YEAR:</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="InfoV" ss:MergeAcross="8"><ss:Data ss:Type="Number">' . $anio . '</ss:Data></ss:Cell>';
    $out .= '</ss:Row>';

    $out .= '<ss:Row ss:Height="16">';
    $out .= '<ss:Cell ss:StyleID="InfoL" ss:MergeAcross="4"><ss:Data ss:Type="String">Nombre de la Organización:</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="InfoV" ss:MergeAcross="7"><ss:Data ss:Type="String">ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="Vacio" ss:MergeAcross="1"/>';
    $out .= '<ss:Cell ss:StyleID="InfoL" ss:MergeAcross="4"><ss:Data ss:Type="String">Name of Organisation:</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="InfoV" ss:MergeAcross="8"><ss:Data ss:Type="String">' . htmlspecialchars($orgEN, ENT_XML1) . '</ss:Data></ss:Cell>';
    $out .= '</ss:Row>';

    // Separador
    $out .= '<ss:Row ss:Height="10"><ss:Cell ss:StyleID="Vacio" ss:MergeAcross="27"/></ss:Row>';

    // Encabezados
    $hdrs = [
        'N°','Zona','Comunidad o Grupo','Cédula del Productor','Apellidos y nombres productor/a',
        'Sexo (F/M)','Celular','Fecha de nacimiento','Fecha de afiliación a la organización',
        'En acercamiento (en proceso para ingresar de socio)',
        'Socios que también son miembros de otra organización certificada Fairtrade SI/NO',
        'Área total de su unidad de produccion (Ha)','Área cultivada de Cacao (Ha)',
        'Número de matas por ha','Estatus de certificación Orgánica SI/NO',
        'Volumen de producción de Cacao','Volumen de producción de Cacao entregado a la organización',
        'E','F','M','A','M','J','JL','A','S','O','N','D'
    ];
    $out .= '<ss:Row ss:Height="56">';
    foreach ($hdrs as $h) {
        $out .= '<ss:Cell ss:StyleID="TH"><ss:Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1) . '</ss:Data></ss:Cell>';
    }
    $out .= '</ss:Row>';

    // Subfila 1000/has y LPA
    $out .= '<ss:Row ss:Height="22">';
    for ($i = 0; $i < 13; $i++) $out .= '<ss:Cell ss:StyleID="TH"/>';
    $out .= '<ss:Cell ss:StyleID="TH"><ss:Data ss:Type="String">1000/has</ss:Data></ss:Cell>';
    for ($i = 0; $i < 3; $i++) $out .= '<ss:Cell ss:StyleID="TH"/>';
    $out .= '<ss:Cell ss:StyleID="TH" ss:MergeAcross="11"><ss:Data ss:Type="String">LPA</ss:Data></ss:Cell>';
    $out .= '</ss:Row>';

    // Filas de datos
    $out .= generarFilasDatos($datos, $meses);

    // Fila totales
    $out .= '<ss:Row ss:Height="16">';
    for ($i = 0; $i < 11; $i++) $out .= '<ss:Cell ss:StyleID="Total"/>';
    $out .= '<ss:Cell ss:StyleID="TotalNum"><ss:Data ss:Type="Number">' . $totAreaTotal . '</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="TotalNum"><ss:Data ss:Type="Number">' . $totAreaCacao . '</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="Total"/>';
    $out .= '<ss:Cell ss:StyleID="Total"/>';
    $out .= '<ss:Cell ss:StyleID="TotalNum"><ss:Data ss:Type="Number">' . $totVol . '</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="Total"/>';
    foreach ($totMeses as $tm) {
        $out .= '<ss:Cell ss:StyleID="TotalNum"><ss:Data ss:Type="Number">' . $tm . '</ss:Data></ss:Cell>';
    }
    $out .= '</ss:Row>';

    // Espacio
    $out .= '<ss:Row ss:Height="10"><ss:Cell ss:StyleID="Vacio" ss:MergeAcross="27"/></ss:Row>';

    // Cálculos Fairtrade
    $out .= '<ss:Row ss:Height="16">';
    for ($i=0;$i<13;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '<ss:Cell ss:StyleID="CalcLbl" ss:MergeAcross="1"><ss:Data ss:Type="String">TONELADA</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="CalcTxt" ss:MergeAcross="1"><ss:Data ss:Type="String">DIVIDIDO A ' . $DIVISOR . ' =</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="CalcVal" ss:MergeAcross="2"><ss:Data ss:Type="Number">' . $toneladas . '</ss:Data></ss:Cell>';
    for ($i=0;$i<9;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '</ss:Row>';

    $out .= '<ss:Row ss:Height="16">';
    for ($i=0;$i<13;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '<ss:Cell ss:StyleID="CalcLbl" ss:MergeAcross="1"><ss:Data ss:Type="String">PRIMA</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="CalcTxt" ss:MergeAcross="1"><ss:Data ss:Type="String">MULTIPLICADO * ' . $PRIMA . ' =</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="CalcVal" ss:MergeAcross="2"><ss:Data ss:Type="Number">' . ($toneladas * $PRIMA) . '</ss:Data></ss:Cell>';
    for ($i=0;$i<9;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '</ss:Row>';

    $out .= '<ss:Row ss:Height="16">';
    for ($i=0;$i<15;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '<ss:Cell ss:StyleID="CalcTxt" ss:MergeAcross="1"><ss:Data ss:Type="String">MULTIPLICADO * ' . $BASE . ' =</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="CalcVal" ss:MergeAcross="2"><ss:Data ss:Type="Number">' . ($toneladas * $BASE) . '</ss:Data></ss:Cell>';
    for ($i=0;$i<9;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '</ss:Row>';

    $out .= '<ss:Row ss:Height="18">';
    for ($i=0;$i<15;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '<ss:Cell ss:StyleID="CalcLblAzul" ss:MergeAcross="1"><ss:Data ss:Type="String">BENEFICIO PRIMA</ss:Data></ss:Cell>';
    $out .= '<ss:Cell ss:StyleID="CalcValBig" ss:MergeAcross="2"><ss:Data ss:Type="Number">' . (($toneladas * $PRIMA) + ($toneladas * $BASE)) . '</ss:Data></ss:Cell>';
    for ($i=0;$i<9;$i++) $out .= '<ss:Cell ss:StyleID="Vacio"/>';
    $out .= '</ss:Row>';

    $out .= '</ss:Table></ss:Worksheet>';
    return $out;
}

// ── HEADERS ───────────────────────────────────────────────────────────
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="LPA_' . $anio . '_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// ── XML SpreadsheetML ─────────────────────────────────────────────────
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
             xmlns:o="urn:schemas-microsoft-com:office:office"
             xmlns:x="urn:schemas-microsoft-com:office:excel"
             xmlns="urn:schemas-microsoft-com:office:spreadsheet">

<ss:Styles>
  <!-- Vacío / sin borde -->
  <ss:Style ss:ID="Vacio">
    <ss:Alignment ss:Vertical="Center"/>
  </ss:Style>

  <!-- Info cabecera izquierda -->
  <ss:Style ss:ID="InfoL">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
    <ss:Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/>
    <ss:Interior ss:Color="#FFE699" ss:Pattern="Solid"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Info cabecera valor -->
  <ss:Style ss:ID="InfoV">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
    <ss:Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/>
    <ss:Interior ss:Color="#FFE699" ss:Pattern="Solid"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Header encabezado de tabla -->
  <ss:Style ss:ID="TH">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
    <ss:Font ss:FontName="Arial" ss:Size="8" ss:Bold="1"/>
    <ss:Interior ss:Color="#FFE699" ss:Pattern="Solid"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Dato centrado -->
  <ss:Style ss:ID="DatoC">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Dato izquierda -->
  <ss:Style ss:ID="DatoL">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Dato numérico -->
  <ss:Style ss:ID="DatoNum">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:NumberFormat ss:Format="#,##0.00"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Total amarillo texto -->
  <ss:Style ss:ID="Total">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9" ss:Bold="1"/>
    <ss:Interior ss:Color="#FFE699" ss:Pattern="Solid"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Total numérico -->
  <ss:Style ss:ID="TotalNum">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9" ss:Bold="1"/>
    <ss:Interior ss:Color="#FFE699" ss:Pattern="Solid"/>
    <ss:NumberFormat ss:Format="#,##0.00"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Cálculo label azul -->
  <ss:Style ss:ID="CalcLbl">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9" ss:Bold="1"/>
  </ss:Style>
  <ss:Style ss:ID="CalcLblAzul">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9" ss:Bold="1" ss:Color="#1F3A5F"/>
  </ss:Style>
  <ss:Style ss:ID="CalcTxt">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
  </ss:Style>
  <ss:Style ss:ID="CalcVal">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="10" ss:Bold="1" ss:Color="#1F3A5F"/>
    <ss:NumberFormat ss:Format="#,##0.00"/>
  </ss:Style>
  <ss:Style ss:ID="CalcValBig">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="11" ss:Bold="1" ss:Color="#1F3A5F"/>
    <ss:NumberFormat ss:Format="#,##0.00"/>
  </ss:Style>

  <!-- Hoja 3 header azul oscuro -->
  <ss:Style ss:ID="Hdr3">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
    <ss:Font ss:FontName="Arial" ss:Size="9" ss:Bold="1" ss:Color="#FFFFFF"/>
    <ss:Interior ss:Color="#1F3A5F" ss:Pattern="Solid"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>

  <!-- Hoja 3 dato -->
  <ss:Style ss:ID="Dato3">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>
  <ss:Style ss:ID="Dato3L">
    <ss:Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>
  <ss:Style ss:ID="Dato3Num">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:NumberFormat ss:Format="#,##0.00"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>
  <!-- Dato centrado genérico -->
  <ss:Style ss:ID="Dato">
    <ss:Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <ss:Font ss:FontName="Arial" ss:Size="9"/>
    <ss:Borders>
      <ss:Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1"/>
      <ss:Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    </ss:Borders>
  </ss:Style>
</ss:Styles>

<?php
// ════════════════════════════════════════════════════
// HOJA 1 — LPA
// ════════════════════════════════════════════════════
echo generarHojaLPA(
    "LPA $anio", $datos, $anio, $total,
    'SANTA LUCIA COROTU',
    $totAreaTotal, $totAreaCacao, $totVol,
    $totMeses, $toneladas, $DIVISOR, $PRIMA, $BASE, $meses
);

// ════════════════════════════════════════════════════
// HOJA 2 — ESTIMACIÓN DE COSECHA
// ════════════════════════════════════════════════════
echo generarHojaLPA(
    "ESTIMACIÓN DE COSECHA", $datos, $anio, $total,
    'ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS',
    $totAreaTotal, $totAreaCacao, $totVol,
    $totMeses, $toneladas, $DIVISOR, $PRIMA, $BASE, $meses
);

// ════════════════════════════════════════════════════
// HOJA 3 — PLAN DE ABASTECIMIENTO
// ════════════════════════════════════════════════════
?>
<ss:Worksheet ss:Name="PLAN DE ABASTECIMIENTO">
<ss:Table>
  <ss:Column ss:Width="30"/>
  <ss:Column ss:Width="300"/>
  <ss:Column ss:Width="110"/>
  <ss:Column ss:Width="100"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="75"/>
  <ss:Column ss:Width="110"/>
  <ss:Column ss:Width="110"/>

  <!-- Encabezados Plan -->
  <ss:Row ss:Height="44">
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">#</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">ASOCIACIÓN</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">FECHA DE CONTRATO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">ESTIMADO ANUAL</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">ENERO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">FEBRERO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">MARZO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">ABRIL</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">MAYO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">JUNIO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">JULIO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">AGOSTO</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">SEPTIEMBRE</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">OCTUBRE</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">NOVIEMBRE</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">DICIEMBRE</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">ENTREGA TOTAL QQ</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Hdr3"><ss:Data ss:Type="String">ENTREGA TOTAL TM</ss:Data></ss:Cell>
  </ss:Row>

  <!-- Fila de datos Plan -->
  <ss:Row ss:Height="18">
    <ss:Cell ss:StyleID="Dato3"><ss:Data ss:Type="Number">6</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Dato3L"><ss:Data ss:Type="String">ASOCIACIÓN DE TRABAJADORES AGRÍCOLAS AUTONÓMOS SANTA LUCIA COROTÚ</ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Dato3"><ss:Data ss:Type="String">01/01/<?= $anio ?></ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Dato3Num"><ss:Data ss:Type="Number"><?= $totVol ?></ss:Data></ss:Cell>
    <?php foreach ($totMeses as $tm): ?>
    <ss:Cell ss:StyleID="Dato3Num"><ss:Data ss:Type="Number"><?= $tm ?></ss:Data></ss:Cell>
    <?php endforeach; ?>
    <ss:Cell ss:StyleID="Dato3Num"><ss:Data ss:Type="Number"><?= $totSumMeses ?></ss:Data></ss:Cell>
    <ss:Cell ss:StyleID="Dato3Num"><ss:Data ss:Type="Number"><?= $totSumMeses / $DIVISOR ?></ss:Data></ss:Cell>
  </ss:Row>

</ss:Table>
</ss:Worksheet>

</ss:Workbook>
<?php
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>