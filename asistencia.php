<?php
// ============================================================
// asistencia.php – Adaptado a la BD real
// socios: id_socio, identificacion, nombre_completo, estado
// convocatorias: id, titulo, fecha, hora, lugar, tipo, estado
// periodos: periodo_comercializacion
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }

require __DIR__ . "/layout/bootstrap.php";

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$rol        = $_SESSION['rol'] ?? $_SESSION['tipo_usuario'] ?? 'viewer';
$es_editor  = in_array($rol, ['admin','secretario','presidente','superadmin']) || $id_usuario === 1;

$id_periodo = intval($periodoSeleccionado['id_periodo'] ?? 0);
$conv_id    = intval($_GET['conv_id'] ?? 0);
$convocatoria = null;

// ── Buscar convocatoria ──────────────────────────────────────
try {
    if ($conv_id) {
        $st = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
        $st->execute([$conv_id]);
        $convocatoria = $st->fetch(PDO::FETCH_ASSOC);
    } elseif ($id_periodo) {
        // Activa primero
        $st = $pdo->prepare("SELECT * FROM convocatorias WHERE id_periodo=? AND estado='activa' ORDER BY fecha DESC, hora DESC LIMIT 1");
        $st->execute([$id_periodo]);
        $convocatoria = $st->fetch(PDO::FETCH_ASSOC);
        if (!$convocatoria) {
            // Cualquier otra que no sea cancelada
            $st2 = $pdo->prepare("SELECT * FROM convocatorias WHERE id_periodo=? AND estado NOT IN('cancelada','borrador') ORDER BY fecha DESC LIMIT 1");
            $st2->execute([$id_periodo]);
            $convocatoria = $st2->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch(PDOException $e) { $convocatoria = null; $err_conv = $e->getMessage(); }

// ── Lista para selector ──────────────────────────────────────
$lista_conv = [];
if ($id_periodo) {
    try {
        $stL = $pdo->prepare("SELECT id,titulo,fecha,estado FROM convocatorias WHERE id_periodo=? ORDER BY fecha DESC");
        $stL->execute([$id_periodo]);
        $lista_conv = $stL->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $lista_conv=[]; }
}

// ── Datos asistencia ─────────────────────────────────────────
$asistentes  = []; $total_socios = 0; $presentes = 0;
$porcentaje  = 0;  $faltantes = 0;    $puntos = [];

if ($convocatoria) {
    $cid = $convocatoria['id'];

    try {
        $stP = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
        $stP->execute([$cid]); $puntos = $stP->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $puntos=[]; }

    try {
        // Usa identificacion (no cedula) y nombre_completo
        $stA = $pdo->prepare("
            SELECT a.id, a.metodo, a.hora_registro,
                   s.identificacion AS cedula,
                   s.nombre_completo
            FROM conv_asistencia a
            JOIN socios s ON s.id_socio = a.id_socio
            WHERE a.convocatoria_id = ?
            ORDER BY a.hora_registro DESC
        ");
        $stA->execute([$cid]); $asistentes = $stA->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $asistentes=[]; $err_asist=$e->getMessage(); }

    try {
        $total_socios = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
    } catch(Exception $e) { $total_socios=0; }

    $presentes  = count($asistentes);
    $porcentaje = $total_socios>0 ? round(($presentes/$total_socios)*100,1) : 0;
    $faltantes  = max(0,$total_socios-$presentes);

    // Bloqueo acta 48h
    if (($convocatoria['estado']??'')==='cerrada'
        && !empty($convocatoria['fecha_cierre_real'])
        && empty($convocatoria['acta_pdf_path'])) {
        $horas = (time()-strtotime($convocatoria['fecha_cierre_real']))/3600;
        if ($horas>48 && empty($convocatoria['acta_bloqueada'])) {
            try { $pdo->prepare("UPDATE convocatorias SET acta_bloqueada=1 WHERE id=?")->execute([$cid]); } catch(Exception $e){}
            $convocatoria['acta_bloqueada']=1;
        }
    }
}

// ── Subida de acta ───────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='subir_acta' && $es_editor) {
    $cid_p = intval($_POST['conv_id']??0);
    try {
        $stCk=$pdo->prepare("SELECT acta_bloqueada FROM convocatorias WHERE id=?");
        $stCk->execute([$cid_p]); $ck=$stCk->fetch();
        if ($ck && $ck['acta_bloqueada']) {
            $_SESSION['flash']=['tipo'=>'error','msg'=>'El plazo de 48h venció.'];
        } elseif (isset($_FILES['acta_pdf']) && $_FILES['acta_pdf']['error']===0) {
            $ext=strtolower(pathinfo($_FILES['acta_pdf']['name'],PATHINFO_EXTENSION));
            if ($ext!=='pdf') { $_SESSION['flash']=['tipo'=>'error','msg'=>'Solo archivos PDF.']; }
            else {
                $dir=__DIR__.'/uploads/actas/';
                if (!is_dir($dir)) mkdir($dir,0755,true);
                $nf='acta_'.$cid_p.'_'.date('Ymd_His').'.pdf';
                if (move_uploaded_file($_FILES['acta_pdf']['tmp_name'],$dir.$nf)) {
                    $pdo->prepare("UPDATE convocatorias SET acta_pdf_path=?,acta_subida_en=NOW() WHERE id=?")->execute(['uploads/actas/'.$nf,$cid_p]);
                    $_SESSION['flash']=['tipo'=>'success','msg'=>'✅ Acta subida correctamente.'];
                } else { $_SESSION['flash']=['tipo'=>'error','msg'=>'Error al guardar. Verifica permisos de /uploads/actas/']; }
            }
        } else { $_SESSION['flash']=['tipo'=>'error','msg'=>'No se recibió archivo.']; }
    } catch(Exception $e) { $_SESSION['flash']=['tipo'=>'error','msg'=>$e->getMessage()]; }
    header("Location: asistencia.php?conv_id=$cid_p"); exit;
}

$horas_para_acta=null;
if ($convocatoria && ($convocatoria['estado']??'')==='cerrada'
    && !empty($convocatoria['fecha_cierre_real']) && empty($convocatoria['acta_pdf_path'])) {
    $horas_para_acta = max(0,round(48-((time()-strtotime($convocatoria['fecha_cierre_real']))/3600),1));
}

$col_bg=['borrador'=>'#f1f5f9','programada'=>'#e0f2fe','publicada'=>'#dbeafe','activa'=>'#dcfce7','cerrada'=>'#fee2e2','cancelada'=>'#fef3c7'];
$col_tx=['borrador'=>'#475569','programada'=>'#0369a1','publicada'=>'#1d4ed8','activa'=>'#15803d','cerrada'=>'#b91c1c','cancelada'=>'#92400e'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Asistencia – Asociación Santa Lucía</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--azul:#1f3a5f;--azul2:#2563eb;--verde:#16a34a;--rojo:#dc2626;--gris:#f8fafc;--borde:#e2e8f0;--sombra:0 2px 12px rgba(0,0,0,.08);}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gris);}
.btn-prim{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 18px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;box-shadow:0 4px 14px rgba(37,99,235,.25);}
.btn-prim:hover{transform:translateY(-2px);color:#fff;}
.btn-sec{display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--azul);border:1.5px solid var(--borde);border-radius:10px;padding:9px 16px;font-weight:600;font-size:.85rem;cursor:pointer;text-decoration:none;transition:.2s;}
.btn-sec:hover{background:#f1f5f9;}
.flash{padding:12px 18px;border-radius:10px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:.875rem;}
.flash.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.conv-selector{background:#fff;border-radius:14px;border:1.5px solid var(--borde);padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:var(--sombra);}
.conv-bar{background:linear-gradient(135deg,#1f3a5f,#2563eb);border-radius:16px;padding:20px 26px;color:#fff;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:18px;}
.conv-bar h2{margin:0 0 6px;font-size:1.05rem;font-weight:800;}
.conv-bar .meta{display:flex;flex-wrap:wrap;gap:12px;font-size:.8rem;opacity:.9;}
.conv-bar .meta span{display:flex;align-items:center;gap:5px;}
.epill{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;}
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:600px){.kpi-row{grid-template-columns:1fr 1fr;}}
.kpi-box{background:#fff;border-radius:14px;padding:16px;text-align:center;border:1.5px solid var(--borde);box-shadow:var(--sombra);}
.kpi-box .num{font-size:1.9rem;font-weight:800;line-height:1;}
.kpi-box .lbl{font-size:.75rem;color:#64748b;margin-top:4px;font-weight:600;}
.prog-card{background:#fff;border-radius:14px;padding:18px;border:1.5px solid var(--borde);box-shadow:var(--sombra);margin-bottom:20px;}
.prog-wrap{background:#e2e8f0;border-radius:50px;height:26px;overflow:hidden;margin-top:10px;}
.prog-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,#16a34a,#22c55e);transition:width 1.2s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;min-width:44px;}
.reg-card{background:#fff;border-radius:14px;padding:18px;border:1.5px solid var(--borde);box-shadow:var(--sombra);margin-bottom:20px;}
.srch-wrap{position:relative;}
.srch-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;}
.srch-wrap input{width:100%;border:1.5px solid var(--borde);border-radius:10px;padding:10px 12px 10px 38px;font-size:.9rem;outline:none;font-family:inherit;transition:.2s;}
.srch-wrap input:focus{border-color:var(--azul2);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.res-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border:1.5px solid var(--borde);border-radius:10px;margin-top:8px;cursor:pointer;transition:.2s;background:#fff;}
.res-item:hover:not(.ya){background:#eff6ff;border-color:var(--azul2);}
.res-item.ya{background:#f0fdf4;border-color:#bbf7d0;cursor:default;}
.av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0;}
.tbl-card{background:#fff;border-radius:14px;border:1.5px solid var(--borde);box-shadow:var(--sombra);overflow:hidden;margin-bottom:20px;}
.tbl-card table{width:100%;border-collapse:collapse;}
.tbl-card thead{background:var(--azul);color:#fff;}
.tbl-card th{padding:11px 14px;font-size:.78rem;font-weight:700;text-align:left;}
.tbl-card td{padding:10px 14px;font-size:.82rem;border-bottom:1px solid #f1f5f9;}
.tbl-card tr:hover td{background:#f8fafc;}
.mpill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.m-biometrico{background:#d1fae5;color:#065f46;}
.m-manual{background:#dbeafe;color:#1e40af;}
.m-qr{background:#fef3c7;color:#92400e;}
.btn-del{background:#fee2e2;color:#dc2626;border:1.5px solid #fecaca;border-radius:7px;padding:4px 8px;font-size:.73rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;}
.btn-del:hover{background:#fecaca;}
.acta-card{border-radius:14px;padding:18px;margin-bottom:20px;border:2px solid;}
.acta-ok{background:#f0fdf4;border-color:#bbf7d0;}
.acta-pend{background:#fffbeb;border-color:#fde68a;}
.acta-block{background:#fef2f2;border-color:#fecaca;}
.moverlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:10000;overflow-y:auto;padding:20px;align-items:center;justify-content:center;}
.moverlay.show{display:flex;}
.mbox{background:#fff;border-radius:20px;width:100%;max-width:480px;box-shadow:0 24px 60px rgba(0,0,0,.22);margin:auto;}
.mhead{background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;padding:18px 24px;border-radius:20px 20px 0 0;display:flex;justify-content:space-between;align-items:center;}
.mhead h2{margin:0;font-size:1rem;font-weight:800;}
.mcls{background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:8px;width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.mbody{padding:24px;}
.mfoot{padding:14px 24px;border-top:1px solid var(--borde);display:flex;justify-content:flex-end;gap:10px;background:#f8fafc;border-radius:0 0 20px 20px;}
.donut-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;}
.donut-center .dp{font-size:1.9rem;font-weight:800;color:var(--azul);}
.donut-center .ds{font-size:.68rem;color:#94a3b8;font-weight:700;text-transform:uppercase;}
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

<?php if ($flash): ?>
<div class="flash <?= $flash['tipo'] ?>">
    <i class="fa-solid <?= $flash['tipo']==='success'?'fa-circle-check':'fa-triangle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if (isset($err_asist)): ?>
<div class="flash error"><i class="fa-solid fa-triangle-exclamation"></i> SQL asistencia: <?= htmlspecialchars($err_asist) ?></div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:22px;">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:var(--azul);margin:0;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-calendar-check" style="color:var(--azul2);"></i> Asistencia
        </h1>
        <p style="margin:4px 0 0;font-size:.875rem;color:#64748b;">
            Período: <b><?= htmlspecialchars($periodoSeleccionado['nombre'] ?? '—') ?></b>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($convocatoria): ?>
        <a class="btn-sec"
           href="resumen_publico.php?conv_id=<?= $convocatoria['id'] ?>"
           target="_blank"
           onclick="event.preventDefault(); document.getElementById('mResumen').classList.add('show');">
            <i class="fa-solid fa-chart-pie"></i> Resumen
        </a>
        <a href="reporte_asistencia.php?id=<?= $convocatoria['id'] ?>" target="_blank" class="btn-sec">
            <i class="fa-solid fa-print"></i> Reporte
        </a>
        <?php if (!empty($convocatoria['acta_pdf_path'])): ?>
        <a href="descargar_conjunto.php?id=<?= $convocatoria['id'] ?>" class="btn-prim" style="background:linear-gradient(135deg,#166534,#16a34a);">
            <i class="fa-solid fa-file-zipper"></i> Descargar Todo
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <a href="convocatorias.php" class="btn-prim">
            <i class="fa-solid fa-calendar-days"></i> Convocatorias
        </a>
    </div>
</div>

<!-- Selector convocatoria -->
<div class="conv-selector">
    <i class="fa-solid fa-calendar-check" style="color:var(--azul2);font-size:1.1rem;"></i>
    <label style="font-weight:700;font-size:.85rem;color:var(--azul);white-space:nowrap;">Convocatoria:</label>
    <select onchange="window.location='asistencia.php?conv_id='+this.value"
            style="flex:1;min-width:200px;border:1.5px solid var(--borde);border-radius:8px;padding:8px 12px;font-size:.875rem;font-family:inherit;">
        <option value="">— Selecciona —</option>
        <?php foreach($lista_conv as $lc): ?>
        <option value="<?= $lc['id'] ?>" <?= ($convocatoria && $convocatoria['id']==$lc['id'])?'selected':'' ?>>
            <?= htmlspecialchars($lc['titulo']) ?> · <?= date('d/m/Y',strtotime($lc['fecha'])) ?> [<?= ucfirst($lc['estado']) ?>]
        </option>
        <?php endforeach; ?>
    </select>
</div>

<?php if (!$convocatoria): ?>
<div style="text-align:center;padding:60px 20px;color:#94a3b8;">
    <i class="fa-solid fa-calendar-xmark" style="font-size:3.5rem;display:block;margin-bottom:14px;"></i>
    <p style="font-size:1rem;">No hay convocatorias en este período o ninguna está activa.</p>
    <a href="convocatorias.php" class="btn-prim" style="margin-top:14px;display:inline-flex;">
        <i class="fa-solid fa-plus"></i> Crear convocatoria
    </a>
</div>
<?php else:
    $est = $convocatoria['estado'] ?? 'programada';
?>

<!-- Barra info convocatoria -->
<div class="conv-bar">
    <div style="flex:1;">
        <div style="margin-bottom:8px;">
            <span class="epill" style="background:<?= $col_bg[$est]??'#f1f5f9' ?>;color:<?= $col_tx[$est]??'#374151' ?>;">
                <i class="fa-solid fa-circle" style="font-size:.45rem;"></i> <?= ucfirst($est) ?>
            </span>
            <span style="font-size:.77rem;opacity:.75;margin-left:10px;">
                <?= ucfirst($convocatoria['tipo_reunion']??$convocatoria['tipo']??'') ?>
                · <?= ($convocatoria['tipo_asistentes']??'general')==='general'?'General':'Solo Directivos' ?>
            </span>
        </div>
        <h2><?= htmlspecialchars($convocatoria['titulo']) ?></h2>
        <div class="meta">
            <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y',strtotime($convocatoria['fecha'])) ?></span>
            <span><i class="fa-solid fa-clock"></i> <?= substr($convocatoria['hora'],0,5) ?></span>
            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($convocatoria['lugar']) ?></span>
            <?php if (!empty($convocatoria['nombre_creador'])): ?>
            <span><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($convocatoria['nombre_creador']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($puntos): ?>
    <div style="background:rgba(255,255,255,.12);border-radius:10px;padding:12px 16px;min-width:210px;">
        <div style="font-size:.73rem;font-weight:800;opacity:.8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Orden del Día</div>
        <ol style="margin:0;padding-left:14px;font-size:.78rem;opacity:.9;line-height:1.8;">
            <?php foreach($puntos as $p): ?><li><?= htmlspecialchars($p['descripcion']) ?></li><?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
</div>

<!-- KPIs -->
<div class="kpi-row">
    <div class="kpi-box"><div class="num" style="color:var(--azul);"><?= $total_socios ?></div><div class="lbl"><i class="fa-solid fa-users"></i> Total Socios</div></div>
    <div class="kpi-box"><div class="num" style="color:var(--verde);"><?= $presentes ?></div><div class="lbl"><i class="fa-solid fa-user-check"></i> Presentes</div></div>
    <div class="kpi-box"><div class="num" style="color:var(--rojo);"><?= $faltantes ?></div><div class="lbl"><i class="fa-solid fa-user-xmark"></i> Ausentes</div></div>
    <div class="kpi-box"><div class="num" style="color:<?= $porcentaje>=50?'var(--verde)':'var(--rojo)' ?>;"><?= $porcentaje ?>%</div><div class="lbl"><i class="fa-solid fa-percent"></i> Asistencia</div></div>
</div>

<!-- Progreso -->
<div class="prog-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <span style="font-weight:700;font-size:.9rem;color:var(--azul);">Progreso de Asistencia</span>
        <span style="font-size:.82rem;color:#64748b;">
            <?= $presentes ?>/<?= $total_socios ?> socios ·
            <?= $porcentaje>=50
                ? '<span style="color:var(--verde);font-weight:700;">✅ Quórum alcanzado</span>'
                : '<span style="color:var(--rojo);font-weight:700;">❌ Faltan '.$faltantes.' para quórum</span>' ?>
        </span>
    </div>
    <div class="prog-wrap">
        <div class="prog-fill" style="width:<?= $porcentaje ?>%;"><?= $porcentaje ?>%</div>
    </div>
</div>

<!-- Acta -->
<?php if ($est === 'cerrada'): ?>
    <?php if (!empty($convocatoria['acta_pdf_path'])): ?>
    <div class="acta-card acta-ok">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div>
                <div style="font-weight:800;color:#166534;display:flex;align-items:center;gap:8px;font-size:.95rem;"><i class="fa-solid fa-file-circle-check"></i> Acta subida</div>
                <div style="font-size:.8rem;color:#64748b;margin-top:3px;">Subida el <?= date('d/m/Y H:i',strtotime($convocatoria['acta_subida_en'])) ?></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= htmlspecialchars($convocatoria['acta_pdf_path']) ?>" target="_blank" class="btn-prim" style="background:linear-gradient(135deg,#166534,#16a34a);">
                    <i class="fa-solid fa-file-pdf"></i> Ver Acta
                </a>
                <a href="descargar_conjunto.php?id=<?= $convocatoria['id'] ?>" class="btn-sec">
                    <i class="fa-solid fa-file-zipper"></i> Descargar ZIP
                </a>
            </div>
        </div>
    </div>
    <?php elseif (!empty($convocatoria['acta_bloqueada'])): ?>
    <div class="acta-card acta-block">
        <div style="font-weight:800;color:#991b1b;display:flex;align-items:center;gap:8px;"><i class="fa-solid fa-lock"></i> Plazo vencido (más de 48h)</div>
        <div style="font-size:.83rem;color:#64748b;margin-top:6px;">Contacta al administrador del sistema.</div>
    </div>
    <?php else: ?>
    <div class="acta-card acta-pend">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-weight:800;color:#92400e;display:flex;align-items:center;gap:8px;"><i class="fa-solid fa-triangle-exclamation"></i> Acta pendiente</div>
                <div style="font-size:.8rem;color:#64748b;margin-top:3px;">
                    Tiempo restante: <b style="color:<?= ($horas_para_acta??99)<6?'#dc2626':'#92400e' ?>;"><?= $horas_para_acta ?>h</b>
                    — El secretario debe subir el acta en PDF
                </div>
            </div>
            <?php if ($es_editor): ?>
            <form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="accion" value="subir_acta">
                <input type="hidden" name="conv_id" value="<?= $convocatoria['id'] ?>">
                <input type="file" name="acta_pdf" accept=".pdf" required style="font-size:.8rem;border:1.5px solid #fde68a;border-radius:8px;padding:5px 8px;background:#fff;">
                <button type="submit" class="btn-prim" style="background:linear-gradient(135deg,#92400e,#d97706);">
                    <i class="fa-solid fa-upload"></i> Subir Acta PDF
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Registro manual (solo si activa) -->
<?php if ($es_editor && $est==='activa'): ?>
<div class="reg-card">
    <div style="font-weight:700;color:var(--azul);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-user-plus" style="color:var(--azul2);"></i> Registrar Asistencia Manual
    </div>
    <div class="srch-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="inputBuscar" placeholder="Buscar socio por nombre o identificación..." oninput="buscarSocio(this.value)" autocomplete="off">
    </div>
    <div id="resultadosBusqueda"></div>
</div>
<?php endif; ?>

<!-- Tabla asistentes -->
<div class="tbl-card">
    <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1.5px solid var(--borde);flex-wrap:wrap;gap:8px;">
        <span style="font-weight:700;color:var(--azul);"><i class="fa-solid fa-clipboard-list"></i> Asistentes registrados</span>
        <span style="font-size:.8rem;color:#64748b;"><?= $presentes ?> persona(s)</span>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>#</th><th>Socio</th><th>Identificación</th><th>Hora</th><th>Método</th>
                <?php if ($es_editor && $est==='activa'): ?><th>Acc.</th><?php endif; ?>
            </tr>
        </thead>
        <tbody id="cuerpoTabla">
        <?php if (empty($asistentes)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:#94a3b8;">
            <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
            Aún no hay asistentes registrados
        </td></tr>
        <?php else: foreach($asistentes as $i=>$a):
            $partes=explode(' ',$a['nombre_completo']);
            $ini=strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));
        ?>
        <tr id="fila-<?= $a['id'] ?>">
            <td style="color:#94a3b8;font-weight:700;"><?= $i+1 ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="av"><?= $ini ?></div>
                    <span style="font-weight:600;"><?= htmlspecialchars($a['nombre_completo']) ?></span>
                </div>
            </td>
            <td><?= htmlspecialchars($a['cedula']) ?></td>
            <td><?= date('H:i:s',strtotime($a['hora_registro'])) ?></td>
            <td>
                <span class="mpill m-<?= $a['metodo'] ?>">
                    <?php if($a['metodo']==='biometrico'):?><i class="fa-solid fa-fingerprint"></i> Biométrico
                    <?php elseif($a['metodo']==='qr'):?><i class="fa-solid fa-qrcode"></i> QR
                    <?php else:?><i class="fa-solid fa-hand-pointer"></i> Manual<?php endif;?>
                </span>
            </td>
            <?php if ($es_editor && $est==='activa'): ?>
            <td><button class="btn-del" onclick="eliminarAsist(<?= $a['id'] ?>,'<?= htmlspecialchars($a['nombre_completo'],ENT_QUOTES) ?>')"><i class="fa-solid fa-trash"></i></button></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php endif; // fin convocatoria ?>
</section>
</main>
</div>

<!-- Modal resumen -->
<div class="moverlay" id="mResumen">
<div class="mbox">
    <div class="mhead">
        <h2><i class="fa-solid fa-chart-pie"></i> Resumen de Asistencia</h2>
        <button class="mcls" onclick="document.getElementById('mResumen').classList.remove('show')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mbody" style="text-align:center;">
        <div style="position:relative;display:inline-block;margin:0 auto 20px;">
            <svg width="170" height="170" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e8f0" stroke-width="3.8"/>
                <circle cx="18" cy="18" r="15.9" fill="none"
                    stroke="<?= $porcentaje>=50?'#16a34a':'#dc2626' ?>"
                    stroke-width="3.8"
                    stroke-dasharray="<?= $porcentaje ?> 100"
                    stroke-linecap="round"
                    transform="rotate(-90 18 18)"/>
            </svg>
            <div class="donut-center"><div class="dp"><?= $porcentaje ?>%</div><div class="ds">asistencia</div></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">
            <div style="background:#eff6ff;border-radius:10px;padding:12px 6px;"><div style="font-size:1.5rem;font-weight:800;color:var(--azul);"><?= $total_socios ?></div><div style="font-size:.72rem;color:#64748b;">Total</div></div>
            <div style="background:#f0fdf4;border-radius:10px;padding:12px 6px;"><div style="font-size:1.5rem;font-weight:800;color:#16a34a;"><?= $presentes ?></div><div style="font-size:.72rem;color:#64748b;">Presentes</div></div>
            <div style="background:#fef2f2;border-radius:10px;padding:12px 6px;"><div style="font-size:1.5rem;font-weight:800;color:#dc2626;"><?= $faltantes ?></div><div style="font-size:.72rem;color:#64748b;">Ausentes</div></div>
        </div>
        <div style="background:<?= $porcentaje>=50?'#f0fdf4':'#fffbeb' ?>;border:1.5px solid <?= $porcentaje>=50?'#bbf7d0':'#fde68a' ?>;border-radius:10px;padding:11px 14px;font-weight:700;color:<?= $porcentaje>=50?'#166534':'#92400e' ?>;font-size:.88rem;">
            <?= $porcentaje>=50 ? '✅ Quórum alcanzado — La sesión es válida.' : '⚠️ Quórum incompleto — Faltan '.$faltantes.' socio(s) para el 50%.' ?>
        </div>
    </div>
    <div class="mfoot">
        <button class="btn-sec" onclick="document.getElementById('mResumen').classList.remove('show')">Cerrar</button>
        <button class="btn-prim" onclick="window.open('resumen_publico.php?conv_id=<?= $convocatoria['id']??0 ?>','_blank')"><i class="fa-solid fa-chart-pie"></i> Abrir en nueva pestaña</button>
    </div>
</div>
</div>

<script>
const CONV_ID = <?= json_encode($convocatoria['id'] ?? 0) ?>;
const TIPO_ASISTENTES = <?= json_encode($convocatoria['tipo_asistentes'] ?? 'general') ?>;
const ID_PERIODO = <?= json_encode($id_periodo) ?>;
let timer;

function buscarSocio(q) {
    clearTimeout(timer);
    const box = document.getElementById('resultadosBusqueda');
    if (q.length < 2) { box.innerHTML=''; return; }
    timer = setTimeout(()=>{
        fetch(`ajax_buscar_socio.php?q=${encodeURIComponent(q)}&conv_id=${CONV_ID}&tipo_asistentes=${encodeURIComponent(TIPO_ASISTENTES)}&id_periodo=${ID_PERIODO}`)
            .then(r=>r.json())
            .then(data=>{
                if (!Array.isArray(data)||!data.length) {
                    box.innerHTML='<p style="color:#94a3b8;font-size:.83rem;padding:8px 0;">Sin resultados</p>'; return;
                }
                if (data[0]?._error) {
                    box.innerHTML=`<p style="color:#ef4444;font-size:.8rem;padding:8px 0;">Error SQL: ${data[0]._error}</p>`; return;
                }
                box.innerHTML = data.map(s=>`
                    <div class="res-item ${s.ya_registro?'ya':''}" onclick="${!s.ya_registro?`registrarManual(${s.id},'${escH(s.nombre_completo)}')`:''}" >
                        <div class="av">${s.iniciales}</div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:.88rem;">${escH(s.nombre_completo)}</div>
                            <div style="font-size:.75rem;color:#64748b;">${s.cedula}</div>
                        </div>
                        ${s.ya_registro
                            ? '<span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;">✅ Ya registrado</span>'
                            : '<span style="background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;cursor:pointer;">Registrar ›</span>'}
                    </div>`).join('');
            }).catch(e=>{ box.innerHTML=`<p style="color:#ef4444;font-size:.8rem;">Error: ${e.message}</p>`; });
    }, 350);
}

function escH(s){return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;');}

function registrarManual(socio_id,nombre){
    if(!confirm(`¿Registrar asistencia de:\n${nombre}?`)) return;
    fetch('ajax_registrar_asistencia.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({convocatoria_id:CONV_ID,socio_id,metodo:'manual'})
    }).then(r=>r.json()).then(d=>{
        if(d.ok) location.reload();
        else alert('Error: '+(d.msg||'No se pudo registrar'));
    });
}

function eliminarAsist(id,nombre){
    if(!confirm(`¿Eliminar asistencia de:\n${nombre}?`)) return;
    fetch('ajax_eliminar_asistencia.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({id})
    }).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.msg);});
}
</script>
</body>
</html>