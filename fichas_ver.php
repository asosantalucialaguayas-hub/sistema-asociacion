<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";

$id_aplicacion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_aplicacion) { header("Location: fichas_aplicaciones.php"); exit; }

// Cargar aplicación
$st = $pdo->prepare("
    SELECT fa.*, f.nombre AS nombre_ficha,
           s.nombre_completo, s.identificacion, s.telefono, s.direccion,
           u.usuario AS inspector
    FROM ficha_aplicaciones fa
    JOIN fichas f ON f.id_ficha = fa.id_ficha
    JOIN socios s ON s.id_socio = fa.id_socio
    LEFT JOIN usuarios u ON u.id_usuario = fa.id_usuario
    WHERE fa.id_aplicacion = ?
");
$st->execute([$id_aplicacion]);
$ap = $st->fetch(PDO::FETCH_ASSOC);
if (!$ap) { header("Location: fichas_aplicaciones.php"); exit; }

// Cargar secciones y preguntas con respuestas
$stS = $pdo->prepare("SELECT * FROM ficha_secciones WHERE id_ficha=? ORDER BY orden");
$stS->execute([$ap['id_ficha']]);
$secciones = $stS->fetchAll(PDO::FETCH_ASSOC);

foreach ($secciones as &$sec) {
    $stP = $pdo->prepare("
        SELECT p.*, r.respuesta_sino, r.cumplimiento, r.observacion, r.respuesta_texto
        FROM ficha_preguntas p
        LEFT JOIN ficha_respuestas r ON r.id_pregunta = p.id_pregunta AND r.id_aplicacion = ?
        WHERE p.id_seccion = ?
        ORDER BY p.orden
    ");
    $stP->execute([$id_aplicacion, $sec['id_seccion']]);
    $sec['preguntas'] = $stP->fetchAll(PDO::FETCH_ASSOC);
}
unset($sec);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ver Ficha – <?= htmlspecialchars($ap['nombre_completo']) ?></title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--azul:#1f3a5f;--azul2:#2563eb;--gris:#f8fafc;--borde:#e2e8f0;--verde:#16a34a;--rojo:#dc2626;}
body{font-family:'Segoe UI',sans-serif;background:var(--gris);}
.btn-prim{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;}
.btn-prim:hover{transform:translateY(-2px);color:#fff;}
.btn-sec{display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--azul);border:1.5px solid var(--borde);border-radius:10px;padding:9px 16px;font-weight:600;font-size:.85rem;cursor:pointer;text-decoration:none;}
.card{background:#fff;border-radius:14px;border:1.5px solid var(--borde);overflow:hidden;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
.card-head{background:linear-gradient(135deg,#1f3a5f,#2563eb);padding:14px 20px;color:#fff;display:flex;align-items:center;justify-content:space-between;}
.card-head h3{margin:0;font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.card-body{padding:18px 20px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;}
@media(max-width:600px){.info-grid{grid-template-columns:1fr 1fr;}}
.info-item label{font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:3px;}
.info-item span{font-size:.875rem;font-weight:600;color:#1f2937;}
.tbl-ficha{width:100%;border-collapse:collapse;font-size:.82rem;}
.tbl-ficha th{background:#1f3a5f;color:#fff;padding:8px 12px;text-align:left;font-size:.75rem;font-weight:700;}
.tbl-ficha th.center{text-align:center;}
.tbl-ficha td{padding:8px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
.tbl-ficha tr:hover td{background:#f8fafc;}
.sec-header td{background:#e0f2fe;color:#0369a1;font-weight:800;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;padding:8px 12px;}
.cumpl-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:800;}
.cumpl-B{background:#dcfce7;color:#166534;}
.cumpl-R{background:#fef3c7;color:#92400e;}
.cumpl-M{background:#fee2e2;color:#991b1b;}
.sino-si{color:#16a34a;font-weight:700;}
.sino-no{color:#dc2626;font-weight:700;}
.firma-box{background:#f8fafc;border:1.5px solid var(--borde);border-radius:10px;padding:12px;text-align:center;}
.firma-box label{font-size:.75rem;font-weight:700;color:#64748b;display:block;margin-bottom:8px;text-transform:uppercase;}
.firma-box img{max-width:100%;max-height:100px;border-bottom:1.5px solid #374151;padding-bottom:4px;}
.firma-box .firma-nombre{font-size:.75rem;color:#374151;margin-top:4px;}
.firmas-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;}
</style>
</head>
<body>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar">
    <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
</header>
<section class="page">

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:var(--azul);margin:0;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-file-lines" style="color:var(--azul2);"></i>
            Detalle de Ficha Aplicada
        </h1>
        <p style="margin:4px 0 0;font-size:.875rem;color:#64748b;">
            <?= htmlspecialchars($ap['nombre_ficha']) ?> · <?= date('d/m/Y H:i', strtotime($ap['fecha_aplicacion'])) ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="fichas_aplicaciones.php" class="btn-sec">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
        <a href="fichas_pdf.php?id=<?= $id_aplicacion ?>" target="_blank" class="btn-prim" style="background:linear-gradient(135deg,#991b1b,#dc2626);">
            <i class="fa-solid fa-file-pdf"></i> Exportar PDF
        </a>
    </div>
</div>

<!-- Datos del socio -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-user"></i> Datos del Productor</h3></div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item"><label>Apellidos y Nombres</label><span><?= htmlspecialchars($ap['nombre_completo']) ?></span></div>
            <div class="info-item"><label>Cédula</label><span><?= htmlspecialchars($ap['identificacion']) ?></span></div>
            <div class="info-item"><label>Teléfono</label><span><?= htmlspecialchars($ap['telefono'] ?? '—') ?></span></div>
            <div class="info-item"><label>Cantón</label><span><?= htmlspecialchars($ap['canton'] ?? '—') ?></span></div>
            <div class="info-item"><label>Parroquia</label><span><?= htmlspecialchars($ap['parroquia'] ?? '—') ?></span></div>
            <div class="info-item"><label>Sector</label><span><?= htmlspecialchars($ap['sector'] ?? '—') ?></span></div>
            <div class="info-item"><label>Coord. Hogar X</label><span><?= htmlspecialchars($ap['coord_hogar_x'] ?? '—') ?></span></div>
            <div class="info-item"><label>Coord. Hogar Y</label><span><?= htmlspecialchars($ap['coord_hogar_y'] ?? '—') ?></span></div>
            <div class="info-item"><label>Coord. Hogar Z</label><span><?= htmlspecialchars($ap['coord_hogar_z'] ?? '—') ?></span></div>
            <div class="info-item"><label>Coord. Finca X</label><span><?= htmlspecialchars($ap['coord_finca_x'] ?? '—') ?></span></div>
            <div class="info-item"><label>Coord. Finca Y</label><span><?= htmlspecialchars($ap['coord_finca_y'] ?? '—') ?></span></div>
            <div class="info-item"><label>Coord. Finca Z</label><span><?= htmlspecialchars($ap['coord_finca_z'] ?? '—') ?></span></div>
        </div>
    </div>
</div>

<!-- Datos del cultivo -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-seedling"></i> Datos del Cultivo</h3></div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item"><label>Cultivo</label><span><?= htmlspecialchars($ap['cultivo'] ?? '—') ?></span></div>
            <div class="info-item"><label>Variedad</label><span><?= htmlspecialchars($ap['variedad'] ?? '—') ?></span></div>
            <div class="info-item"><label>Edad del Cultivo</label><span><?= htmlspecialchars($ap['edad_cultivo'] ?? '—') ?></span></div>
            <div class="info-item"><label>Hectáreas</label><span><?= htmlspecialchars($ap['hectareas'] ?? '—') ?></span></div>
            <div class="info-item"><label>Riego</label><span><?= $ap['riego'] ? 'Sí' : 'No' ?></span></div>
            <div class="info-item"><label>Fuente de Agua</label><span><?= htmlspecialchars($ap['fuente_agua'] ?? '—') ?></span></div>
            <div class="info-item"><label>Poda</label><span><?= htmlspecialchars($ap['poda_semestre'] ?? '—') ?></span></div>
            <div class="info-item"><label>Inspector</label><span><?= htmlspecialchars($ap['inspector'] ?? '—') ?></span></div>
            <div class="info-item"><label>Fecha</label><span><?= date('d/m/Y H:i', strtotime($ap['fecha_aplicacion'])) ?></span></div>
        </div>
    </div>
</div>

<!-- Secciones y respuestas -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-list-check"></i> Respuestas de la Ficha</h3></div>
    <div style="overflow-x:auto;">
    <table class="tbl-ficha">
        <thead>
            <tr>
                <th style="width:50%;">Descripción de actividades</th>
                <th class="center" style="width:7%;">No</th>
                <th class="center" style="width:7%;">Sí</th>
                <th class="center" style="width:12%;">Cumplimiento</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($secciones as $sec): ?>
        <tr class="sec-header">
            <td colspan="5"><?= htmlspecialchars($sec['titulo']) ?></td>
        </tr>
        <?php foreach ($sec['preguntas'] as $preg): ?>
        <tr>
            <td><?= htmlspecialchars($preg['texto']) ?></td>
            <?php if ($preg['tipo'] === 'cumplimiento' || $preg['tipo'] === 'si_no'): ?>
            <td style="text-align:center;">
                <?= $preg['respuesta_sino'] === '0' ? '<i class="fa-solid fa-xmark" style="color:#dc2626;"></i>' : '' ?>
            </td>
            <td style="text-align:center;">
                <?= $preg['respuesta_sino'] === '1' ? '<i class="fa-solid fa-check" style="color:#16a34a;"></i>' : '' ?>
            </td>
            <td style="text-align:center;">
                <?php if ($preg['cumplimiento']): ?>
                <span class="cumpl-pill cumpl-<?= $preg['cumplimiento'] ?>"><?= $preg['cumplimiento'] ?></span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($preg['observacion'] ?? '') ?></td>
            <?php else: ?>
            <td colspan="3" style="color:#64748b;"><?= htmlspecialchars($preg['respuesta_texto'] ?? '—') ?></td>
            <td></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Firmas -->
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-signature"></i> Firmas</h3></div>
    <div class="card-body">
        <div class="firmas-grid">
            <div class="firma-box">
                <label>Firma Inspector Interno</label>
                <?php if (!empty($ap['firma_inspector'])): ?>
                <img src="<?= $ap['firma_inspector'] ?>" alt="Firma Inspector">
                <?php else: ?>
                <div style="height:60px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.8rem;">Sin firma</div>
                <?php endif; ?>
                <div class="firma-nombre"><?= htmlspecialchars($ap['inspector'] ?? '—') ?></div>
            </div>
            <div class="firma-box">
                <label>Firma Productor</label>
                <?php if (!empty($ap['firma_productor'])): ?>
                <img src="<?= $ap['firma_productor'] ?>" alt="Firma Productor">
                <?php else: ?>
                <div style="height:60px;display:flex;align-items:center;justify-content:color:#94a3b8;font-size:.8rem;">Sin firma</div>
                <?php endif; ?>
                <div class="firma-nombre"><?= htmlspecialchars($ap['nombre_completo']) ?></div>
            </div>
        </div>
    </div>
</div>

</section>
</main>
</div>
</body>
</html>
