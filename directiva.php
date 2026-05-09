<?php
// ============================================================
// directiva.php – Módulo de Directiva y Junta de Vigilancia
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }

require __DIR__ . "/layout/bootstrap.php";

$rol      = $_SESSION['rol'] ?? $_SESSION['tipo_usuario'] ?? 'viewer';
$id_usr   = (int)($_SESSION['id_usuario'] ?? 0);
if ($id_usr && function_exists('tienePermiso') && isset($pdo)) {
    $puede_ver       = tienePermiso($pdo, $id_usr, 'directiva', 'puede_ver');
    $puede_agregar   = tienePermiso($pdo, $id_usr, 'directiva', 'puede_agregar');
    $puede_modificar = tienePermiso($pdo, $id_usr, 'directiva', 'puede_modificar');
    $puede_eliminar  = tienePermiso($pdo, $id_usr, 'directiva', 'puede_eliminar');
} else {
    $fallback = in_array(strtolower($rol), ['admin','secretario','presidente','superadmin']) || $id_usr === 1;
    $puede_ver = $puede_agregar = $puede_modificar = $puede_eliminar = $fallback;
}

if (!$puede_ver) {
    http_response_code(403);
    die('Sin permisos para ver directiva.');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Obtener períodos de directiva ─────────────────────────────
try {
    $periodos = $pdo->query("
        SELECT p.*, 
               COUNT(DISTINCT m.id) AS total_miembros,
               u.usuario AS creado_por_nombre
        FROM directiva_periodos p
        LEFT JOIN directiva_miembros m ON m.periodo_id = p.id
        LEFT JOIN usuarios u ON u.id_usuario = p.creado_por
        GROUP BY p.id
        ORDER BY p.fecha_inicio DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tablas no existen aún — mostrar aviso de instalación
    $periodos = [];
    $db_error = $e->getMessage();
}

// ── Período activo ────────────────────────────────────────────
$periodo_activo = null;
foreach ($periodos as $p) {
    if ($p['estado'] === 'activo') { $periodo_activo = $p; break; }
}

// ── Miembros del período activo ───────────────────────────────
$miembros_directiva   = [];
$miembros_vigilancia  = [];

if ($periodo_activo) {
    try {
        $stM = $pdo->prepare("
            SELECT m.*, s.nombre_completo, s.identificacion
            FROM directiva_miembros m
            LEFT JOIN socios s ON s.id_socio = m.socio_id
            WHERE m.periodo_id = ?
            ORDER BY m.tipo_junta, m.orden_cargo
        ");
        $stM->execute([$periodo_activo['id']]);
        $todos = $stM->fetchAll(PDO::FETCH_ASSOC);
        foreach ($todos as $m) {
            if ($m['tipo_junta'] === 'directiva') $miembros_directiva[] = $m;
            else $miembros_vigilancia[] = $m;
        }
    } catch (PDOException $e) {
        $miembros_directiva = $miembros_vigilancia = [];
    }
}

// ── Cargos disponibles ────────────────────────────────────────
$cargos_directiva = [
    'administrador'   => 'Administrador/a',
    'presidente'      => 'Presidente/a',
    'secretario'      => 'Secretario/a',
    'vocal_principal_1' => 'Vocal Principal 1',
    'vocal_principal_2' => 'Vocal Principal 2',
    'vocal_principal_3' => 'Vocal Principal 3',
    'vocal_principal_4' => 'Vocal Principal 4',
    'vocal_principal_5' => 'Vocal Principal 5',
    'vocal_suplente_1'  => 'Vocal Suplente 1',
    'vocal_suplente_2'  => 'Vocal Suplente 2',
    'vocal_suplente_3'  => 'Vocal Suplente 3',
    'vocal_suplente_4'  => 'Vocal Suplente 4',
    'vocal_suplente_5'  => 'Vocal Suplente 5',
];
$cargos_vigilancia = [
    'vocal_principal_1' => 'Vocal Principal 1',
    'vocal_principal_2' => 'Vocal Principal 2',
    'vocal_principal_3' => 'Vocal Principal 3',
    'vocal_suplente_1'  => 'Vocal Suplente 1',
    'vocal_suplente_2'  => 'Vocal Suplente 2',
    'vocal_suplente_3'  => 'Vocal Suplente 3',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Directiva – Asociación Santa Lucía</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --azul:#1f3a5f; --azul2:#2563eb; --verde:#16a34a; --rojo:#dc2626;
    --dorado:#b45309; --gris:#f8fafc; --borde:#e2e8f0;
    --sombra:0 2px 12px rgba(0,0,0,.08);
}
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--gris); }

/* Botones */
.btn-prim { display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;border:none;border-radius:10px;padding:10px 18px;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none;transition:.2s;box-shadow:0 4px 14px rgba(37,99,235,.25); }
.btn-prim:hover { transform:translateY(-2px);color:#fff; }
.btn-sec  { display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--azul);border:1.5px solid var(--borde);border-radius:10px;padding:9px 16px;font-weight:600;font-size:.85rem;cursor:pointer;text-decoration:none;transition:.2s; }
.btn-sec:hover { background:#f1f5f9; }
.btn-gold { display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#92400e,#b45309);color:#fff;border:none;border-radius:10px;padding:9px 16px;font-weight:700;font-size:.85rem;cursor:pointer;transition:.2s; }
.btn-sm   { padding:5px 10px;font-size:.75rem;border-radius:7px; }

/* Flash */
.flash { padding:12px 18px;border-radius:10px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:.875rem; }
.flash.success { background:#dcfce7;color:#166534;border:1px solid #bbf7d0; }
.flash.error   { background:#fee2e2;color:#991b1b;border:1px solid #fecaca; }

/* Header del período activo */
.periodo-header {
    background:linear-gradient(135deg,#1f3a5f 0%,#1e40af 60%,#1f3a5f 100%);
    border-radius:20px; padding:24px 28px; color:#fff; margin-bottom:24px;
    position:relative; overflow:hidden;
}
.periodo-header::before {
    content:''; position:absolute; top:-40px; right:-40px;
    width:200px; height:200px; background:rgba(255,255,255,.05);
    border-radius:50%;
}
.periodo-header .badge-estado {
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
    padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;
    margin-bottom:10px;
}
.periodo-header h2 { font-size:1.2rem;font-weight:800;margin:0 0 8px; }
.periodo-header .meta { display:flex;flex-wrap:wrap;gap:16px;font-size:.82rem;opacity:.85; }
.periodo-header .meta span { display:flex;align-items:center;gap:6px; }

/* Tabs */
.tabs { display:flex;gap:4px;background:#fff;border:1.5px solid var(--borde);border-radius:12px;padding:4px;margin-bottom:20px; }
.tab  { flex:1;padding:10px;text-align:center;border-radius:9px;font-weight:700;font-size:.85rem;cursor:pointer;transition:.2s;color:#64748b;border:none;background:transparent; }
.tab.active { background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;box-shadow:0 4px 10px rgba(37,99,235,.3); }

/* Grid miembros */
.miembros-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:24px; }
.miembro-card {
    background:#fff;border-radius:14px;padding:16px 18px;border:1.5px solid var(--borde);
    box-shadow:var(--sombra);display:flex;align-items:center;gap:14px;transition:.2s;
    position:relative;
}
.miembro-card:hover { border-color:var(--azul2);transform:translateY(-2px);box-shadow:0 8px 24px rgba(37,99,235,.12); }
.miembro-av {
    width:48px;height:48px;border-radius:50%;
    background:linear-gradient(135deg,#1f3a5f,#2563eb);
    color:#fff;display:flex;align-items:center;justify-content:center;
    font-weight:800;font-size:.9rem;flex-shrink:0;
    border:2px solid #e0e9ff;
}
.miembro-av.gold { background:linear-gradient(135deg,#92400e,#d97706); border-color:#fde68a; }
.miembro-cargo  { font-size:.7rem;font-weight:800;color:var(--azul2);text-transform:uppercase;letter-spacing:.5px; }
.miembro-nombre { font-size:.88rem;font-weight:700;color:#1e293b;margin:2px 0; }
.miembro-cedula { font-size:.75rem;color:#94a3b8; }
.miembro-acciones { position:absolute;top:10px;right:10px;display:flex;gap:4px;opacity:0;transition:.2s; }
.miembro-card:hover .miembro-acciones { opacity:1; }
.btn-ico { width:28px;height:28px;border-radius:7px;border:1.5px solid var(--borde);background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.75rem;transition:.2s; }
.btn-ico:hover { background:#eff6ff;border-color:var(--azul2);color:var(--azul2); }
.btn-ico.del:hover { background:#fee2e2;border-color:var(--rojo);color:var(--rojo); }

/* Períodos lista */
.periodos-lista { display:flex;flex-direction:column;gap:10px; }
.periodo-item {
    background:#fff;border-radius:12px;padding:16px 20px;border:1.5px solid var(--borde);
    display:flex;align-items:center;gap:16px;flex-wrap:wrap;
}
.periodo-item.activo { border-color:#bfdbfe;background:#eff6ff; }
.pill { display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700; }
.pill-activo   { background:#dcfce7;color:#166534; }
.pill-cerrado  { background:#f1f5f9;color:#475569; }
.pill-directiva   { background:#dbeafe;color:#1e40af; }
.pill-vigilancia  { background:#fef3c7;color:#92400e; }

/* Sin datos */
.empty-state { text-align:center;padding:60px 20px;color:#94a3b8; }
.empty-state i { font-size:3rem;display:block;margin-bottom:14px; }

/* Modal overlay */
.moverlay { display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:10000;overflow-y:auto;padding:20px;align-items:flex-start;justify-content:center; }
.moverlay.show { display:flex; }
.mbox { background:#fff;border-radius:20px;width:100%;max-width:580px;box-shadow:0 24px 60px rgba(0,0,0,.25);margin:auto; }
.mhead { background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;padding:18px 24px;border-radius:20px 20px 0 0;display:flex;justify-content:space-between;align-items:center; }
.mhead h2 { margin:0;font-size:1rem;font-weight:800; }
.mcls { background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:8px;width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem; }
.mbody { padding:24px; }
.mfoot { padding:14px 24px;border-top:1px solid var(--borde);display:flex;justify-content:flex-end;gap:10px;background:#f8fafc;border-radius:0 0 20px 20px; }

/* Formulario */
.fg { margin-bottom:16px; }
.fg label { display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px; }
.fg input,.fg select,.fg textarea {
    width:100%;background:#f8fafc;border:1.5px solid var(--borde);
    border-radius:8px;padding:9px 12px;color:#1e293b;font-size:.875rem;
    font-family:inherit;transition:.2s;
}
.fg input:focus,.fg select:focus { outline:none;border-color:var(--azul2);background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.fg-row { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.fg small { color:#94a3b8;font-size:.75rem;margin-top:3px;display:block; }

/* Sección */
.section-title { font-size:.8rem;font-weight:800;color:var(--azul);text-transform:uppercase;letter-spacing:.5px;margin:20px 0 12px;padding-bottom:6px;border-bottom:2px solid #e0e9ff; }

/* Documento PDF card */
.doc-card { background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px; }
.doc-card.pending { background:#fffbeb;border-color:#fde68a; }
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

<?php if (isset($db_error)): ?>
<div class="flash error">
    <i class="fa-solid fa-database"></i>
    Tablas no encontradas. <strong><a href="ajax_directiva.php?accion=instalar" style="color:inherit;">Haz clic aquí para instalar</a></strong>
    (<?= htmlspecialchars($db_error) ?>)
</div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:22px;">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:var(--azul);margin:0;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-people-group" style="color:var(--azul2);"></i> Directiva
        </h1>
        <p style="margin:4px 0 0;font-size:.875rem;color:#64748b;">
            Gestión de Junta Directiva y Junta de Vigilancia
        </p>
    </div>
    <?php if ($puede_agregar): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn-sec" onclick="abrirModalPeriodo()">
            <i class="fa-solid fa-plus"></i> Nuevo Período
        </button>
        <?php if ($periodo_activo): ?>
        <button class="btn-prim" onclick="abrirModalMiembro(<?= $periodo_activo['id'] ?>, 'directiva')">
            <i class="fa-solid fa-user-plus"></i> Agregar Miembro
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($periodo_activo): ?>
<!-- Período activo -->
<div class="periodo-header">
    <div class="badge-estado"><span style="width:7px;height:7px;background:#22c55e;border-radius:50%;display:inline-block;"></span> Período Activo</div>
    <h2><?= htmlspecialchars($periodo_activo['nombre'] ?? 'Período ' . date('Y', strtotime($periodo_activo['fecha_inicio']))) ?></h2>
    <div class="meta">
        <span><i class="fa-solid fa-calendar-day"></i> Desde: <?= date('d/m/Y', strtotime($periodo_activo['fecha_inicio'])) ?></span>
        <?php if ($periodo_activo['fecha_fin']): ?>
        <span><i class="fa-solid fa-calendar-xmark"></i> Hasta: <?= date('d/m/Y', strtotime($periodo_activo['fecha_fin'])) ?></span>
        <?php endif; ?>
        <span><i class="fa-solid fa-users"></i> <?= $periodo_activo['total_miembros'] ?> miembros registrados</span>
        <?php if ($periodo_activo['duracion_anos']): ?>
        <span><i class="fa-solid fa-clock"></i> <?= $periodo_activo['duracion_anos'] ?> año(s) de período</span>
        <?php endif; ?>
    </div>
    <?php if ($puede_modificar): ?>
    <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (!empty($periodo_activo['documento_pdf'])): ?>
        <a href="<?= htmlspecialchars($periodo_activo['documento_pdf']) ?>" target="_blank" class="btn-sec btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3);">
            <i class="fa-solid fa-file-pdf"></i> Ver Documento
        </a>
        <?php else: ?>
        <button class="btn-sec btn-sm" onclick="subirDocumento(<?= $periodo_activo['id'] ?>)" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3);">
            <i class="fa-solid fa-upload"></i> Subir Documento PDF
        </button>
        <?php endif; ?>
        <button class="btn-sec btn-sm" onclick="editarPeriodo(<?= $periodo_activo['id'] ?>)" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3);">
            <i class="fa-solid fa-pen"></i> Editar Período
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Tabs Junta Directiva / Vigilancia -->
<div class="tabs">
    <button class="tab active" id="tab-directiva" onclick="switchTab('directiva')">
        <i class="fa-solid fa-star"></i> Junta Directiva (<?= count($miembros_directiva) ?>)
    </button>
    <button class="tab" id="tab-vigilancia" onclick="switchTab('vigilancia')">
        <i class="fa-solid fa-eye"></i> Junta de Vigilancia (<?= count($miembros_vigilancia) ?>)
    </button>
</div>

<!-- Junta Directiva -->
<div id="panel-directiva">
    <?php if ($puede_agregar): ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <button class="btn-prim btn-sm" onclick="abrirModalMiembro(<?= $periodo_activo['id'] ?>, 'directiva')">
            <i class="fa-solid fa-user-plus"></i> Agregar a Directiva
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($miembros_directiva)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-users-slash"></i>
        <p>No hay miembros registrados en la Junta Directiva</p>
        <?php if ($puede_agregar): ?>
        <button class="btn-prim" style="margin-top:12px;" onclick="abrirModalMiembro(<?= $periodo_activo['id'] ?>, 'directiva')">
            <i class="fa-solid fa-user-plus"></i> Registrar miembros
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="miembros-grid">
        <?php foreach ($miembros_directiva as $m): 
            $partes = explode(' ', $m['nombre_completo'] ?? $m['nombre_manual'] ?? '');
            $ini = strtoupper(substr($partes[0]??'',0,1) . substr($partes[1]??'',0,1));
            $esLider = in_array($m['cargo'], ['presidente','administrador','secretario']);
        ?>
        <div class="miembro-card">
            <div class="miembro-av <?= $esLider ? 'gold' : '' ?>"><?= $ini ?></div>
            <div style="flex:1;min-width:0;">
                <div class="miembro-cargo"><?= htmlspecialchars($m['cargo_label'] ?? ucfirst(str_replace('_',' ',$m['cargo']))) ?></div>
                <div class="miembro-nombre"><?= htmlspecialchars($m['nombre_completo'] ?? $m['nombre_manual']) ?></div>
                <div class="miembro-cedula">
                    <?php if ($m['identificacion']): ?>
                    <i class="fa-solid fa-id-card" style="font-size:.65rem;"></i> <?= htmlspecialchars($m['identificacion']) ?>
                    <?php endif; ?>
                    <?php if ($m['fecha_nombramiento']): ?>
                    · desde <?= date('d/m/Y', strtotime($m['fecha_nombramiento'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($puede_modificar || $puede_eliminar): ?>
            <div class="miembro-acciones">
                <?php if ($puede_modificar): ?>
                <button class="btn-ico" onclick="editarMiembro(<?= $m['id'] ?>)" title="Editar"><i class="fa-solid fa-pen"></i></button>
                <?php endif; ?>
                <?php if ($puede_eliminar): ?>
                <button class="btn-ico del" onclick="eliminarMiembro(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre_completo'] ?? $m['nombre_manual'], ENT_QUOTES) ?>')" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Junta de Vigilancia -->
<div id="panel-vigilancia" style="display:none;">
    <?php if ($puede_agregar): ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <button class="btn-gold btn-sm" onclick="abrirModalMiembro(<?= $periodo_activo['id'] ?>, 'vigilancia')">
            <i class="fa-solid fa-user-plus"></i> Agregar a Vigilancia
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($miembros_vigilancia)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-eye-slash"></i>
        <p>No hay miembros registrados en la Junta de Vigilancia</p>
        <?php if ($puede_agregar): ?>
        <button class="btn-gold" style="margin-top:12px;" onclick="abrirModalMiembro(<?= $periodo_activo['id'] ?>, 'vigilancia')">
            <i class="fa-solid fa-user-plus"></i> Registrar miembros
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="miembros-grid">
        <?php foreach ($miembros_vigilancia as $m):
            $partes = explode(' ', $m['nombre_completo'] ?? $m['nombre_manual'] ?? '');
            $ini = strtoupper(substr($partes[0]??'',0,1) . substr($partes[1]??'',0,1));
        ?>
        <div class="miembro-card">
            <div class="miembro-av" style="background:linear-gradient(135deg,#92400e,#d97706);border-color:#fde68a;"><?= $ini ?></div>
            <div style="flex:1;min-width:0;">
                <div class="miembro-cargo" style="color:#b45309;"><?= htmlspecialchars($m['cargo_label'] ?? ucfirst(str_replace('_',' ',$m['cargo']))) ?></div>
                <div class="miembro-nombre"><?= htmlspecialchars($m['nombre_completo'] ?? $m['nombre_manual']) ?></div>
                <div class="miembro-cedula">
                    <?php if ($m['identificacion']): ?>
                    <i class="fa-solid fa-id-card" style="font-size:.65rem;"></i> <?= htmlspecialchars($m['identificacion']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($puede_modificar || $puede_eliminar): ?>
            <div class="miembro-acciones">
                <?php if ($puede_modificar): ?>
                <button class="btn-ico" onclick="editarMiembro(<?= $m['id'] ?>)" title="Editar"><i class="fa-solid fa-pen"></i></button>
                <?php endif; ?>
                <?php if ($puede_eliminar): ?>
                <button class="btn-ico del" onclick="eliminarMiembro(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre_completo'] ?? $m['nombre_manual'], ENT_QUOTES) ?>')" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="empty-state">
    <i class="fa-solid fa-people-group"></i>
    <p style="font-size:1rem;">No hay un período de directiva activo</p>
    <?php if ($puede_agregar): ?>
    <button class="btn-prim" style="margin-top:14px;" onclick="abrirModalPeriodo()">
        <i class="fa-solid fa-plus"></i> Crear primer período
    </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Historial de períodos ──────────────────────────────────── -->
<?php if (count($periodos) > 0): ?>
<div style="margin-top:32px;">
    <h3 style="font-size:1rem;font-weight:800;color:var(--azul);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-clock-rotate-left"></i> Historial de Períodos
    </h3>
    <div class="periodos-lista">
        <?php foreach ($periodos as $p): ?>
        <div class="periodo-item <?= $p['estado']==='activo'?'activo':'' ?>">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                    <span class="pill <?= $p['estado']==='activo'?'pill-activo':'pill-cerrado' ?>">
                        <?= $p['estado']==='activo' ? '● Activo' : '○ Cerrado' ?>
                    </span>
                    <strong style="font-size:.9rem;color:var(--azul);">
                        <?= htmlspecialchars($p['nombre'] ?? 'Período') ?>
                    </strong>
                </div>
                <div style="font-size:.8rem;color:#64748b;display:flex;gap:14px;flex-wrap:wrap;">
                    <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?><?= $p['fecha_fin'] ? ' → ' . date('d/m/Y', strtotime($p['fecha_fin'])) : '' ?></span>
                    <span><i class="fa-solid fa-users"></i> <?= $p['total_miembros'] ?> miembros</span>
                    <?php if ($p['duracion_anos']): ?>
                    <span><i class="fa-solid fa-hourglass"></i> <?= $p['duracion_anos'] ?> año(s)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <?php if (!empty($p['documento_pdf'])): ?>
                <a href="<?= htmlspecialchars($p['documento_pdf']) ?>" target="_blank" class="btn-sec btn-sm">
                    <i class="fa-solid fa-file-pdf"></i> Documento
                </a>
                <?php endif; ?>
                <?php if ($puede_ver || $puede_modificar || $puede_eliminar): ?>
                    <?php if ($puede_ver): ?>
                    <button class="btn-sec btn-sm" onclick="verPeriodo(<?= $p['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                    <?php endif; ?>
                    <?php if ($puede_modificar): ?>
                    <button class="btn-sec btn-sm" onclick="editarPeriodo(<?= $p['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                    <?php endif; ?>
                    <?php if ($p['estado'] !== 'activo' && $puede_eliminar): ?>
                    <button class="btn-sec btn-sm" style="color:var(--rojo);" onclick="eliminarPeriodo(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nombre']??'', ENT_QUOTES) ?>')"><i class="fa-solid fa-trash"></i></button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</section>
</main>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: Nuevo/Editar Período
══════════════════════════════════════════════════════════ -->
<div class="moverlay" id="mPeriodo">
<div class="mbox">
    <div class="mhead">
        <h2><i class="fa-solid fa-calendar-plus"></i> <span id="mPeriodoTitulo">Nuevo Período de Directiva</span></h2>
        <button class="mcls" onclick="cerrarModal('mPeriodo')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mbody">
        <input type="hidden" id="pId">
        <div class="fg">
            <label>Nombre del período</label>
            <input type="text" id="pNombre" placeholder="Ej: Directiva 2024-2026">
        </div>
        <div class="fg-row">
            <div class="fg">
                <label>Fecha de inicio</label>
                <input type="date" id="pFechaInicio" value="2024-07-24">
                <small>Registro: 24 de julio del 2024</small>
            </div>
            <div class="fg">
                <label>Duración (años)</label>
                <input type="number" id="pDuracion" value="2" min="1" max="10">
                <small>La fecha fin se calcula automáticamente</small>
            </div>
        </div>
        <div class="fg">
            <label>Estado</label>
            <select id="pEstado">
                <option value="activo">Activo</option>
                <option value="cerrado">Cerrado</option>
            </select>
        </div>
        <div class="fg">
            <label>Documento PDF (acta de nombramiento)</label>
            <input type="file" id="pDocumento" accept=".pdf">
            <small>Sube el documento oficial de registro del MIES / MAGAP</small>
        </div>
        <div class="fg">
            <label>Notas</label>
            <textarea id="pNotas" rows="2" placeholder="Observaciones opcionales..." style="resize:vertical;"></textarea>
        </div>
    </div>
    <div class="mfoot">
        <button class="btn-sec" onclick="cerrarModal('mPeriodo')">Cancelar</button>
        <button class="btn-prim" onclick="guardarPeriodo()"><i class="fa-solid fa-save"></i> Guardar Período</button>
    </div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: Agregar/Editar Miembro
══════════════════════════════════════════════════════════ -->
<div class="moverlay" id="mMiembro">
<div class="mbox">
    <div class="mhead" id="mMiembroHead">
        <h2><i class="fa-solid fa-user-plus"></i> <span id="mMiembroTitulo">Agregar Miembro</span></h2>
        <button class="mcls" onclick="cerrarModal('mMiembro')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mbody">
        <input type="hidden" id="mId">
        <input type="hidden" id="mPeriodoId">
        <input type="hidden" id="mTipoJunta" value="directiva">

        <div class="fg">
            <label>Tipo de Junta</label>
            <select id="mTipoJuntaSelect" onchange="cambiarTipoJunta(this.value)">
                <option value="directiva">Junta Directiva</option>
                <option value="vigilancia">Junta de Vigilancia</option>
            </select>
        </div>

        <div class="fg">
            <label>Cargo</label>
            <select id="mCargo" onchange="actualizarOrden()">
                <option value="">— Selecciona cargo —</option>
            </select>
        </div>

        <div class="section-title">Identificación del Miembro</div>

        <div class="fg">
            <label>Buscar socio (por nombre o cédula)</label>
            <input type="text" id="mBuscarSocio" placeholder="Escribe nombre o cédula..." oninput="buscarSocio(this.value)" autocomplete="off">
            <div id="mResultadosSocio" style="margin-top:6px;"></div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;margin:10px 0;color:#94a3b8;font-size:.8rem;">
            <div style="flex:1;height:1px;background:#e2e8f0;"></div>
            <span>o registrar manualmente</span>
            <div style="flex:1;height:1px;background:#e2e8f0;"></div>
        </div>

        <input type="hidden" id="mSocioId">
        <div class="fg-row">
            <div class="fg">
                <label>Nombres y Apellidos</label>
                <input type="text" id="mNombre" placeholder="Nombre completo" oninput="document.getElementById('mSocioId').value=''">
            </div>
            <div class="fg">
                <label>N° Cédula</label>
                <input type="text" id="mCedula" placeholder="XXXXXXXXX###">
            </div>
        </div>

        <div class="fg">
            <label>Fecha de nombramiento</label>
            <input type="date" id="mFechaNom" value="2024-07-24">
        </div>

        <div class="fg">
            <label>Período (años)</label>
            <input type="number" id="mPeriodoAnos" value="2" min="1">
        </div>
    </div>
    <div class="mfoot">
        <button class="btn-sec" onclick="cerrarModal('mMiembro')">Cancelar</button>
        <button class="btn-prim" id="btnGuardarMiembro" onclick="guardarMiembro()"><i class="fa-solid fa-save"></i> Guardar</button>
    </div>
</div>
</div>

<!-- MODAL: Subir documento -->
<div class="moverlay" id="mDocumento">
<div class="mbox" style="max-width:440px;">
    <div class="mhead">
        <h2><i class="fa-solid fa-file-pdf"></i> Subir Documento Oficial</h2>
        <button class="mcls" onclick="cerrarModal('mDocumento')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mbody">
        <input type="hidden" id="docPeriodoId">
        <p style="color:#64748b;font-size:.875rem;margin-bottom:16px;">Sube el acta de nombramiento o el documento oficial del período de directiva.</p>
        <div class="fg">
            <label>Archivo PDF</label>
            <input type="file" id="docArchivo" accept=".pdf">
        </div>
    </div>
    <div class="mfoot">
        <button class="btn-sec" onclick="cerrarModal('mDocumento')">Cancelar</button>
        <button class="btn-prim" onclick="guardarDocumento()"><i class="fa-solid fa-upload"></i> Subir PDF</button>
    </div>
</div>
</div>

<script>
// ─── Cargos ───────────────────────────────────────────────────────────────────
const CARGOS = {
    directiva: [
        {v:'administrador',   l:'Administrador/a',  o:1},
        {v:'presidente',      l:'Presidente/a',      o:2},
        {v:'secretario',      l:'Secretario/a',      o:3},
        {v:'vocal_principal_1',l:'Vocal Principal 1',o:4},
        {v:'vocal_principal_2',l:'Vocal Principal 2',o:5},
        {v:'vocal_principal_3',l:'Vocal Principal 3',o:6},
        {v:'vocal_principal_4',l:'Vocal Principal 4',o:7},
        {v:'vocal_principal_5',l:'Vocal Principal 5',o:8},
        {v:'vocal_suplente_1', l:'Vocal Suplente 1', o:9},
        {v:'vocal_suplente_2', l:'Vocal Suplente 2', o:10},
        {v:'vocal_suplente_3', l:'Vocal Suplente 3', o:11},
        {v:'vocal_suplente_4', l:'Vocal Suplente 4', o:12},
        {v:'vocal_suplente_5', l:'Vocal Suplente 5', o:13},
    ],
    vigilancia: [
        {v:'vocal_principal_1',l:'Vocal Principal 1',o:1},
        {v:'vocal_principal_2',l:'Vocal Principal 2',o:2},
        {v:'vocal_principal_3',l:'Vocal Principal 3',o:3},
        {v:'vocal_suplente_1', l:'Vocal Suplente 1', o:4},
        {v:'vocal_suplente_2', l:'Vocal Suplente 2', o:5},
        {v:'vocal_suplente_3', l:'Vocal Suplente 3', o:6},
    ]
};

function cambiarTipoJunta(tipo) {
    document.getElementById('mTipoJunta').value = tipo;
    const sel = document.getElementById('mCargo');
    sel.innerHTML = '<option value="">— Selecciona cargo —</option>';
    (CARGOS[tipo]||[]).forEach(c => {
        sel.innerHTML += `<option value="${c.v}">${c.l}</option>`;
    });
    const head = document.getElementById('mMiembroHead');
    if (tipo === 'vigilancia') {
        head.style.background = 'linear-gradient(135deg,#92400e,#b45309)';
    } else {
        head.style.background = 'linear-gradient(135deg,#1f3a5f,#2563eb)';
    }
}

function actualizarOrden() {} // Orden se calcula en el backend

// ─── Tabs ─────────────────────────────────────────────────────────────────────
function switchTab(tipo) {
    document.getElementById('panel-directiva').style.display  = tipo==='directiva'  ? '' : 'none';
    document.getElementById('panel-vigilancia').style.display = tipo==='vigilancia' ? '' : 'none';
    document.getElementById('tab-directiva').classList.toggle('active',  tipo==='directiva');
    document.getElementById('tab-vigilancia').classList.toggle('active', tipo==='vigilancia');
}

// ─── Modales ──────────────────────────────────────────────────────────────────
function cerrarModal(id) { document.getElementById(id).classList.remove('show'); }

function abrirModalPeriodo() {
    document.getElementById('pId').value = '';
    document.getElementById('pNombre').value = '';
    document.getElementById('pFechaInicio').value = '2024-07-24';
    document.getElementById('pDuracion').value = '2';
    document.getElementById('pEstado').value = 'activo';
    document.getElementById('pNotas').value = '';
    document.getElementById('mPeriodoTitulo').textContent = 'Nuevo Período de Directiva';
    document.getElementById('mPeriodo').classList.add('show');
}

function editarPeriodo(id) {
    fetch(`ajax_directiva.php?accion=get_periodo&id=${id}`)
        .then(r=>r.json()).then(d=>{
            if (!d.ok) { alert(d.msg); return; }
            const p = d.periodo;
            document.getElementById('pId').value = p.id;
            document.getElementById('pNombre').value = p.nombre||'';
            document.getElementById('pFechaInicio').value = p.fecha_inicio||'';
            document.getElementById('pDuracion').value = p.duracion_anos||2;
            document.getElementById('pEstado').value = p.estado||'activo';
            document.getElementById('pNotas').value = p.notas||'';
            document.getElementById('mPeriodoTitulo').textContent = 'Editar Período';
            document.getElementById('mPeriodo').classList.add('show');
        });
}

function abrirModalMiembro(periodoId, tipo='directiva') {
    document.getElementById('mId').value = '';
    document.getElementById('mPeriodoId').value = periodoId;
    document.getElementById('mSocioId').value = '';
    document.getElementById('mNombre').value = '';
    document.getElementById('mCedula').value = '';
    document.getElementById('mFechaNom').value = '2024-07-24';
    document.getElementById('mPeriodoAnos').value = '2';
    document.getElementById('mBuscarSocio').value = '';
    document.getElementById('mResultadosSocio').innerHTML = '';
    document.getElementById('mTipoJuntaSelect').value = tipo;
    document.getElementById('mMiembroTitulo').textContent = 'Agregar Miembro';
    cambiarTipoJunta(tipo);
    document.getElementById('mMiembro').classList.add('show');
}

function editarMiembro(id) {
    fetch(`ajax_directiva.php?accion=get_miembro&id=${id}`)
        .then(r=>r.json()).then(d=>{
            if (!d.ok) { alert(d.msg); return; }
            const m = d.miembro;
            document.getElementById('mId').value = m.id;
            document.getElementById('mPeriodoId').value = m.periodo_id;
            document.getElementById('mSocioId').value = m.socio_id||'';
            document.getElementById('mNombre').value = m.nombre_completo||m.nombre_manual||'';
            document.getElementById('mCedula').value = m.identificacion||m.cedula_manual||'';
            document.getElementById('mFechaNom').value = m.fecha_nombramiento||'';
            document.getElementById('mPeriodoAnos').value = m.periodo_anos||2;
            document.getElementById('mTipoJuntaSelect').value = m.tipo_junta||'directiva';
            document.getElementById('mBuscarSocio').value = '';
            document.getElementById('mResultadosSocio').innerHTML = '';
            document.getElementById('mMiembroTitulo').textContent = 'Editar Miembro';
            cambiarTipoJunta(m.tipo_junta||'directiva');
            document.getElementById('mCargo').value = m.cargo||'';
            document.getElementById('mMiembro').classList.add('show');
        });
}

function subirDocumento(periodoId) {
    document.getElementById('docPeriodoId').value = periodoId;
    document.getElementById('docArchivo').value = '';
    document.getElementById('mDocumento').classList.add('show');
}

// ─── Buscar socio ─────────────────────────────────────────────────────────────
let timerBio;
function buscarSocio(q) {
    clearTimeout(timerBio);
    const box = document.getElementById('mResultadosSocio');
    if (q.length < 2) { box.innerHTML=''; return; }
    timerBio = setTimeout(()=>{
        fetch(`ajax_buscar_socio.php?q=${encodeURIComponent(q)}`)
            .then(r=>r.json()).then(data=>{
                if (!Array.isArray(data)||!data.length) {
                    box.innerHTML='<p style="color:#94a3b8;font-size:.8rem;padding:6px 0;">Sin resultados — puedes registrarlo manualmente</p>';
                    return;
                }
                box.innerHTML = data.slice(0,5).map(s=>`
                    <div onclick="seleccionarSocio(${s.id},'${esc(s.nombre_completo)}','${esc(s.cedula)}')"
                         style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;margin-bottom:4px;cursor:pointer;display:flex;gap:10px;align-items:center;transition:.15s;"
                         onmouseover="this.style.background='#eff6ff';this.style.borderColor='#2563eb'"
                         onmouseout="this.style.background='';this.style.borderColor='#e2e8f0'">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1f3a5f,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.75rem;flex-shrink:0;">
                            ${(s.nombre_completo||'').split(' ').slice(0,2).map(p=>p[0]||'').join('').toUpperCase()}
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.85rem;">${esc(s.nombre_completo)}</div>
                            <div style="font-size:.73rem;color:#64748b;">${esc(s.cedula)}</div>
                        </div>
                    </div>`).join('');
            });
    }, 300);
}

function seleccionarSocio(id, nombre, cedula) {
    document.getElementById('mSocioId').value  = id;
    document.getElementById('mNombre').value   = nombre;
    document.getElementById('mCedula').value   = cedula;
    document.getElementById('mBuscarSocio').value = nombre;
    document.getElementById('mResultadosSocio').innerHTML =
        `<div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;padding:8px 12px;font-size:.82rem;color:#166534;font-weight:600;">
            ✅ Socio seleccionado: ${esc(nombre)}
        </div>`;
}

function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;'); }

// ─── Guardar período ──────────────────────────────────────────────────────────
async function guardarPeriodo() {
    const id       = document.getElementById('pId').value;
    const nombre   = document.getElementById('pNombre').value.trim();
    const inicio   = document.getElementById('pFechaInicio').value;
    const duracion = parseInt(document.getElementById('pDuracion').value)||2;
    const estado   = document.getElementById('pEstado').value;
    const notas    = document.getElementById('pNotas').value.trim();
    const file     = document.getElementById('pDocumento').files[0];

    if (!nombre || !inicio) { alert('Completa nombre y fecha de inicio'); return; }

    const fd = new FormData();
    fd.append('accion',        id ? 'editar_periodo' : 'crear_periodo');
    fd.append('id',            id);
    fd.append('nombre',        nombre);
    fd.append('fecha_inicio',  inicio);
    fd.append('duracion_anos', duracion);
    fd.append('estado',        estado);
    fd.append('notas',         notas);
    if (file) fd.append('documento', file);

    try {
        const r = await fetch('ajax_directiva.php', { method:'POST', body:fd });
        const d = await r.json();
        if (d.ok) { location.reload(); }
        else { alert('Error: ' + d.msg); }
    } catch(e) { alert('Error de red: ' + e.message); }
}

// ─── Guardar miembro ──────────────────────────────────────────────────────────
async function guardarMiembro() {
    const id        = document.getElementById('mId').value;
    const periodoId = document.getElementById('mPeriodoId').value;
    const socioId   = document.getElementById('mSocioId').value;
    const nombre    = document.getElementById('mNombre').value.trim();
    const cedula    = document.getElementById('mCedula').value.trim();
    const cargo     = document.getElementById('mCargo').value;
    const tipoJunta = document.getElementById('mTipoJuntaSelect').value;
    const fechaNom  = document.getElementById('mFechaNom').value;
    const periAnos  = document.getElementById('mPeriodoAnos').value;

    if (!cargo)  { alert('Selecciona un cargo'); return; }
    if (!nombre) { alert('Escribe el nombre del miembro'); return; }

    const fd = new FormData();
    fd.append('accion',          id ? 'editar_miembro' : 'crear_miembro');
    fd.append('id',              id);
    fd.append('periodo_id',      periodoId);
    fd.append('socio_id',        socioId);
    fd.append('nombre_manual',   nombre);
    fd.append('cedula_manual',   cedula);
    fd.append('cargo',           cargo);
    fd.append('tipo_junta',      tipoJunta);
    fd.append('fecha_nombramiento', fechaNom);
    fd.append('periodo_anos',    periAnos);

    try {
        const r = await fetch('ajax_directiva.php', { method:'POST', body:fd });
        const d = await r.json();
        if (d.ok) { location.reload(); }
        else { alert('Error: ' + d.msg); }
    } catch(e) { alert('Error: ' + e.message); }
}

// ─── Subir documento ─────────────────────────────────────────────────────────
async function guardarDocumento() {
    const periodoId = document.getElementById('docPeriodoId').value;
    const file      = document.getElementById('docArchivo').files[0];
    if (!file) { alert('Selecciona un archivo PDF'); return; }

    const fd = new FormData();
    fd.append('accion',    'subir_documento');
    fd.append('periodo_id', periodoId);
    fd.append('documento', file);

    const r = await fetch('ajax_directiva.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) { location.reload(); }
    else { alert('Error: ' + d.msg); }
}

// ─── Eliminar ─────────────────────────────────────────────────────────────────
async function eliminarMiembro(id, nombre) {
    if (!confirm(`¿Eliminar a ${nombre} de la directiva?`)) return;
    const fd = new FormData();
    fd.append('accion','eliminar_miembro'); fd.append('id',id);
    const r = await fetch('ajax_directiva.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) location.reload(); else alert(d.msg);
}

async function eliminarPeriodo(id, nombre) {
    if (!confirm(`¿Eliminar el período "${nombre}"? Se eliminarán todos sus miembros.`)) return;
    const fd = new FormData();
    fd.append('accion','eliminar_periodo'); fd.append('id',id);
    const r = await fetch('ajax_directiva.php',{method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) location.reload(); else alert(d.msg);
}

function verPeriodo(id) {
    window.location.href = `directiva.php?periodo_id=${id}`;
}

// Cerrar modales al hacer clic fuera
document.querySelectorAll('.moverlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target===m) m.classList.remove('show'); });
});

// Inicializar cargos
cambiarTipoJunta('directiva');
</script>
</body>
</html>
