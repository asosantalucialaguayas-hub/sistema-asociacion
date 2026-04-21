<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Asignación de Cupos LPA</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php include 'layout/modals.php'; ?>
<style>
.app { display:flex; height:100vh; overflow:hidden; }
.sidebar, nav.sidebar, aside.sidebar { position:sticky; top:0; height:100vh; overflow-y:auto; flex-shrink:0; }
.content { flex:1; overflow-y:auto; height:100vh; }

.btn-primary   { background:#1f3a5f; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-secondary { background:#5f7c99; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-success   { background:#10b981; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-warning   { background:#f59e0b; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary:hover   { background:#162e4a; }
.btn-secondary:hover { background:#4a6580; }
.btn-success:hover   { background:#059669; }
.btn-warning:hover   { background:#d97706; }

.periodo-banner {
    background: linear-gradient(135deg, #1f3a5f, #2563eb);
    color:#fff; padding:16px 22px; border-radius:10px;
    margin-bottom:20px; display:flex; align-items:center;
    justify-content:space-between; flex-wrap:wrap; gap:10px;
}
.periodo-banner h3 { margin:0; font-size:15px; opacity:.8; font-weight:500; }
.periodo-banner p  { margin:4px 0 0; font-size:20px; font-weight:700; }
.periodo-badge { background:rgba(255,255,255,.2); padding:6px 16px; border-radius:20px; font-size:13px; font-weight:600; }

.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.stat-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; text-align:center; }
.stat-card h4 { margin:0 0 6px; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
.stat-card p  { margin:0; font-size:24px; font-weight:700; color:#1f3a5f; }
.stat-card.green p { color:#10b981; }
.stat-card.red   p { color:#ef4444; }
.stat-card.blue  p { color:#3b82f6; }

.toolbar { display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap; background:#f9fafb; padding:14px; border-radius:8px; }
.toolbar input  { padding:9px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; min-width:260px; flex:1; }
.toolbar select { padding:9px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; }
.btn-actions { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }

.table-container { width:100%; overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead th { background:#1f3a5f; color:#fff; padding:11px 10px; text-align:left; white-space:nowrap; position:sticky; top:0; z-index:10; }
.data-table td { padding:10px; border-bottom:1px solid #e5e7eb; vertical-align:middle; }
.data-table tbody tr:hover { background:#f9fafb; }

/* Input cupo */
.cupo-input {
    width:110px; padding:7px 9px; border-radius:6px;
    border:1.5px solid #d1d5db; font-size:13px; font-weight:600;
    text-align:right; transition:border-color .2s, background .2s;
}
.cupo-input:focus      { outline:none; border-color:#2563eb; background:#eff6ff; }
.cupo-input.modificado { border-color:#10b981; background:#f0fdf4; }
.cupo-input.sin-cupo   { border-color:#f59e0b; background:#fffbeb; }

/* Bloqueado: totalmente deshabilitado visualmente */
.cupo-input.bloqueado {
    background:#f1f5f9 !important;
    border-color:#cbd5e1 !important;
    color:#94a3b8 !important;
    cursor:not-allowed !important;
    pointer-events:none !important;
    user-select:none;
}

.fila-con-cupo       { background:#f0fdf4 !important; }
.fila-con-cupo:hover { background:#dcfce7 !important; }
.fila-bloqueada      { background:#fefce8 !important; }
.fila-bloqueada:hover{ background:#fef9c3 !important; }

.estado { padding:4px 10px; border-radius:4px; font-size:11px; font-weight:700; color:#fff; display:inline-block; }
.estado.activo   { background:#10b981; }
.estado.cerrado  { background:#6b7280; }
.estado.inactivo { background:#ef4444; }

.badge-ad1 { background:#dbeafe; color:#1d4ed8; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }
.badge-ad2 { background:#fef9c3; color:#854d0e; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }

.btn-candado {
    width:32px; height:32px; border-radius:6px; border:none;
    cursor:pointer; display:inline-flex; align-items:center;
    justify-content:center; font-size:13px; transition:all .2s;
}
.btn-candado.abierto       { background:#e5e7eb; color:#6b7280; }
.btn-candado.cerrado       { background:#fef3c7; color:#d97706; }
.btn-candado.abierto:hover { background:#fef3c7; color:#d97706; }
.btn-candado.cerrado:hover { background:#d1fae5; color:#059669; }

.badge-bloqueado {
    background:#fef3c7; color:#92400e; border:1px solid #f59e0b;
    padding:2px 7px; border-radius:999px; font-size:10px; font-weight:700;
    display:inline-flex; align-items:center; gap:3px; white-space:nowrap;
}

.btn-icon { width:32px; height:32px; border-radius:6px; border:none; cursor:pointer; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
.btn-icon.azul    { background:#3b82f6; }
.btn-icon.verde   { background:#10b981; }
.btn-icon.naranja { background:#f59e0b; }
.btn-icon.azul:hover    { background:#2563eb; }
.btn-icon.verde:hover   { background:#059669; }
.btn-icon.naranja:hover { background:#d97706; }

.acciones-celda { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }

.paginacion { display:flex; align-items:center; gap:6px; margin-top:16px; justify-content:center; flex-wrap:wrap; }
.paginacion button { padding:7px 13px; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-size:13px; font-weight:600; }
.paginacion button:hover   { background:#f3f4f6; }
.paginacion button.active  { background:#1f3a5f; color:#fff; border-color:#1f3a5f; }
.paginacion button:disabled{ opacity:.4; cursor:not-allowed; }
.info-paginacion { text-align:center; margin-top:6px; font-size:13px; color:#6b7280; }

.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.55); z-index:999999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,.25); padding:28px; position:relative; max-width:680px; width:95%; max-height:90vh; overflow:auto; }
.close-btn { position:absolute; top:14px; right:14px; width:36px; height:36px; border-radius:50%; border:none; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.2); cursor:pointer; font-size:18px; z-index:10; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; }
.form-group input, .form-group select { width:100%; padding:9px 11px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; box-sizing:border-box; }
.form-actions { margin-top:18px; display:flex; gap:10px; justify-content:flex-end; }

.info-socio-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px; margin-bottom:16px; }
.info-socio-box p { margin:4px 0; font-size:13px; }
.info-socio-box strong { color:#1d4ed8; }

.cupo-bar { height:10px; border-radius:6px; background:#e5e7eb; overflow:hidden; margin-top:4px; }
.cupo-bar-fill { height:100%; border-radius:6px; background:#10b981; transition:width .4s; }
.cupo-bar-fill.warn   { background:#f59e0b; }
.cupo-bar-fill.danger { background:#ef4444; }
.cupo-labels { display:flex; justify-content:space-between; font-size:11px; color:#6b7280; margin-top:2px; }

#toast {
    position:fixed; bottom:24px; right:24px; background:#1f3a5f; color:#fff;
    padding:12px 22px; border-radius:10px; font-size:14px; font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.25); z-index:9999999;
    transform:translateY(80px); opacity:0; transition:all .3s;
}
#toast.show    { transform:translateY(0); opacity:1; }
#toast.success { background:#10b981; }
#toast.error   { background:#ef4444; }

.pendientes-badge {
    background:#fef3c7; border:1px solid #f59e0b; color:#92400e;
    padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600;
    display:flex; align-items:center; gap:6px;
}

/* Modal PIN */
.pin-icono { width:60px; height:60px; border-radius:50%; background:#fef3c7; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:28px; }
.pin-error-msg { color:#ef4444; font-size:12px; font-weight:600; min-height:18px; margin-top:8px; }
.pin-teclado { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin:16px 0; }
.pin-key { padding:12px; border-radius:8px; border:1px solid #e5e7eb; background:#f9fafb; font-size:16px; font-weight:700; color:#1f3a5f; cursor:pointer; transition:background .15s; text-align:center; }
.pin-key:hover        { background:#e0e7ff; border-color:#6366f1; }
.pin-key.borrar       { background:#fee2e2; color:#ef4444; border-color:#fca5a5; }
.pin-key.borrar:hover { background:#fca5a5; }
.pin-key.vacio        { visibility:hidden; }
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
<h1><i class="fa fa-layer-group" style="color:#1f3a5f;margin-right:8px;"></i> Asignación de Cupos por Periodo</h1>

<div class="periodo-banner">
    <div>
        <h3>Periodo de Comercialización Activo</h3>
        <p id="periodoNombre"><i class="fa fa-spinner fa-spin"></i> Cargando...</p>
    </div>
    <span class="periodo-badge" id="periodoEstado">—</span>
</div>

<div class="stats-row">
    <div class="stat-card"><h4>Total Productores LPA</h4><p id="statTotal">—</p></div>
    <div class="stat-card green"><h4>Con Cupo Asignado</h4><p id="statConCupo">—</p></div>
    <div class="stat-card red"><h4>Sin Cupo (0)</h4><p id="statSinCupo">—</p></div>
    <div class="stat-card blue"><h4>Cupo Total (kg)</h4><p id="statKgTotal">—</p></div>
</div>

<div class="btn-actions">
    <button class="btn-primary"  onclick="guardarTodosCambios()"><i class="fa fa-save"></i> Aplicar Cupos</button>
    <button class="btn-success"  onclick="abrirModalCupoGlobal()"><i class="fa fa-wand-magic-sparkles"></i> Aplicar cupo a todos</button>
    <button class="btn-warning"  onclick="exportarExcel()"><i class="fa fa-file-excel"></i> Exportar</button>
    <div id="badgePendientes" class="pendientes-badge" style="display:none;">
        <i class="fa fa-triangle-exclamation"></i>
        <span id="textoPendientes">0 cambios sin guardar</span>
    </div>
</div>

<div class="toolbar">
    <input type="text" id="inputBuscar" placeholder="🔍 Buscar por cédula o nombre..." oninput="buscarConDelay()">
    <select id="filtroAdendum" onchange="cargarProductores(1)">
        <option value="">Todos los adendum</option>
        <option value="1">Adendum 1</option>
        <option value="2">Adendum 2</option>
    </select>
    <select id="filtroEstado" onchange="cargarProductores(1)">
        <option value="">Todos los estados</option>
        <option value="activo">Activos</option>
        <option value="cerrado">Cerrados</option>
    </select>
    <select id="filtroCandado" onchange="cargarProductores(1)">
        <option value="">Todos</option>
        <option value="0">Desbloqueados</option>
        <option value="1">Bloqueados</option>
    </select>
    <button class="btn-primary"   onclick="cargarProductores(1)"><i class="fa fa-search"></i> Buscar</button>
    <button class="btn-secondary" onclick="limpiarFiltros()"><i class="fa fa-rotate-left"></i> Limpiar</button>
</div>

<div class="form-card">
<div class="table-container">
<table class="data-table">
<thead>
<tr>
    <th>#</th><th>Cédula</th><th>Productor/a</th><th>Zona</th>
    <th>Área Cacao (Ha)</th><th>Adendum</th><th>Estado LPA</th>
    <th>Cupo Actual (kg)</th><th>Nuevo Cupo (kg)</th>
    <th>Consumido (kg)</th><th>Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTabla">
    <tr><td colspan="11" style="text-align:center;padding:30px;color:#6b7280;">
        <i class="fa fa-spinner fa-spin"></i> Cargando...
    </td></tr>
</tbody>
</table>
</div>
<div class="paginacion" id="paginacion"></div>
<div class="info-paginacion" id="infoPaginacion"></div>
</div>
</section>
</main>
</div>

<!-- MODAL PIN -->
<div id="modalPin" class="modal-overlay">
<div class="modal-box" style="max-width:340px;text-align:center;">
    <button class="close-btn" onclick="cerrarModalPin()">×</button>
    <div class="pin-icono" id="pinIcono">🔒</div>
    <h2 style="margin:0 0 6px;color:#1f3a5f;font-size:18px;" id="pinTitulo">Bloquear cupo</h2>
    <p style="color:#6b7280;font-size:13px;margin:0 0 14px;" id="pinSubtitulo">Ingresa el PIN de seguridad</p>
    <div style="display:flex;justify-content:center;gap:10px;margin-bottom:4px;">
        <span id="p1" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p2" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p3" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p4" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p5" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p6" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p7" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
        <span id="p8" style="width:14px;height:14px;border-radius:50%;background:#d1d5db;display:inline-block;transition:background .15s;"></span>
    </div>
    <p class="pin-error-msg" id="pinError"></p>
    <div class="pin-teclado">
        <button class="pin-key" onclick="teclaPin('1')">1</button>
        <button class="pin-key" onclick="teclaPin('2')">2</button>
        <button class="pin-key" onclick="teclaPin('3')">3</button>
        <button class="pin-key" onclick="teclaPin('4')">4</button>
        <button class="pin-key" onclick="teclaPin('5')">5</button>
        <button class="pin-key" onclick="teclaPin('6')">6</button>
        <button class="pin-key" onclick="teclaPin('7')">7</button>
        <button class="pin-key" onclick="teclaPin('8')">8</button>
        <button class="pin-key" onclick="teclaPin('9')">9</button>
        <button class="pin-key vacio"></button>
        <button class="pin-key" onclick="teclaPin('0')">0</button>
        <button class="pin-key borrar" onclick="borrarPin()"><i class="fa fa-delete-left"></i></button>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn-secondary" style="flex:1" onclick="cerrarModalPin()">Cancelar</button>
        <button class="btn-primary"   style="flex:1" onclick="confirmarPin()"><i class="fa fa-lock"></i> Confirmar</button>
    </div>
</div>
</div>

<!-- MODAL Cupo global -->
<div id="modalCupoGlobal" class="modal-overlay">
<div class="modal-box" style="max-width:480px;">
<button class="close-btn" onclick="cerrarModal('modalCupoGlobal')">×</button>
<h2 style="margin-top:0;color:#1f3a5f;"><i class="fa fa-wand-magic-sparkles"></i> Aplicar Cupo a Todos</h2>
<p style="color:#6b7280;font-size:13px;">Establece el mismo cupo para todos los productores visibles.</p>
<div class="form-group">
    <label>Cupo en kilogramos (kg) *</label>
    <input type="number" id="cupoGlobalValor" placeholder="Ej: 1500.00" step="0.01" min="0.01">
</div>
<div class="form-group">
    <label>Aplicar a:</label>
    <select id="cupoGlobalFiltro">
        <option value="todos">Todos los productores activos</option>
        <option value="sin_cupo">Solo los que tienen cupo 0</option>
    </select>
</div>
<div class="form-actions">
    <button class="btn-secondary" onclick="cerrarModal('modalCupoGlobal')">Cancelar</button>
    <button class="btn-success"   onclick="aplicarCupoGlobal()"><i class="fa fa-check"></i> Aplicar</button>
</div>
</div>
</div>

<!-- MODAL Detalle cupo -->
<div id="modalDetalleCupo" class="modal-overlay">
<div class="modal-box" style="max-width:560px;">
<button class="close-btn" onclick="cerrarModal('modalDetalleCupo')">×</button>
<h2 style="margin-top:0;color:#1f3a5f;"><i class="fa fa-user-check"></i> Detalle de Cupo</h2>
<div class="info-socio-box" id="infoSocioDetalle"></div>
<div class="form-group">
    <label>Nuevo Cupo (kg) *</label>
    <input type="number" id="cupoIndividualValor" step="0.01" min="0">
</div>
<div id="progressWrap" style="display:none;margin-top:10px;">
    <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#374151;">
        <span>Consumido</span><span id="pctLabel">0%</span>
    </div>
    <div class="cupo-bar"><div class="cupo-bar-fill" id="cupoBarFill" style="width:0%"></div></div>
    <div class="cupo-labels"><span id="consumidoLabel">0 kg</span><span id="totalLabel">0 kg</span></div>
</div>
<div style="margin-top:14px;">
    <button class="btn-primary" onclick="imprimirAcuerdoProdutor()"><i class="fa fa-print"></i> Imprimir Acuerdo Productor</button>
</div>
<div class="form-actions">
    <button class="btn-secondary" onclick="cerrarModal('modalDetalleCupo')">Cancelar</button>
    <button class="btn-success"   onclick="guardarCupoIndividual()"><i class="fa fa-save"></i> Guardar Cupo</button>
</div>
</div>
</div>

<div id="toast"></div>

<script>
// PIN solo en frontend (no se guarda en BD — se reinicia al recargar la página)
const PIN_CORRECTO = '40242745';

let paginaActual      = 1;
let buscarTimer       = null;
let cambiosPendientes = {};
let productoraActual  = null;
let estadoCandados    = {}; // { id_lpa: true/false } — estado en memoria

// ── PIN modal vars ──
let _pinIdLpa  = null;
let _pinEsBloq = false;
let _pinValor  = '';

window.onload = function () {
    cargarPeriodoActivo();
    cargarProductores(1);
};

function cargarPeriodoActivo() {
    fetch('cupos_periodo.php?accion=periodo_activo')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.periodo) {
                document.getElementById('periodoNombre').textContent = d.periodo.nombre;
                document.getElementById('periodoEstado').textContent = d.periodo.estado;
                document.getElementById('periodoEstado').style.background =
                    d.periodo.estado === 'ABIERTO' ? 'rgba(16,185,129,.3)' : 'rgba(239,68,68,.3)';
            } else {
                document.getElementById('periodoNombre').textContent = 'Sin periodo activo';
            }
        }).catch(() => {});
}

function cargarProductores(pagina) {
    pagina       = pagina || paginaActual;
    paginaActual = pagina;

    const q       = (document.getElementById('inputBuscar').value || '').trim();
    const adendum = document.getElementById('filtroAdendum').value;
    const estado  = document.getElementById('filtroEstado').value;
    const candado = document.getElementById('filtroCandado').value;

    const params = new URLSearchParams({ pagina, q, adendum, estado, candado, accion: 'lista' });
    const tbody  = document.getElementById('cuerpoTabla');
    tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:25px;color:#6b7280;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>';

    fetch(`cupos_periodo.php?${params}`)
        .then(r => r.text())
        .then(txt => {
            let resp;
            try { resp = JSON.parse(txt); } catch(e) { console.error(txt); return; }
            tbody.innerHTML = '';
            if (!resp.success) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:25px;color:#ef4444;">❌ ${resp.message||'Error'}</td></tr>`;
                return;
            }

            const lista     = resp.datos        || [];
            const paginaR   = resp.pagina       || 1;
            const totalPag  = resp.totalPaginas || 1;
            const total     = resp.total        || 0;
            const porPagina = resp.porPagina    || 15;
            const stats     = resp.stats        || {};

            document.getElementById('statTotal').textContent   = stats.total    || 0;
            document.getElementById('statConCupo').textContent = stats.con_cupo || 0;
            document.getElementById('statSinCupo').textContent = stats.sin_cupo || 0;
            document.getElementById('statKgTotal').textContent =
                parseFloat(stats.kg_total || 0).toLocaleString('es-EC', {minimumFractionDigits:2});

            if (!lista.length) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:30px;color:#6b7280;">No se encontraron registros.</td></tr>';
                document.getElementById('paginacion').innerHTML = '';
                document.getElementById('infoPaginacion').textContent = '';
                return;
            }

            const inicio = (paginaR - 1) * porPagina;
            lista.forEach((row, idx) => {
                const idLpa      = row.id_lpa;
                const cupoActual = parseFloat(row.volumen_produccion_estimado) || 0;
                const consumido  = parseFloat(row.cupo_consumido)              || 0;
                const pct        = cupoActual > 0 ? ((consumido / cupoActual) * 100).toFixed(1) : 0;

                // Estado candado: memoria > BD
                const bloqueadoBD = parseInt(row.cupo_bloqueado) === 1;
                const bloqueado   = estadoCandados.hasOwnProperty(idLpa)
                    ? estadoCandados[idLpa] : bloqueadoBD;

                const valorInput  = (!bloqueado && cambiosPendientes.hasOwnProperty(idLpa))
                    ? cambiosPendientes[idLpa] : cupoActual;
                const esPendiente = !bloqueado && cambiosPendientes.hasOwnProperty(idLpa);
                const tieneCupo   = cupoActual > 0;
                const estadoClass = (row.estado_lpa || '').toLowerCase();
                const ad = row.adendum == 2
                    ? '<span class="badge-ad2">Adendum 2</span>'
                    : '<span class="badge-ad1">Adendum 1</span>';

                const claseFilas = bloqueado ? 'fila-bloqueada' : (tieneCupo ? 'fila-con-cupo' : '');
                const claseInput = bloqueado ? 'bloqueado' : (esPendiente ? 'modificado' : (!tieneCupo ? 'sin-cupo' : ''));

                tbody.innerHTML += `
                <tr id="fila-${idLpa}" class="${claseFilas}">
                    <td>${inicio + idx + 1}</td>
                    <td>${row.identificacion || '-'}</td>
                    <td><strong>${row.nombre_completo || '-'}</strong></td>
                    <td>${row.zona || '-'}</td>
                    <td style="text-align:right;">${parseFloat(row.area_cacao_ha||0).toFixed(2)}</td>
                    <td style="text-align:center;">${ad}</td>
                    <td style="text-align:center;"><span class="estado ${estadoClass}">${row.estado_lpa||'-'}</span></td>
                    <td style="text-align:right;font-weight:600;color:#374151;">${cupoActual.toFixed(2)}</td>
                    <td style="white-space:nowrap;">
                        <input
                            type="number"
                            class="cupo-input ${esPendiente ? 'modificado' : (cupoActual === 0 ? 'sin-cupo' : '')}"
                            id="cupo-${idLpa}"
                            value="${valorInput}"
                            step="0.01"
                            min="0"
                            data-id-lpa="${idLpa}"
                            data-cupo-original="${cupoActual}"
                            disabled
                            readonly
                            style="pointer-events:none; background:#f1f5f9; color:#94a3b8; border-color:#cbd5e1; cursor:not-allowed;"
                        >
                        <span id="badge-${idLpa}" class="badge-bloqueado"
                            style="display:${bloqueado?'inline-flex':'none'};margin-left:4px;">
                            <i class="fa fa-lock"></i> Bloqueado
                        </span>
                    </td>
                    <td style="text-align:right;color:${pct>=90?'#ef4444':pct>=70?'#f59e0b':'#10b981'};font-weight:600;">
                        ${consumido.toFixed(2)}
                        <small style="color:#9ca3af;font-weight:400;">(${pct}%)</small>
                    </td>
                    <td>
                        <div class="acciones-celda">
                            <button id="candado-${idLpa}"
                                class="btn-candado ${bloqueado?'cerrado':'abierto'}"
                                title="${bloqueado?'Desbloquear cupo':'Bloquear cupo'}"
                                onclick="abrirModalPin(${idLpa},${bloqueado})">
                                <i class="fa fa-${bloqueado?'lock':'lock-open'}"></i>
                            </button>
                            <button class="btn-icon azul" title="Ver detalle"
                                onclick='abrirDetalle(${idLpa},${row.id_socio},"${escapa(row.identificacion)}","${escapa(row.nombre_completo)}",${cupoActual},${consumido})'>
                                <i class="fa fa-eye"></i>
                            </button>
                            <button id="btnGuardar-${idLpa}" class="btn-icon verde" title="Guardar cupo"
                                onclick="guardarCupoUno(${idLpa})"
                                ${bloqueado?'disabled style="opacity:.4;cursor:not-allowed;"':''}>
                                <i class="fa fa-save"></i>
                            </button>
                            <button class="btn-icon naranja" title="Imprimir Acuerdo"
                                onclick="imprimirAcuerdo(${idLpa},${row.id_socio})">
                                <i class="fa fa-print"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });

            renderPaginacion(paginaR, totalPag, total, porPagina);
        })
        .catch(err => {
            document.getElementById('cuerpoTabla').innerHTML =
                '<tr><td colspan="11" style="text-align:center;padding:25px;color:#ef4444;">❌ Error al cargar.</td></tr>';
            console.error(err);
        });
}

function escapa(s) { return (s||'').replace(/"/g,'&quot;').replace(/'/g,"\\'"); }
function buscarConDelay() { clearTimeout(buscarTimer); buscarTimer = setTimeout(()=>cargarProductores(1),500); }
function limpiarFiltros() {
    ['inputBuscar','filtroAdendum','filtroEstado','filtroCandado'].forEach(id => {
        document.getElementById(id).value = '';
    });
    cargarProductores(1);
}

function marcarCambio(idLpa, inp) {
    const nuevo    = parseFloat(inp.value) || 0;
    const original = parseFloat(inp.dataset.cupoOriginal) || 0;
    if (nuevo !== original) {
        cambiosPendientes[idLpa] = nuevo;
        inp.classList.add('modificado'); inp.classList.remove('sin-cupo');
    } else {
        delete cambiosPendientes[idLpa];
        inp.classList.remove('modificado');
        if (original === 0) inp.classList.add('sin-cupo');
    }
    actualizarBadgePendientes();
}

function actualizarBadgePendientes() {
    const n = Object.keys(cambiosPendientes).length;
    document.getElementById('badgePendientes').style.display = n > 0 ? 'flex' : 'none';
    if (n > 0) document.getElementById('textoPendientes').textContent = `${n} cambio${n>1?'s':''} sin guardar`;
}

function guardarCupoUno(idLpa) {
    const inp = document.getElementById(`cupo-${idLpa}`);
    if (!inp || inp.dataset.bloqueado === '1') { mostrarToast('🔒 Cupo bloqueado','error'); return; }
    const nuevo = parseFloat(inp.value);
    if (isNaN(nuevo) || nuevo < 0) { mostrarToast('Cupo inválido','error'); return; }
    fetch('cupos_guardar.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id_lpa=${idLpa}&nuevo_cupo=${nuevo}`
    }).then(r=>r.json()).then(j => {
        if (j.success) {
            delete cambiosPendientes[idLpa];
            inp.dataset.cupoOriginal = nuevo;
            inp.classList.remove('modificado','sin-cupo');
            if (nuevo === 0) inp.classList.add('sin-cupo');
            actualizarBadgePendientes();
            const fila = document.getElementById(`fila-${idLpa}`);
            if (fila) fila.cells[7].textContent = nuevo.toFixed(2);
            mostrarToast('✅ Cupo guardado correctamente','success');
            cargarEstadisticas();
        } else { mostrarToast(j.message||'Error','error'); }
    }).catch(()=>mostrarToast('Error de red','error'));
}

function guardarTodosCambios() {
    const ids = Object.keys(cambiosPendientes);
    if (!ids.length) { mostrarToast('No hay cambios pendientes',''); return; }
    fetch('cupos_guardar.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ lote: ids.map(id=>({id_lpa:parseInt(id),nuevo_cupo:cambiosPendientes[id]})) })
    }).then(r=>r.json()).then(j => {
        if (j.success) {
            cambiosPendientes = {};
            actualizarBadgePendientes();
            mostrarToast(`✅ ${j.actualizados} cupo(s) aplicado(s)`,'success');
            cargarProductores(paginaActual);
        } else { mostrarToast(j.message||'Error','error'); }
    }).catch(()=>mostrarToast('Error de red','error'));
}

function abrirModalCupoGlobal() {
    document.getElementById('cupoGlobalValor').value = '';
    document.getElementById('modalCupoGlobal').classList.add('active');
}
function aplicarCupoGlobal() {
    const val    = parseFloat(document.getElementById('cupoGlobalValor').value);
    const filtro = document.getElementById('cupoGlobalFiltro').value;
    if (!val || val <= 0) { mostrarToast('Ingrese un cupo válido','error'); return; }
    document.querySelectorAll('.cupo-input:not(.bloqueado)').forEach(inp => {
        const idLpa  = parseInt(inp.dataset.idLpa);
        const actual = parseFloat(inp.dataset.cupoOriginal) || 0;
        if (filtro === 'sin_cupo' && actual > 0) return;
        inp.value = val;
        marcarCambio(idLpa, inp);
    });
    cerrarModal('modalCupoGlobal');
    mostrarToast(`Cupo ${val} kg aplicado. Pulsa "Aplicar Cupos" para guardar.`,'success');
}

function abrirDetalle(idLpa, idSocio, cedula, nombre, cupoActual, consumido) {
    productoraActual = { idLpa, idSocio, cedula, nombre, cupoActual, consumido };
    const disponible = cupoActual - consumido;
    const pct        = cupoActual > 0 ? ((consumido/cupoActual)*100).toFixed(1) : 0;
    const pctNum     = parseFloat(pct);
    document.getElementById('infoSocioDetalle').innerHTML = `
        <p><strong>Cédula:</strong> ${cedula}</p>
        <p><strong>Productor/a:</strong> ${nombre}</p>
        <p><strong>Cupo:</strong> ${cupoActual.toFixed(2)} kg &nbsp;|&nbsp;
           <strong>Consumido:</strong> ${consumido.toFixed(2)} kg &nbsp;|&nbsp;
           <strong>Disponible:</strong> ${disponible.toFixed(2)} kg</p>`;
    document.getElementById('cupoIndividualValor').value = cupoActual;
    const wrap = document.getElementById('progressWrap');
    wrap.style.display = cupoActual > 0 ? 'block' : 'none';
    if (cupoActual > 0) {
        const fill = document.getElementById('cupoBarFill');
        fill.style.width = Math.min(pctNum,100)+'%';
        fill.className   = 'cupo-bar-fill'+(pctNum>=90?' danger':pctNum>=70?' warn':'');
        document.getElementById('pctLabel').textContent       = pct+'%';
        document.getElementById('consumidoLabel').textContent = consumido.toFixed(2)+' kg';
        document.getElementById('totalLabel').textContent     = cupoActual.toFixed(2)+' kg';
    }
    document.getElementById('modalDetalleCupo').classList.add('active');
}
function guardarCupoIndividual() {
    if (!productoraActual) return;
    const nuevo = parseFloat(document.getElementById('cupoIndividualValor').value);
    if (isNaN(nuevo)||nuevo<0) { mostrarToast('Cupo inválido','error'); return; }
    const inp = document.getElementById(`cupo-${productoraActual.idLpa}`);
    if (inp && inp.dataset.bloqueado !== '1') { inp.value = nuevo; marcarCambio(productoraActual.idLpa, inp); }
    guardarCupoUno(productoraActual.idLpa);
    cerrarModal('modalDetalleCupo');
}
function imprimirAcuerdoProdutor() { if (productoraActual) imprimirAcuerdo(productoraActual.idLpa, productoraActual.idSocio); }
function imprimirAcuerdo(idLpa, idSocio) { window.open(`cupos_acuerdo_pdf.php?id_lpa=${idLpa}&id_socio=${idSocio}`,'_blank'); }
function exportarExcel() { window.open('cupos_exportar.php','_blank'); }

function cargarEstadisticas() {
    fetch('cupos_periodo.php?accion=stats').then(r=>r.json()).then(d => {
        if (d.success && d.stats) {
            const s = d.stats;
            document.getElementById('statTotal').textContent   = s.total    || 0;
            document.getElementById('statConCupo').textContent = s.con_cupo || 0;
            document.getElementById('statSinCupo').textContent = s.sin_cupo || 0;
            document.getElementById('statKgTotal').textContent =
                parseFloat(s.kg_total||0).toLocaleString('es-EC',{minimumFractionDigits:2});
        }
    }).catch(()=>{});
}

// ════════════════════════════════════════════════
// CANDADO + PIN  (100% frontend, sin backend)
// ════════════════════════════════════════════════
function abrirModalPin(id_lpa, esBloqueado) {
    _pinIdLpa  = id_lpa;
    _pinEsBloq = esBloqueado;
    _pinValor  = '';
    document.getElementById('pinIcono').textContent     = esBloqueado ? '🔓' : '🔒';
    document.getElementById('pinTitulo').textContent    = esBloqueado ? 'Desbloquear cupo' : 'Bloquear cupo';
    document.getElementById('pinSubtitulo').textContent = esBloqueado
        ? 'Ingresa el PIN para habilitar la edición'
        : 'Ingresa el PIN para deshabilitar la edición';
    document.getElementById('pinError').textContent = '';
    actualizarPuntos();
    document.getElementById('modalPin').classList.add('active');
}

function cerrarModalPin() {
    document.getElementById('modalPin').classList.remove('active');
    _pinIdLpa = null; _pinValor = '';
}

function teclaPin(num) {
    if (_pinValor.length >= 8) return;
    _pinValor += num;
    actualizarPuntos();
    document.getElementById('pinError').textContent = '';
    if (_pinValor.length === 8) setTimeout(confirmarPin, 150);
}

function borrarPin() {
    _pinValor = _pinValor.slice(0,-1);
    actualizarPuntos();
}

function actualizarPuntos() {
    for (let i = 1; i <= 8; i++) {
        const p = document.getElementById(`p${i}`);
        if (p) p.style.background = i <= _pinValor.length ? '#1f3a5f' : '#d1d5db';
    }
}

function confirmarPin() {
    if (!_pinValor) return;
    if (_pinValor !== PIN_CORRECTO) {
        _pinValor = '';
        actualizarPuntos();
        document.getElementById('pinError').textContent = '❌ PIN incorrecto';
        for (let i=1;i<=8;i++) {
            const p = document.getElementById(`p${i}`);
            if (p) { p.style.background='#ef4444'; setTimeout(()=>{ p.style.background='#d1d5db'; },600); }
        }
        return;
    }
    // Correcto
    const nuevoBloqueado = !_pinEsBloq;
    estadoCandados[_pinIdLpa] = nuevoBloqueado;
    const idGuardado = _pinIdLpa;
    cerrarModalPin();
    aplicarCandadoEnFila(idGuardado, nuevoBloqueado);
    mostrarToast(nuevoBloqueado ? '🔒 Cupo bloqueado — no se puede editar' : '🔓 Cupo desbloqueado', 'success');
}

function aplicarCandadoEnFila(id_lpa, bloqueado) {
    const inp        = document.getElementById(`cupo-${id_lpa}`);
    const btnCandado = document.getElementById(`candado-${id_lpa}`);
    const badge      = document.getElementById(`badge-${id_lpa}`);
    const fila       = document.getElementById(`fila-${id_lpa}`);
    const btnGuardar = document.getElementById(`btnGuardar-${id_lpa}`);
    if (!inp || !btnCandado) return;

    if (bloqueado) {
        inp.disabled = true;
        inp.readOnly = true;
        inp.setAttribute('tabindex','-1');
        inp.dataset.bloqueado = '1';
        inp.classList.add('bloqueado');
        inp.classList.remove('modificado','sin-cupo');
        if (cambiosPendientes[id_lpa]) { delete cambiosPendientes[id_lpa]; actualizarBadgePendientes(); }

        btnCandado.className = 'btn-candado cerrado';
        btnCandado.title     = 'Desbloquear cupo';
        btnCandado.innerHTML = '<i class="fa fa-lock"></i>';
        btnCandado.onclick   = () => abrirModalPin(id_lpa, true);

        if (badge)      badge.style.display = 'inline-flex';
        if (fila)       { fila.classList.remove('fila-con-cupo'); fila.classList.add('fila-bloqueada'); }
        if (btnGuardar) { btnGuardar.disabled=true; btnGuardar.style.opacity='.4'; btnGuardar.style.cursor='not-allowed'; }
    } else {
        inp.disabled = false;
        inp.readOnly = false;
        inp.removeAttribute('tabindex');
        inp.dataset.bloqueado = '0';
        inp.classList.remove('bloqueado');

        btnCandado.className = 'btn-candado abierto';
        btnCandado.title     = 'Bloquear cupo';
        btnCandado.innerHTML = '<i class="fa fa-lock-open"></i>';
        btnCandado.onclick   = () => abrirModalPin(id_lpa, false);

        if (badge)      badge.style.display = 'none';
        if (fila)       {
            fila.classList.remove('fila-bloqueada');
            if (parseFloat(inp.dataset.cupoOriginal)>0) fila.classList.add('fila-con-cupo');
        }
        if (btnGuardar) { btnGuardar.disabled=false; btnGuardar.style.opacity='1'; btnGuardar.style.cursor='pointer'; }
    }
}

function renderPaginacion(pagina, totalPaginas, total, porPagina) {
    const div  = document.getElementById('paginacion');
    const info = document.getElementById('infoPaginacion');
    info.textContent = `Mostrando ${(pagina-1)*porPagina+1}–${Math.min(pagina*porPagina,total)} de ${total} registros`;
    if (totalPaginas <= 1) { div.innerHTML=''; return; }
    let html = `<button onclick="cargarProductores(1)" ${pagina===1?'disabled':''}>«</button>`;
    html    += `<button onclick="cargarProductores(${pagina-1})" ${pagina===1?'disabled':''}>‹</button>`;
    for (let p=Math.max(1,pagina-2); p<=Math.min(totalPaginas,pagina+2); p++)
        html += `<button onclick="cargarProductores(${p})" class="${p===pagina?'active':''}">${p}</button>`;
    html += `<button onclick="cargarProductores(${pagina+1})" ${pagina===totalPaginas?'disabled':''}>›</button>`;
    html += `<button onclick="cargarProductores(${totalPaginas})" ${pagina===totalPaginas?'disabled':''}>»</button>`;
    div.innerHTML = html;
}

function mostrarToast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'show '+(tipo||'');
    setTimeout(()=>{ t.className=''; }, 3200);
}

function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
</script>
</body>
</html>