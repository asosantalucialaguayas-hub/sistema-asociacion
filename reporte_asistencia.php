<?php
// ============================================================
// reporte_asistencia.php – Reporte imprimible de asistencia
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id = intval($_GET['id']??0);
if (!$id) { header('Location: asistencia.php'); exit; }

$st = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
$st->execute([$id]); $c = $st->fetch(PDO::FETCH_ASSOC);
if (!$c) { header('Location: asistencia.php'); exit; }

$stP = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
$stP->execute([$id]); $puntos = $stP->fetchAll(PDO::FETCH_ASSOC);

$stA = $pdo->prepare("
    SELECT a.*, s.cedula, s.nombre_completo, a.metodo, a.hora_registro
    FROM conv_asistencia a JOIN socios s ON s.id_socio=a.id_socio
    WHERE a.convocatoria_id=? ORDER BY s.nombre_completo");
$stA->execute([$id]); $asistentes = $stA->fetchAll(PDO::FETCH_ASSOC);

$stAus = $pdo->prepare("
    SELECT s.cedula, s.nombre_completo FROM socios s
    WHERE s.estado='activo'
      AND s.id_socio NOT IN (SELECT id_socio FROM conv_asistencia WHERE convocatoria_id=?)
    ORDER BY s.nombre_completo");
$stAus->execute([$id]); $ausentes = $stAus->fetchAll(PDO::FETCH_ASSOC);

$total_socios  = count($asistentes)+count($ausentes);
$total_pres    = count($asistentes);
$pct           = $total_socios>0?round(($total_pres/$total_socios)*100,1):0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Asistencia</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:11px;color:#111;padding:16px;}
.no-print{text-align:right;margin-bottom:14px;}
.no-print button{background:#1f3a5f;color:#fff;border:none;padding:7px 16px;border-radius:7px;cursor:pointer;font-size:12px;font-weight:700;margin-left:6px;}
.no-print button.sec{background:#fff;color:#1f3a5f;border:1.5px solid #1f3a5f;}
.hdr{text-align:center;border-bottom:3px solid #1f3a5f;padding-bottom:12px;margin-bottom:14px;}
.hdr h1{font-size:15px;color:#1f3a5f;font-weight:800;}
.hdr p{font-size:10px;color:#555;margin-top:2px;}
.kpi-row{display:flex;gap:10px;margin-bottom:14px;}
.kpi{flex:1;border:1.5px solid #ddd;border-radius:7px;padding:8px;text-align:center;}
.kpi .n{font-size:20px;font-weight:800;color:#1f3a5f;}
.kpi .l{font-size:9px;color:#666;}
.sec-title{background:#1f3a5f;color:#fff;padding:5px 10px;font-weight:700;font-size:10px;margin-top:14px;}
table{width:100%;border-collapse:collapse;}
th{background:#e8edf5;color:#1f3a5f;padding:6px 8px;text-align:left;font-size:10px;border:1px solid #ddd;}
td{padding:5px 8px;border:1px solid #eee;font-size:10px;}
tr:nth-child(even) td{background:#f9f9f9;}
.m-bio{background:#d1fae5;color:#065f46;padding:1px 7px;border-radius:10px;font-size:9px;font-weight:700;}
.m-man{background:#dbeafe;color:#1e40af;padding:1px 7px;border-radius:10px;font-size:9px;font-weight:700;}
.m-qr {background:#fef3c7;color:#92400e;padding:1px 7px;border-radius:10px;font-size:9px;font-weight:700;}
.firmas{display:flex;gap:30px;margin-top:30px;}
.firma{flex:1;text-align:center;border-top:1px solid #333;padding-top:5px;font-size:10px;}
.firma .cargo{font-weight:700;font-size:9px;color:#1f3a5f;}
.pie{text-align:center;margin-top:18px;font-size:9px;color:#aaa;border-top:1px solid #eee;padding-top:8px;}
@media print{.no-print{display:none!important;} body{padding:8px;}}
</style>
</head>
<body>
<div class="no-print">
    <button class="sec" onclick="window.close()">✕ Cerrar</button>
    <button onclick="window.print()">🖨️ Imprimir / PDF</button>
</div>

<div class="hdr">
    <h1>ASOCIACIÓN SANTA LUCÍA – REPORTE DE ASISTENCIA</h1>
    <p><?= htmlspecialchars($c['titulo']) ?> · <?= date('d/m/Y',strtotime($c['fecha_reunion'])) ?> <?= substr($c['hora_reunion'],0,5) ?> · <?= htmlspecialchars($c['lugar']) ?></p>
    <p>Generado: <?= date('d/m/Y H:i') ?></p>
</div>

<div class="kpi-row">
    <div class="kpi"><div class="n"><?= $total_socios ?></div><div class="l">Total Socios</div></div>
    <div class="kpi"><div class="n" style="color:#16a34a"><?= $total_pres ?></div><div class="l">Presentes</div></div>
    <div class="kpi"><div class="n" style="color:#dc2626"><?= count($ausentes) ?></div><div class="l">Ausentes</div></div>
    <div class="kpi"><div class="n"><?= $pct ?>%</div><div class="l">Asistencia</div></div>
    <div class="kpi" style="background:<?= $pct>=50?'#dcfce7':'#fee2e2' ?>;border-color:<?= $pct>=50?'#86efac':'#fca5a5' ?>;">
        <div class="n" style="font-size:14px;color:<?= $pct>=50?'#166534':'#b91c1c' ?>"><?= $pct>=50?'✅ QUÓRUM':'❌ SIN QUÓRUM' ?></div>
        <div class="l">Estado (min. 50%)</div>
    </div>
</div>

<?php if ($puntos): ?>
<div class="sec-title">📋 ORDEN DEL DÍA</div>
<div style="padding:8px 12px;border:1px solid #ddd;border-top:none;">
    <ol style="padding-left:14px;line-height:1.7;">
        <?php foreach($puntos as $p): ?><li><?= htmlspecialchars($p['descripcion']) ?></li><?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<div class="sec-title">✅ SOCIOS PRESENTES (<?= $total_pres ?>)</div>
<table>
    <thead><tr><th>#</th><th>Cédula</th><th>Nombres y Apellidos</th><th>Hora</th><th>Método</th><th>Firma / Huella</th></tr></thead>
    <tbody>
    <?php foreach($asistentes as $i=>$a): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($a['cedula']) ?></td>
        <td><?= htmlspecialchars($a['nombre_completo']) ?></td>
        <td><?= date('H:i:s',strtotime($a['hora_registro'])) ?></td>
        <td><span class="m-<?= $a['metodo']==='biometrico'?'bio':($a['metodo']==='qr'?'qr':'man') ?>"><?= ucfirst($a['metodo']) ?></span></td>
        <td style="min-width:70px;"></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($asistentes)): ?><tr><td colspan="6" style="text-align:center;color:#aaa;">Sin asistentes</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($ausentes): ?>
<div class="sec-title" style="background:#dc2626;">❌ SOCIOS AUSENTES (<?= count($ausentes) ?>)</div>
<table>
    <thead><tr><th>#</th><th>Cédula</th><th>Nombres y Apellidos</th><th>Justificación</th></tr></thead>
    <tbody>
    <?php foreach($ausentes as $i=>$a): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($a['cedula']) ?></td>
        <td><?= htmlspecialchars($a['nombre_completo']) ?></td>
        <td style="min-width:120px;"></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="firmas">
    <div class="firma">__________________________________<div class="cargo">PRESIDENTE</div><div>Asociación Santa Lucía</div></div>
    <div class="firma">__________________________________<div class="cargo">SECRETARIO/A</div><div>Asociación Santa Lucía</div></div>
    <div class="firma">__________________________________<div class="cargo">RESPONSABLE</div><div>&nbsp;</div></div>
</div>
<div class="pie">Sistema de Gestión · Asociación Santa Lucía · <?= date('d/m/Y H:i') ?></div>
</body>
</html>