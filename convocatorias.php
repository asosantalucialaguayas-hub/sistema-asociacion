<?php
// ============================================================
// convocatorias.php  – Gestión completa de Convocatorias
// Colocar en: /asosantalu/convocatorias.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }

require __DIR__ . "/layout/bootstrap.php"; // $pdo, $periodoSeleccionado, etc.

$id_usuario  = (int)($_SESSION['id_usuario'] ?? 0);
$rol         = $_SESSION['rol'] ?? 'viewer';
$es_editor   = in_array($rol, ['admin','secretario','presidente']);

// ── Acciones POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ── GUARDAR NUEVA / EDITAR CONVOCATORIA ──────────────────
    if ($accion === 'guardar' && $es_editor) {
        $id_conv  = intval($_POST['id_conv'] ?? 0);
        $id_per   = intval($_POST['id_periodo'] ?? ($periodoSeleccionado['id_periodo'] ?? 0));
        $titulo   = trim($_POST['titulo'] ?? '');
        $tipo_r   = $_POST['tipo_reunion'] ?? 'ordinaria';
        $tipo_a   = $_POST['tipo_asistentes'] ?? 'general';
        $fecha_r  = $_POST['fecha_reunion'] ?? '';
        $hora_r   = $_POST['hora_reunion']  ?? '';
        $lugar    = trim($_POST['lugar'] ?? '');
        $estado   = $_POST['estado'] ?? 'borrador';
        $puntos   = array_filter(array_map('trim', $_POST['puntos'] ?? []));
        $firmas_cargo  = $_POST['firma_cargo']  ?? [];
        $firmas_nombre = $_POST['firma_nombre'] ?? [];

        if ($titulo && $fecha_r && $hora_r && $lugar) {
            try {
                $pdo->beginTransaction();
                $nombre_creador = $_SESSION['usuario'] ?? 'Sistema';

                if ($id_conv > 0) {
                    $st = $pdo->prepare("UPDATE convocatorias SET id_periodo=?,titulo=?,tipo_reunion=?,tipo_asistentes=?,fecha_reunion=?,hora_reunion=?,lugar=?,estado=? WHERE id=?");
                    $st->execute([$id_per,$titulo,$tipo_r,$tipo_a,$fecha_r,$hora_r,$lugar,$estado,$id_conv]);
                    $pdo->prepare("DELETE FROM convocatoria_puntos WHERE convocatoria_id=?")->execute([$id_conv]);
                    $pdo->prepare("DELETE FROM convocatoria_firmas  WHERE convocatoria_id=?")->execute([$id_conv]);
                } else {
                    $st = $pdo->prepare("INSERT INTO convocatorias (id_periodo,titulo,tipo_reunion,tipo_asistentes,fecha_reunion,hora_reunion,lugar,estado,creado_por,nombre_creador) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $st->execute([$id_per,$titulo,$tipo_r,$tipo_a,$fecha_r,$hora_r,$lugar,$estado,$id_usuario,$nombre_creador]);
                    $id_conv = $pdo->lastInsertId();
                }

                $stP = $pdo->prepare("INSERT INTO convocatoria_puntos (convocatoria_id,numero,descripcion) VALUES (?,?,?)");
                $n = 1;
                foreach ($puntos as $p) { if ($p) { $stP->execute([$id_conv,$n++,$p]); } }

                $stF = $pdo->prepare("INSERT INTO convocatoria_firmas (convocatoria_id,cargo,nombre,orden) VALUES (?,?,?,?)");
                foreach ($firmas_cargo as $i => $cargo) {
                    $cargo  = trim($cargo);
                    $nombre = trim($firmas_nombre[$i] ?? '');
                    if ($cargo && $nombre) { $stF->execute([$id_conv,$cargo,$nombre,$i+1]); }
                }

                $pdo->commit();
                $_SESSION['flash'] = ['tipo'=>'success','msg'=>'Convocatoria guardada correctamente.'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash'] = ['tipo'=>'error','msg'=>'Error: '.$e->getMessage()];
            }
        } else {
            $_SESSION['flash'] = ['tipo'=>'error','msg'=>'Faltan campos obligatorios.'];
        }
        header("Location: convocatorias.php"); exit;
    }

    // ── CAMBIAR ESTADO ───────────────────────────────────────
    if ($accion === 'cambiar_estado' && $es_editor) {
        $id_conv = intval($_POST['id_conv'] ?? 0);
        $nuevo   = $_POST['nuevo_estado'] ?? '';
        $permitidos = ['borrador','publicada','activa','cerrada','cancelada'];
        if ($id_conv && in_array($nuevo, $permitidos)) {
            $extra = $nuevo === 'cerrada' ? ", fecha_cierre_real=NOW()" : "";
            $pdo->prepare("UPDATE convocatorias SET estado=? $extra WHERE id=?")->execute([$nuevo,$id_conv]);
            $_SESSION['flash'] = ['tipo'=>'success','msg'=>'Estado actualizado.'];
        }
        header("Location: convocatorias.php"); exit;
    }

    // ── ELIMINAR ─────────────────────────────────────────────
    if ($accion === 'eliminar' && $es_editor) {
        $id_conv = intval($_POST['id_conv'] ?? 0);
        if ($id_conv) {
            $pdo->prepare("DELETE FROM convocatorias WHERE id=?")->execute([$id_conv]);
            $_SESSION['flash'] = ['tipo'=>'success','msg'=>'Convocatoria eliminada.'];
        }
        header("Location: convocatorias.php"); exit;
    }
}

// ── Cargar convocatoria para edición ────────────────────────
$editando = null;
$edit_puntos = [];
$edit_firmas = [];
if (isset($_GET['editar'])) {
    $stE = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
    $stE->execute([intval($_GET['editar'])]);
    $editando = $stE->fetch(PDO::FETCH_ASSOC);
    if ($editando) {
        $stP2 = $pdo->prepare("SELECT * FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
        $stP2->execute([$editando['id']]);
        $edit_puntos = $stP2->fetchAll(PDO::FETCH_ASSOC);
        $stF2 = $pdo->prepare("SELECT * FROM convocatoria_firmas WHERE convocatoria_id=? ORDER BY orden");
        $stF2->execute([$editando['id']]);
        $edit_firmas = $stF2->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ── Lista de convocatorias ───────────────────────────────────
$filtro_periodo = intval($_GET['periodo'] ?? ($periodoSeleccionado['id_periodo'] ?? 0));
$convocatorias  = [];
if ($filtro_periodo) {
    $stL = $pdo->prepare("
        SELECT c.*,
               COUNT(a.id) AS total_asistentes,
               (SELECT COUNT(*) FROM socios WHERE estado='activo') AS total_socios
        FROM convocatorias c
        LEFT JOIN conv_asistencia a ON a.convocatoria_id = c.id
        WHERE c.id_periodo = ?
        GROUP BY c.id
        ORDER BY c.fecha_reunion DESC
    ");
    $stL->execute([$filtro_periodo]);
    $convocatorias = $stL->fetchAll(PDO::FETCH_ASSOC);
}

// Todos los períodos para el selector
$todos_periodos = [];
try {
    $todos_periodos = $pdo->query("SELECT id_periodo,nombre,estado FROM periodos ORDER BY id_periodo DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Colores de estado
$colores_estado = [
    'borrador'   => ['bg'=>'#f3f4f6','txt'=>'#6b7280','icono'=>'fa-pen-to-square'],
    'publicada'  => ['bg'=>'#dbeafe','txt'=>'#1d4ed8','icono'=>'fa-paper-plane'],
    'activa'     => ['bg'=>'#dcfce7','txt'=>'#15803d','icono'=>'fa-circle-play'],
    'cerrada'    => ['bg'=>'#fee2e2','txt'=>'#b91c1c','icono'=>'fa-lock'],
    'cancelada'  => ['bg'=>'#fef3c7','txt'=>'#92400e','icono'=>'fa-ban'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Convocatorias – Asociación Santa Lucía</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Variables del sistema ─────────────────────────────── */
:root{
    --azul:    #1f3a5f;
    --azul2:   #2563eb;
    --verde:   #16a34a;
    --rojo:    #dc2626;
    --naranja: #d97706;
    --gris:    #f8fafc;
    --borde:   #e2e8f0;
    --radio:   12px;
    --sombra:  0 2px 12px rgba(0,0,0,.08);
}
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--gris); }

/* ── Header de página ──────────────────────────────────── */
.ph { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:28px; }
.ph h1 { font-size:1.5rem; font-weight:800; color:var(--azul); margin:0; display:flex; align-items:center; gap:10px; }
.ph p  { margin:4px 0 0; font-size:.875rem; color:#64748b; }

/* ── Botón principal ───────────────────────────────────── */
.btn-prim {
    display:inline-flex; align-items:center; gap:7px;
    background:linear-gradient(135deg,#1f3a5f,#2563eb);
    color:#fff; border:none; border-radius:10px;
    padding:10px 20px; font-weight:700; font-size:.875rem;
    cursor:pointer; text-decoration:none; transition:.2s;
    box-shadow:0 4px 14px rgba(37,99,235,.3);
}
.btn-prim:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(37,99,235,.4); color:#fff; }

.btn-sec {
    display:inline-flex; align-items:center; gap:6px;
    background:#fff; color:var(--azul); border:1.5px solid var(--borde);
    border-radius:10px; padding:9px 16px; font-weight:600; font-size:.85rem;
    cursor:pointer; text-decoration:none; transition:.2s;
}
.btn-sec:hover { background:#f1f5f9; color:var(--azul); }

/* ── Tarjetas de convocatoria ──────────────────────────── */
.conv-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(360px,1fr)); gap:20px; }
.conv-card {
    background:#fff; border-radius:16px; box-shadow:var(--sombra);
    border:1.5px solid var(--borde); overflow:hidden; transition:.25s;
    display:flex; flex-direction:column;
}
.conv-card:hover { transform:translateY(-4px); box-shadow:0 8px 28px rgba(0,0,0,.12); }
.conv-card-head {
    background:linear-gradient(135deg,#1f3a5f 0%,#2563eb 100%);
    padding:18px 20px; color:#fff;
}
.conv-card-head .tipo-badge {
    display:inline-flex; align-items:center; gap:5px;
    background:rgba(255,255,255,.18); border-radius:20px;
    padding:3px 10px; font-size:.72rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;
}
.conv-card-head h3 { margin:0; font-size:1rem; font-weight:700; line-height:1.3; }
.conv-card-head .meta { font-size:.8rem; opacity:.85; margin-top:6px; display:flex; gap:14px; flex-wrap:wrap; }
.conv-card-body { padding:16px 20px; flex:1; }
.conv-card-body .detalle { font-size:.82rem; color:#64748b; display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.conv-card-body .detalle i { width:14px; color:#94a3b8; }
.progreso-mini { margin:12px 0; }
.progreso-mini .bar-wrap { background:#e2e8f0; border-radius:50px; height:8px; margin-top:4px; }
.progreso-mini .bar-fill { height:100%; border-radius:50px; background:linear-gradient(90deg,#16a34a,#22c55e); transition:width .8s; }
.conv-card-foot {
    padding:12px 20px; border-top:1px solid var(--borde);
    display:flex; gap:8px; flex-wrap:wrap; align-items:center;
    background:#f8fafc;
}
.estado-pill {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 12px; border-radius:20px; font-size:.75rem; font-weight:700;
}

/* ── Modal ─────────────────────────────────────────────── */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(15,23,42,.55); backdrop-filter:blur(4px);
    z-index:10000; overflow-y:auto; padding:20px;
    animation:fadeIn .2s ease;
}
.modal-overlay.show { display:flex; align-items:flex-start; justify-content:center; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal-box {
    background:#fff; border-radius:20px; width:100%; max-width:760px;
    box-shadow:0 24px 60px rgba(0,0,0,.22);
    animation:slideUp .25s ease;
    margin:auto;
}
@keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-header {
    background:linear-gradient(135deg,#1f3a5f,#2563eb);
    color:#fff; padding:22px 28px; border-radius:20px 20px 0 0;
    display:flex; justify-content:space-between; align-items:center;
}
.modal-header h2 { margin:0; font-size:1.1rem; font-weight:800; }
.modal-close { background:rgba(255,255,255,.2); border:none; color:#fff; border-radius:8px; width:32px; height:32px; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; }
.modal-close:hover { background:rgba(255,255,255,.35); }
.modal-body { padding:28px; }
.modal-foot { padding:16px 28px; border-top:1px solid var(--borde); display:flex; justify-content:flex-end; gap:10px; background:#f8fafc; border-radius:0 0 20px 20px; }

/* ── Formulario ────────────────────────────────────────── */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-grid.one { grid-template-columns:1fr; }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-group label { font-size:.82rem; font-weight:700; color:var(--azul); }
.form-group input, .form-group select, .form-group textarea {
    border:1.5px solid var(--borde); border-radius:8px;
    padding:9px 12px; font-size:.875rem; font-family:inherit;
    outline:none; transition:.2s; background:#fff;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color:var(--azul2); box-shadow:0 0 0 3px rgba(37,99,235,.12);
}
.seccion-title {
    font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.8px;
    color:var(--azul2); margin:20px 0 12px; display:flex; align-items:center; gap:7px;
    border-bottom:2px solid var(--borde); padding-bottom:8px;
}
.punto-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.punto-num {
    width:26px; height:26px; border-radius:50%; background:var(--azul);
    color:#fff; display:flex; align-items:center; justify-content:center;
    font-size:.75rem; font-weight:700; flex-shrink:0;
}
.firma-row { display:grid; grid-template-columns:1fr 2fr auto; gap:8px; align-items:center; margin-bottom:8px; }
.btn-agregar {
    border:2px dashed #cbd5e1; background:transparent; color:#64748b;
    border-radius:8px; padding:7px 14px; width:100%; cursor:pointer;
    font-size:.82rem; font-weight:600; transition:.2s; display:flex; align-items:center; gap:6px; justify-content:center;
}
.btn-agregar:hover { border-color:var(--azul2); color:var(--azul2); background:#eff6ff; }
.btn-del { background:none; border:none; color:#ef4444; cursor:pointer; padding:4px 6px; border-radius:6px; }
.btn-del:hover { background:#fee2e2; }

/* ── Flash ──────────────────────────────────────────────── */
.flash {
    padding:12px 18px; border-radius:10px; margin-bottom:20px;
    display:flex; align-items:center; gap:10px; font-weight:600; font-size:.875rem;
}
.flash.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.flash.error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

/* ── Filtros ────────────────────────────────────────────── */
.filtros { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
.filtros select { border:1.5px solid var(--borde); border-radius:8px; padding:7px 12px; font-size:.85rem; background:#fff; }

/* ── Empty state ────────────────────────────────────────── */
.empty { text-align:center; padding:60px 20px; color:#94a3b8; }
.empty i { font-size:3.5rem; margin-bottom:14px; display:block; }
.empty p { font-size:1rem; margin:0; }

/* ── Acciones pequeñas ──────────────────────────────────── */
.btn-xs {
    display:inline-flex; align-items:center; gap:4px;
    padding:5px 10px; border-radius:7px; font-size:.75rem; font-weight:700;
    border:1.5px solid transparent; cursor:pointer; text-decoration:none; transition:.15s;
}
.btn-xs.editar  { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
.btn-xs.editar:hover  { background:#bfdbfe; }
.btn-xs.estado  { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
.btn-xs.estado:hover  { background:#e5e7eb; }
.btn-xs.eliminar { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
.btn-xs.eliminar:hover { background:#fecaca; }
.btn-xs.ver { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.btn-xs.ver:hover { background:#bbf7d0; }
.btn-xs.pdf { background:#fef3c7; color:#92400e; border-color:#fde68a; }
.btn-xs.pdf:hover { background:#fde68a; }

@media(max-width:640px){
    .form-grid { grid-template-columns:1fr; }
    .conv-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
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

<!-- Encabezado -->
<div class="ph">
    <div>
        <h1><i class="fa-solid fa-calendar-days" style="color:#2563eb;"></i> Convocatorias</h1>
        <p>Gestión de convocatorias a reuniones de la asociación</p>
    </div>
    <?php if ($es_editor): ?>
    <button class="btn-prim" onclick="abrirModal()">
        <i class="fa-solid fa-plus"></i> Nueva Convocatoria
    </button>
    <?php endif; ?>
</div>

<!-- Filtros -->
<div class="filtros">
    <i class="fa-solid fa-filter" style="color:#94a3b8;"></i>
    <form method="GET" style="display:contents;">
        <select name="periodo" onchange="this.form.submit()">
            <?php foreach ($todos_periodos as $p): ?>
            <option value="<?= $p['id_periodo'] ?>" <?= $p['id_periodo']==$filtro_periodo?'selected':'' ?>>
                <?= htmlspecialchars($p['nombre']) ?> (<?= $p['estado'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </form>
    <span style="font-size:.82rem;color:#94a3b8;"><?= count($convocatorias) ?> convocatoria(s) encontrada(s)</span>
</div>

<!-- Grid de convocatorias -->
<?php if (empty($convocatorias)): ?>
<div class="empty">
    <i class="fa-regular fa-calendar-xmark"></i>
    <p>No hay convocatorias en este período.<br>
    <?php if ($es_editor): ?><button class="btn-prim" onclick="abrirModal()" style="margin-top:16px;"><i class="fa-solid fa-plus"></i> Crear la primera</button><?php endif; ?>
    </p>
</div>
<?php else: ?>
<div class="conv-grid">
<?php foreach ($convocatorias as $c):
    $pct = $c['total_socios']>0 ? round(($c['total_asistentes']/$c['total_socios'])*100) : 0;
    $col = $colores_estado[$c['estado']] ?? $colores_estado['borrador'];
    $iconos_tipo = ['ordinaria'=>'fa-users','extraordinaria'=>'fa-bolt','urgente'=>'fa-siren-on'];
    $icono_tipo  = $iconos_tipo[$c['tipo_reunion']] ?? 'fa-users';
?>
<div class="conv-card">
    <div class="conv-card-head">
        <div>
            <span class="tipo-badge"><i class="fa-solid <?= $icono_tipo ?>"></i> <?= ucfirst($c['tipo_reunion']) ?> · <?= $c['tipo_asistentes']==='general'?'General':'Solo Directivos' ?></span>
        </div>
        <h3><?= htmlspecialchars($c['titulo']) ?></h3>
        <div class="meta">
            <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($c['fecha_reunion'])) ?></span>
            <span><i class="fa-solid fa-clock"></i> <?= substr($c['hora_reunion'],0,5) ?></span>
        </div>
    </div>

    <div class="conv-card-body">
        <div class="detalle"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($c['lugar']) ?></div>
        <div class="detalle"><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($c['nombre_creador'] ?? '—') ?></div>
        <?php if ($c['acta_pdf_path']): ?>
        <div class="detalle" style="color:#16a34a;"><i class="fa-solid fa-file-pdf"></i> Acta subida</div>
        <?php elseif ($c['estado']==='cerrada'): ?>
        <div class="detalle" style="color:<?= $c['acta_bloqueada'] ? '#dc2626':'#d97706' ?>;"><i class="fa-solid fa-file-circle-<?= $c['acta_bloqueada']?'xmark':'exclamation' ?>"></i> <?= $c['acta_bloqueada']?'Plazo acta vencido':'Acta pendiente (48h)' ?></div>
        <?php endif; ?>

        <div class="progreso-mini">
            <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#64748b;">
                <span>Asistencia</span>
                <span><b><?= $c['total_asistentes'] ?></b> / <?= $c['total_socios'] ?> (<?= $pct ?>%)</span>
            </div>
            <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
        </div>
    </div>

    <div class="conv-card-foot">
        <span class="estado-pill" style="background:<?= $col['bg'] ?>;color:<?= $col['txt'] ?>;">
            <i class="fa-solid <?= $col['icono'] ?>"></i> <?= ucfirst($c['estado']) ?>
        </span>
        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;">
            <?php if ($es_editor): ?>
            <a href="convocatorias.php?editar=<?= $c['id'] ?>" class="btn-xs editar"><i class="fa-solid fa-pen"></i> Editar</a>
            <?php endif; ?>
            <a href="asistencia.php?conv_id=<?= $c['id'] ?>" class="btn-xs ver"><i class="fa-solid fa-clipboard-list"></i> Asistencia</a>
            <a href="exportar_convocatoria.php?id=<?= $c['id'] ?>" target="_blank" class="btn-xs pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
            <?php if ($es_editor): ?>
                <?php if ($c['estado'] !== 'cerrada' && $c['estado'] !== 'cancelada'): ?>
                <button onclick="cambiarEstado(<?= $c['id'] ?>,this)" class="btn-xs estado" data-estado="<?= $c['estado'] ?>">
                    <i class="fa-solid fa-arrows-rotate"></i> Estado
                </button>
                <?php endif; ?>
                <?php if (in_array($c['estado'],['borrador','cancelada'])): ?>
                <button onclick="confirmarEliminar(<?= $c['id'] ?>,'<?= htmlspecialchars($c['titulo'],ENT_QUOTES) ?>')" class="btn-xs eliminar">
                    <i class="fa-solid fa-trash"></i>
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</section>
</main>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!--  MODAL FORMULARIO CONVOCATORIA                            -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalConv">
<div class="modal-box">
    <div class="modal-header">
        <h2><i class="fa-solid fa-calendar-plus me-2"></i> <span id="modalTitulo">Nueva Convocatoria</span></h2>
        <button class="modal-close" onclick="cerrarModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" style="max-height:75vh;overflow-y:auto;">
    <form method="POST" id="formConv">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id_conv" id="fIdConv" value="0">
        <input type="hidden" name="id_periodo" value="<?= $periodoSeleccionado['id_periodo'] ?? 0 ?>">

        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1;">
                <label>Título de la convocatoria *</label>
                <input type="text" name="titulo" id="fTitulo" placeholder="Ej: Asamblea General Ordinaria – Período 2026" required>
            </div>
            <div class="form-group">
                <label>Tipo de reunión *</label>
                <select name="tipo_reunion" id="fTipoReunion">
                    <option value="ordinaria">Ordinaria</option>
                    <option value="extraordinaria">Extraordinaria</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
            <div class="form-group">
                <label>Participantes</label>
                <select name="tipo_asistentes" id="fTipoAsist">
                    <option value="general">General (todos los socios)</option>
                    <option value="solo_directivos">Solo Directivos</option>
                </select>
            </div>
            <div class="form-group">
                <label>Fecha de la reunión *</label>
                <input type="date" name="fecha_reunion" id="fFecha" required>
            </div>
            <div class="form-group">
                <label>Hora *</label>
                <input type="time" name="hora_reunion" id="fHora" required>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label>Lugar / Dirección *</label>
                <input type="text" name="lugar" id="fLugar" placeholder="Ej: Salón de la Asociación, Parroquia Santa Lucía, Guayas" required>
            </div>
            <div class="form-group">
                <label>Estado inicial</label>
                <select name="estado" id="fEstado">
                    <option value="borrador">Borrador</option>
                    <option value="publicada">Publicada</option>
                    <option value="activa">Activa</option>
                </select>
            </div>
        </div>

        <!-- Puntos del orden del día -->
        <div class="seccion-title"><i class="fa-solid fa-list-ol"></i> Orden del Día</div>
        <div id="listaPuntos"></div>
        <button type="button" class="btn-agregar" onclick="agregarPunto()">
            <i class="fa-solid fa-plus"></i> Agregar punto
        </button>

        <!-- Firmas -->
        <div class="seccion-title" style="margin-top:22px;"><i class="fa-solid fa-signature"></i> Firmas del documento</div>
        <div id="listaFirmas"></div>
        <button type="button" class="btn-agregar" onclick="agregarFirma()">
            <i class="fa-solid fa-plus"></i> Agregar firma
        </button>
    </form>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn-sec" onclick="cerrarModal()">Cancelar</button>
        <button type="submit" form="formConv" class="btn-prim">
            <i class="fa-solid fa-floppy-disk"></i> Guardar Convocatoria
        </button>
    </div>
</div>
</div>

<!-- Modal cambiar estado -->
<div class="modal-overlay" id="modalEstado">
<div class="modal-box" style="max-width:420px;">
    <div class="modal-header">
        <h2><i class="fa-solid fa-arrows-rotate"></i> Cambiar Estado</h2>
        <button class="modal-close" onclick="cerrarEstado()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
        <form method="POST" id="formEstado">
            <input type="hidden" name="accion" value="cambiar_estado">
            <input type="hidden" name="id_conv" id="eIdConv">
            <div class="form-group">
                <label>Nuevo estado</label>
                <select name="nuevo_estado" id="eNuevoEstado">
                    <option value="borrador">Borrador</option>
                    <option value="publicada">Publicada</option>
                    <option value="activa">Activa (inicia asistencia)</option>
                    <option value="cerrada">Cerrada (finaliza reunión)</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <p style="font-size:.82rem;color:#64748b;margin-top:10px;">
                <i class="fa-solid fa-circle-info"></i> Poner en <b>Activa</b> habilita el registro de asistencia. Al <b>Cerrar</b> comienza el plazo de 48h para subir el acta.
            </p>
        </form>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn-sec" onclick="cerrarEstado()">Cancelar</button>
        <button type="submit" form="formEstado" class="btn-prim"><i class="fa-solid fa-check"></i> Confirmar</button>
    </div>
</div>
</div>

<!-- Modal eliminar -->
<form method="POST" id="formEliminar">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id_conv" id="delIdConv">
</form>

<script>
// ── Datos para edición ──────────────────────────────────
const editandoData = <?= $editando ? json_encode([
    'id'              => $editando['id'],
    'titulo'          => $editando['titulo'],
    'tipo_reunion'    => $editando['tipo_reunion'],
    'tipo_asistentes' => $editando['tipo_asistentes'],
    'fecha_reunion'   => $editando['fecha_reunion'],
    'hora_reunion'    => substr($editando['hora_reunion'],0,5),
    'lugar'           => $editando['lugar'],
    'estado'          => $editando['estado'],
]) : 'null' ?>;
const editPuntos = <?= json_encode(array_column($edit_puntos,'descripcion')) ?>;
const editFirmas = <?= json_encode($edit_firmas) ?>;

let contPuntos = 0, contFirmas = 0;

// ── Modal formulario ────────────────────────────────────
function abrirModal(datos, puntos, firmas) {
    document.getElementById('modalConv').classList.add('show');
    document.body.style.overflow='hidden';
    limpiarModal();
    if (datos) {
        document.getElementById('modalTitulo').textContent   = 'Editar Convocatoria';
        document.getElementById('fIdConv').value             = datos.id;
        document.getElementById('fTitulo').value             = datos.titulo;
        document.getElementById('fTipoReunion').value        = datos.tipo_reunion;
        document.getElementById('fTipoAsist').value          = datos.tipo_asistentes;
        document.getElementById('fFecha').value              = datos.fecha_reunion;
        document.getElementById('fHora').value               = datos.hora_reunion;
        document.getElementById('fLugar').value              = datos.lugar;
        document.getElementById('fEstado').value             = datos.estado;
        (puntos||[]).forEach(p => agregarPunto(p));
        (firmas||[]).forEach(f => agregarFirma(f.cargo, f.nombre));
    } else {
        document.getElementById('modalTitulo').textContent = 'Nueva Convocatoria';
        // Puntos por defecto
        ['Palabras de bienvenida','Lectura y aprobación del orden del día','Puntos varios','Clausura'].forEach(p=>agregarPunto(p));
        // Firmas por defecto
        agregarFirma('Presidente','');
        agregarFirma('Secretario/a','');
    }
}

function cerrarModal() {
    document.getElementById('modalConv').classList.remove('show');
    document.body.style.overflow='';
}

function limpiarModal() {
    contPuntos=0; contFirmas=0;
    document.getElementById('listaPuntos').innerHTML='';
    document.getElementById('listaFirmas').innerHTML='';
    document.getElementById('formConv').reset();
    document.getElementById('fIdConv').value='0';
}

// ── Puntos ──────────────────────────────────────────────
function agregarPunto(valor='') {
    contPuntos++;
    const div = document.createElement('div');
    div.className='punto-row';
    div.id='punto-row-'+contPuntos;
    div.innerHTML=`
        <div class="punto-num">${contPuntos}</div>
        <input type="text" name="puntos[]" class="form-group input" value="${escHtml(valor)}"
               placeholder="Describe el punto ${contPuntos}..."
               style="flex:1;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 10px;font-size:.875rem;font-family:inherit;">
        ${contPuntos>1?`<button type="button" class="btn-del" onclick="eliminarPunto('punto-row-${contPuntos}')"><i class="fa-solid fa-xmark"></i></button>`:''}
    `;
    document.getElementById('listaPuntos').appendChild(div);
    renumerarPuntos();
}

function eliminarPunto(id){document.getElementById(id)?.remove();renumerarPuntos();}

function renumerarPuntos(){
    document.querySelectorAll('#listaPuntos .punto-num').forEach((el,i)=>el.textContent=i+1);
}

// ── Firmas ──────────────────────────────────────────────
function agregarFirma(cargo='',nombre='') {
    contFirmas++;
    const div = document.createElement('div');
    div.className='firma-row';
    div.id='firma-row-'+contFirmas;
    const sty='border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 10px;font-size:.875rem;font-family:inherit;';
    div.innerHTML=`
        <input type="text" name="firma_cargo[]"  value="${escHtml(cargo)}"  placeholder="Cargo (Ej: Presidente)" style="${sty}">
        <input type="text" name="firma_nombre[]" value="${escHtml(nombre)}" placeholder="Nombre completo"         style="${sty}">
        <button type="button" class="btn-del" onclick="document.getElementById('firma-row-${contFirmas}').remove()"><i class="fa-solid fa-xmark"></i></button>
    `;
    document.getElementById('listaFirmas').appendChild(div);
}

// ── Cambiar estado ──────────────────────────────────────
function cambiarEstado(id, btn) {
    document.getElementById('eIdConv').value = id;
    const estado = btn?.dataset?.estado || 'borrador';
    document.getElementById('eNuevoEstado').value = estado;
    document.getElementById('modalEstado').classList.add('show');
    document.body.style.overflow='hidden';
}
function cerrarEstado(){
    document.getElementById('modalEstado').classList.remove('show');
    document.body.style.overflow='';
}

// ── Eliminar ────────────────────────────────────────────
function confirmarEliminar(id, titulo) {
    if (!confirm(`¿Eliminar la convocatoria "${titulo}"?\nEsta acción no se puede deshacer.`)) return;
    document.getElementById('delIdConv').value = id;
    document.getElementById('formEliminar').submit();
}

function escHtml(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Abrir modal de edición si viene ?editar=
<?php if ($editando): ?>
window.addEventListener('DOMContentLoaded',function(){
    abrirModal(editandoData, editPuntos, editFirmas);
});
<?php endif; ?>
</script>
</body>
</html>
