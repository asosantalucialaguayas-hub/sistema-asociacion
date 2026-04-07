<?php
// ============================================================
// convocatorias.php  – Gestión de Convocatorias
// Adaptado a la BD real: periodo_comercializacion, identificacion, etc.
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }

require __DIR__ . "/layout/bootstrap.php";

$id_usuario   = (int)($_SESSION['id_usuario'] ?? 0);
$rol          = $_SESSION['rol'] ?? $_SESSION['tipo_usuario'] ?? 'viewer';
$es_editor    = in_array($rol, ['admin','secretario','presidente','superadmin']) || $id_usuario === 1;

// Periodo activo viene de bootstrap como $periodoSeleccionado
$id_periodo_activo = intval($periodoSeleccionado['id_periodo'] ?? 0);

// ── ACCIONES POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ── GUARDAR (nueva o editar) ─────────────────────────────
    if ($accion === 'guardar' && $es_editor) {
        $id_conv       = intval($_POST['id_conv'] ?? 0);
        $id_per        = intval($_POST['id_periodo'] ?? $id_periodo_activo);
        $titulo        = trim($_POST['titulo'] ?? '');
        $tipo          = in_array($_POST['tipo']??'', ['ordinaria','extraordinaria','urgente']) ? $_POST['tipo'] : 'ordinaria';
        $tipo_reunion  = in_array($_POST['tipo_reunion']??'', ['ordinaria','extraordinaria','urgente']) ? $_POST['tipo_reunion'] : $tipo;
        $tipo_asist    = in_array($_POST['tipo_asistentes']??'', ['general','solo_directivos']) ? $_POST['tipo_asistentes'] : 'general';
        $fecha         = $_POST['fecha_reunion'] ?? '';
        $hora          = $_POST['hora_reunion']  ?? '';
        $lugar         = trim($_POST['lugar'] ?? '');
        $estado        = in_array($_POST['estado']??'', ['borrador','programada','publicada','activa','cerrada','cancelada']) ? $_POST['estado'] : 'programada';
        $puntos        = array_filter(array_map('trim', $_POST['puntos'] ?? []));
        $firmas_cargo  = $_POST['firma_cargo']  ?? [];
        $firmas_nombre = $_POST['firma_nombre'] ?? [];

        if (!$titulo || !$fecha || !$hora || !$lugar) {
            $_SESSION['flash'] = ['tipo'=>'error','msg'=>'Completa todos los campos obligatorios (*)'];
        } else {
            try {
                $pdo->beginTransaction();
                $nombre_creador = $_SESSION['usuario'] ?? 'Sistema';

                if ($id_conv > 0) {
                    // EDITAR — mantiene columnas existentes intactas
                    $st = $pdo->prepare("
                        UPDATE convocatorias SET
                            id_periodo=?, titulo=?, tipo=?, tipo_reunion=?, tipo_asistentes=?,
                            fecha=?, hora=?, lugar=?, estado=?, nombre_creador=?
                        WHERE id=?
                    ");
                    $st->execute([$id_per,$titulo,$tipo,$tipo_reunion,$tipo_asist,$fecha,$hora,$lugar,$estado,$nombre_creador,$id_conv]);
                    $pdo->prepare("DELETE FROM convocatoria_puntos WHERE convocatoria_id=?")->execute([$id_conv]);
                    $pdo->prepare("DELETE FROM convocatoria_firmas  WHERE convocatoria_id=?")->execute([$id_conv]);
                } else {
                    // NUEVA
                    $st = $pdo->prepare("
                        INSERT INTO convocatorias
                            (id_periodo,titulo,tipo,tipo_reunion,tipo_asistentes,fecha,hora,lugar,estado,creado_por,nombre_creador)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $st->execute([$id_per,$titulo,$tipo,$tipo_reunion,$tipo_asist,$fecha,$hora,$lugar,$estado,$id_usuario,$nombre_creador]);
                    $id_conv = $pdo->lastInsertId();
                }

                // Puntos del orden del día
                $stP = $pdo->prepare("INSERT INTO convocatoria_puntos (convocatoria_id,numero,descripcion) VALUES (?,?,?)");
                $n = 1;
                foreach ($puntos as $p) { if ($p) $stP->execute([$id_conv,$n++,$p]); }

                // Firmas
                $stF = $pdo->prepare("INSERT INTO convocatoria_firmas (convocatoria_id,cargo,nombre,orden) VALUES (?,?,?,?)");
                foreach ($firmas_cargo as $i => $cargo) {
                    $cargo  = trim($cargo);
                    $nombre = trim($firmas_nombre[$i] ?? '');
                    if ($cargo && $nombre) $stF->execute([$id_conv,$cargo,$nombre,$i+1]);
                }

                $pdo->commit();
                $_SESSION['flash'] = ['tipo'=>'success','msg'=>'✅ Convocatoria guardada correctamente.'];
                header("Location: convocatorias.php"); exit;
            } catch(Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash'] = ['tipo'=>'error','msg'=>'Error: '.$e->getMessage()];
            }
        }
    }

    // ── CAMBIAR ESTADO ──────────────────────────────────────
    if ($accion === 'cambiar_estado' && $es_editor) {
        $id_conv  = intval($_POST['id_conv'] ?? 0);
        $nuevo    = $_POST['nuevo_estado'] ?? '';
        $ok_est   = ['borrador','programada','publicada','activa','cerrada','cancelada'];
        if ($id_conv && in_array($nuevo, $ok_est)) {
            $extra = $nuevo === 'cerrada' ? ", fecha_cierre_real=NOW()" : "";
            $pdo->prepare("UPDATE convocatorias SET estado=? $extra WHERE id=?")->execute([$nuevo,$id_conv]);
            $_SESSION['flash'] = ['tipo'=>'success','msg'=>'Estado actualizado a: '.ucfirst($nuevo)];
        }
        header("Location: convocatorias.php"); exit;
    }

    // ── ELIMINAR ────────────────────────────────────────────
    if ($accion === 'eliminar' && $es_editor) {
        $id_conv = intval($_POST['id_conv'] ?? 0);
        if ($id_conv) {
            $pdo->prepare("DELETE FROM convocatorias WHERE id=?")->execute([$id_conv]);
            $_SESSION['flash'] = ['tipo'=>'success','msg'=>'Convocatoria eliminada.'];
        }
        header("Location: convocatorias.php"); exit;
    }
}

// ── Datos para edición via GET ───────────────────────────────
$editando    = null;
$edit_puntos = [];
$edit_firmas = [];
if (isset($_GET['editar']) && $es_editor) {
    $stE = $pdo->prepare("SELECT * FROM convocatorias WHERE id=?");
    $stE->execute([intval($_GET['editar'])]);
    $editando = $stE->fetch(PDO::FETCH_ASSOC);
    if ($editando) {
        $stP2 = $pdo->prepare("SELECT descripcion FROM convocatoria_puntos WHERE convocatoria_id=? ORDER BY numero");
        $stP2->execute([$editando['id']]);
        $edit_puntos = $stP2->fetchAll(PDO::FETCH_COLUMN);
        $stF2 = $pdo->prepare("SELECT cargo,nombre FROM convocatoria_firmas WHERE convocatoria_id=? ORDER BY orden");
        $stF2->execute([$editando['id']]);
        $edit_firmas = $stF2->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ── Lista convocatorias (filtro por período) ─────────────────
$filtro_periodo = intval($_GET['periodo'] ?? $id_periodo_activo);
$convocatorias  = [];
try {
    $stL = $pdo->prepare("
        SELECT c.*,
               COUNT(DISTINCT a.id) AS total_asistentes,
               (SELECT COUNT(*) FROM socios WHERE estado='activo') AS total_socios
        FROM convocatorias c
        LEFT JOIN conv_asistencia a ON a.convocatoria_id=c.id
        WHERE c.id_periodo=?
        GROUP BY c.id
        ORDER BY c.fecha DESC, c.hora DESC
    ");
    $stL->execute([$filtro_periodo]);
    $convocatorias = $stL->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error_lista = $e->getMessage();
}

// ── Todos los períodos para selector ────────────────────────
$todos_periodos = [];
try {
    $todos_periodos = $pdo->query("SELECT id_periodo,nombre,estado FROM periodo_comercializacion ORDER BY id_periodo DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $todos_periodos = []; }

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// Paleta de estados
$st_cfg = [
    'borrador'   => ['bg'=>'#f1f5f9','txt'=>'#475569','ico'=>'fa-pen'],
    'programada' => ['bg'=>'#e0f2fe','txt'=>'#0369a1','ico'=>'fa-calendar-days'],
    'publicada'  => ['bg'=>'#dbeafe','txt'=>'#1d4ed8','ico'=>'fa-paper-plane'],
    'activa'     => ['bg'=>'#dcfce7','txt'=>'#15803d','ico'=>'fa-circle-play'],
    'cerrada'    => ['bg'=>'#fee2e2','txt'=>'#b91c1c','ico'=>'fa-lock'],
    'cancelada'  => ['bg'=>'#fef3c7','txt'=>'#92400e','ico'=>'fa-ban'],
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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
    --azul:#1f3a5f; --azul2:#2563eb; --verde:#16a34a; --rojo:#dc2626;
    --naranja:#d97706; --gris:#f8fafc; --borde:#e2e8f0;
    --sombra:0 2px 12px rgba(0,0,0,.08);
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--gris);}

/* ── Botones ──────────────────────────────────── */
.btn-prim{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;box-shadow:0 4px 14px rgba(37,99,235,.25);}
.btn-prim:hover{transform:translateY(-2px);color:#fff;box-shadow:0 6px 20px rgba(37,99,235,.35);}
.btn-sec{display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--azul);border:1.5px solid var(--borde);border-radius:10px;padding:9px 16px;font-weight:600;font-size:.85rem;cursor:pointer;text-decoration:none;transition:.2s;}
.btn-sec:hover{background:#f1f5f9;}
.btn-xs{display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:7px;font-size:.75rem;font-weight:700;cursor:pointer;text-decoration:none;border:1.5px solid transparent;transition:.15s;}
.bx-edit{background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe;} .bx-edit:hover{background:#bfdbfe;}
.bx-eye {background:#dcfce7;color:#166534;border-color:#bbf7d0;} .bx-eye:hover{background:#bbf7d0;}
.bx-pdf {background:#fef3c7;color:#92400e;border-color:#fde68a;} .bx-pdf:hover{background:#fde68a;}
.bx-del {background:#fee2e2;color:#b91c1c;border-color:#fecaca;} .bx-del:hover{background:#fecaca;}
.bx-est {background:#f1f5f9;color:#374151;border-color:#d1d5db;} .bx-est:hover{background:#e5e7eb;}

/* ── Flash ────────────────────────────────────── */
.flash{padding:12px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:.875rem;}
.flash.success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.flash.error  {background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}

/* ── Grid cards ───────────────────────────────── */
.conv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;}
.conv-card{background:#fff;border-radius:16px;box-shadow:var(--sombra);border:1.5px solid var(--borde);overflow:hidden;display:flex;flex-direction:column;transition:.25s;}
.conv-card:hover{transform:translateY(-4px);box-shadow:0 8px 28px rgba(0,0,0,.12);}
.cc-head{background:linear-gradient(135deg,#1f3a5f 0%,#2563eb 100%);padding:16px 20px;color:#fff;}
.cc-head .badge-tipo{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.18);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.cc-head h3{margin:0;font-size:.95rem;font-weight:700;line-height:1.3;}
.cc-head .meta{display:flex;flex-wrap:wrap;gap:12px;font-size:.78rem;opacity:.88;margin-top:6px;}
.cc-body{padding:14px 20px;flex:1;}
.cc-det{font-size:.8rem;color:#64748b;display:flex;align-items:center;gap:6px;margin-bottom:5px;}
.cc-det i{width:14px;text-align:center;color:#94a3b8;}
.prog-mini .bar-w{background:#e2e8f0;border-radius:50px;height:7px;margin-top:4px;}
.prog-mini .bar-f{height:100%;border-radius:50px;background:linear-gradient(90deg,#16a34a,#22c55e);transition:width .8s;}
.cc-foot{padding:11px 20px;border-top:1px solid var(--borde);display:flex;gap:6px;flex-wrap:wrap;align-items:center;background:#f8fafc;}
.estado-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:.72rem;font-weight:700;}

/* ── Empty ────────────────────────────────────── */
.empty{text-align:center;padding:60px 20px;color:#94a3b8;}
.empty i{font-size:3rem;display:block;margin-bottom:12px;}

/* ── Modal ────────────────────────────────────── */
.moverlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:10000;overflow-y:auto;padding:20px 16px;align-items:flex-start;justify-content:center;}
.moverlay.show{display:flex;}
.mbox{background:#fff;border-radius:20px;width:100%;max-width:740px;box-shadow:0 24px 60px rgba(0,0,0,.25);margin:auto;animation:slideUp .22s ease;}
@keyframes slideUp{from{transform:translateY(28px);opacity:0}to{transform:translateY(0);opacity:1}}
.mhead{background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;padding:20px 26px;border-radius:20px 20px 0 0;display:flex;justify-content:space-between;align-items:center;}
.mhead h2{margin:0;font-size:1.05rem;font-weight:800;display:flex;align-items:center;gap:8px;}
.mcls{background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;}
.mcls:hover{background:rgba(255,255,255,.35);}
.mbody{padding:24px 28px;max-height:72vh;overflow-y:auto;}
.mfoot{padding:14px 28px;border-top:1px solid var(--borde);display:flex;justify-content:flex-end;gap:10px;background:#f8fafc;border-radius:0 0 20px 20px;}

/* ── Formulario ───────────────────────────────── */
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;}
.fg label{font-size:.8rem;font-weight:700;color:var(--azul);}
.fg input,.fg select,.fg textarea{border:1.5px solid var(--borde);border-radius:8px;padding:9px 12px;font-size:.875rem;font-family:inherit;outline:none;transition:.2s;background:#fff;width:100%;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--azul2);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fgrid.three{grid-template-columns:1fr 1fr 1fr;}
@media(max-width:560px){.fgrid,.fgrid.three{grid-template-columns:1fr;}}
.sec-lbl{font-size:.73rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--azul2);display:flex;align-items:center;gap:6px;border-bottom:2px solid var(--borde);padding-bottom:7px;margin:18px 0 12px;}
.punto-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;}
.num-badge{width:26px;height:26px;border-radius:50%;background:var(--azul);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;}
.firma-row{display:grid;grid-template-columns:1fr 2fr auto;gap:8px;align-items:center;margin-bottom:8px;}
.btn-add{border:2px dashed #cbd5e1;background:transparent;color:#64748b;border-radius:8px;padding:8px 14px;width:100%;cursor:pointer;font-size:.82rem;font-weight:600;display:flex;align-items:center;gap:6px;justify-content:center;transition:.2s;margin-top:4px;}
.btn-add:hover{border-color:var(--azul2);color:var(--azul2);background:#eff6ff;}
.btn-rm{background:none;border:none;color:#ef4444;cursor:pointer;padding:4px 7px;border-radius:6px;font-size:.85rem;}
.btn-rm:hover{background:#fee2e2;}

/* filtros */
.filtros{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
.filtros select{border:1.5px solid var(--borde);border-radius:8px;padding:7px 12px;font-size:.85rem;background:#fff;}

@media(max-width:600px){.conv-grid{grid-template-columns:1fr;}}
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

<?php if (isset($error_lista)): ?>
<div class="flash error"><i class="fa-solid fa-triangle-exclamation"></i>
    Error cargando convocatorias: <?= htmlspecialchars($error_lista) ?>
    <br><small>Si ves "Unknown column 'id_periodo'", ejecuta primero <b>MIGRACION_ejecutar_primero.sql</b></small>
</div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-size:1.45rem;font-weight:800;color:var(--azul);margin:0;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-calendar-days" style="color:var(--azul2);"></i> Convocatorias
        </h1>
        <p style="margin:4px 0 0;font-size:.875rem;color:#64748b;">
            Período activo: <b><?= htmlspecialchars($periodoSeleccionado['nombre'] ?? '—') ?></b>
        </p>
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
            <?php foreach($todos_periodos as $p): ?>
            <option value="<?= $p['id_periodo'] ?>" <?= $p['id_periodo']==$filtro_periodo?'selected':'' ?>>
                <?= htmlspecialchars($p['nombre']) ?> (<?= $p['estado'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </form>
    <span style="font-size:.82rem;color:#94a3b8;"><?= count($convocatorias) ?> convocatoria(s)</span>
</div>

<!-- Grid -->
<?php if (empty($convocatorias) && !isset($error_lista)): ?>
<div class="empty">
    <i class="fa-regular fa-calendar-xmark"></i>
    <p>No hay convocatorias en este período.</p>
    <?php if ($es_editor): ?>
    <button class="btn-prim" onclick="abrirModal()" style="margin-top:14px;"><i class="fa-solid fa-plus"></i> Crear la primera</button>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="conv-grid">
<?php foreach($convocatorias as $cv):
    $est = $cv['estado'] ?? 'programada';
    $cfg = $st_cfg[$est] ?? $st_cfg['programada'];
    $pct = $cv['total_socios']>0 ? round(($cv['total_asistentes']/$cv['total_socios'])*100) : 0;
    $tipo_icon = ['ordinaria'=>'fa-users','extraordinaria'=>'fa-bolt','urgente'=>'fa-siren-on'];
?>
<div class="conv-card">
    <div class="cc-head">
        <div class="badge-tipo">
            <i class="fa-solid <?= $tipo_icon[$cv['tipo_reunion']??$cv['tipo']??'ordinaria'] ?? 'fa-users' ?>"></i>
            <?= ucfirst($cv['tipo_reunion']??$cv['tipo']??'ordinaria') ?>
            · <?= ($cv['tipo_asistentes']??'general')==='general'?'General':'Directivos' ?>
        </div>
        <h3><?= htmlspecialchars($cv['titulo']) ?></h3>
        <div class="meta">
            <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y',strtotime($cv['fecha'])) ?></span>
            <span><i class="fa-solid fa-clock"></i> <?= substr($cv['hora'],0,5) ?></span>
        </div>
    </div>
    <div class="cc-body">
        <div class="cc-det"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($cv['lugar']) ?></div>
        <?php if (!empty($cv['nombre_creador'])): ?>
        <div class="cc-det"><i class="fa-solid fa-user-pen"></i><?= htmlspecialchars($cv['nombre_creador']) ?></div>
        <?php endif; ?>
        <?php if (!empty($cv['acta_pdf_path'])): ?>
        <div class="cc-det" style="color:#16a34a;"><i class="fa-solid fa-file-pdf"></i>Acta disponible</div>
        <?php elseif ($est==='cerrada'): ?>
        <div class="cc-det" style="color:<?= !empty($cv['acta_bloqueada'])?'#dc2626':'#d97706' ?>;"><i class="fa-solid fa-file-circle-<?= !empty($cv['acta_bloqueada'])?'xmark':'exclamation' ?>"></i>Acta <?= !empty($cv['acta_bloqueada'])?'vencida':'pendiente' ?></div>
        <?php endif; ?>

        <!-- mini progreso -->
        <div class="prog-mini" style="margin-top:10px;">
            <div style="display:flex;justify-content:space-between;font-size:.75rem;color:#64748b;margin-bottom:3px;">
                <span>Asistencia</span>
                <span><b><?= $cv['total_asistentes'] ?></b>/<?= $cv['total_socios'] ?> (<?= $pct ?>%)</span>
            </div>
            <div class="bar-w"><div class="bar-f" style="width:<?= $pct ?>%;"></div></div>
        </div>
    </div>
    <div class="cc-foot">
        <span class="estado-pill" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['txt'] ?>;">
            <i class="fa-solid <?= $cfg['ico'] ?>" style="font-size:.6rem;"></i> <?= ucfirst($est) ?>
        </span>
        <div style="margin-left:auto;display:flex;gap:5px;flex-wrap:wrap;">
            <?php if ($es_editor): ?>
            <button class="btn-xs bx-edit" onclick="abrirEditar(<?= $cv['id'] ?>)">
                <i class="fa-solid fa-pen"></i> Editar
            </button>
            <?php endif; ?>
            <a href="asistencia.php?conv_id=<?= $cv['id'] ?>" class="btn-xs bx-eye">
                <i class="fa-solid fa-clipboard-list"></i> Asistencia
            </a>
            <a href="exportar_convocatoria.php?id=<?= $cv['id'] ?>" target="_blank" class="btn-xs bx-pdf">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </a>
            <?php if ($es_editor && !in_array($est,['cerrada','cancelada'])): ?>
            <button class="btn-xs bx-est" onclick="abrirCambioEstado(<?= $cv['id'] ?>,'<?= $est ?>')">
                <i class="fa-solid fa-arrows-rotate"></i> Estado
            </button>
            <?php endif; ?>
            <?php if ($es_editor && in_array($est,['borrador','programada','cancelada'])): ?>
            <button class="btn-xs bx-del" onclick="confirmarBorrar(<?= $cv['id'] ?>,'<?= htmlspecialchars($cv['titulo'],ENT_QUOTES) ?>')">
                <i class="fa-solid fa-trash"></i>
            </button>
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

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MODAL: FORMULARIO NUEVA / EDITAR CONVOCATORIA            -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="moverlay" id="mConv">
<div class="mbox">
    <div class="mhead">
        <h2><i class="fa-solid fa-calendar-plus"></i> <span id="mTitulo">Nueva Convocatoria</span></h2>
        <button class="mcls" onclick="cerrarModal('mConv')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mbody">
    <form method="POST" id="frmConv" autocomplete="off">
        <input type="hidden" name="accion"   value="guardar">
        <input type="hidden" name="id_conv"  id="fIdConv"  value="0">
        <input type="hidden" name="id_periodo" value="<?= $id_periodo_activo ?>">

        <!-- Título -->
        <div class="fg">
            <label>Título de la convocatoria *</label>
            <input type="text" name="titulo" id="fTitulo" placeholder="Ej: Asamblea General Ordinaria – CONTRATO 2026" required>
        </div>

        <div class="fgrid">
            <div class="fg">
                <label>Tipo de reunión *</label>
                <select name="tipo_reunion" id="fTipoReunion">
                    <option value="ordinaria">Ordinaria</option>
                    <option value="extraordinaria">Extraordinaria</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
            <div class="fg">
                <label>Participantes</label>
                <select name="tipo_asistentes" id="fTipoAsist">
                    <option value="general">General (todos los socios)</option>
                    <option value="solo_directivos">Solo Directivos</option>
                </select>
            </div>
        </div>

        <div class="fgrid three">
            <div class="fg">
                <label>Fecha *</label>
                <input type="date" name="fecha_reunion" id="fFecha" required>
            </div>
            <div class="fg">
                <label>Hora *</label>
                <input type="time" name="hora_reunion" id="fHora" value="09:00" required>
            </div>
            <div class="fg">
                <label>Estado inicial</label>
                <select name="estado" id="fEstado">
                    <option value="programada">Programada</option>
                    <option value="publicada">Publicada</option>
                    <option value="activa">Activa (abre asistencia)</option>
                </select>
            </div>
        </div>

        <div class="fg">
            <label>Lugar / Dirección *</label>
            <input type="text" name="lugar" id="fLugar" placeholder="Ej: Salón de la Asociación, Parroquia Santa Lucía, Guayas" required>
        </div>

        <!-- Orden del día -->
        <div class="sec-lbl"><i class="fa-solid fa-list-ol"></i> Orden del Día</div>
        <div id="listaPuntos"></div>
        <button type="button" class="btn-add" onclick="addPunto()"><i class="fa-solid fa-plus"></i> Agregar punto</button>

        <!-- Firmas -->
        <div class="sec-lbl" style="margin-top:20px;"><i class="fa-solid fa-signature"></i> Firmas del documento</div>
        <div id="listaFirmas"></div>
        <button type="button" class="btn-add" onclick="addFirma()"><i class="fa-solid fa-plus"></i> Agregar firma</button>

        <!-- input oculto para compatibilidad con columna 'tipo' existente -->
        <input type="hidden" name="tipo" id="fTipo" value="ordinaria">
    </form>
    </div>
    <div class="mfoot">
        <button type="button" class="btn-sec" onclick="cerrarModal('mConv')">Cancelar</button>
        <button type="submit" form="frmConv" class="btn-prim">
            <i class="fa-solid fa-floppy-disk"></i> Guardar Convocatoria
        </button>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  MODAL: CAMBIAR ESTADO                                     -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="moverlay" id="mEstado">
<div class="mbox" style="max-width:420px;">
    <div class="mhead">
        <h2><i class="fa-solid fa-arrows-rotate"></i> Cambiar Estado</h2>
        <button class="mcls" onclick="cerrarModal('mEstado')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mbody">
    <form method="POST" id="frmEstado">
        <input type="hidden" name="accion"       value="cambiar_estado">
        <input type="hidden" name="id_conv"      id="eIdConv">
        <div class="fg">
            <label>Nuevo estado</label>
            <select name="nuevo_estado" id="eEstado">
                <option value="programada">Programada</option>
                <option value="publicada">Publicada</option>
                <option value="activa">Activa — habilita registro de asistencia</option>
                <option value="cerrada">Cerrada — finaliza la reunión (inicia 48h para acta)</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
        <p style="font-size:.82rem;color:#64748b;margin-top:4px;background:#f1f5f9;border-radius:8px;padding:10px 12px;">
            <i class="fa-solid fa-circle-info" style="color:var(--azul2);"></i>
            Al poner <b>Activa</b> el biométrico y el registro manual quedan habilitados.<br>
            Al <b>Cerrar</b> comienza el plazo de 48h para subir el acta.
        </p>
    </form>
    </div>
    <div class="mfoot">
        <button type="button" class="btn-sec" onclick="cerrarModal('mEstado')">Cancelar</button>
        <button type="submit" form="frmEstado" class="btn-prim"><i class="fa-solid fa-check"></i> Confirmar</button>
    </div>
</div>
</div>

<!-- Form oculto borrar -->
<form method="POST" id="frmBorrar" style="display:none;">
    <input type="hidden" name="accion"  value="eliminar">
    <input type="hidden" name="id_conv" id="bIdConv">
</form>

<!-- Datos para JS (edición) -->
<script>
const EDITANDO = <?= $editando ? json_encode([
    'id'             => $editando['id'],
    'titulo'         => $editando['titulo'],
    'tipo_reunion'   => $editando['tipo_reunion'] ?? $editando['tipo'] ?? 'ordinaria',
    'tipo_asistentes'=> $editando['tipo_asistentes'] ?? 'general',
    'fecha_reunion'  => $editando['fecha'],
    'hora_reunion'   => substr($editando['hora'],0,5),
    'lugar'          => $editando['lugar'],
    'estado'         => $editando['estado'],
]) : 'null' ?>;
const EDIT_PUNTOS = <?= json_encode($edit_puntos) ?>;
const EDIT_FIRMAS = <?= json_encode($edit_firmas) ?>;

let cP=0, cF=0;

// ── Modal abrir/cerrar ──────────────────────────
function abrirModal() {
    limpiarForm();
    document.getElementById('mTitulo').textContent = 'Nueva Convocatoria';
    // Puntos por defecto
    ['Apertura de la sesión','Lectura y aprobación del orden del día','Informe de presidencia','Puntos varios','Clausura']
        .forEach(p => addPunto(p));
    // Firmas por defecto
    addFirma('Presidente',''); addFirma('Secretario/a','');
    // Fecha por defecto: hoy
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fFecha').value = hoy;
    document.getElementById('mConv').classList.add('show');
    document.body.style.overflow='hidden';
}

function abrirEditar(id) {
    // Carga datos vía AJAX para no recargar la página
    fetch(`ajax_conv_datos.php?id=${id}`)
        .then(r=>r.json())
        .then(d=>{
            if(!d.ok){alert(d.msg);return;}
            limpiarForm();
            document.getElementById('mTitulo').textContent='Editar Convocatoria';
            document.getElementById('fIdConv').value       = d.conv.id;
            document.getElementById('fTitulo').value       = d.conv.titulo;
            document.getElementById('fTipoReunion').value  = d.conv.tipo_reunion||d.conv.tipo||'ordinaria';
            document.getElementById('fTipoAsist').value    = d.conv.tipo_asistentes||'general';
            document.getElementById('fFecha').value        = d.conv.fecha;
            document.getElementById('fHora').value         = (d.conv.hora||'').substring(0,5);
            document.getElementById('fLugar').value        = d.conv.lugar;
            document.getElementById('fEstado').value       = d.conv.estado;
            document.getElementById('fTipo').value         = d.conv.tipo||d.conv.tipo_reunion||'ordinaria';
            d.puntos.forEach(p=>addPunto(p));
            d.firmas.forEach(f=>addFirma(f.cargo,f.nombre));
            document.getElementById('mConv').classList.add('show');
            document.body.style.overflow='hidden';
        }).catch(e=>{ alert('Error cargando datos: '+e.message); });
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow='';
}

function limpiarForm() {
    cP=0; cF=0;
    document.getElementById('frmConv').reset();
    document.getElementById('fIdConv').value='0';
    document.getElementById('listaPuntos').innerHTML='';
    document.getElementById('listaFirmas').innerHTML='';
}

// ── Puntos ───────────────────────────────────────
function addPunto(val='') {
    cP++;
    const d=document.createElement('div');
    d.className='punto-row'; d.id='pr-'+cP;
    d.innerHTML=`
        <div class="num-badge">${cP}</div>
        <input type="text" name="puntos[]" value="${escH(val)}" placeholder="Describe el punto ${cP}..."
               style="flex:1;border:1.5px solid var(--borde);border-radius:8px;padding:8px 10px;font-size:.875rem;font-family:inherit;">
        ${cP>1?`<button type="button" class="btn-rm" onclick="delPunto('pr-${cP}')"><i class="fa-solid fa-xmark"></i></button>`:''}
    `;
    document.getElementById('listaPuntos').appendChild(d);
    renumPuntos();
}
function delPunto(id){document.getElementById(id)?.remove();renumPuntos();}
function renumPuntos(){document.querySelectorAll('#listaPuntos .num-badge').forEach((e,i)=>e.textContent=i+1);}

// ── Firmas ───────────────────────────────────────
function addFirma(cargo='',nombre='') {
    cF++;
    const d=document.createElement('div');
    d.className='firma-row'; d.id='fr-'+cF;
    const sty='border:1.5px solid var(--borde);border-radius:8px;padding:8px 10px;font-size:.875rem;font-family:inherit;';
    d.innerHTML=`
        <input type="text" name="firma_cargo[]"  value="${escH(cargo)}"  placeholder="Cargo (Ej: Presidente)" style="${sty}">
        <input type="text" name="firma_nombre[]" value="${escH(nombre)}" placeholder="Nombre completo"         style="${sty}">
        <button type="button" class="btn-rm" onclick="document.getElementById('fr-${cF}').remove()"><i class="fa-solid fa-xmark"></i></button>
    `;
    document.getElementById('listaFirmas').appendChild(d);
}

// ── Sincronizar tipo con tipo_reunion ────────────
document.getElementById('fTipoReunion').addEventListener('change',function(){
    document.getElementById('fTipo').value = this.value;
});

// ── Estado ───────────────────────────────────────
function abrirCambioEstado(id, estado) {
    document.getElementById('eIdConv').value  = id;
    document.getElementById('eEstado').value  = estado;
    document.getElementById('mEstado').classList.add('show');
    document.body.style.overflow='hidden';
}

// ── Eliminar ─────────────────────────────────────
function confirmarBorrar(id, titulo) {
    if(!confirm(`¿Eliminar la convocatoria:\n"${titulo}"?\n\nEsta acción no se puede deshacer.`)) return;
    document.getElementById('bIdConv').value=id;
    document.getElementById('frmBorrar').submit();
}

// ── Cerrar con Escape ────────────────────────────
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        cerrarModal('mConv'); cerrarModal('mEstado');
    }
});

// ── Cerrar clic fuera del modal ──────────────────
['mConv','mEstado'].forEach(id=>{
    document.getElementById(id).addEventListener('click',function(e){
        if(e.target===this) cerrarModal(id);
    });
});

function escH(s){return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

// Abrir edición si viene ?editar=N en la URL
<?php if ($editando): ?>
window.addEventListener('DOMContentLoaded',()=>abrirEditar(<?= $editando['id'] ?>));
<?php endif; ?>
</script>
</body>
</html>