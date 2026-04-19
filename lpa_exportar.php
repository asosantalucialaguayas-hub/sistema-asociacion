<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { die('No autorizado'); }

require "config/conexion.php";

$id_periodo = intval($_GET['id_periodo'] ?? 0);
$anio       = intval($_GET['anio'] ?? date('Y'));

// ── CONSTANTES FAIRTRADE ──────────────────────────────────────────────
$DIVISOR = 22.046;
$PRIMA   = 240;
$BASE    = 275;
$ORG_ES      = 'ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS';
$ORG_EN_LPA  = 'SANTA LUCIA COROTU';
$ORG_EN_EST  = 'ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS';
$ORG_PLAN    = 'ASOCIACIÓN DE TRABAJADORES AGRÍCOLAS AUTONÓMOS SANTA LUCIA COROTÚ';

// ── CONSULTA DB ───────────────────────────────────────────────────────
try {
    $sql = "
        SELECT
            l.zona,
            l.comunidad_grupo,
            s.identificacion AS cedula,
            TRIM(COALESCE(NULLIF(s.nombre_completo,''),
                 CONCAT(COALESCE(s.apellidos,''),' ',COALESCE(s.nombres,'')))) AS nombre_completo,
            UPPER(COALESCE(l.sexo, s.sexo, ''))                     AS sexo,
            COALESCE(l.celular, s.telefono, '')                     AS celular,
            DATE_FORMAT(COALESCE(l.fecha_nacimiento,s.fecha_nacimiento),'%d/%m/%Y') AS fecha_nacimiento,
            DATE_FORMAT(COALESCE(l.fecha_ingreso,s.fecha_ingreso),'%d/%m/%Y')       AS fecha_ingreso,
            UPPER(COALESCE(l.en_acercamiento,'NO'))                 AS en_acercamiento,
            UPPER(COALESCE(l.otra_org_fairtrade,'NO'))              AS otra_org_fairtrade,
            COALESCE(l.area_total_ha,0)                             AS area_total_ha,
            COALESCE(l.area_cacao_ha,0)                             AS area_cacao_ha,
            COALESCE(l.num_matas_ha,0)                              AS num_matas_ha,
            COALESCE(l.certificacion_organica,'NO')                 AS cert_organica,
            COALESCE(l.volumen_produccion_estimado,0)               AS vol_produccion,
            COALESCE(l.volumen_entregado_org,0)                     AS vol_entregado,
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

$mesesKeys = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
$mesesLabel = ['E','F','M','A','M','J','JL','A','S','O','N','D'];

// ── TOTALES ───────────────────────────────────────────────────────────
$totAreaTotal = 0; $totAreaCacao = 0; $totVol = 0; $totEntregado = 0;
$totMeses = array_fill(0, 12, 0);
foreach ($datos as $r) {
    $totAreaTotal += (float)$r['area_total_ha'];
    $totAreaCacao += (float)$r['area_cacao_ha'];
    $totVol       += (float)$r['vol_produccion'];
    $totEntregado += (float)$r['vol_entregado'];
    foreach ($mesesKeys as $mi => $mes) $totMeses[$mi] += (float)$r[$mes];
}
$toneladas    = $totVol / $DIVISOR;
$benefPrima   = ($toneladas * $PRIMA) + ($toneladas * $BASE);

// ── ESTILOS COMUNES ───────────────────────────────────────────────────
$s_info  = 'background:#FFE699;font-family:Arial;font-size:10pt;font-weight:bold;text-align:left;vertical-align:middle;border:1px solid #000;padding:3px 6px;';
$s_th    = 'background:#FFE699;font-family:Arial;font-size:8pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid #000;padding:3px 2px;';
$s_td    = 'font-family:Arial;font-size:9pt;text-align:center;vertical-align:middle;border:1px solid #000;padding:2px 3px;';
$s_tdl   = 'font-family:Arial;font-size:9pt;text-align:left;vertical-align:middle;border:1px solid #000;padding:2px 4px;';
$s_tot   = 'background:#FFE699;font-family:Arial;font-size:9pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid #000;padding:2px 3px;';
$s_hdr3  = 'background:#1F3A5F;color:#fff;font-family:Arial;font-size:9pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid #000;padding:4px 3px;';
$s_td3   = 'font-family:Arial;font-size:9pt;text-align:center;vertical-align:middle;border:1px solid #000;padding:3px 4px;';

function n2($v) { return $v != 0 ? number_format((float)$v, 2, '.', ',') : ''; }
function h($s)  { return htmlspecialchars($s ?? '', ENT_QUOTES); }

// ── HEADERS HTTP ──────────────────────────────────────────────────────
$filename = "LPA_{$anio}_" . date('Ymd_His') . ".xls";
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
  br { mso-data-placement:same-cell; }
  td { mso-number-format:"\@"; }
  .num { mso-number-format:"#\,##0\.00"; }
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     HOJA 1 — LPA
══════════════════════════════════════════════════════ -->
<table border="0" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;"
       x:Name="LPA <?= $anio ?>">

  <!-- INFO SUPERIOR -->
  <tr>
    <td colspan="5"  style="<?= $s_info ?>">Lista de Productores Miembros:</td>
    <td colspan="8"  style="<?= $s_info ?>"><?= $total ?> SOCIOS</td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="5"  style="<?= $s_info ?>">List of members producers:</td>
    <td colspan="9"  style="<?= $s_info ?>"><?= $total ?> SOCIOS</td>
  </tr>
  <tr>
    <td colspan="5"  style="<?= $s_info ?>">AÑO:</td>
    <td colspan="8"  style="<?= $s_info ?>"><?= $anio ?></td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="5"  style="<?= $s_info ?>">YEAR:</td>
    <td colspan="9"  style="<?= $s_info ?>"><?= $anio ?></td>
  </tr>
  <tr>
    <td colspan="5"  style="<?= $s_info ?>">Nombre de la Organización:</td>
    <td colspan="8"  style="<?= $s_info ?>"><?= $GLOBALS['ORG_ES'] ?></td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="5"  style="<?= $s_info ?>">Name of Organisation:</td>
    <td colspan="9"  style="<?= $s_info ?>"><?= $ORG_EN_LPA ?></td>
  </tr>
  <tr><td colspan="29" style="height:8px;border:0">&nbsp;</td></tr>

  <!-- ENCABEZADOS -->
  <tr style="height:60px;">
    <td style="<?= $s_th ?> width:30px;">N°</td>
    <td style="<?= $s_th ?> width:70px;">Zona</td>
    <td style="<?= $s_th ?> width:100px;">Comunidad o Grupo</td>
    <td style="<?= $s_th ?> width:100px;">Cédula del Productor</td>
    <td style="<?= $s_th ?> width:230px;">Apellidos y nombres productor/a</td>
    <td style="<?= $s_th ?> width:45px;">Sexo<br>(F/M)</td>
    <td style="<?= $s_th ?> width:85px;">Celular</td>
    <td style="<?= $s_th ?> width:80px;">Fecha de<br>nacimiento</td>
    <td style="<?= $s_th ?> width:90px;">Fecha de afiliación<br>a la organización</td>
    <td style="<?= $s_th ?> width:85px;">En acercamiento<br>(en proceso para<br>ingresar de socio)</td>
    <td style="<?= $s_th ?> width:110px;">Socios que también son<br>miembros de otra<br>organización certificada<br>Fairtrade SI/NO</td>
    <td style="<?= $s_th ?> width:75px;">Área total<br>unidad prod.<br>(Ha)</td>
    <td style="<?= $s_th ?> width:75px;">Área cultivada<br>de Cacao (Ha)</td>
    <td style="<?= $s_th ?> width:75px;">Número de<br>matas por ha</td>
    <td style="<?= $s_th ?> width:75px;">Estatus<br>certificación<br>Orgánica SI/NO</td>
    <td style="<?= $s_th ?> width:75px;">Volumen de<br>producción<br>de Cacao</td>
    <td style="<?= $s_th ?> width:85px;">Volumen de producción<br>Cacao entregado<br>a la organización</td>
    <?php foreach ($mesesLabel as $ml): ?>
    <td style="<?= $s_th ?> width:38px;"><?= $ml ?></td>
    <?php endforeach; ?>
  </tr>

  <!-- SUBFILA -->
  <tr style="height:14px;">
    <?php for ($i=0;$i<13;$i++): ?><td style="<?= $s_th ?>">&nbsp;</td><?php endfor; ?>
    <td style="<?= $s_th ?>">1000/has</td>
    <?php for ($i=0;$i<3;$i++): ?><td style="<?= $s_th ?>">&nbsp;</td><?php endfor; ?>
    <td colspan="12" style="<?= $s_th ?>">LPA</td>
  </tr>

  <!-- FILAS DE DATOS -->
  <?php foreach ($datos as $i => $r):
    $matas = $r['num_matas_ha'] > 0 ? $r['num_matas_ha'].'/has' : '';
  ?>
  <tr style="height:14px;">
    <td style="<?= $s_td ?>"><?= $i+1 ?></td>
    <td style="<?= $s_tdl ?>"><?= h($r['zona']) ?></td>
    <td style="<?= $s_tdl ?>"><?= h($r['comunidad_grupo']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['cedula']) ?></td>
    <td style="<?= $s_tdl ?>"><?= h($r['nombre_completo']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['sexo']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['celular']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['fecha_nacimiento']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['fecha_ingreso']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['en_acercamiento']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['otra_org_fairtrade']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['area_total_ha']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['area_cacao_ha']) ?></td>
    <td style="<?= $s_td ?>"><?= $matas ?></td>
    <td style="<?= $s_td ?>"><?= h($r['cert_organica']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['vol_produccion']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['vol_entregado']) ?></td>
    <?php foreach ($mesesKeys as $mes): ?>
    <td class="num" style="<?= $s_td ?>"><?= n2($r[$mes]) ?></td>
    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>

  <!-- FILA TOTALES -->
  <tr style="height:16px;">
    <?php for ($i=0;$i<11;$i++): ?><td style="<?= $s_tot ?>">&nbsp;</td><?php endfor; ?>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totAreaTotal) ?></td>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totAreaCacao) ?></td>
    <td style="<?= $s_tot ?>">&nbsp;</td>
    <td style="<?= $s_tot ?>">&nbsp;</td>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totVol) ?></td>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totEntregado) ?></td>
    <?php foreach ($totMeses as $tm): ?>
    <td class="num" style="<?= $s_tot ?>"><?= n2($tm) ?></td>
    <?php endforeach; ?>
  </tr>

  <!-- CÁLCULOS FAIRTRADE -->
  <tr><td colspan="29" style="border:0;height:8px;">&nbsp;</td></tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;font-weight:bold;">TONELADA</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;">DIVIDIDO A <?= $DIVISOR ?> =</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;"><?= number_format($toneladas,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;font-weight:bold;">PRIMA</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;">MULTIPLICADO * <?= $PRIMA ?> =</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;"><?= number_format($toneladas*$PRIMA,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;">MULTIPLICADO * <?= $BASE ?> =</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;"><?= number_format($toneladas*$BASE,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;font-weight:bold;color:#1F3A5F;">BENEFICIO PRIMA</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:11pt;font-weight:bold;color:#1F3A5F;"><?= number_format($benefPrima,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>

</table>

<!-- ══════════════════════════════════════════════════════
     HOJA 2 — ESTIMACIÓN DE COSECHA
     (separador de hoja para Excel HTML multi-sheet)
══════════════════════════════════════════════════════ -->
<br clear="all" style="mso-break-type:section-break;page-break-before:always">

<table border="0" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;"
       x:Name="ESTIMACIÓN DE COSECHA">

  <tr>
    <td colspan="5"  style="<?= $s_info ?>">Lista de Productores Miembros:</td>
    <td colspan="8"  style="<?= $s_info ?>"><?= $total ?> SOCIOS</td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="5"  style="<?= $s_info ?>">List of members producers:</td>
    <td colspan="9"  style="<?= $s_info ?>"><?= $total ?> SOCIOS</td>
  </tr>
  <tr>
    <td colspan="5"  style="<?= $s_info ?>">AÑO:</td>
    <td colspan="8"  style="<?= $s_info ?>"><?= $anio ?></td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="5"  style="<?= $s_info ?>">YEAR:</td>
    <td colspan="9"  style="<?= $s_info ?>"><?= $anio ?></td>
  </tr>
  <tr>
    <td colspan="5"  style="<?= $s_info ?>">Nombre de la Organización:</td>
    <td colspan="8"  style="<?= $s_info ?>"><?= $ORG_ES ?></td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="5"  style="<?= $s_info ?>">Name of Organisation:</td>
    <td colspan="9"  style="<?= $s_info ?>"><?= $ORG_EN_EST ?></td>
  </tr>
  <tr><td colspan="29" style="height:8px;border:0">&nbsp;</td></tr>

  <tr style="height:60px;">
    <td style="<?= $s_th ?> width:30px;">N°</td>
    <td style="<?= $s_th ?> width:70px;">Zona</td>
    <td style="<?= $s_th ?> width:100px;">Comunidad o Grupo</td>
    <td style="<?= $s_th ?> width:100px;">Cédula del Productor</td>
    <td style="<?= $s_th ?> width:230px;">Apellidos y nombres productor/a</td>
    <td style="<?= $s_th ?> width:45px;">Sexo<br>(F/M)</td>
    <td style="<?= $s_th ?> width:85px;">Celular</td>
    <td style="<?= $s_th ?> width:80px;">Fecha de<br>nacimiento</td>
    <td style="<?= $s_th ?> width:90px;">Fecha de afiliación<br>a la organización</td>
    <td style="<?= $s_th ?> width:85px;">En acercamiento<br>(en proceso para<br>ingresar de socio)</td>
    <td style="<?= $s_th ?> width:110px;">Socios que también son<br>miembros de otra<br>organización certificada<br>Fairtrade SI/NO</td>
    <td style="<?= $s_th ?> width:75px;">Área total<br>unidad prod.<br>(Ha)</td>
    <td style="<?= $s_th ?> width:75px;">Área cultivada<br>de Cacao (Ha)</td>
    <td style="<?= $s_th ?> width:75px;">Número de<br>matas por ha</td>
    <td style="<?= $s_th ?> width:75px;">Estatus<br>certificación<br>Orgánica SI/NO</td>
    <td style="<?= $s_th ?> width:75px;">Volumen de<br>producción<br>de Cacao</td>
    <td style="<?= $s_th ?> width:85px;">Volumen de producción<br>Cacao entregado<br>a la organización</td>
    <?php foreach ($mesesLabel as $ml): ?>
    <td style="<?= $s_th ?> width:38px;"><?= $ml ?></td>
    <?php endforeach; ?>
  </tr>

  <tr style="height:14px;">
    <?php for ($i=0;$i<13;$i++): ?><td style="<?= $s_th ?>">&nbsp;</td><?php endfor; ?>
    <td style="<?= $s_th ?>">1000/has</td>
    <?php for ($i=0;$i<3;$i++): ?><td style="<?= $s_th ?>">&nbsp;</td><?php endfor; ?>
    <td colspan="12" style="<?= $s_th ?>">LPA</td>
  </tr>

  <?php foreach ($datos as $i => $r):
    $matas = $r['num_matas_ha'] > 0 ? $r['num_matas_ha'].'/has' : '';
  ?>
  <tr style="height:14px;">
    <td style="<?= $s_td ?>"><?= $i+1 ?></td>
    <td style="<?= $s_tdl ?>"><?= h($r['zona']) ?></td>
    <td style="<?= $s_tdl ?>"><?= h($r['comunidad_grupo']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['cedula']) ?></td>
    <td style="<?= $s_tdl ?>"><?= h($r['nombre_completo']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['sexo']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['celular']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['fecha_nacimiento']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['fecha_ingreso']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['en_acercamiento']) ?></td>
    <td style="<?= $s_td  ?>"><?= h($r['otra_org_fairtrade']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['area_total_ha']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['area_cacao_ha']) ?></td>
    <td style="<?= $s_td ?>"><?= $matas ?></td>
    <td style="<?= $s_td ?>"><?= h($r['cert_organica']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['vol_produccion']) ?></td>
    <td class="num" style="<?= $s_td ?>"><?= n2($r['vol_entregado']) ?></td>
    <?php foreach ($mesesKeys as $mes): ?>
    <td class="num" style="<?= $s_td ?>"><?= n2($r[$mes]) ?></td>
    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>

  <tr style="height:16px;">
    <?php for ($i=0;$i<11;$i++): ?><td style="<?= $s_tot ?>">&nbsp;</td><?php endfor; ?>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totAreaTotal) ?></td>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totAreaCacao) ?></td>
    <td style="<?= $s_tot ?>">&nbsp;</td>
    <td style="<?= $s_tot ?>">&nbsp;</td>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totVol) ?></td>
    <td class="num" style="<?= $s_tot ?>"><?= n2($totEntregado) ?></td>
    <?php foreach ($totMeses as $tm): ?>
    <td class="num" style="<?= $s_tot ?>"><?= n2($tm) ?></td>
    <?php endforeach; ?>
  </tr>

  <tr><td colspan="29" style="border:0;height:8px;">&nbsp;</td></tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;font-weight:bold;">TONELADA</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;">DIVIDIDO A <?= $DIVISOR ?> =</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;"><?= number_format($toneladas,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;font-weight:bold;">PRIMA</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;">MULTIPLICADO * <?= $PRIMA ?> =</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;"><?= number_format($toneladas*$PRIMA,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;">MULTIPLICADO * <?= $BASE ?> =</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;"><?= number_format($toneladas*$BASE,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="13" style="border:0">&nbsp;</td>
    <td colspan="2"  style="border:0">&nbsp;</td>
    <td colspan="2"  style="font-family:Arial;font-size:9pt;font-weight:bold;color:#1F3A5F;">BENEFICIO PRIMA</td>
    <td colspan="3"  class="num" style="font-family:Arial;font-size:11pt;font-weight:bold;color:#1F3A5F;"><?= number_format($benefPrima,2,'.', ',') ?></td>
    <td colspan="9"  style="border:0">&nbsp;</td>
  </tr>

</table>

<!-- ══════════════════════════════════════════════════════
     HOJA 3 — PLAN DE ABASTECIMIENTO
══════════════════════════════════════════════════════ -->
<br clear="all" style="mso-break-type:section-break;page-break-before:always">

<table border="0" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;"
       x:Name="PLAN DE ABASTECIMIENTO">

  <!-- ENCABEZADOS PLAN -->
  <tr style="height:44px;">
    <td style="<?= $s_hdr3 ?> width:30px;">#</td>
    <td style="<?= $s_hdr3 ?> width:320px;">ASOCIACIÓN</td>
    <td style="<?= $s_hdr3 ?> width:110px;">FECHA DE CONTRATO</td>
    <td style="<?= $s_hdr3 ?> width:100px;">ESTIMADO ANUAL</td>
    <td style="<?= $s_hdr3 ?> width:75px;">ENERO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">FEBRERO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">MARZO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">ABRIL</td>
    <td style="<?= $s_hdr3 ?> width:75px;">MAYO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">JUNIO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">JULIO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">AGOSTO</td>
    <td style="<?= $s_hdr3 ?> width:75px;">SEPTIEMBRE</td>
    <td style="<?= $s_hdr3 ?> width:75px;">OCTUBRE</td>
    <td style="<?= $s_hdr3 ?> width:75px;">NOVIEMBRE</td>
    <td style="<?= $s_hdr3 ?> width:75px;">DICIEMBRE</td>
    <td style="<?= $s_hdr3 ?> width:100px;">ENTREGA TOTAL QQ</td>
    <td style="<?= $s_hdr3 ?> width:100px;">ENTREGA TOTAL TM</td>
  </tr>

  <!-- FILA DATOS PLAN -->
  <tr style="height:18px;">
    <td style="<?= $s_td3 ?>">6</td>
    <td style="<?= $s_td3 ?> text-align:left;"><?= h($ORG_PLAN) ?></td>
    <td style="<?= $s_td3 ?>">01/01/<?= $anio ?></td>
    <td class="num" style="<?= $s_td3 ?>"><?= n2($totVol) ?></td>
    <?php foreach ($totMeses as $tm): ?>
    <td class="num" style="<?= $s_td3 ?>"><?= n2($tm) ?></td>
    <?php endforeach; ?>
    <td class="num" style="<?= $s_td3 ?>"><?= n2(array_sum($totMeses)) ?></td>
    <td class="num" style="<?= $s_td3 ?>"><?= number_format(array_sum($totMeses)/$DIVISOR,2,'.', ',') ?></td>
  </tr>

</table>

</body>
</html>