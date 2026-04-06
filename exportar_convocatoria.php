<?php
// ============================================================
// exportar_convocatoria.php – Vista imprimible de la convocatoria
// Se puede imprimir como PDF desde el navegador
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id = intval($_GET['id']??0);
if (!$id) { header('Location: convocatorias.php'); exit; }

$st = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
$st->execute([$id]); $c = $st->fetch(PDO::FETCH_ASSOC);
if (!$c) { header('Location: convocatorias.php'); exit; }

$stP = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
$stP->execute([$id]); $puntos = $stP->fetchAll(PDO::FETCH_ASSOC);

$stF = $pdo->prepare("SELECT * FROM convocatoria_firmas WHERE convocatoria_id=? ORDER BY orden");
$stF->execute([$id]); $firmas = $stF->fetchAll(PDO::FETCH_ASSOC);

$tipos = ['ordinaria'=>'ORDINARIA','extraordinaria'=>'EXTRAORDINARIA','urgente'=>'URGENTE'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Convocatoria – <?= htmlspecialchars($c['titulo']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'EB Garamond',Georgia,serif;background:#fff;color:#1a1a1a;font-size:13pt;line-height:1.6;}
.page{max-width:700px;margin:0 auto;padding:40px 50px;}

/* botones no imprimir */
.no-print{text-align:right;margin-bottom:24px;font-family:'Plus Jakarta Sans',sans-serif;}
.no-print button{background:#1f3a5f;color:#fff;border:none;padding:9px 20px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;margin-left:8px;}
.no-print button.sec{background:#fff;color:#1f3a5f;border:1.5px solid #1f3a5f;}

/* Membrete */
.membrete{text-align:center;border-bottom:3px double #1f3a5f;padding-bottom:18px;margin-bottom:24px;}
.membrete img{height:70px;margin-bottom:8px;}
.membrete h1{font-size:15pt;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#1f3a5f;}
.membrete .sub{font-size:10pt;color:#555;margin-top:3px;}
.membrete .tipo-badge{display:inline-block;background:#1f3a5f;color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:9pt;font-weight:700;padding:3px 16px;border-radius:20px;margin-top:8px;letter-spacing:.5px;}

/* Título convocatoria */
.conv-titulo{text-align:center;margin:22px 0 18px;}
.conv-titulo h2{font-size:16pt;font-weight:700;color:#1f3a5f;line-height:1.3;}
.conv-titulo .sub{font-size:11pt;font-style:italic;color:#555;margin-top:4px;}

/* Párrafo introductorio */
.intro{text-align:justify;margin-bottom:18px;font-size:12.5pt;}

/* Info table */
.info-table{width:100%;border-collapse:collapse;margin-bottom:18px;font-size:12pt;}
.info-table td{padding:6px 10px;border:1px solid #d0d0d0;}
.info-table td:first-child{font-weight:700;width:38%;background:#f5f5f5;color:#1f3a5f;}

/* Orden del día */
.oda-title{font-size:13pt;font-weight:700;color:#1f3a5f;border-bottom:1.5px solid #1f3a5f;padding-bottom:4px;margin:18px 0 10px;}
.oda-list{padding-left:20px;}
.oda-list li{margin-bottom:8px;font-size:12.5pt;}

/* Firmas */
.firmas-wrap{display:flex;gap:30px;margin-top:50px;flex-wrap:wrap;}
.firma-item{flex:1;min-width:130px;text-align:center;}
.firma-line{border-top:1.5px solid #333;padding-top:6px;margin-top:50px;font-size:10.5pt;}
.firma-cargo{font-weight:700;font-size:10pt;color:#1f3a5f;text-transform:uppercase;letter-spacing:.3px;}

/* Pie */
.pie{text-align:center;margin-top:40px;font-size:9pt;color:#aaa;border-top:1px solid #e0e0e0;padding-top:10px;font-family:'Plus Jakarta Sans',sans-serif;}

@media print{
    body{font-size:11pt;}
    .no-print{display:none!important;}
    .page{padding:20px 30px;}
}
</style>
</head>
<body>
<div class="page">

<div class="no-print">
    <button class="sec" onclick="window.close()">✕ Cerrar</button>
    <button onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</div>

<!-- Membrete -->
<div class="membrete">
    <?php if (file_exists(__DIR__.'/img/logo.png')): ?>
    <img src="img/logo.png" alt="Logo Asociación">
    <?php endif; ?>
    <h1>Asociación de Productores Santa Lucía</h1>
    <div class="sub">Parroquia Santa Lucía – Provincia del Guayas – Ecuador</div>
    <div class="tipo-badge">CONVOCATORIA <?= $tipos[$c['tipo_reunion']]??'ORDINARIA' ?></div>
</div>

<!-- Título -->
<div class="conv-titulo">
    <h2><?= htmlspecialchars($c['titulo']) ?></h2>
    <div class="sub"><?= $c['tipo_asistentes']==='general'?'Asamblea General de Socios':'Reunión de Directivos' ?></div>
</div>

<!-- Párrafo introductorio -->
<p class="intro">
    El Presidente de la Asociación de Productores Santa Lucía, en uso de las atribuciones que le confieren los Estatutos de la organización, convoca a los
    <strong><?= $c['tipo_asistentes']==='general'?'señores socios activos':'señores miembros de la Directiva' ?></strong>
    a la presente <?= strtolower($tipos[$c['tipo_reunion']]??'ordinaria') ?> de conformidad con el orden del día señalado a continuación:
</p>

<!-- Datos de la reunión -->
<table class="info-table">
    <tr><td>Fecha</td><td><?= date('d \d\e F \d\e\l Y', strtotime($c['fecha_reunion'])) ?></td></tr>
    <tr><td>Hora</td><td><?= date('H:i', strtotime($c['hora_reunion'])) ?> horas</td></tr>
    <tr><td>Lugar</td><td><?= htmlspecialchars($c['lugar']) ?></td></tr>
    <tr><td>Tipo</td><td><?= ucfirst($c['tipo_reunion']) ?> · <?= $c['tipo_asistentes']==='general'?'General':'Solo Directivos' ?></td></tr>
    <tr><td>Emitida por</td><td><?= htmlspecialchars($c['nombre_creador']??'Secretaría') ?></td></tr>
    <tr><td>Fecha de emisión</td><td><?= date('d/m/Y', strtotime($c['fecha_creacion'])) ?></td></tr>
</table>

<!-- Orden del día -->
<div class="oda-title"><i>Orden del Día</i></div>
<?php if ($puntos): ?>
<ol class="oda-list">
    <?php foreach ($puntos as $p): ?>
    <li><?= htmlspecialchars($p['descripcion']) ?></li>
    <?php endforeach; ?>
</ol>
<?php else: ?>
<p style="font-style:italic;color:#888;">Sin puntos registrados.</p>
<?php endif; ?>

<p style="margin-top:22px;font-size:12pt;font-style:italic;">
    Se ruega puntual asistencia. La inasistencia sin justificación debida estará sujeta a las disposiciones estatutarias vigentes.
</p>

<!-- Firmas -->
<?php if ($firmas): ?>
<div class="firmas-wrap">
    <?php foreach ($firmas as $f): ?>
    <div class="firma-item">
        <div class="firma-line"></div>
        <div><?= htmlspecialchars($f['nombre']) ?></div>
        <div class="firma-cargo"><?= htmlspecialchars($f['cargo']) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="firmas-wrap">
    <div class="firma-item"><div class="firma-line"></div><div>Presidente</div><div class="firma-cargo">Asociación Santa Lucía</div></div>
    <div class="firma-item"><div class="firma-line"></div><div>Secretario/a</div><div class="firma-cargo">Asociación Santa Lucía</div></div>
</div>
<?php endif; ?>

<div class="pie">
    Documento generado por el Sistema de Gestión de la Asociación Santa Lucía · <?= date('d/m/Y H:i') ?>
</div>
</div>
</body>
</html>
