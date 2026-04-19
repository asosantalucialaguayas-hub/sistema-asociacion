<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { die('No autorizado'); }

require "config/conexion.php";

$id_periodo = intval($_GET['id_periodo'] ?? 0);
$anio       = intval($_GET['anio'] ?? date('Y'));

// Constantes Fairtrade
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
    if ($id_periodo) {
        $stmt->bindValue(':id_periodo', $id_periodo, PDO::PARAM_INT);
    }
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($datos);

    // Calcular totales para hojas 2 y 3
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

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="LPA_' . $anio . '_' . date('Ymd_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";

    $amarillo = "#FFE699";
    $borde    = "#000000";
    $grisFila = "#F2F2F2";

    $infoLeft = "background:$amarillo;font-family:Arial;font-size:10pt;font-weight:bold;text-align:left;vertical-align:middle;border:1px solid $borde;padding:4px 6px;";
    $infoVal  = "background:$amarillo;font-family:Arial;font-size:10pt;font-weight:bold;text-align:left;vertical-align:middle;border:1px solid $borde;padding:4px 6px;";
    $th       = "background:$amarillo;font-family:Arial;font-size:9pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid $borde;padding:4px 3px;";
    $thWrap   = "background:$amarillo;font-family:Arial;font-size:8pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid $borde;padding:3px 2px;";
    $tot      = "background:$amarillo;font-family:Arial;font-size:9pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid $borde;padding:2px 4px;";
    $hdr3     = "background:#1F3A5F;color:#fff;font-family:Arial;font-size:9pt;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid $borde;padding:4px 3px;";
    $td3      = "font-family:Arial;font-size:9pt;text-align:center;vertical-align:middle;border:1px solid $borde;padding:3px 4px;";
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
        .txt { mso-number-format:"\@"; }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     HOJA 1 — LPA
