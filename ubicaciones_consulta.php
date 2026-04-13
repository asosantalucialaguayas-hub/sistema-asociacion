<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";

$buscarInicial = htmlspecialchars($_GET['q'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ubicaciones - Mapas KML</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php include 'layout/modals.php'; ?>
<style>
/* ── Layout ─────────────────────────────────────────────── */
.app { display:flex; height:100vh; overflow:hidden; }
.sidebar, nav.sidebar, aside.sidebar { position:sticky; top:0; height:100vh; overflow-y:auto; flex-shrink:0; }
.content { flex:1; overflow-y:auto; height:100vh; }

/* ── Botones ────────────────────────────────────────────── */
.btn-primary   { background:#1f3a5f; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-secondary { background:#5f7c99; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-danger    { background:#ef4444; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-success   { background:#10b981; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-warning   { background:#f59e0b; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary:hover   { background:#162e4a; }
.btn-secondary:hover { background:#4a6580; }
.btn-danger:hover    { background:#dc2626; }
.btn-success:hover   { background:#059669; }
.btn-warning:hover   { background:#d97706; }

/* ── Toolbar ────────────────────────────────────────────── */
.toolbar { display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap; background:#f9fafb; padding:14px; border-radius:8px; border:1px solid #e5e7eb; }
.toolbar input  { padding:9px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; min-width:300px; flex:1; }
.toolbar input:focus { outline:none; border-color:#1f3a5f; }

/* ── Tabla ──────────────────────────────────────────────── */
.table-container { width:100%; overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead th { background:#1f3a5f; color:#fff; padding:12px 10px; text-align:left; white-space:nowrap; position:sticky; top:0; z-index:10; }
.data-table td { padding:10px; border-bottom:1px solid #e5e7eb; vertical-align:middle; }
.data-table tbody tr:hover { background:#f9fafb; }

.badge-archivos { background:#dbeafe; color:#1d4ed8; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
.badge-archivos.vacio { background:#f3f4f6; color:#9ca3af; }
.badge-archivos.verde { background:#d1fae5; color:#065f46; }

.btn-icon { width:32px; height:32px; border-radius:6px; border:none; cursor:pointer; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; margin-right:3px; }
.btn-icon.azul    { background:#3b82f6; } .btn-icon.azul:hover    { background:#2563eb; }
.btn-icon.verde   { background:#10b981; } .btn-icon.verde:hover   { background:#059669; }
.btn-icon.rojo    { background:#ef4444; } .btn-icon.rojo:hover    { background:#dc2626; }
.btn-icon.naranja { background:#f59e0b; } .btn-icon.naranja:hover { background:#d97706; }
.btn-icon.gris    { background:#6b7280; } .btn-icon.gris:hover    { background:#4b5563; }
.btn-icon.violeta { background:#7c3aed; } .btn-icon.violeta:hover { background:#6d28d9; }

/* ── Paginación ─────────────────────────────────────────── */
.paginacion { display:flex; align-items:center; gap:6px; margin-top:16px; justify-content:center; flex-wrap:wrap; }
.paginacion button { padding:7px 13px; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-size:13px; font-weight:600; }
.paginacion button:hover   { background:#f3f4f6; }
.paginacion button.active  { background:#1f3a5f; color:#fff; border-color:#1f3a5f; }
.paginacion button:disabled{ opacity:.4; cursor:not-allowed; }
.info-paginacion { text-align:center; margin-top:6px; font-size:13px; color:#6b7280; }

/* ── Modal ──────────────────────────────────────────────── */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.6); z-index:9999999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,.3); padding:28px; position:relative; width:95%; max-height:90vh; overflow:auto; }
.modal-box.grande  { max-width:920px; }
.modal-box.pequeno { max-width:500px; }
.modal-box.mediano { max-width:700px; }
.close-btn { position:absolute; top:14px; right:14px; width:36px; height:36px; border-radius:50%; border:none; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.2); cursor:pointer; font-size:18px; z-index:10; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:9px 11px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; box-sizing:border-box; }
.form-actions { margin-top:18px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }

.socio-info-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 16px; margin-bottom:16px; }
.socio-info-box p { margin:3px 0; font-size:13px; }

/* ── Archivo item ───────────────────────────────────────── */
.archivo-item { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:8px; background:#f9fafb; gap:10px; flex-wrap:wrap; }
.archivo-item:hover { background:#f3f4f6; }
.archivo-info { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
.archivo-icon { font-size:22px; }
.archivo-icon.kml { color:#10b981; }
.archivo-icon.kmz { color:#f59e0b; }
.archivo-nombre { font-weight:600; font-size:13px; color:#1f3a5f; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:300px; }
.archivo-codigo { display:inline-block; background:#e0f2fe; color:#0369a1; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; margin-left:6px; letter-spacing:.3px; }
.archivo-meta   { font-size:11px; color:#6b7280; }
.archivo-acciones { display:flex; gap:6px; flex-shrink:0; }

/* ── Upload zone ────────────────────────────────────────── */
.upload-zone { border:2px dashed #d1d5db; border-radius:10px; padding:30px; text-align:center; cursor:pointer; transition:all .2s; background:#fafafa; }
.upload-zone:hover, .upload-zone.drag { border-color:#1f3a5f; background:#eff6ff; }
.upload-zone i { font-size:36px; color:#9ca3af; margin-bottom:10px; display:block; }
.upload-zone.drag i { color:#1f3a5f; }
.upload-zone p { margin:4px 0; color:#6b7280; font-size:13px; }

/* ── Cola de archivos múltiples ─────────────────────────── */
.cola-archivos { margin-top:14px; display:flex; flex-direction:column; gap:8px; }
.cola-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; display:flex; flex-direction:column; gap:8px; }
.cola-item.cola-ok    { border-color:#10b981; background:#f0fdf4; }
.cola-item.cola-error { border-color:#ef4444; background:#fef2f2; }
.cola-item.cola-subiendo { border-color:#3b82f6; background:#eff6ff; }
.cola-item-header { display:flex; align-items:center; gap:8px; }
.cola-item-nombre { font-weight:600; font-size:13px; color:#1f3a5f; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cola-item-estado { font-size:12px; font-weight:700; }
.cola-item-estado.ok      { color:#10b981; }
.cola-item-estado.error   { color:#ef4444; }
.cola-item-estado.pending { color:#6b7280; }
.cola-item-estado.uploading { color:#3b82f6; }
.cola-item-inputs { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.cola-item-inputs input { padding:7px 10px; border-radius:7px; border:1px solid #d1d5db; font-size:13px; width:100%; box-sizing:border-box; }
.cola-item-inputs input.input-error { border-color:#ef4444; background:#fef2f2; }
.cola-item-inputs input.input-ok    { border-color:#10b981; }
.cola-item-remove { width:28px; height:28px; border-radius:6px; border:none; background:#fca5a5; color:#dc2626; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
.cola-item-remove:hover { background:#ef4444; color:#fff; }
.cola-hint { font-size:11px; color:#9ca3af; margin-top:2px; }

/* ── Barra progreso bulk ────────────────────────────────── */
.bulk-progress { background:#e5e7eb; border-radius:999px; height:10px; overflow:hidden; margin:10px 0; }
.bulk-progress-bar { height:100%; background:linear-gradient(90deg,#1f3a5f,#3b82f6); border-radius:999px; transition:width .3s; }
.bulk-info { font-size:13px; color:#374151; font-weight:600; text-align:center; margin-bottom:8px; }

/* ── Mapa ───────────────────────────────────────────────── */
#mapaContainer { width:100%; height:420px; border-radius:10px; border:1px solid #e5e7eb; overflow:hidden; background:#f3f4f6; position:relative; }
.mapa-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#9ca3af; font-size:14px; gap:12px; }
.mapa-placeholder i { font-size:48px; }

/* ── Tabs ───────────────────────────────────────────────── */
.tabs { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:18px; }
.tab-btn { padding:10px 20px; border:none; background:none; cursor:pointer; font-size:13px; font-weight:600; color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px; }
.tab-btn.active { color:#1f3a5f; border-bottom-color:#1f3a5f; }
.tab-content { display:none; }
.tab-content.active { display:block; }

/* ── Stats mini ─────────────────────────────────────────── */
.stats-mini { display:flex; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
.stat-mini { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px 18px; text-align:center; flex:1; min-width:120px; }
.stat-mini h5 { margin:0 0 4px; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
.stat-mini p  { margin:0; font-size:22px; font-weight:700; color:#1f3a5f; }
.btn-actions { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }

/* ── Código badge tabla ─────────────────────────────────── */
.badge-codigo { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; font-family:monospace; }

/* ══════════════════════════════════════════════════════════
   MODAL RESUMEN — ESTILOS
══════════════════════════════════════════════════════════ */
.resumen-header { background:linear-gradient(135deg,#1f3a5f,#2563eb); color:#fff; border-radius:10px; padding:16px 20px; margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.resumen-header h3 { margin:0; font-size:15px; font-weight:700; }
.resumen-header p  { margin:4px 0 0; font-size:12px; opacity:.8; }
.resumen-stats { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.resumen-stat { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 16px; text-align:center; flex:1; min-width:100px; }
.resumen-stat h6 { margin:0 0 3px; font-size:10px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
.resumen-stat p  { margin:0; font-size:20px; font-weight:700; color:#1f3a5f; }
.resumen-stat.verde p { color:#10b981; }
.resumen-stat.azul  p { color:#3b82f6; }
.resumen-stat.ambar p { color:#f59e0b; }

/* Tabla de lotes */
.lotes-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.lotes-table thead th { background:#1f3a5f; color:#fff; padding:9px 10px; text-align:left; font-size:11px; letter-spacing:.3px; text-transform:uppercase; }
.lotes-table td { padding:9px 10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.lotes-table tbody tr:hover { background:#f8fafc; }
.lotes-table tbody tr:last-child td { border-bottom:none; }
.badge-ocupado { background:#dcfce7; color:#166534; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
.badge-libre   { background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
.hectareas-val { font-weight:700; color:#1f3a5f; font-family:monospace; }
.hectareas-nd  { color:#9ca3af; font-style:italic; font-size:11px; }

.resumen-loading { text-align:center; padding:30px; color:#6b7280; font-size:14px; }

/* ══════════════════════════════════════════════════════════
   MODAL RESUMEN GLOBAL — ESTILOS (BLOQUE 2)
══════════════════════════════════════════════════════════ */
.rg-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999999;align-items:center;justify-content:center;backdrop-filter:blur(3px)}
.rg-overlay.active{display:flex}
.rg-box{background:#fff;border-radius:14px;box-shadow:0 30px 80px rgba(0,0,0,.35);width:96%;max-width:1050px;max-height:92vh;overflow:hidden;display:flex;flex-direction:column}
.rg-head{background:linear-gradient(135deg,#1f3a5f,#4f46e5);color:#fff;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.rg-head h2{margin:0;font-size:16px;font-weight:700;display:flex;align-items:center;gap:10px}
.rg-close{width:34px;height:34px;border-radius:50%;border:none;background:rgba(255,255,255,.15);color:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s}
.rg-close:hover{background:rgba(255,255,255,.3)}
.rg-tabs{display:flex;border-bottom:2px solid #e5e7eb;padding:0 20px;background:#f9fafb;flex-shrink:0}
.rg-tab{padding:11px 20px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#6b7280;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:6px}
.rg-tab.active{color:#4f46e5;border-bottom-color:#4f46e5}
.rg-tab:hover:not(.active){color:#374151}
.rg-toolbar{display:flex;gap:10px;align-items:center;padding:14px 20px;border-bottom:1px solid #e5e7eb;flex-wrap:wrap;flex-shrink:0;background:#fff}
.rg-search{padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;min-width:220px;flex:1;max-width:340px}
.rg-search:focus{outline:none;border-color:#4f46e5}
.rg-stats{display:flex;gap:10px;padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e5e7eb;flex-shrink:0;flex-wrap:wrap}
.rg-stat{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;text-align:center;flex:1;min-width:110px}
.rg-stat h6{margin:0 0 2px;font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px}
.rg-stat p{margin:0;font-size:18px;font-weight:700;color:#1f3a5f}
.rg-stat.violeta p{color:#7c3aed}
.rg-stat.verde   p{color:#10b981}
.rg-stat.ambar   p{color:#f59e0b}
.rg-stat.rojo    p{color:#ef4444}
.rg-body{flex:1;overflow-y:auto;min-height:0}
.rg-body::-webkit-scrollbar{width:5px}
.rg-body::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px}
.rg-tc{display:none;padding:0}
.rg-tc.active{display:block}
.rg-table{width:100%;border-collapse:collapse;font-size:12.5px}
.rg-table thead th{background:#1f3a5f;color:#fff;padding:10px 10px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;position:sticky;top:0;z-index:5}
.rg-table td{padding:9px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.rg-table tbody tr:hover{background:#f8fafc}
.rg-table tbody tr:last-child td{border-bottom:none}
.rg-ha{font-weight:700;color:#1f3a5f;font-family:monospace}
.rg-ha-nd{color:#9ca3af;font-style:italic;font-size:11px}
.rg-cod-list{display:flex;flex-wrap:wrap;gap:4px}
.rg-cod{background:#e0f2fe;color:#0369a1;font-size:10.5px;font-weight:700;padding:2px 7px;border-radius:999px;font-family:monospace;white-space:nowrap}
.rg-no-arch{background:#fef3c7;color:#92400e;font-size:10.5px;padding:2px 8px;border-radius:999px;font-style:italic}
.libre-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;padding:16px 20px}
.libre-chip{background:#f0fdf4;border:1px solid #10b981;border-radius:8px;padding:8px 12px;text-align:center;font-family:monospace;font-weight:700;font-size:12px;color:#065f46;cursor:default}
.libre-chip:hover{background:#dcfce7}
.ocupado-chip{background:#fef2f2;border:1px solid #ef4444;border-radius:8px;padding:8px 12px;text-align:center;font-family:monospace;font-weight:700;font-size:12px;color:#991b1b}
.rg-foot{padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;background:#f9fafb}
.rg-loading{text-align:center;padding:40px;color:#6b7280;font-size:14px}
</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>

<section class="page">
<h1><i class="fa fa-map-location-dot" style="color:#1f3a5f;margin-right:8px;"></i> Ubicaciones y Mapas KML</h1>

<!-- Stats -->
<div class="stats-mini">
    <div class="stat-mini"><h5>Total Socios</h5><p id="stTotal"><i class="fa fa-spinner fa-spin" style="font-size:16px;"></i></p></div>
    <div class="stat-mini"><h5>Con Ubicación</h5><p id="stConUbic" style="color:#10b981;"><i class="fa fa-spinner fa-spin" style="font-size:16px;"></i></p></div>
    <div class="stat-mini"><h5>Sin Ubicación</h5><p id="stSinUbic" style="color:#ef4444;"><i class="fa fa-spinner fa-spin" style="font-size:16px;"></i></p></div>
    <div class="stat-mini"><h5>Total Archivos</h5><p id="stArchivos" style="color:#3b82f6;"><i class="fa fa-spinner fa-spin" style="font-size:16px;"></i></p></div>
</div>

<!-- Acciones -->
<div class="btn-actions">
    <a href="mapa_global.php" target="_blank" style="text-decoration:none;">
        <button class="btn-primary" style="background:linear-gradient(135deg,#0d9488,#0891b2);border:none;box-shadow:0 4px 14px rgba(13,148,136,.35);">
            <i class="fa fa-globe"></i> Ver Mapa Global KML
        </button>
    </a>
    <button class="btn-warning" onclick="exportarTodos()">
        <i class="fa fa-file-zipper"></i> Exportar Todo (ZIP)
    </button>
    <!-- BLOQUE 1 — BOTÓN RESUMEN GLOBAL -->
    <button class="btn-primary" onclick="abrirResumenGlobal()"
            style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border:none;box-shadow:0 4px 14px rgba(124,58,237,.35);">
        <i class="fa fa-table-list"></i> Resumen Global
    </button>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <input type="text" id="inputBuscar"
           placeholder="🔍 Buscar por cédula, nombre o código SLC..."
           oninput="buscarConDelay()"
           value="<?= $buscarInicial ?>">
    <select id="filtroKml" onchange="cargarSocios(1)" style="padding:9px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:14px;min-width:170px;">
        <option value="">— Archivos KML —</option>
        <option value="con">✅ Con archivos KML</option>
        <option value="sin">❌ Sin archivos KML</option>
    </select>
    <select id="filtroAdendum" onchange="cargarSocios(1)" style="padding:9px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:14px;min-width:160px;">
        <option value="">— Adendum —</option>
        <option value="1">Adendum 1</option>
        <option value="2">Adendum 2</option>
    </select>
    <button class="btn-primary" onclick="cargarSocios(1)"><i class="fa fa-search"></i> Buscar</button>
    <button class="btn-secondary" onclick="limpiarBusqueda()"><i class="fa fa-rotate-left"></i> Limpiar</button>
    <a href="conversor_kml.php" target="_blank">
      <button class="btn-primary"><i class="fa fa-map"></i> Mini-QGIS</button>
    </a>
</div>

<!-- Tabla -->
<div class="form-card">
<div class="table-container">
<table class="data-table">
<thead>
<tr>
    <th>#</th>
    <th>Cédula</th>
    <th>Productor/a</th>
    <th>Zona</th>
    <th style="text-align:center;">Adendum</th>
    <th style="text-align:center;">Archivos KML/KMZ</th>
    <th style="text-align:center;">Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTabla">
    <tr><td colspan="7" style="text-align:center;padding:30px;color:#6b7280;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>
</tbody>
</table>
</div>
<div class="paginacion" id="paginacion"></div>
<div class="info-paginacion" id="infoPaginacion"></div>
</div>
</section>
</main>
</div>

<!-- ══ MODAL: Gestión ubicaciones ══ -->
<div id="modalUbicacion" class="modal-overlay">
<div class="modal-box grande">
<button class="close-btn" onclick="cerrarModal('modalUbicacion')">×</button>
<h2 style="margin-top:0;color:#1f3a5f;"><i class="fa fa-map-location-dot"></i> Ubicaciones del Productor</h2>

<div class="socio-info-box" id="infoSocioModal"></div>

<div class="tabs">
    <button class="tab-btn active" id="tabBtnArchivos" onclick="cambiarTab('tabArchivos',this)"><i class="fa fa-folder"></i> Archivos</button>
    <button class="tab-btn" id="tabBtnMapa" onclick="cambiarTab('tabMapa',this)"><i class="fa fa-map"></i> Ver Mapa</button>
    <button class="tab-btn" id="tabBtnSubir" onclick="cambiarTab('tabSubir',this)"><i class="fa fa-upload"></i> Subir Archivos</button>
</div>

<!-- Tab: Archivos -->
<div id="tabArchivos" class="tab-content active">
    <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
        <button class="btn-warning" onclick="exportarSocio()">
            <i class="fa fa-file-zipper"></i> Exportar archivos de este socio (ZIP)
        </button>
    </div>
    <div id="listaArchivos">
        <p style="text-align:center;color:#9ca3af;padding:20px;"><i class="fa fa-spinner fa-spin"></i> Cargando...</p>
    </div>
</div>

<!-- Tab: Mapa -->
<div id="tabMapa" class="tab-content">
    <div style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button onclick="centrarMapa()" style="padding:8px 14px;border-radius:8px;background:#1f3a5f;color:#fff;border:none;cursor:pointer;font-weight:600;font-size:13px;"><i class="fa fa-crosshairs"></i> Centrar</button>
        <button onclick="if(mapaLeaflet)mapaLeaflet.zoomIn()" style="padding:8px 12px;border-radius:8px;background:#5f7c99;color:#fff;border:none;cursor:pointer;font-size:13px;"><i class="fa fa-plus"></i></button>
        <button onclick="if(mapaLeaflet)mapaLeaflet.zoomOut()" style="padding:8px 12px;border-radius:8px;background:#5f7c99;color:#fff;border:none;cursor:pointer;font-size:13px;"><i class="fa fa-minus"></i></button>
        <button onclick="toggleCapas()" style="padding:8px 12px;border-radius:8px;background:#5f7c99;color:#fff;border:none;cursor:pointer;font-size:13px;" title="Cambiar capa base"><i class="fa fa-layer-group"></i></button>
        <button onclick="mostrarTodosKml()" style="padding:8px 14px;border-radius:8px;background:#10b981;color:#fff;border:none;cursor:pointer;font-weight:600;font-size:13px;"><i class="fa fa-map"></i> Ver todos</button>
    </div>
    <div id="listaCapasMapa" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;min-height:10px;"></div>
    <div id="mapaContainer">
        <div class="mapa-placeholder">
            <i class="fa fa-map"></i>
            <span>Haz clic en el ícono de mapa <i class="fa fa-map" style="color:#3b82f6;"></i> en la lista de archivos</span>
        </div>
    </div>
</div>

<!-- ══ Tab: Subir (MULTI-ARCHIVO) ══ -->
<div id="tabSubir" class="tab-content">
    <input type="hidden" id="subirIdSocio">

    <div class="upload-zone" id="uploadZone"
         onclick="document.getElementById('archivoInput').click()"
         ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFile(event)">
        <i class="fa fa-cloud-arrow-up"></i>
        <p><strong>Clic para seleccionar</strong> o arrastra aquí</p>
        <p>Formatos: <strong>.kml</strong>, <strong>.kmz</strong> — Puedes seleccionar <strong>múltiples archivos</strong> a la vez — Máx. 10MB c/u</p>
    </div>
    <input type="file" id="archivoInput" accept=".kml,.kmz" multiple style="display:none;" onchange="onArchivosSeleccionados(this.files)">

    <div id="colaArchivos" class="cola-archivos" style="display:none;"></div>

    <div id="bulkProgressWrap" style="display:none;margin-top:12px;">
        <div class="bulk-info" id="bulkInfo">Preparando...</div>
        <div class="bulk-progress"><div class="bulk-progress-bar" id="bulkBar" style="width:0%"></div></div>
    </div>

    <div class="form-actions" id="bulkActions" style="display:none;">
        <button type="button" class="btn-secondary" onclick="limpiarCola()"><i class="fa fa-trash"></i> Limpiar cola</button>
        <button type="button" class="btn-success" id="btnSubirTodos" onclick="subirTodos()"><i class="fa fa-upload"></i> Subir todos</button>
    </div>
</div>

</div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL RESUMEN DE LOTES
══════════════════════════════════════════════════════════ -->
<div id="modalResumen" class="modal-overlay">
<div class="modal-box mediano">
<button class="close-btn" onclick="cerrarModal('modalResumen')">×</button>
<h2 style="margin-top:0;color:#1f3a5f;"><i class="fa fa-eye"></i> Resumen de Lotes KML</h2>

<div id="resumenContenido">
    <div class="resumen-loading"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#3b82f6;margin-bottom:8px;display:block;"></i>Cargando resumen...</div>
</div>

<div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end;flex-wrap:wrap;">
    <button class="btn-success" onclick="exportarResumenExcel()" id="btnExportarResumen" style="display:none;">
        <i class="fa fa-file-excel"></i> Exportar a Excel
    </button>
    <button class="btn-secondary" onclick="cerrarModal('modalResumen')">
        <i class="fa fa-times"></i> Cerrar
    </button>
</div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════
     BLOQUE 2 — MODAL RESUMEN GLOBAL
══════════════════════════════════════════════════════════ -->
<div id="modalResumenGlobal" class="rg-overlay">
  <div class="rg-box">
    <div class="rg-head">
      <h2><i class="fa fa-table-list"></i> Resumen Global de Lotes KML</h2>
      <button class="rg-close" onclick="cerrarResumenGlobal()"><i class="fa fa-times"></i></button>
    </div>

    <!-- Stats -->
    <div class="rg-stats" id="rgStats">
      <div class="rg-stat"><h6>Socios activos</h6><p id="rgStTotal">—</p></div>
      <div class="rg-stat verde"><h6>Con KML</h6><p id="rgStConKml">—</p></div>
      <div class="rg-stat rojo"><h6>Sin KML</h6><p id="rgStSinKml">—</p></div>
      <div class="rg-stat violeta"><h6>Total ha registradas</h6><p id="rgStHa">—</p></div>
      <div class="rg-stat ambar"><h6>Códigos libres</h6><p id="rgStLibres">—</p></div>
    </div>

    <!-- Tabs -->
    <div class="rg-tabs">
      <button class="rg-tab active" onclick="cambiarRgTab('rgTabSocios',this)">
        <i class="fa fa-users"></i> Socios y Hectáreas
      </button>
      <button class="rg-tab" onclick="cambiarRgTab('rgTabLibres',this)">
        <i class="fa fa-circle-check"></i> Códigos Disponibles
      </button>
    </div>

    <!-- Toolbar -->
    <div class="rg-toolbar">
      <input type="text" class="rg-search" id="rgBuscar" placeholder="🔍 Buscar socio, cédula o código..." oninput="filtrarResumenGlobal()">
      <button onclick="exportarResumenGlobalExcel()"
              style="padding:8px 16px;border-radius:8px;background:#10b981;color:#fff;border:none;cursor:pointer;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-file-excel"></i> Exportar Excel
      </button>
    </div>

    <!-- Cuerpo -->
    <div class="rg-body">
      <!-- TAB: Socios y Hectáreas -->
      <div id="rgTabSocios" class="rg-tc active">
        <div id="rgContenidoSocios">
          <div class="rg-loading"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#4f46e5;display:block;margin-bottom:8px;"></i>Cargando datos...</div>
        </div>
      </div>

      <!-- TAB: Códigos Disponibles -->
      <div id="rgTabLibres" class="rg-tc">
        <div id="rgContenidoLibres">
          <div class="rg-loading"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#10b981;display:block;margin-bottom:8px;"></i>Calculando códigos...</div>
        </div>
      </div>
    </div>

    <div class="rg-foot">
      <button onclick="cerrarResumenGlobal()"
              style="padding:8px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:#374151;">
        <i class="fa fa-times"></i> Cerrar
      </button>
    </div>
  </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   VARIABLES GLOBALES
══════════════════════════════════════════════════════════════ */
let paginaActual = 1;
let buscarTimer  = null;
let socioActual  = null;
let mapaLeaflet  = null;
let colaFiles    = [];
let resumenActual = null;

window.onload = function() {
    cargarSocios(1);
    cargarStats();
};

/* ── Stats ────────────────────────────────────────────────── */
function cargarStats() {
    fetch('ubicaciones_api.php?accion=stats')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            document.getElementById('stTotal').textContent    = data.stats.total;
            document.getElementById('stConUbic').textContent  = data.stats.con_ubic;
            document.getElementById('stSinUbic').textContent  = data.stats.sin_ubic;
            document.getElementById('stArchivos').textContent = data.stats.archivos;
        }).catch(() => {});
}

/* ── Cargar socios ────────────────────────────────────────── */
function cargarSocios(pagina) {
    pagina       = pagina || paginaActual;
    paginaActual = pagina;
    const q      = (document.getElementById('inputBuscar').value || '').trim();
    const tbody  = document.getElementById('cuerpoTabla');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:25px;color:#6b7280;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>';

    const conKml   = document.getElementById('filtroKml')?.value || '';
    const adendumF = document.getElementById('filtroAdendum')?.value || '';
    const url = `ubicaciones_api.php?accion=buscar_socios&pagina=${pagina}&q=${encodeURIComponent(q)}&con_kml=${encodeURIComponent(conKml)}&adendum_f=${encodeURIComponent(adendumF)}`;

    fetch(url)
        .then(r => r.text())
        .then(txt => {
            let data;
            try { data = JSON.parse(txt); }
            catch(e) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:25px;color:#ef4444;">
                    ❌ El servidor devolvió una respuesta inválida.<br>
                    <small style="font-family:monospace;color:#9ca3af;">${txt.substring(0,300).replace(/</g,'&lt;')}</small>
                </td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:25px;color:#ef4444;">❌ Error: ${data.message || 'desconocido'}</td></tr>`;
                return;
            }
            if (!data.datos || !data.datos.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#6b7280;">No se encontraron socios.</td></tr>';
                document.getElementById('paginacion').innerHTML       = '';
                document.getElementById('infoPaginacion').textContent = '';
                return;
            }
            const inicio = (data.pagina - 1) * data.porPagina;
            data.datos.forEach((s, idx) => {
                const total  = parseInt(s.total_archivos || 0);
                const adendum = parseInt(s.adendum || 0);
                const adendumBadge = adendum === 2
                    ? '<span style="background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;">Adendum 2</span>'
                    : adendum === 1
                        ? '<span style="background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;">Adendum 1</span>'
                        : '<span style="background:#f3f4f6;color:#9ca3af;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;">-</span>';
                const badge = total > 0
                    ? `<span class="badge-archivos verde"><i class="fa fa-file"></i> ${total} archivo${total>1?'s':''}</span>`
                    : `<span class="badge-archivos vacio"><i class="fa fa-file-circle-xmark"></i> Sin archivos</span>`;
                tbody.innerHTML += `
                <tr>
                    <td>${inicio + idx + 1}</td>
                    <td>${s.identificacion || '-'}</td>
                    <td><strong>${s.nombre_completo || '-'}</strong></td>
                    <td>${s.zona || '-'}</td>
                    <td style="text-align:center;">${adendumBadge}</td>
                    <td style="text-align:center;">${badge}</td>
                    <td style="text-align:center;">
                        <button class="btn-icon azul" title="Ver / Gestionar ubicaciones"
                            onclick='abrirUbicaciones(${s.id_socio},"${esc(s.identificacion)}","${esc(s.nombre_completo)}","${esc(s.zona||'')}","${esc(s.comunidad_grupo||'')}","${esc(s.codigo_slc||'')}",${s.proximo_lote||1})'>
                            <i class="fa fa-map-location-dot"></i>
                        </button>
                        <button class="btn-icon" style="background:#0891b2;" title="Generar plano catastral"
                            onclick="window.open('plano_catastral.php?id_socio=${s.id_socio}','_blank')">
                            <i class="fa-solid fa-globe"></i>
                        </button>
                        <button class="btn-icon violeta" title="Ver resumen de lotes y hectáreas"
                            onclick='abrirResumen(${s.id_socio},"${esc(s.identificacion)}","${esc(s.nombre_completo)}","${esc(s.zona||'')}","${esc(s.codigo_slc||'')}",${total})'>
                            <i class="fa fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            });
            renderPaginacion(data.pagina, data.totalPaginas, data.total, data.porPagina);
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:25px;color:#ef4444;">❌ Error al cargar. Ver consola.</td></tr>';
            console.error(err);
        });
}

function esc(s) { return (s||'').replace(/"/g,'&quot;').replace(/'/g,"\\'"); }
function buscarConDelay() { clearTimeout(buscarTimer); buscarTimer = setTimeout(() => cargarSocios(1), 400); }
function limpiarBusqueda() {
    document.getElementById('inputBuscar').value = '';
    const fk = document.getElementById('filtroKml'); if(fk) fk.value = '';
    const fa = document.getElementById('filtroAdendum'); if(fa) fa.value = '';
    cargarSocios(1);
}

/* ══════════════════════════════════════════════════════════════
   MODAL RESUMEN DE LOTES
══════════════════════════════════════════════════════════════ */
async function abrirResumen(idSocio, cedula, nombre, zona, codigoSlc, totalArchivos) {
    resumenActual = null;
    document.getElementById('btnExportarResumen').style.display = 'none';
    document.getElementById('resumenContenido').innerHTML =
        '<div class="resumen-loading"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#3b82f6;margin-bottom:8px;display:block;"></i>Cargando resumen...</div>';
    document.getElementById('modalResumen').classList.add('active');

    let archivos = [];
    try {
        const r = await fetch(`ubicaciones_api.php?accion=listar&id_socio=${idSocio}`);
        const j = await r.json();
        if (j.success) archivos = j.datos;
    } catch(e) {}

    const lotes = [];
    let totalHa = 0;

    for (const arch of archivos) {
        let hectareas = null;

        if (arch.atributos) {
            try {
                const atrs = typeof arch.atributos === 'string' ? JSON.parse(arch.atributos) : arch.atributos;
                if (Array.isArray(atrs)) {
                    const areaAttr = atrs.find(a => {
                        const k = (a.k||'').toLowerCase();
                        return k.includes('area') || k.includes('área') || k.includes('hectarea') || k.includes('ha') || a.tipo === 'area';
                    });
                    if (areaAttr) hectareas = parseFloat(areaAttr.v);
                }
            } catch(e) {}
        }

        if (hectareas === null) {
            try {
                const r2 = await fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${arch.id_ubicacion}`);
                const j2 = await r2.json();
                if (j2.success) {
                    const kmlStr = atob(j2.kml);
                    if (j2.atributos && j2.atributos.length) {
                        const areaAttr = j2.atributos.find(a => {
                            const k = (a.k||'').toLowerCase();
                            return k.includes('area') || k.includes('área') || k.includes('ha') || a.tipo === 'area';
                        });
                        if (areaAttr) hectareas = parseFloat(areaAttr.v);
                    }
                    if (hectareas === null) {
                        hectareas = calcularAreaDesdeKml(kmlStr);
                    }
                }
            } catch(e) {}
        }

        if (hectareas !== null && !isNaN(hectareas)) totalHa += hectareas;

        lotes.push({
            id_ubicacion: arch.id_ubicacion,
            codigo:       arch.codigo_archivo || arch.nombre_archivo,
            nombre:       arch.nombre_archivo,
            hectareas,
            descripcion:  arch.descripcion || '',
            fecha:        arch.fecha_subida,
            tipo:         arch.tipo_archivo,
        });
    }

    resumenActual = { idSocio, cedula, nombre, zona, codigoSlc, lotes, totalHa, totalArchivos: archivos.length };
    renderResumen(resumenActual);
    document.getElementById('btnExportarResumen').style.display = archivos.length ? 'flex' : 'none';
}

function calcularAreaDesdeKml(kmlStr) {
    try {
        const doc = new DOMParser().parseFromString(kmlStr, 'text/xml');
        const descEl = doc.querySelector('description');
        if (descEl) {
            const content = descEl.textContent || '';
            const dd = new DOMParser().parseFromString(content, 'text/html');
            const rows = dd.querySelectorAll('tr');
            for (const row of rows) {
                const tds = row.querySelectorAll('td');
                if (tds.length >= 2) {
                    const k = (tds[0].textContent||'').toLowerCase();
                    if (k.includes('area') || k.includes('área') || k.includes('hectarea') || k === 'ha') {
                        const v = parseFloat((tds[1].textContent||'').replace(',','.'));
                        if (!isNaN(v)) return v;
                    }
                }
            }
        }
        const simpleDatas = doc.querySelectorAll('SimpleData');
        for (const sd of simpleDatas) {
            const n = (sd.getAttribute('name')||'').toLowerCase();
            if (n.includes('area') || n.includes('área') || n.includes('hectarea')) {
                const v = parseFloat((sd.textContent||'').replace(',','.'));
                if (!isNaN(v)) return v;
            }
        }
        const datas = doc.querySelectorAll('ExtendedData > Data');
        for (const d of datas) {
            const n = (d.getAttribute('name')||'').toLowerCase();
            if (n.includes('area') || n.includes('área') || n.includes('hectarea')) {
                const valEl = d.querySelector('value');
                if (valEl) {
                    const v = parseFloat((valEl.textContent||'').replace(',','.'));
                    if (!isNaN(v)) return v;
                }
            }
        }
        const coordEls = doc.querySelectorAll('coordinates');
        if (!coordEls.length) return null;
        let allCoords = [];
        coordEls.forEach(el => {
            (el.textContent||'').trim().split(/\s+/).forEach(c => {
                const p = c.split(',');
                if (p.length >= 2) {
                    const lon = parseFloat(p[0]), lat = parseFloat(p[1]);
                    if (!isNaN(lon) && !isNaN(lat)) allCoords.push([lat, lon]);
                }
            });
        });
        if (allCoords.length > 3) {
            let area = 0;
            const n2 = allCoords.length, R = 6371000;
            for (let i = 0; i < n2-1; i++) {
                const lat1 = allCoords[i][0]*Math.PI/180, lat2 = allCoords[i+1][0]*Math.PI/180;
                const dlon = (allCoords[i+1][1]-allCoords[i][1])*Math.PI/180;
                area += dlon*(2+Math.sin(lat1)+Math.sin(lat2));
            }
            return parseFloat((Math.abs(area)*R*R/2/10000).toFixed(3));
        }
    } catch(e) {}
    return null;
}

function renderResumen(data) {
    let filas = '';
    data.lotes.forEach((l, i) => {
        const haStr = l.hectareas !== null && !isNaN(l.hectareas)
            ? `<span class="hectareas-val">${parseFloat(l.hectareas).toFixed(3)}</span>`
            : `<span class="hectareas-nd">Sin datos</span>`;
        const fecha = l.fecha ? new Date(l.fecha).toLocaleDateString('es-EC',{day:'2-digit',month:'short',year:'numeric'}) : '-';
        filas += `<tr>
            <td style="text-align:center;font-weight:600;color:#6b7280;">${i+1}</td>
            <td><span style="font-family:monospace;font-weight:700;color:#1f3a5f;font-size:12px;">${escHtml(l.codigo)}</span></td>
            <td style="text-align:center;">${haStr}</td>
            <td>${escHtml(l.descripcion||'—')}</td>
            <td style="text-align:center;"><span class="badge-ocupado"><i class="fa fa-check"></i> Activo</span></td>
            <td style="font-size:11px;color:#6b7280;">${fecha}</td>
        </tr>`;
    });

    if (!data.lotes.length) {
        filas = `<tr><td colspan="6" style="text-align:center;padding:24px;color:#9ca3af;">
            <i class="fa fa-folder-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>
            Este productor no tiene archivos KML registrados
        </td></tr>`;
    }

    document.getElementById('resumenContenido').innerHTML = `
        <div class="resumen-header">
            <div>
                <h3><i class="fa fa-user"></i> ${escHtml(data.nombre)}</h3>
                <p>Cédula: ${escHtml(data.cedula)} &nbsp;·&nbsp; Zona: ${escHtml(data.zona||'—')}
                   ${data.codigoSlc ? ' &nbsp;·&nbsp; Código base: <strong>' + escHtml(data.codigoSlc) + '</strong>' : ''}</p>
            </div>
        </div>
        <div class="resumen-stats">
            <div class="resumen-stat">
                <h6>Lotes registrados</h6>
                <p>${data.totalArchivos}</p>
            </div>
            <div class="resumen-stat verde">
                <h6>Total hectáreas</h6>
                <p>${data.totalHa > 0 ? data.totalHa.toFixed(3) : '—'}</p>
            </div>
            <div class="resumen-stat azul">
                <h6>Archivos KML/KMZ</h6>
                <p>${data.totalArchivos}</p>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table class="lotes-table">
                <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Código</th>
                        <th style="text-align:center;">Hectáreas</th>
                        <th>Descripción</th>
                        <th style="text-align:center;">Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
                ${data.lotes.length ? `
                <tfoot>
                    <tr style="background:#f1f5f9;">
                        <td colspan="2" style="font-weight:700;padding:9px 10px;text-align:right;font-size:12px;">TOTAL:</td>
                        <td style="text-align:center;font-weight:700;color:#1f3a5f;font-family:monospace;">${data.totalHa > 0 ? data.totalHa.toFixed(3)+' ha' : '—'}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>` : ''}
            </table>
        </div>`;
}

function escHtml(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function exportarResumenExcel() {
    if (!resumenActual) return;
    const d = resumenActual;
    const payload = {
        cedula:     d.cedula,
        nombre:     d.nombre,
        zona:       d.zona,
        codigoSlc:  d.codigoSlc,
        lotes:      d.lotes,
        totalHa:    d.totalHa,
    };
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ubicaciones_api.php';
    form.target = '_blank';
    const inpAccion = document.createElement('input');
    inpAccion.type='hidden'; inpAccion.name='accion'; inpAccion.value='exportar_resumen_excel';
    const inpData = document.createElement('input');
    inpData.type='hidden'; inpData.name='payload'; inpData.value=JSON.stringify(payload);
    form.appendChild(inpAccion);
    form.appendChild(inpData);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

/* ── Abrir modal gestión ubicaciones ─────────────────────── */
function abrirUbicaciones(idSocio, cedula, nombre, zona, comunidad, codigoSlc, proximoLote) {
    socioActual = { idSocio, cedula, nombre, zona, comunidad, codigoSlc, proximoLote: proximoLote || 1 };

    document.getElementById('infoSocioModal').innerHTML = `
        <p><strong>Cédula:</strong> ${cedula} &nbsp;|&nbsp;
           <strong>Nombre:</strong> ${nombre} &nbsp;|&nbsp;
           <strong>Zona:</strong> ${zona || '-'}
           ${comunidad ? ' &nbsp;|&nbsp; <strong>Comunidad:</strong> ' + comunidad : ''}
           ${codigoSlc ? ' &nbsp;|&nbsp; <strong>Código:</strong> <span class="badge-codigo">' + codigoSlc + '</span>' : ''}
        </p>`;
    document.getElementById('subirIdSocio').value = idSocio;

    colaFiles = [];
    renderCola();
    document.getElementById('bulkProgressWrap').style.display = 'none';
    document.getElementById('colaArchivos').style.display = 'none';
    document.getElementById('bulkActions').style.display = 'none';

    capasKml = {};
    document.getElementById('listaCapasMapa').innerHTML = '';

    cambiarTab('tabArchivos', document.getElementById('tabBtnArchivos'));
    document.getElementById('modalUbicacion').classList.add('active');
    cargarArchivos(idSocio);
}

/* ── Cargar archivos ──────────────────────────────────────── */
function cargarArchivos(idSocio) {
    const div = document.getElementById('listaArchivos');
    div.innerHTML = '<p style="text-align:center;color:#9ca3af;padding:20px;"><i class="fa fa-spinner fa-spin"></i> Cargando archivos...</p>';

    fetch(`ubicaciones_api.php?accion=listar&id_socio=${idSocio}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.datos.length) {
                div.innerHTML = `
                    <div style="text-align:center;padding:30px;color:#9ca3af;">
                        <i class="fa fa-folder-open" style="font-size:40px;margin-bottom:10px;display:block;"></i>
                        No hay archivos subidos para este productor.<br>
                        <small>Ve a la pestaña <strong>Subir Archivos</strong> para agregar uno.</small>
                    </div>`;
                return;
            }
            div.innerHTML = '';
            data.datos.forEach(a => {
                const fecha = new Date(a.fecha_subida).toLocaleDateString('es-EC', {day:'2-digit',month:'short',year:'numeric'});
                const codigoBadge = a.codigo_archivo
                    ? `<span class="archivo-codigo">${a.codigo_archivo}</span>`
                    : '';
                div.innerHTML += `
                <div class="archivo-item">
                    <div class="archivo-info">
                        <i class="fa fa-map archivo-icon ${a.tipo_archivo}"></i>
                        <div style="min-width:0;">
                            <div>
                                <span class="archivo-nombre" title="${a.nombre_archivo}">${a.nombre_archivo}</span>
                                ${codigoBadge}
                            </div>
                            <div class="archivo-meta">
                                <strong>${a.tipo_archivo.toUpperCase()}</strong> &nbsp;·&nbsp; ${fecha} &nbsp;·&nbsp; ${a.subido_por || '-'}
                                ${a.descripcion ? '<br><em>' + a.descripcion + '</em>' : ''}
                            </div>
                        </div>
                    </div>
                    <div class="archivo-acciones">

                        <button class="btn-icon azul" title="Ver en mapa" onclick="verEnMapa(${a.id_ubicacion}, '${esc(a.nombre_archivo)}')">
                            <i class="fa fa-map"></i>
                        </button>
                        <a href="ubicaciones_api.php?accion=descargar_kml_actualizado&id_ubicacion=${a.id_ubicacion}" download="${a.codigo_archivo||a.nombre_archivo}.kml" style="text-decoration:none;">
                            <button class="btn-icon naranja" title="Descargar" type="button"><i class="fa fa-download"></i></button>
                        </a>
                        <button class="btn-icon rojo" title="Eliminar" onclick="eliminarArchivo(${a.id_ubicacion})">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>`;
            });
        });
}

/* ══════════════════════════════════════════════════════════════
   SUBIDA MÚLTIPLE
══════════════════════════════════════════════════════════════ */
function onArchivosSeleccionados(files) {
    const nuevos = Array.from(files);
    let lote = socioActual ? socioActual.proximoLote : 1;
    nuevos.forEach(file => {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['kml','kmz'].includes(ext)) return;
        const codigoBase = socioActual?.codigoSlc || '';
        const codigoSugerido = codigoBase ? `${codigoBase}_${lote}` : '';
        colaFiles.push({ file, codigo: codigoSugerido, descripcion: '', estado: 'pending', mensaje: '' });
        lote++;
    });
    if (socioActual) socioActual.proximoLote = lote;
    renderCola();
    document.getElementById('archivoInput').value = '';
}

function renderCola() {
    const div = document.getElementById('colaArchivos');
    const btnActions = document.getElementById('bulkActions');
    if (!colaFiles.length) { div.style.display='none'; btnActions.style.display='none'; div.innerHTML=''; return; }
    div.style.display='flex'; btnActions.style.display='flex';
    div.innerHTML = `<h4 style="margin:0 0 8px;color:#374151;font-size:14px;"><i class="fa fa-list"></i> Cola de archivos (${colaFiles.length})</h4>`;
    colaFiles.forEach((item, idx) => {
        const estadoClass = item.estado==='ok'?'cola-ok':item.estado==='error'?'cola-error':item.estado==='uploading'?'cola-subiendo':'';
        const estadoLabel = item.estado==='ok'?'<span class="cola-item-estado ok"><i class="fa fa-check"></i> Subido</span>'
            :item.estado==='error'?`<span class="cola-item-estado error"><i class="fa fa-times"></i> ${item.mensaje||'Error'}</span>`
            :item.estado==='uploading'?'<span class="cola-item-estado uploading"><i class="fa fa-spinner fa-spin"></i> Subiendo...</span>'
            :'<span class="cola-item-estado pending"><i class="fa fa-clock"></i> Pendiente</span>';
        const dis = item.estado==='ok'||item.estado==='uploading'?'disabled':'';
        div.innerHTML += `
        <div class="cola-item ${estadoClass}" id="colaItem_${idx}">
            <div class="cola-item-header">
                <i class="fa fa-file-circle-check" style="color:#6b7280;font-size:16px;"></i>
                <span class="cola-item-nombre" title="${item.file.name}">${item.file.name}</span>
                ${estadoLabel}
                ${item.estado!=='ok'&&item.estado!=='uploading'?`<button class="cola-item-remove" onclick="quitarDeCola(${idx})"><i class="fa fa-times"></i></button>`:''}
            </div>
            <div class="cola-item-inputs">
                <div>
                    <input type="text" id="colaCodigo_${idx}" value="${item.codigo}" placeholder="Código (ej: SLC-001_1)"
                        oninput="colaFiles[${idx}].codigo=this.value;validarCodigoCola(${idx})" onblur="validarCodigoCola(${idx})"
                        ${dis} style="font-family:monospace;font-weight:700;text-transform:uppercase;">
                    <div class="cola-hint" id="colaCodigoHint_${idx}">Formato: SLC-NNN_L</div>
                </div>
                <div>
                    <input type="text" id="colaDesc_${idx}" value="${item.descripcion}" placeholder="Descripción (opcional)"
                        oninput="colaFiles[${idx}].descripcion=this.value" ${dis}>
                </div>
            </div>
        </div>`;
    });
}

function quitarDeCola(idx) { colaFiles.splice(idx,1); renderCola(); }
function limpiarCola() { colaFiles=[]; renderCola(); document.getElementById('bulkProgressWrap').style.display='none'; }

const validarTimeouts = {};
function validarCodigoCola(idx) {
    const input=document.getElementById(`colaCodigo_${idx}`);
    const hint=document.getElementById(`colaCodigoHint_${idx}`);
    if(!input||!hint) return;
    const val=input.value.trim().toUpperCase();
    colaFiles[idx].codigo=val; input.value=val;
    const formatOk=/^SLC-\d{3}_\d+$/.test(val);
    if(!val){input.className='input-error';hint.innerHTML='<span style="color:#ef4444;">⚠ Requerido</span>';return;}
    if(!formatOk){input.className='input-error';hint.innerHTML='<span style="color:#ef4444;">⚠ Formato inválido</span>';return;}
    const dup=colaFiles.some((c,i)=>i!==idx&&c.codigo===val);
    if(dup){input.className='input-error';hint.innerHTML='<span style="color:#ef4444;">⚠ Duplicado en cola</span>';return;}
    input.className='';
    hint.innerHTML='<i class="fa fa-spinner fa-spin" style="color:#9ca3af;font-size:11px;"></i> Verificando...';
    clearTimeout(validarTimeouts[idx]);
    validarTimeouts[idx]=setTimeout(()=>{
        fetch(`ubicaciones_api.php?accion=validar_codigo&codigo=${encodeURIComponent(val)}`)
            .then(r=>r.json())
            .then(res=>{
                if(res.existe){input.className='input-error';hint.innerHTML=`<span style="color:#ef4444;">⚠ Ya existe (${res.socio||'otro'})</span>`;}
                else{input.className='input-ok';hint.innerHTML='<span style="color:#10b981;">✓ Disponible</span>';}
            }).catch(()=>{input.className='';hint.innerHTML='<span style="color:#9ca3af;">No verificado</span>';});
    },500);
}

async function subirTodos() {
    let hayError=false;
    for(let i=0;i<colaFiles.length;i++){
        const item=colaFiles[i];
        if(item.estado==='ok') continue;
        const val=(item.codigo||'').trim().toUpperCase();
        if(!val||!/^SLC-\d{3}_\d+$/.test(val)){mostrarToastUbic(`❌ Archivo #${i+1} código inválido`,'error');document.getElementById(`colaCodigo_${i}`)?.focus();hayError=true;break;}
        const dup=colaFiles.some((c,j)=>j!==i&&c.codigo===val);
        if(dup){mostrarToastUbic(`❌ Código duplicado: ${val}`,'error');hayError=true;break;}
    }
    if(hayError) return;
    const pendientes=colaFiles.filter(c=>c.estado!=='ok');
    if(!pendientes.length){mostrarToastUbic('Todos ya subidos','info');return;}
    const wrap=document.getElementById('bulkProgressWrap'),bar=document.getElementById('bulkBar'),info=document.getElementById('bulkInfo'),btn=document.getElementById('btnSubirTodos');
    wrap.style.display='block';btn.disabled=true;
    let subidos=0;const total=pendientes.length;
    for(let i=0;i<colaFiles.length;i++){
        const item=colaFiles[i];
        if(item.estado==='ok') continue;
        item.estado='uploading';renderCola();info.textContent=`Subiendo ${subidos+1}/${total}: ${item.file.name}`;
        const fd=new FormData();
        fd.append('accion','subir');fd.append('id_socio',document.getElementById('subirIdSocio').value);
        fd.append('archivo',item.file);fd.append('codigo_archivo',item.codigo.toUpperCase());fd.append('descripcion',item.descripcion);
        try{
            const res=await fetch('ubicaciones_api.php',{method:'POST',body:fd});
            const json=await res.json();
            if(json.success){item.estado='ok';subidos++;}else{item.estado='error';item.mensaje=json.message||'Error';}
        }catch(e){item.estado='error';item.mensaje='Error de red';}
        bar.style.width=Math.round((subidos/total)*100)+'%';renderCola();
    }
    info.textContent=`✅ ${subidos}/${total} subidos`;btn.disabled=false;
    cargarArchivos(socioActual.idSocio);cargarStats();cargarSocios(paginaActual);
    const err=colaFiles.filter(c=>c.estado==='error').length;
    if(err>0){mostrarToastUbic(`⚠ ${subidos} subidos, ${err} con error`,'error');}
    else{
        mostrarToastUbic(`✅ ${subidos} archivo(s) subidos`,'success');
        setTimeout(()=>{colaFiles=colaFiles.filter(c=>c.estado!=='ok');if(!colaFiles.length){document.getElementById('colaArchivos').style.display='none';document.getElementById('bulkActions').style.display='none';document.getElementById('bulkProgressWrap').style.display='none';}else{renderCola();}},2000);
    }
}

function dragOver(e){e.preventDefault();document.getElementById('uploadZone').classList.add('drag');}
function dragLeave(e){document.getElementById('uploadZone').classList.remove('drag');}
function dropFile(e){e.preventDefault();document.getElementById('uploadZone').classList.remove('drag');if(e.dataTransfer.files.length)onArchivosSeleccionados(e.dataTransfer.files);}

/* ── Mapa ────────────────────────────────────────────────── */
let modoMapa=0;
const tilesDisponibles=[
    {url:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',att:'© OpenStreetMap',nombre:'Mapa'},
    {url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',att:'© Esri',nombre:'Satélite'},
    {url:'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',att:'© OpenTopoMap',nombre:'Topográfico'}
];
let tileLayerActual=null;let capasKml={};
const COLORES_KML=['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63'];

function verEnMapa(idUbicacion,nombreArchivo){cambiarTab('tabMapa',document.getElementById('tabBtnMapa'));cargarKmlEnMapa(idUbicacion,nombreArchivo||('Archivo '+idUbicacion));}
function cargarKmlEnMapa(idUbicacion,nombreArchivo){
    const div=document.getElementById('mapaContainer');
    div.innerHTML='<div class="mapa-placeholder"><i class="fa fa-spinner fa-spin"></i><span>Cargando KML...</span></div>';
    fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${idUbicacion}`)
        .then(r=>r.json())
        .then(data=>{
            if(!data.success){div.innerHTML=`<div class="mapa-placeholder"><i class="fa fa-triangle-exclamation" style="color:#ef4444;"></i><span>${data.message}</span></div>`;return;}
            const kmlContent=atob(data.kml);
            div.innerHTML='<div id="mapaLeaflet" style="width:100%;height:100%;"></div>';
            cargarLeafletConCallback(kmlContent,idUbicacion,nombreArchivo||data.nombre);
        });
}
function asegurarMapa(){
    if(!mapaLeaflet){
        const c=document.getElementById('mapaContainer');
        c.innerHTML='<div id="mapaLeaflet" style="width:100%;height:100%;"></div>';
        mapaLeaflet=L.map('mapaLeaflet',{zoomControl:true}).setView([-1.5,-79.5],9);
        tileLayerActual=L.tileLayer(tilesDisponibles[modoMapa].url,{attribution:tilesDisponibles[modoMapa].att}).addTo(mapaLeaflet);
        setTimeout(()=>{if(mapaLeaflet)mapaLeaflet.invalidateSize();},300);
    }
}
function cargarLeafletConCallback(kmlContent,idUbicacion,nombreArchivo){
    if(window.L&&window.omnivore){agregarCapaKml(kmlContent,idUbicacion,nombreArchivo);return;}
    if(!document.getElementById('leafletCSS')){const css=document.createElement('link');css.id='leafletCSS';css.rel='stylesheet';css.href='https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';document.head.appendChild(css);}
    if(!window.L){
        const js=document.createElement('script');js.src='https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
        js.onload=()=>{const js2=document.createElement('script');js2.src='https://cdnjs.cloudflare.com/ajax/libs/leaflet-omnivore/0.3.4/leaflet-omnivore.min.js';js2.onload=()=>agregarCapaKml(kmlContent,idUbicacion,nombreArchivo);document.head.appendChild(js2);};
        document.head.appendChild(js);
    }else if(!window.omnivore){const js=document.createElement('script');js.src='https://cdnjs.cloudflare.com/ajax/libs/leaflet-omnivore/0.3.4/leaflet-omnivore.min.js';js.onload=()=>agregarCapaKml(kmlContent,idUbicacion,nombreArchivo);document.head.appendChild(js);}
}
function agregarCapaKml(kmlContent,idUbicacion,nombreArchivo){
    asegurarMapa();
    const idx=Object.keys(capasKml).length%COLORES_KML.length;const color=COLORES_KML[idx];
    const layer=omnivore.kml.parse(kmlContent,null,L.geoJson(null,{
        style:{color,weight:2.5,fillOpacity:0.3,fillColor:color},
        pointToLayer:(f,latlng)=>L.circleMarker(latlng,{radius:7,fillColor:color,color:'#fff',weight:2,fillOpacity:0.9})
    }))
    .on('ready',function(){
        try{const ab=Object.values(capasKml).map(c=>c.layer.getBounds()).filter(b=>b.isValid());if(ab.length>0){let cb=ab[0];ab.forEach(b=>{cb=cb.extend(b);});mapaLeaflet.fitBounds(cb,{padding:[30,30]});}}catch(e){}
        setTimeout(()=>{if(mapaLeaflet)mapaLeaflet.invalidateSize();},200);
    })
    .on('error',function(e){console.warn('KML:',e);})
    .addTo(mapaLeaflet);
    capasKml[idUbicacion]={layer,nombre:nombreArchivo,color,activa:true};
    actualizarChipsCapas();
}
function actualizarChipsCapas(){
    const div=document.getElementById('listaCapasMapa');if(!div)return;div.innerHTML='';
    Object.entries(capasKml).forEach(([id,c])=>{
        const chip=document.createElement('div');
        chip.style.cssText=`display:inline-flex;align-items:center;gap:6px;background:#f9fafb;border:2px solid ${c.color};border-radius:20px;padding:4px 10px;font-size:12px;font-weight:600;cursor:pointer;user-select:none;`;
        chip.title='Clic para ocultar/mostrar';
        chip.innerHTML=`<span style="width:10px;height:10px;border-radius:50%;background:${c.color};display:inline-block;flex-shrink:0;"></span>${c.nombre}<i class="fa fa-times" style="color:#9ca3af;margin-left:4px;cursor:pointer;"></i>`;
        chip.querySelector('.fa-times').addEventListener('click',(e)=>{e.stopPropagation();quitarCapaKml(id);});
        chip.addEventListener('click',()=>toggleCapaKml(id));
        div.appendChild(chip);
    });
}
function toggleCapaKml(id){const c=capasKml[id];if(!c||!mapaLeaflet)return;if(c.activa){mapaLeaflet.removeLayer(c.layer);c.activa=false;}else{c.layer.addTo(mapaLeaflet);c.activa=true;}}
function quitarCapaKml(id){const c=capasKml[id];if(!c||!mapaLeaflet)return;mapaLeaflet.removeLayer(c.layer);delete capasKml[id];actualizarChipsCapas();}
function mostrarTodosKml(){
    if(!socioActual) return;
    fetch(`ubicaciones_api.php?accion=listar&id_socio=${socioActual.idSocio}`)
        .then(r=>r.json())
        .then(data=>{
            if(!data.success||!data.datos.length){mostrarToastUbic('No hay archivos KML','error');return;}
            Object.keys(capasKml).forEach(id=>{if(capasKml[id]&&mapaLeaflet)mapaLeaflet.removeLayer(capasKml[id].layer);});
            capasKml={};actualizarChipsCapas();
            const div=document.getElementById('mapaContainer');
            if(!mapaLeaflet||!document.getElementById('mapaLeaflet')){div.innerHTML='<div id="mapaLeaflet" style="width:100%;height:100%;"></div>';mapaLeaflet=null;}
            let cargados=0;
            data.datos.forEach(a=>{
                fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${a.id_ubicacion}`)
                    .then(r=>r.json())
                    .then(d=>{if(d.success)cargarLeafletConCallback(atob(d.kml),a.id_ubicacion,a.codigo_archivo||a.nombre_archivo);cargados++;if(cargados===data.datos.length)mostrarToastUbic(`✅ ${data.datos.length} cargados`,'success');});
            });
        });
}
function centrarMapa(){if(!mapaLeaflet)return;const act=Object.values(capasKml).filter(c=>c.activa);if(!act.length)return;try{const b=act.map(c=>c.layer.getBounds()).filter(b=>b.isValid());let cb=b[0];b.forEach(x=>cb=cb.extend(x));mapaLeaflet.fitBounds(cb,{padding:[30,30]});}catch(e){mapaLeaflet.setView([-1.5,-79.5],10);}}
function toggleCapas(){if(!mapaLeaflet)return;modoMapa=(modoMapa+1)%tilesDisponibles.length;if(tileLayerActual)mapaLeaflet.removeLayer(tileLayerActual);tileLayerActual=L.tileLayer(tilesDisponibles[modoMapa].url,{attribution:tilesDisponibles[modoMapa].att}).addTo(mapaLeaflet);mostrarToastUbic('Capa: '+tilesDisponibles[modoMapa].nombre,'info');}

/* ── Eliminar archivo ─────────────────────────────────────── */
function eliminarArchivo(idUbicacion){
    mostrarConfirmacion('Eliminar archivo','¿Eliminar este archivo? Esta acción no se puede deshacer.',function(){
        fetch('ubicaciones_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`accion=eliminar&id_ubicacion=${idUbicacion}`})
            .then(r=>r.json())
            .then(j=>{
                if(j.success){if(capasKml[idUbicacion]&&mapaLeaflet){mapaLeaflet.removeLayer(capasKml[idUbicacion].layer);delete capasKml[idUbicacion];actualizarChipsCapas();}cargarArchivos(socioActual.idSocio);cargarStats();cargarSocios(paginaActual);mostrarToastUbic('🗑️ Eliminado','success');}
                else{mostrarToastUbic(j.message||'Error','error');}
            });
    });
}

/* ── Export ───────────────────────────────────────────────── */
function exportarSocio(){if(!socioActual)return;window.open(`ubicaciones_api.php?accion=exportar_socio&id_socio=${socioActual.idSocio}`,'_blank');}
function exportarTodos(){mostrarConfirmacion('Exportar todo','¿Descargar un ZIP con todos los archivos KML/KMZ?',function(){window.open('ubicaciones_api.php?accion=exportar_todos','_blank');});}

/* ── Tabs ─────────────────────────────────────────────────── */
function cambiarTab(tabId,btn){
    document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    if(btn)btn.classList.add('active');
    if(tabId==='tabMapa'&&mapaLeaflet)setTimeout(()=>mapaLeaflet.invalidateSize(),100);
}

/* ── Paginación ───────────────────────────────────────────── */
function renderPaginacion(pagina,totalPaginas,total,porPagina){
    const div=document.getElementById('paginacion'),info=document.getElementById('infoPaginacion');
    const desde=(pagina-1)*porPagina+1,hasta=Math.min(pagina*porPagina,total);
    info.textContent=`Mostrando ${desde}–${hasta} de ${total} socios`;
    if(totalPaginas<=1){div.innerHTML='';return;}
    let html=`<button onclick="cargarSocios(1)" ${pagina===1?'disabled':''}>«</button>`;
    html+=`<button onclick="cargarSocios(${pagina-1})" ${pagina===1?'disabled':''}>‹</button>`;
    for(let p=Math.max(1,pagina-2);p<=Math.min(totalPaginas,pagina+2);p++)
        html+=`<button onclick="cargarSocios(${p})" class="${p===pagina?'active':''}">${p}</button>`;
    html+=`<button onclick="cargarSocios(${pagina+1})" ${pagina===totalPaginas?'disabled':''}>›</button>`;
    html+=`<button onclick="cargarSocios(${totalPaginas})" ${pagina===totalPaginas?'disabled':''}>»</button>`;
    div.innerHTML=html;
}

/* ── Toast ────────────────────────────────────────────────── */
function mostrarToastUbic(msg,tipo){
    let t=document.getElementById('toastUbic');
    if(!t){t=document.createElement('div');t.id='toastUbic';t.style.cssText='position:fixed;bottom:24px;right:24px;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:99999999;transform:translateY(80px);opacity:0;transition:all .3s;color:#fff;';document.body.appendChild(t);}
    t.textContent=msg;
    t.style.background=tipo==='success'?'#10b981':tipo==='error'?'#ef4444':'#1f3a5f';
    t.style.transform='translateY(0)';t.style.opacity='1';
    setTimeout(()=>{t.style.transform='translateY(80px)';t.style.opacity='0';},3500);
}

/* ── Cerrar modal ─────────────────────────────────────────── */
function cerrarModal(id){
    document.getElementById(id).classList.remove('active');
    if(id==='modalUbicacion'){if(mapaLeaflet){mapaLeaflet.remove();mapaLeaflet=null;}capasKml={};document.getElementById('listaCapasMapa').innerHTML='';}
}

/* ══════════════════════════════════════════════════════════════
   BLOQUE 3 — RESUMEN GLOBAL (JavaScript)
══════════════════════════════════════════════════════════════ */
let rgDatos   = [];
let rgFiltro  = '';
let rgCargado = false;

async function abrirResumenGlobal() {
    document.getElementById('modalResumenGlobal').classList.add('active');
    if (!rgCargado) await cargarResumenGlobal();
}

function cerrarResumenGlobal() {
    document.getElementById('modalResumenGlobal').classList.remove('active');
}

async function cargarResumenGlobal() {
    document.getElementById('rgContenidoSocios').innerHTML =
        '<div class="rg-loading"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#4f46e5;display:block;margin-bottom:8px;"></i>Obteniendo socios...</div>';
    document.getElementById('rgContenidoLibres').innerHTML =
        '<div class="rg-loading"><i class="fa fa-spinner fa-spin" style="font-size:24px;color:#10b981;display:block;margin-bottom:8px;"></i>Calculando...</div>';

    let todosSociosRg = [];
    try {
        const r = await fetch('ubicaciones_api.php?accion=buscar_socios&pagina=1&porPagina=9999&con_kml=con');
        const j = await r.json();
        if (j.success) todosSociosRg = j.datos;
    } catch(e) { mostrarToastUbic('❌ Error al cargar socios','error'); return; }

    rgDatos = [];
    let procesados = 0;

    for (const s of todosSociosRg) {
        let archivos = [];
        try {
            const r2 = await fetch(`ubicaciones_api.php?accion=listar&id_socio=${s.id_socio}`);
            const j2 = await r2.json();
            if (j2.success) archivos = j2.datos;
        } catch(e) {}

        const lotes = [];
        let totalHaSocio = 0;

        for (const arch of archivos) {
            let hectareas = null;

            if (arch.atributos) {
                try {
                    const atrs = typeof arch.atributos === 'string' ? JSON.parse(arch.atributos) : arch.atributos;
                    if (Array.isArray(atrs)) {
                        const a = atrs.find(x => {
                            const k = (x.k||'').toLowerCase();
                            return k.includes('area')||k.includes('área')||k.includes('hectarea')||x.tipo==='area';
                        });
                        if (a) hectareas = parseFloat(a.v);
                    }
                } catch(e) {}
            }

            if (hectareas === null) {
                try {
                    const r3 = await fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${arch.id_ubicacion}`);
                    const j3 = await r3.json();
                    if (j3.success) {
                        if (j3.atributos && j3.atributos.length) {
                            const a = j3.atributos.find(x => {
                                const k=(x.k||'').toLowerCase();
                                return k.includes('area')||k.includes('área')||k.includes('hectarea')||x.tipo==='area';
                            });
                            if (a) hectareas = parseFloat(a.v);
                        }
                        if (hectareas === null) hectareas = rgExtraerHaKml(atob(j3.kml));
                    }
                } catch(e) {}
            }

            if (hectareas !== null && !isNaN(hectareas)) totalHaSocio += hectareas;
            lotes.push({ codigo: arch.codigo_archivo || arch.nombre_archivo, hectareas });
        }

        rgDatos.push({
            id_socio:       s.id_socio,
            identificacion: s.identificacion,
            nombre:         s.nombre_completo || s.identificacion,
            zona:           s.zona || '',
            comunidad:      s.comunidad_grupo || '',
            adendum:        parseInt(s.adendum||0),
            lotes,
            totalHa:        totalHaSocio,
            totalArchivos:  archivos.length,
            codigoBase:     s.codigo_slc || '',
        });

        procesados++;
        document.getElementById('rgContenidoSocios').innerHTML =
            `<div class="rg-loading"><i class="fa fa-spinner fa-spin" style="font-size:20px;color:#4f46e5;display:block;margin-bottom:8px;"></i>Cargando ${procesados}/${todosSociosRg.length} socios...</div>`;
    }

    rgCargado = true;
    rgActualizarStats();
    renderResumenGlobalSocios();
    renderResumenGlobalLibres();
}

function rgExtraerHaKml(kmlStr) {
    try {
        const doc = new DOMParser().parseFromString(kmlStr, 'text/xml');
        const desc = doc.querySelector('description');
        if (desc) {
            const dd = new DOMParser().parseFromString(desc.textContent||'','text/html');
            for (const row of dd.querySelectorAll('tr')) {
                const tds = row.querySelectorAll('td');
                if (tds.length>=2) {
                    const k=(tds[0].textContent||'').toLowerCase();
                    if (k.includes('area')||k.includes('área')||k.includes('hectarea')||k==='ha') {
                        const v=parseFloat((tds[1].textContent||'').replace(',','.'));
                        if (!isNaN(v)) return v;
                    }
                }
            }
        }
        for (const sd of doc.querySelectorAll('SimpleData')) {
            const n=(sd.getAttribute('name')||'').toLowerCase();
            if (n.includes('area')||n.includes('área')||n.includes('hectarea')) {
                const v=parseFloat((sd.textContent||'').replace(',','.'));
                if (!isNaN(v)) return v;
            }
        }
        for (const d of doc.querySelectorAll('ExtendedData > Data')) {
            const n=(d.getAttribute('name')||'').toLowerCase();
            if (n.includes('area')||n.includes('área')||n.includes('hectarea')) {
                const el=d.querySelector('value');
                if (el) { const v=parseFloat((el.textContent||'').replace(',','.'));if(!isNaN(v))return v; }
            }
        }
        const coords=[];
        doc.querySelectorAll('coordinates').forEach(el=>{
            (el.textContent||'').trim().split(/\s+/).forEach(c=>{
                const p=c.split(',');
                if(p.length>=2){const lon=parseFloat(p[0]),lat=parseFloat(p[1]);if(!isNaN(lon)&&!isNaN(lat))coords.push([lat,lon]);}
            });
        });
        if (coords.length>3) {
            let area=0;const n2=coords.length,R=6371000;
            for(let i=0;i<n2-1;i++){
                const lat1=coords[i][0]*Math.PI/180,lat2=coords[i+1][0]*Math.PI/180;
                const dlon=(coords[i+1][1]-coords[i][1])*Math.PI/180;
                area+=dlon*(2+Math.sin(lat1)+Math.sin(lat2));
            }
            return parseFloat((Math.abs(area)*R*R/2/10000).toFixed(3));
        }
    } catch(e) {}
    return null;
}

function rgActualizarStats() {
    const totalSocios = rgDatos.length;
    const conKml      = rgDatos.filter(s=>s.totalArchivos>0).length;
    const sinKml      = totalSocios - conKml;
    const totalHa     = rgDatos.reduce((s,x)=>s+x.totalHa,0);
    const libres      = rgCalcularLibres();

    document.getElementById('rgStTotal').textContent  = totalSocios;
    document.getElementById('rgStConKml').textContent = conKml;
    document.getElementById('rgStSinKml').textContent = sinKml;
    document.getElementById('rgStHa').textContent     = totalHa > 0 ? totalHa.toFixed(2)+' ha' : '—';
    document.getElementById('rgStLibres').textContent = libres.length;
}

function rgCalcularLibres() {
    const usados = new Set();
    let maxNum = 0;
    rgDatos.forEach(s => {
        s.lotes.forEach(l => {
            const m = (l.codigo||'').match(/^SLC-(\d+)/i);
            if (m) {
                const n = parseInt(m[1]);
                usados.add(n);
                if (n > maxNum) maxNum = n;
            }
        });
    });
    const libres = [];
    for (let i = 1; i <= maxNum + 10; i++) {
        if (!usados.has(i)) libres.push(`SLC-${String(i).padStart(3,'0')}`);
    }
    return libres;
}

function renderResumenGlobalSocios() {
    const q = rgFiltro.toLowerCase();
    let datos = rgDatos;
    if (q) {
        datos = rgDatos.filter(s =>
            s.nombre.toLowerCase().includes(q) ||
            s.identificacion.toLowerCase().includes(q) ||
            s.lotes.some(l=>(l.codigo||'').toLowerCase().includes(q))
        );
    }

    if (!datos.length) {
        document.getElementById('rgContenidoSocios').innerHTML =
            '<div class="rg-loading" style="color:#9ca3af;">Sin resultados para "'+escHtmlRg(rgFiltro)+'"</div>';
        return;
    }

    let filas = '';
    datos.forEach((s, idx) => {
        const haStr = s.totalHa > 0 ? `<span class="rg-ha">${s.totalHa.toFixed(3)} ha</span>` : `<span class="rg-ha-nd">Sin datos</span>`;
        const codigosHtml = s.lotes.length
            ? s.lotes.map(l=>`<span class="rg-cod">${escHtmlRg(l.codigo)}</span>`).join('')
            : `<span class="rg-no-arch">Sin archivos</span>`;
        const adBadge = s.adendum===2
            ? '<span style="background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;">Ad.2</span>'
            : s.adendum===1
                ? '<span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;">Ad.1</span>'
                : '<span style="background:#f3f4f6;color:#9ca3af;padding:2px 8px;border-radius:999px;font-size:10px;">—</span>';
        filas += `<tr>
            <td style="font-weight:600;color:#6b7280;text-align:center;">${idx+1}</td>
            <td style="font-family:monospace;font-size:12px;font-weight:700;">${escHtmlRg(s.identificacion)}</td>
            <td><strong>${escHtmlRg(s.nombre)}</strong><br><small style="color:#9ca3af;">${escHtmlRg(s.zona)}</small></td>
            <td style="text-align:center;">${adBadge}</td>
            <td style="text-align:center;">${haStr}</td>
            <td style="text-align:center;font-weight:700;color:#4f46e5;">${s.totalArchivos}</td>
            <td><div class="rg-cod-list">${codigosHtml}</div></td>
        </tr>`;
    });

    const totalHaFiltrado = datos.reduce((s,x)=>s+x.totalHa,0);

    document.getElementById('rgContenidoSocios').innerHTML = `
        <table class="rg-table">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th style="text-align:center;">Adendum</th>
                    <th style="text-align:center;">Hectáreas</th>
                    <th style="text-align:center;">Lotes</th>
                    <th>Códigos</th>
                </tr>
            </thead>
            <tbody>${filas}</tbody>
            <tfoot>
                <tr style="background:#f1f5f9;font-weight:700;">
                    <td colspan="4" style="padding:10px;text-align:right;font-size:12px;">TOTAL ${datos.length} socios:</td>
                    <td style="text-align:center;font-family:monospace;color:#1f3a5f;padding:10px;">
                        ${totalHaFiltrado>0 ? totalHaFiltrado.toFixed(3)+' ha' : '—'}
                    </td>
                    <td style="text-align:center;padding:10px;">${datos.reduce((s,x)=>s+x.totalArchivos,0)}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>`;
}

function renderResumenGlobalLibres() {
    const libres = rgCalcularLibres();
    const q = rgFiltro.toLowerCase();

    const ocupados = [];
    rgDatos.forEach(s => s.lotes.forEach(l => {
        const m = (l.codigo||'').match(/^SLC-\d+/i);
        if (m) ocupados.push({ codigo: l.codigo, socio: s.nombre, cedula: s.identificacion });
    }));
    ocupados.sort((a,b)=>a.codigo.localeCompare(b.codigo));

    const maxOcupado = ocupados.reduce((max, o) => {
        const m = (o.codigo||'').match(/SLC-(\d+)/i);
        return m ? Math.max(max, parseInt(m[1])) : max;
    }, 0);

    const libresHTML = libres
        .filter(c => !q || c.toLowerCase().includes(q))
        .map(c => `<div class="libre-chip" title="Código disponible"><i class="fa fa-check" style="color:#10b981;margin-right:4px;font-size:10px;"></i>${c}</div>`)
        .join('');

    document.getElementById('rgContenidoLibres').innerHTML = `
        <div style="padding:16px 20px 8px;">
            <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:4px;">
                <i class="fa fa-circle-check" style="color:#10b981;"></i>
                Códigos disponibles (${libres.length} libres entre SLC-001 y SLC-${String(maxOcupado+10).padStart(3,'0')})
            </div>
            <div style="font-size:12px;color:#6b7280;margin-bottom:12px;">Los códigos en verde no tienen ningún lote asignado.</div>
            <div class="libre-grid">${libresHTML || '<div style="color:#9ca3af;font-size:13px;">Sin resultados</div>'}</div>
        </div>
        <div style="padding:0 20px 16px;">
            <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:10px;padding-top:10px;border-top:1px solid #e5e7eb;">
                <i class="fa fa-circle-xmark" style="color:#ef4444;"></i>
                Códigos ocupados (${ocupados.length})
            </div>
            <table class="rg-table">
                <thead><tr>
                    <th>Código</th>
                    <th>Socio</th>
                    <th>Cédula</th>
                </tr></thead>
                <tbody>
                    ${ocupados.filter(o=>!q||o.codigo.toLowerCase().includes(q)||o.socio.toLowerCase().includes(q)||o.cedula.toLowerCase().includes(q))
                        .map(o=>`<tr>
                            <td><span class="ocupado-chip">${escHtmlRg(o.codigo)}</span></td>
                            <td>${escHtmlRg(o.socio)}</td>
                            <td style="font-family:monospace;font-size:11px;">${escHtmlRg(o.cedula)}</td>
                        </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

function filtrarResumenGlobal() {
    rgFiltro = document.getElementById('rgBuscar').value || '';
    renderResumenGlobalSocios();
    renderResumenGlobalLibres();
}

function cambiarRgTab(tabId, btn) {
    document.querySelectorAll('.rg-tc').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.rg-tab').forEach(b=>b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    if (btn) btn.classList.add('active');
}

function escHtmlRg(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function exportarResumenGlobalExcel() {
    const datos = rgFiltro
        ? rgDatos.filter(s=>s.nombre.toLowerCase().includes(rgFiltro.toLowerCase())||s.identificacion.toLowerCase().includes(rgFiltro.toLowerCase()))
        : rgDatos;

    if (!datos.length) { mostrarToastUbic('No hay datos para exportar','error'); return; }

    const lotes = [];
    datos.forEach(s => {
        s.lotes.forEach(l => {
            lotes.push({
                cedula:    s.identificacion,
                nombre:    s.nombre,
                zona:      s.zona,
                comunidad: s.comunidad,
                adendum:   s.adendum===2?'Adendum 2':s.adendum===1?'Adendum 1':'—',
                codigo:    l.codigo,
                hectareas: l.hectareas,
                totalHa:   s.totalHa,
            });
        });
        if (!s.lotes.length) {
            lotes.push({ cedula:s.identificacion,nombre:s.nombre,zona:s.zona,comunidad:s.comunidad,adendum:s.adendum===2?'Adendum 2':s.adendum===1?'Adendum 1':'—',codigo:'',hectareas:null,totalHa:0 });
        }
    });

    const totalHaGlobal = datos.reduce((s,x)=>s+x.totalHa,0);
    const payload = { lotes, totalHaGlobal, tipo: 'global' };

    const form = document.createElement('form');
    form.method='POST'; form.action='ubicaciones_api.php'; form.target='_blank';
    const i1=document.createElement('input'); i1.type='hidden'; i1.name='accion'; i1.value='exportar_resumen_global_excel';
    const i2=document.createElement('input'); i2.type='hidden'; i2.name='payload'; i2.value=JSON.stringify(payload);
    form.appendChild(i1); form.appendChild(i2);
    document.body.appendChild(form); form.submit(); document.body.removeChild(form);
}
</script>
</body>
</html>