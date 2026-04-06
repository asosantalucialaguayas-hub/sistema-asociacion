<?php
// ============================================================
// reporte_asistencia.php - Reporte imprimible
// ============================================================
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login.php'); exit; }
require_once '../config/db.php';

$conv_id = intval($_GET['id'] ?? 0);
if (!$conv_id) { header('Location: asistencia.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
$stmt->execute([$conv_id]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conv) { header('Location: asistencia.php'); exit; }

$puntos = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id = ? ORDER BY numero");
$puntos->execute([$conv_id]);
$puntos = $puntos->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("
    SELECT a.*, s.cedula, s.nombres, s.apellidos, a.metodo, a.hora_registro
    FROM asistencia a
    JOIN socios s ON s.id = a.socio_id
    WHERE a.convocatoria_id = ?
    ORDER BY s.apellidos, s.nombres
");
$stmt2->execute([$conv_id]);
$asistieron = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Socios ausentes
$ausentes_stmt = $pdo->prepare("
    SELECT s.cedula, s.nombres, s.apellidos
    FROM socios s
    WHERE s.estado = 'activo'
      AND s.id NOT IN (SELECT socio_id FROM asistencia WHERE convocatoria_id = ?)
    ORDER BY s.apellidos, s.nombres
");
$ausentes_stmt->execute([$conv_id]);
$ausentes = $ausentes_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_socios  = count($asistieron) + count($ausentes);
$total_presentes = count($asistieron);
$porcentaje = $total_socios > 0 ? round(($total_presentes / $total_socios) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Asistencia - <?= htmlspecialchars($conv['titulo']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI', Arial, sans-serif; font-size:12px; color:#222; padding:20px; }
.header { text-align:center; border-bottom:3px solid #2c3e7a; padding-bottom:15px; margin-bottom:20px; }
.header h1 { font-size:18px; color:#2c3e7a; }
.header h2 { font-size:14px; color:#444; margin-top:4px; }
.header p  { color:#666; margin-top:3px; font-size:11px; }
.kpis { display:flex; gap:16px; margin-bottom:18px; }
.kpi  { flex:1; border:1.5px solid #ddd; border-radius:8px; padding:10px; text-align:center; }
.kpi .num { font-size:22px; font-weight:700; color:#2c3e7a; }
.kpi .lbl { font-size:10px; color:#666; }
.seccion-titulo { background:#2c3e7a; color:#fff; padding:6px 12px; font-weight:700; margin-bottom:0; font-size:11px; margin-top:16px; }
table { width:100%; border-collapse:collapse; }
th { background:#f0f4ff; color:#2c3e7a; padding:6px 10px; text-align:left; font-size:11px; border:1px solid #ddd; }
td { padding:5px 10px; border:1px solid #eee; font-size:11px; }
tr:nth-child(even) td { background:#fafafa; }
.metodo { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600; }
.m-biometrico { background:#dff0d8; color:#27ae60; }
.m-manual     { background:#d9edf7; color:#2c3e7a; }
.m-qr         { background:#fcf8e3; color:#e67e22; }
.puntos li { margin:4px 0; font-size:11px; }
.firma-area { display:flex; gap:40px; margin-top:30px; }
.firma { flex:1; text-align:center; border-top:1.5px solid #333; padding-top:6px; font-size:11px; color:#555; }
.pie { text-align:center; margin-top:20px; font-size:10px; color:#aaa; border-top:1px solid #eee; padding-top:10px; }
@media print {
    body { padding:10px; }
    .no-print { display:none !important; }
    .page-break { page-break-before: always; }
}
</style>
</head>
<body>

<!-- Botón imprimir -->
<div class="no-print" style="text-align:right; margin-bottom:12px;">
    <button onclick="window.print()" style="background:#2c3e7a;color:#fff;border:none;padding:8px 20px;border-radius:20px;cursor:pointer;font-size:13px;">
        🖨️ Imprimir / Guardar PDF
    </button>
    <button onclick="window.close()" style="background:#888;color:#fff;border:none;padding:8px 16px;border-radius:20px;cursor:pointer;font-size:13px;margin-left:8px;">
        ✕ Cerrar
    </button>
</div>

<!-- Encabezado -->
<div class="header">
    <h1>ASOCIACIÓN SANTA LUCÍA - SISTEMA DE GESTIÓN</h1>
    <h2><?= htmlspecialchars($conv['titulo']) ?></h2>
    <p>Fecha: <?= date('d/m/Y', strtotime($conv['fecha'])) ?> &nbsp;|&nbsp; 
       Hora: <?= substr($conv['hora'],0,5) ?> &nbsp;|&nbsp;
       Lugar: <?= htmlspecialchars($conv['lugar']) ?> &nbsp;|&nbsp;
       Tipo: <?= ucfirst($conv['tipo']) ?>
    </p>
    <p style="margin-top:5px;">Reporte generado: <?= date('d/m/Y H:i:s') ?></p>
</div>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi">
        <div class="num"><?= $total_socios ?></div>
        <div class="lbl">Total Socios</div>
    </div>
    <div class="kpi">
        <div class="num" style="color:#27ae60"><?= $total_presentes ?></div>
        <div class="lbl">Presentes</div>
    </div>
    <div class="kpi">
        <div class="num" style="color:#e74c3c"><?= count($ausentes) ?></div>
        <div class="lbl">Ausentes</div>
    </div>
    <div class="kpi">
        <div class="num"><?= $porcentaje ?>%</div>
        <div class="lbl">Porcentaje Asistencia</div>
    </div>
    <div class="kpi">
        <div class="num" style="font-size:14px;color:<?= $porcentaje >= 50 ? '#27ae60':'#e74c3c' ?>">
            <?= $porcentaje >= 50 ? '✅ QUÓRUM' : '❌ SIN QUÓRUM' ?>
        </div>
        <div class="lbl">Estado (mínimo 50%)</div>
    </div>
</div>

<!-- Orden del día -->
<?php if (!empty($puntos)): ?>
<div class="seccion-titulo">📋 ORDEN DEL DÍA</div>
<div style="padding:10px 14px; border:1px solid #ddd; border-top:none;">
    <ol class="puntos">
        <?php foreach ($puntos as $p): ?>
        <li><?= htmlspecialchars($p['descripcion']) ?></li>
        <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<!-- Lista de presentes -->
<div class="seccion-titulo">✅ SOCIOS PRESENTES (<?= $total_presentes ?>)</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Cédula</th>
            <th>Nombres y Apellidos</th>
            <th>Hora Registro</th>
            <th>Método</th>
            <th>Firma / Huella</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($asistieron as $i => $a): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($a['cedula']) ?></td>
        <td><?= htmlspecialchars($a['apellidos'].' '.$a['nombres']) ?></td>
        <td><?= date('H:i:s', strtotime($a['hora_registro'])) ?></td>
        <td>
            <span class="metodo m-<?= $a['metodo'] ?>">
                <?= $a['metodo'] === 'biometrico' ? '👆 Biométrico' : ($a['metodo'] === 'qr' ? '📷 QR' : '✋ Manual') ?>
            </span>
        </td>
        <td style="min-width:80px;"></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($asistieron)): ?>
    <tr><td colspan="6" style="text-align:center;color:#aaa;">Sin registros</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Lista de ausentes -->
<?php if (!empty($ausentes)): ?>
<div class="page-break"></div>
<div class="seccion-titulo" style="background:#e74c3c;">❌ SOCIOS AUSENTES (<?= count($ausentes) ?>)</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Cédula</th>
            <th>Nombres y Apellidos</th>
            <th>Justificación (llenar manual)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($ausentes as $i => $a): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($a['cedula']) ?></td>
        <td><?= htmlspecialchars($a['apellidos'].' '.$a['nombres']) ?></td>
        <td style="min-width:150px;"></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Firmas -->
<div class="firma-area" style="margin-top:40px;">
    <div class="firma">PRESIDENTE<br>Asociación Santa Lucía</div>
    <div class="firma">SECRETARIO/A<br>Asociación Santa Lucía</div>
    <div class="firma">DIRECTIVO RESPONSABLE</div>
</div>

<div class="pie">
    Sistema de Gestión - Asociación Santa Lucía &nbsp;|&nbsp; 
    Documento generado automáticamente el <?= date('d/m/Y \a \l\a\s H:i') ?>
</div>
</body>
</html>