═══════════════════════════════════════════ -->
<table border="0" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;"
       x:Name="LPA <?= $anio ?>">

    <!-- FILA SUPERIOR CON IMAGEN -->
    <tr>
        <td colspan="6" style="border:0; padding:0; height:58px; vertical-align:middle;">
            <img src="img/portada.jpg" alt="Portada" style="display:block; width:440px; height:58px;">
        </td>
        <td colspan="23" style="border:0;">&nbsp;</td>
    </tr>

    <!-- BLOQUE DE INFORMACIÓN SUPERIOR -->
    <tr>
        <td colspan="5" style="<?= $infoLeft ?>">Lista de Productores Miembros:</td>
        <td colspan="8" style="<?= $infoVal ?>"><?= $total ?> SOCIOS</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="5" style="<?= $infoLeft ?>">List of members producers:</td>
        <td colspan="9" style="<?= $infoVal ?>"><?= $total ?> SOCIOS</td>
    </tr>
    <tr>
        <td colspan="5" style="<?= $infoLeft ?>">AÑO:</td>
        <td colspan="8" style="<?= $infoVal ?>"><?= $anio ?></td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="5" style="<?= $infoLeft ?>">YEAR:</td>
        <td colspan="9" style="<?= $infoVal ?>"><?= $anio ?></td>
    </tr>
    <tr>
        <td colspan="5" style="<?= $infoLeft ?>">Nombre de la Organización:</td>
        <td colspan="8" style="<?= $infoVal ?>">ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="5" style="<?= $infoLeft ?>">Name of Organisation:</td>
        <td colspan="9" style="<?= $infoVal ?>">SANTA LUCIA COROTU</td>
    </tr>

    <!-- SEPARADOR -->
    <tr>
        <td colspan="29" style="height:10px; border:0;">&nbsp;</td>
    </tr>

    <!-- ENCABEZADOS -->
    <tr style="height:56px;">
        <td style="<?= $th ?> width:40px;">N°</td>
        <td style="<?= $th ?> width:90px;">Zona</td>
        <td style="<?= $thWrap ?> width:120px;">Comunidad o Grupo</td>
        <td style="<?= $thWrap ?> width:110px;">Cédula del Productor</td>
        <td style="<?= $thWrap ?> width:250px;">Apellidos y nombres productor/a</td>
        <td style="<?= $thWrap ?> width:70px;">Sexo<br>(F/M)</td>
        <td style="<?= $th ?> width:95px;">Celular</td>
        <td style="<?= $thWrap ?> width:95px;">Fecha de nacimiento</td>
        <td style="<?= $thWrap ?> width:120px;">Fecha de afiliación a la organización</td>
        <td style="<?= $thWrap ?> width:110px;">En acercamiento (en proceso para ingresar de socio)</td>
        <td style="<?= $thWrap ?> width:130px;">Socios que también son miembros de otra organización certificada Fairtrade SI/NO</td>
        <td style="<?= $thWrap ?> width:95px;">Área total de su unidad de produccion (Ha)</td>
        <td style="<?= $thWrap ?> width:95px;">Área cultivada de Cacao (Ha)</td>
        <td style="<?= $thWrap ?> width:95px;">Número de matas por ha</td>
        <td style="<?= $thWrap ?> width:95px;">Estatus de certificación Orgánica SI/NO</td>
        <td style="<?= $thWrap ?> width:95px;">Volumen de producción de Cacao</td>
        <td style="<?= $thWrap ?> width:95px;">Volumen de producción de Cacao entregado a la organización</td>
        <td style="<?= $th ?> width:45px;">E</td>
        <td style="<?= $th ?> width:45px;">F</td>
        <td style="<?= $th ?> width:45px;">M</td>
        <td style="<?= $th ?> width:45px;">A</td>
        <td style="<?= $th ?> width:45px;">M</td>
        <td style="<?= $th ?> width:45px;">J</td>
        <td style="<?= $th ?> width:45px;">JL</td>
        <td style="<?= $th ?> width:45px;">A</td>
        <td style="<?= $th ?> width:45px;">S</td>
        <td style="<?= $th ?> width:45px;">O</td>
        <td style="<?= $th ?> width:45px;">N</td>
        <td style="<?= $th ?> width:45px;">D</td>
    </tr>

    <!-- SUBFILA -->
    <tr style="height:22px;">
        <?php for ($i = 0; $i < 13; $i++): ?>
            <td style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>;">&nbsp;</td>
        <?php endfor; ?>
        <td style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>; text-align:center; font-family:Arial; font-size:9pt;">1000/has</td>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <td style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>;">&nbsp;</td>
        <?php endfor; ?>
        <td colspan="12" style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>; text-align:center; font-family:Arial; font-size:10pt; font-weight:bold;">LPA</td>
    </tr>

    <!-- DATOS HOJA 1 -->
    <?php foreach ($datos as $i => $row):
        $bg  = '#FFFFFF';
        $tdc = "font-family:Arial;font-size:9pt;text-align:center;vertical-align:middle;border:1px solid $borde;padding:2px 4px;background:$bg;";
        $tdl = "font-family:Arial;font-size:9pt;text-align:left;vertical-align:middle;border:1px solid $borde;padding:2px 4px;background:$bg;";
        $matas = $row['num_matas_ha'] > 0 ? $row['num_matas_ha'] . '/has' : '';
    ?>
    <tr>
        <td style="<?= $tdc ?>"><?= $i + 1 ?></td>
        <td style="<?= $tdl ?>"><?= htmlspecialchars($row['zona'] ?? '') ?></td>
        <td style="<?= $tdl ?>"><?= htmlspecialchars($row['comunidad_grupo'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['cedula'] ?? '') ?></td>
        <td style="<?= $tdl ?>"><?= htmlspecialchars($row['nombre_completo'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['sexo'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['celular'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['fecha_nacimiento'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['fecha_ingreso'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['en_acercamiento'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['otra_org_fairtrade'] ?? '') ?></td>
        <td class="num" style="<?= $tdc ?>"><?= number_format((float)$row['area_total_ha'], 2) ?></td>
        <td class="num" style="<?= $tdc ?>"><?= number_format((float)$row['area_cacao_ha'], 2) ?></td>
        <td style="<?= $tdc ?>"><?= $matas ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['cert_organica'] ?? '') ?></td>
        <td class="num" style="<?= $tdc ?>"><?= number_format((float)$row['vol_produccion'], 2) ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['vol_entregado'] ?? '') ?></td>
        <?php foreach ($meses as $mes): ?>
            <td class="num" style="<?= $tdc ?>"><?= (float)$row[$mes] > 0 ? number_format((float)$row[$mes], 2) : '' ?></td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>

    <!-- FILA TOTALES HOJA 1 -->
    <tr>
        <?php for ($i = 0; $i < 11; $i++): ?><td style="<?= $tot ?>">&nbsp;</td><?php endfor; ?>
        <td class="num" style="<?= $tot ?>"><?= number_format($totAreaTotal, 2) ?></td>
        <td class="num" style="<?= $tot ?>"><?= number_format($totAreaCacao, 2) ?></td>
        <td style="<?= $tot ?>">&nbsp;</td>
        <td style="<?= $tot ?>">&nbsp;</td>
        <td class="num" style="<?= $tot ?>"><?= number_format($totVol, 2) ?></td>
        <td style="<?= $tot ?>">&nbsp;</td>
        <?php foreach ($totMeses as $tm): ?>
            <td class="num" style="<?= $tot ?>"><?= number_format($tm, 2) ?></td>
        <?php endforeach; ?>
    </tr>

    <!-- CÁLCULOS FAIRTRADE HOJA 1 -->
    <tr><td colspan="29" style="border:0;height:10px;">&nbsp;</td></tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;font-weight:bold;border:0;">TONELADA</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;border:0;">DIVIDIDO A <?= $DIVISOR ?> =</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format($toneladas, 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;font-weight:bold;border:0;">PRIMA</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;border:0;">MULTIPLICADO * <?= $PRIMA ?> =</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format($toneladas * $PRIMA, 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;border:0;">MULTIPLICADO * <?= $BASE ?> =</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format($toneladas * $BASE, 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;font-weight:bold;color:#1F3A5F;border:0;">BENEFICIO PRIMA</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:11pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format(($toneladas * $PRIMA) + ($toneladas * $BASE), 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>

</table>

<!-- ═══════════════════════════════════════════
     HOJA 2 — ESTIMACIÓN DE COSECHA
═══════════════════════════════════════════ -->
<br clear="all" style="mso-break-type:section-break;page-break-before:always">

<table border="0" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;"
       x:Name="ESTIMACIÓN DE COSECHA">

    <tr>
        <td colspan="6" style="border:0; padding:0; height:58px; vertical-align:middle;">
            <img src="img/portada.jpg" alt="Portada" style="display:block; width:440px; height:58px;">
        </td>
        <td colspan="23" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="5" style="<?= $infoLeft ?>">Lista de Productores Miembros:</td>
        <td colspan="8" style="<?= $infoVal ?>"><?= $total ?> SOCIOS</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="5" style="<?= $infoLeft ?>">List of members producers:</td>
        <td colspan="9" style="<?= $infoVal ?>"><?= $total ?> SOCIOS</td>
    </tr>
    <tr>
        <td colspan="5" style="<?= $infoLeft ?>">AÑO:</td>
        <td colspan="8" style="<?= $infoVal ?>"><?= $anio ?></td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="5" style="<?= $infoLeft ?>">YEAR:</td>
        <td colspan="9" style="<?= $infoVal ?>"><?= $anio ?></td>
    </tr>
    <tr>
        <td colspan="5" style="<?= $infoLeft ?>">Nombre de la Organización:</td>
        <td colspan="8" style="<?= $infoVal ?>">ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="5" style="<?= $infoLeft ?>">Name of Organisation:</td>
        <td colspan="9" style="<?= $infoVal ?>">ASOCIACION DE TRABAJADORES AGRICOLAS AUTONOMOS</td>
    </tr>
    <tr><td colspan="29" style="height:10px; border:0;">&nbsp;</td></tr>

    <tr style="height:56px;">
        <td style="<?= $th ?> width:40px;">N°</td>
        <td style="<?= $th ?> width:90px;">Zona</td>
        <td style="<?= $thWrap ?> width:120px;">Comunidad o Grupo</td>
        <td style="<?= $thWrap ?> width:110px;">Cédula del Productor</td>
        <td style="<?= $thWrap ?> width:250px;">Apellidos y nombres productor/a</td>
        <td style="<?= $thWrap ?> width:70px;">Sexo<br>(F/M)</td>
        <td style="<?= $th ?> width:95px;">Celular</td>
        <td style="<?= $thWrap ?> width:95px;">Fecha de nacimiento</td>
        <td style="<?= $thWrap ?> width:120px;">Fecha de afiliación a la organización</td>
        <td style="<?= $thWrap ?> width:110px;">En acercamiento (en proceso para ingresar de socio)</td>
        <td style="<?= $thWrap ?> width:130px;">Socios que también son miembros de otra organización certificada Fairtrade SI/NO</td>
        <td style="<?= $thWrap ?> width:95px;">Área total de su unidad de produccion (Ha)</td>
        <td style="<?= $thWrap ?> width:95px;">Área cultivada de Cacao (Ha)</td>
        <td style="<?= $thWrap ?> width:95px;">Número de matas por ha</td>
        <td style="<?= $thWrap ?> width:95px;">Estatus de certificación Orgánica SI/NO</td>
        <td style="<?= $thWrap ?> width:95px;">Volumen de producción de Cacao</td>
        <td style="<?= $thWrap ?> width:95px;">Volumen de producción de Cacao entregado a la organización</td>
        <td style="<?= $th ?> width:45px;">E</td>
        <td style="<?= $th ?> width:45px;">F</td>
        <td style="<?= $th ?> width:45px;">M</td>
        <td style="<?= $th ?> width:45px;">A</td>
        <td style="<?= $th ?> width:45px;">M</td>
        <td style="<?= $th ?> width:45px;">J</td>
        <td style="<?= $th ?> width:45px;">JL</td>
        <td style="<?= $th ?> width:45px;">A</td>
        <td style="<?= $th ?> width:45px;">S</td>
        <td style="<?= $th ?> width:45px;">O</td>
        <td style="<?= $th ?> width:45px;">N</td>
        <td style="<?= $th ?> width:45px;">D</td>
    </tr>
    <tr style="height:22px;">
        <?php for ($i = 0; $i < 13; $i++): ?>
            <td style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>;">&nbsp;</td>
        <?php endfor; ?>
        <td style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>; text-align:center; font-family:Arial; font-size:9pt;">1000/has</td>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <td style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>;">&nbsp;</td>
        <?php endfor; ?>
        <td colspan="12" style="border:1px solid <?= $borde ?>; background:<?= $amarillo ?>; text-align:center; font-family:Arial; font-size:10pt; font-weight:bold;">LPA</td>
    </tr>

    <!-- DATOS HOJA 2 (mismos datos, distinto nombre organización EN) -->
    <?php foreach ($datos as $i => $row):
        $bg  = '#FFFFFF';
        $tdc = "font-family:Arial;font-size:9pt;text-align:center;vertical-align:middle;border:1px solid $borde;padding:2px 4px;background:$bg;";
        $tdl = "font-family:Arial;font-size:9pt;text-align:left;vertical-align:middle;border:1px solid $borde;padding:2px 4px;background:$bg;";
        $matas = $row['num_matas_ha'] > 0 ? $row['num_matas_ha'] . '/has' : '';
    ?>
    <tr>
        <td style="<?= $tdc ?>"><?= $i + 1 ?></td>
        <td style="<?= $tdl ?>"><?= htmlspecialchars($row['zona'] ?? '') ?></td>
        <td style="<?= $tdl ?>"><?= htmlspecialchars($row['comunidad_grupo'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['cedula'] ?? '') ?></td>
        <td style="<?= $tdl ?>"><?= htmlspecialchars($row['nombre_completo'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['sexo'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['celular'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['fecha_nacimiento'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['fecha_ingreso'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['en_acercamiento'] ?? '') ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['otra_org_fairtrade'] ?? '') ?></td>
        <td class="num" style="<?= $tdc ?>"><?= number_format((float)$row['area_total_ha'], 2) ?></td>
        <td class="num" style="<?= $tdc ?>"><?= number_format((float)$row['area_cacao_ha'], 2) ?></td>
        <td style="<?= $tdc ?>"><?= $matas ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['cert_organica'] ?? '') ?></td>
        <td class="num" style="<?= $tdc ?>"><?= number_format((float)$row['vol_produccion'], 2) ?></td>
        <td style="<?= $tdc ?>"><?= htmlspecialchars($row['vol_entregado'] ?? '') ?></td>
        <?php foreach ($meses as $mes): ?>
            <td class="num" style="<?= $tdc ?>"><?= (float)$row[$mes] > 0 ? number_format((float)$row[$mes], 2) : '' ?></td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>

    <!-- FILA TOTALES HOJA 2 -->
    <tr>
        <?php for ($i = 0; $i < 11; $i++): ?><td style="<?= $tot ?>">&nbsp;</td><?php endfor; ?>
        <td class="num" style="<?= $tot ?>"><?= number_format($totAreaTotal, 2) ?></td>
        <td class="num" style="<?= $tot ?>"><?= number_format($totAreaCacao, 2) ?></td>
        <td style="<?= $tot ?>">&nbsp;</td>
        <td style="<?= $tot ?>">&nbsp;</td>
        <td class="num" style="<?= $tot ?>"><?= number_format($totVol, 2) ?></td>
        <td style="<?= $tot ?>">&nbsp;</td>
        <?php foreach ($totMeses as $tm): ?>
            <td class="num" style="<?= $tot ?>"><?= number_format($tm, 2) ?></td>
        <?php endforeach; ?>
    </tr>

    <!-- CÁLCULOS FAIRTRADE HOJA 2 -->
    <tr><td colspan="29" style="border:0;height:10px;">&nbsp;</td></tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;font-weight:bold;border:0;">TONELADA</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;border:0;">DIVIDIDO A <?= $DIVISOR ?> =</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format($toneladas, 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;font-weight:bold;border:0;">PRIMA</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;border:0;">MULTIPLICADO * <?= $PRIMA ?> =</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format($toneladas * $PRIMA, 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;border:0;">MULTIPLICADO * <?= $BASE ?> =</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:10pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format($toneladas * $BASE, 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="13" style="border:0;">&nbsp;</td>
        <td colspan="2" style="border:0;">&nbsp;</td>
        <td colspan="2" style="font-family:Arial;font-size:9pt;font-weight:bold;color:#1F3A5F;border:0;">BENEFICIO PRIMA</td>
        <td colspan="3" class="num" style="font-family:Arial;font-size:11pt;font-weight:bold;color:#1F3A5F;border:0;"><?= number_format(($toneladas * $PRIMA) + ($toneladas * $BASE), 2) ?></td>
        <td colspan="9" style="border:0;">&nbsp;</td>
    </tr>

</table>

<!-- ═══════════════════════════════════════════
     HOJA 3 — PLAN DE ABASTECIMIENTO
═══════════════════════════════════════════ -->
<br clear="all" style="mso-break-type:section-break;page-break-before:always">

<table border="0" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;"
       x:Name="PLAN DE ABASTECIMIENTO">

    <tr style="height:44px;">
        <td style="<?= $hdr3 ?> width:30px;">#</td>
        <td style="<?= $hdr3 ?> width:320px;">ASOCIACIÓN</td>
        <td style="<?= $hdr3 ?> width:110px;">FECHA DE CONTRATO</td>
        <td style="<?= $hdr3 ?> width:100px;">ESTIMADO ANUAL</td>
        <td style="<?= $hdr3 ?> width:75px;">ENERO</td>
        <td style="<?= $hdr3 ?> width:75px;">FEBRERO</td>
        <td style="<?= $hdr3 ?> width:75px;">MARZO</td>
        <td style="<?= $hdr3 ?> width:75px;">ABRIL</td>
        <td style="<?= $hdr3 ?> width:75px;">MAYO</td>
        <td style="<?= $hdr3 ?> width:75px;">JUNIO</td>
        <td style="<?= $hdr3 ?> width:75px;">JULIO</td>
        <td style="<?= $hdr3 ?> width:75px;">AGOSTO</td>
        <td style="<?= $hdr3 ?> width:75px;">SEPTIEMBRE</td>
        <td style="<?= $hdr3 ?> width:75px;">OCTUBRE</td>
        <td style="<?= $hdr3 ?> width:75px;">NOVIEMBRE</td>
        <td style="<?= $hdr3 ?> width:75px;">DICIEMBRE</td>
        <td style="<?= $hdr3 ?> width:110px;">ENTREGA TOTAL QQ</td>
        <td style="<?= $hdr3 ?> width:110px;">ENTREGA TOTAL TM</td>
    </tr>

    <tr style="height:18px;">
        <td style="<?= $td3 ?>">6</td>
        <td style="<?= $td3 ?> text-align:left;">ASOCIACIÓN DE TRABAJADORES AGRÍCOLAS AUTONÓMOS SANTA LUCIA COROTÚ</td>
        <td style="<?= $td3 ?>">01/01/<?= $anio ?></td>
        <td class="num" style="<?= $td3 ?>"><?= number_format($totVol, 2) ?></td>
        <?php foreach ($totMeses as $tm): ?>
            <td class="num" style="<?= $td3 ?>"><?= number_format($tm, 2) ?></td>
        <?php endforeach; ?>
        <td class="num" style="<?= $td3 ?>"><?= number_format($totSumMeses, 2) ?></td>
        <td class="num" style="<?= $td3 ?>"><?= number_format($totSumMeses / $DIVISOR, 2) ?></td>
    </tr>

</table>

</body>
</html>
<?php
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>