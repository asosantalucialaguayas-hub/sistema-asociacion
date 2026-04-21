<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "../../config/conexion.php";
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
/* ── Layout ── */
.app { display:flex; height:100vh; overflow:hidden; }
.sidebar, nav.sidebar, aside.sidebar { position:sticky; top:0; height:100vh; overflow-y:auto; flex-shrink:0; }
.content { flex:1; overflow-y:auto; height:100vh; }

/* ── Botones ── */
.btn-primary   { background:#1f3a5f; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-secondary { background:#5f7c99; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-success   { background:#10b981; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-warning   { background:#f59e0b; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary:hover   { background:#162e4a; }
.btn-secondary:hover { background:#4a6580; }
.btn-success:hover   { background:#059669; }
.btn-warning:hover   { background:#d97706; }

/* ── Panel periodo activo ── */
.periodo-banner {
    background: linear-gradient(135deg, #1f3a5f, #2563eb);
    color: #fff;
    padding: 16px 22px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.periodo-banner h3 { margin:0; font-size:15px; opacity:.8; font-weight:500; }
.periodo-banner p  { margin:4px 0 0; font-size:20px; font-weight:700; }
.periodo-badge {
    background: rgba(255,255,255,.2);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

/* ── Stats cards ── */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.stat-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; text-align:center; }
.stat-card h4 { margin:0 0 6px; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
.stat-card p  { margin:0; font-size:24px; font-weight:700; color:#1f3a5f; }
.stat-card.green p { color:#10b981; }
.stat-card.red   p { color:#ef4444; }
.stat-card.blue  p { color:#3b82f6; }

/* ── Toolbar ── */
.toolbar { display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap; background:#f9fafb; padding:14px; border-radius:8px; }
.toolbar input  { padding:9px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; min-width:260px; flex:1; }
.toolbar select { padding:9px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; }
.btn-actions { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }

/* ── Tabla ── */
.table-container { width:100%; overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead th { background:#1f3a5f; color:#fff; padding:11px 10px; text-align:left; white-space:nowrap; position:sticky; top:0; z-index:10; }
.data-table td { padding:10px; border-bottom:1px solid #e5e7eb; vertical-align:middle; }
.data-table tbody tr:hover { background:#f9fafb; }

/* Cupo input en tabla */
.cupo-input {
    width: 110px;
    padding: 7px 9px;
    border-radius:6px;
    border:1.5px solid #d1d5db;
    font-size:13px;
    font-weight:600;
    text-align:right;
    transition: border-color .2s;
}
.cupo-input:focus { outline:none; border-color:#2563eb; background:#eff6ff; }
.cupo-input.modificado { border-color:#10b981; background:#f0fdf4; }
.cupo-input.sin-cupo   { border-color:#f59e0b; background:#fffbeb; }

/* Fila con cupo asignado - fondo verde suave */
.fila-con-cupo { background:#f0fdf4 !important; }
.fila-con-cupo:hover { background:#dcfce7 !important; }

/* Estado LPA */
.estado { padding:4px 10px; border-radius:4px; font-size:11px; font-weight:700; color:#fff; display:inline-block; }
.estado.activo   { background:#10b981; }
.estado.cerrado  { background:#6b7280; }
.estado.inactivo { background:#ef4444; }

/* Badge adendum */
.badge-ad1 { background:#dbeafe; color:#1d4ed8; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }
.badge-ad2 { background:#fef9c3; color:#854d0e; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }

/* Indicador cambio */
.cambio-pill {
    display:inline-flex; align-items:center; gap:4px;
    background:#f3f4f6; border-radius:999px; padding:2px 8px;
    font-size:11px; color:#6b7280;
}
.cambio-pill.activo { background:#d1fae5; color:#065f46; }

/* Botones icono */
.btn-icon { width:32px; height:32px; border-radius:6px; border:none; cursor:pointer; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
.btn-icon.azul   { background:#3b82f6; }
.btn-icon.verde  { background:#10b981; }
.btn-icon.naranja{ background:#f59e0b; }
.btn-icon.azul:hover   { background:#2563eb; }
.btn-icon.verde:hover  { background:#059669; }
.btn-icon.naranja:hover{ background:#d97706; }

/* Paginación */
.paginacion { display:flex; align-items:center; gap:6px; margin-top:16px; justify-content:center; flex-wrap:wrap; }
.paginacion button { padding:7px 13px; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-size:13px; font-weight:600; }
.paginacion button:hover   { background:#f3f4f6; }
.paginacion button.active  { background:#1f3a5f; color:#fff; border-color:#1f3a5f; }
.paginacion button:disabled{ opacity:.4; cursor:not-allowed; }
.info-paginacion { text-align:center; margin-top:6px; font-size:13px; color:#6b7280; }

/* Modal */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.55); z-index:999999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,.25); padding:28px; position:relative; max-width:680px; width:95%; max-height:90vh; overflow:auto; }
.close-btn { position:absolute; top:14px; right:14px; width:36px; height:36px; border-radius:50%; border:none; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.2); cursor:pointer; font-size:18px; z-index:10; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; }
.form-group input, .form-group select { width:100%; padding:9px 11px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; box-sizing:border-box; }
.form-actions { margin-top:18px; display:flex; gap:10px; justify-content:flex-end; }

/* Info socio en modal */
.info-socio-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px; margin-bottom:16px; }
.info-socio-box p { margin:4px 0; font-size:13px; }
.info-socio-box strong { color:#1d4ed8; }

/* Barra de progreso cupo */
.cupo-progress-wrap { margin-top:10px; }
.cupo-bar { height:10px; border-radius:6px; background:#e5e7eb; overflow:hidden; margin-top:4px; }
.cupo-bar-fill { height:100%; border-radius:6px; background:#10b981; transition:width .4s; }
.cupo-bar-fill.warn   { background:#f59e0b; }
.cupo-bar-fill.danger { background:#ef4444; }
.cupo-labels { display:flex; justify-content:space-between; font-size:11px; color:#6b7280; margin-top:2px; }

/* Toast flotante */
#toast {
    position:fixed; bottom:24px; right:24px; background:#1f3a5f; color:#fff;
    padding:12px 22px; border-radius:10px; font-size:14px; font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.25); z-index:9999999;
    transform:translateY(80px); opacity:0; transition:all .3s;
}
#toast.show { transform:translateY(0); opacity:1; }
#toast.success { background:#10b981; }
#toast.error   { background:#ef4444; }

/* Cupos pendientes indicador */
.pendientes-badge {
    background:#fef3c7; border:1px solid #f59e0b; color:#92400e;
    padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600;
    display:flex; align-items:center; gap:6px;
}
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

<!-- Banner periodo activo -->
<div class="periodo-banner" id="periodoBanner">
    <div>
        <h3>Periodo de Comercialización Activo</h3>
        <p id="periodoNombre"><i class="fa fa-spinner fa-spin"></i> Cargando...</p>
    </div>
    <span class="periodo-badge" id="periodoEstado">—</span>
</div>

<!-- Stats -->
<div class="stats-row" id="statsRow">
    <div class="stat-card"><h4>Total Productores LPA</h4><p id="statTotal">—</p></div>
    <div class="stat-card green"><h4>Con Cupo Asignado</h4><p id="statConCupo">—</p></div>
    <div class="stat-card red"><h4>Sin Cupo (0)</h4><p id="statSinCupo">—</p></div>
    <div class="stat-card blue"><h4>Cupo Total (kg)</h4><p id="statKgTotal">—</p></div>
</div>

<!-- Acciones principales -->
<div class="btn-actions">
    <button class="btn-primary" onclick="guardarTodosCambios()">
        <i class="fa fa-save"></i> Aplicar Cupos
    </button>
    <button class="btn-success" onclick="abrirModalCupoGlobal()">
        <i class="fa fa-wand-magic-sparkles"></i> Aplicar cupo a todos
    </button>
    <button class="btn-warning" onclick="exportarExcel()">
        <i class="fa fa-file-excel"></i> Exportar
    </button>
    <div id="badgePendientes" class="pendientes-badge" style="display:none;">
        <i class="fa fa-triangle-exclamation"></i>
        <span id="textoPendientes">0 cambios sin guardar</span>
    </div>
</div>

<!-- Toolbar búsqueda -->
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
    <button class="btn-primary" onclick="cargarProductores(1)"><i class="fa fa-search"></i> Buscar</button>
    <button class="btn-secondary" onclick="limpiarFiltros()"><i class="fa fa-rotate-left"></i> Limpiar</button>
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
    <th>Área Cacao (Ha)</th>
    <th>Adendum</th>
    <th>Estado LPA</th>
    <th>Cupo Actual (kg)</th>
    <th>Nuevo Cupo (kg)</th>
    <th>Consumido (kg)</th>
    <th>Acciones</th>
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

<!-- MODAL: Cupo global -->
<div id="modalCupoGlobal" class="modal-overlay">
<div class="modal-box" style="max-width:480px;">
<button class="close-btn" onclick="cerrarModal('modalCupoGlobal')">×</button>
<h2 style="margin-top:0;color:#1f3a5f;"><i class="fa fa-wand-magic-sparkles"></i> Aplicar Cupo a Todos</h2>
<p style="color:#6b7280;font-size:13px;">Establece el mismo cupo para todos los productores visibles (según filtros activos). Puedes ajustar individualmente después.</p>
<div class="form-group">
    <label>Cupo en kilogramos (kg) *</label>
    <input type="number" id="cupoGlobalValor" placeholder="Ej: 1500.00" step="0.01" min="0.01">
</div>
<div class="form-group">
    <label>Aplicar a:</label>
    <select id="cupoGlobalFiltro">
        <option value="todos">Todos los productores activos del periodo</option>
        <option value="sin_cupo">Solo los que tienen cupo 0</option>
    </select>
</div>
<div class="form-actions">
    <button class="btn-secondary" onclick="cerrarModal('modalCupoGlobal')">Cancelar</button>
    <button class="btn-success" onclick="aplicarCupoGlobal()"><i class="fa fa-check"></i> Aplicar</button>
</div>
</div>
</div>

<!-- MODAL: Detalle cupo individual -->
<div id="modalDetalleCupo" class="modal-overlay">
<div class="modal-box" style="max-width:560px;">
<button class="close-btn" onclick="cerrarModal('modalDetalleCupo')">×</button>
<h2 style="margin-top:0;color:#1f3a5f;"><i class="fa fa-user-check"></i> Detalle de Cupo</h2>
<div class="info-socio-box" id="infoSocioDetalle"></div>
<div class="form-group">
    <label>Nuevo Cupo (kg) *</label>
    <input type="number" id="cupoIndividualValor" step="0.01" min="0">
</div>
<div class="cupo-progress-wrap" id="progressWrap" style="display:none;">
    <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#374151;">
        <span>Consumido</span><span id="pctLabel">0%</span>
    </div>
    <div class="cupo-bar"><div class="cupo-bar-fill" id="cupoBarFill" style="width:0%"></div></div>
    <div class="cupo-labels"><span id="consumidoLabel">0 kg</span><span id="totalLabel">0 kg</span></div>
</div>
<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
    <button class="btn-primary" onclick="imprimirAcuerdoProdutor()" id="btnImprimirModal">
        <i class="fa fa-print"></i> Imprimir Acuerdo Productor
    </button>
</div>
<div class="form-actions">
    <button class="btn-secondary" onclick="cerrarModal('modalDetalleCupo')">Cancelar</button>
    <button class="btn-success" onclick="guardarCupoIndividual()"><i class="fa fa-save"></i> Guardar Cupo</button>
</div>
</div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
// ─── Estado global ───────────────────────────────────────────────────────────
let paginaActual   = 1;
let buscarTimer    = null;
let cambiosPendientes = {};   // { id_lpa: nuevoCupo }
let productoraActual  = null; // Para modal detalle
let listaTodos        = [];   // Cache para cupo global

// ─── Inicialización ──────────────────────────────────────────────────────────
window.onload = function() {
    cargarPeriodoActivo();
    cargarProductores(1);
};

// ─── Periodo activo ──────────────────────────────────────────────────────────
function cargarPeriodoActivo() {
    fetch('cupos_periodo.php?accion=periodo_activo')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.periodo) {
                document.getElementById('periodoNombre').textContent = data.periodo.nombre;
                document.getElementById('periodoEstado').textContent = data.periodo.estado;
                document.getElementById('periodoEstado').style.background =
                    data.periodo.estado === 'ABIERTO' ? 'rgba(16,185,129,.3)' : 'rgba(239,68,68,.3)';
            } else {
                document.getElementById('periodoNombre').textContent = 'Sin periodo activo';
                document.getElementById('periodoEstado').textContent = '—';
            }
        })
        .catch(() => {
            document.getElementById('periodoNombre').textContent = 'Error al cargar periodo';
        });
}

// ─── Cargar productores ──────────────────────────────────────────────────────
function cargarProductores(pagina) {
    pagina       = pagina || paginaActual;
    paginaActual = pagina;

    const q       = (document.getElementById('inputBuscar').value || '').trim();
    const adendum = document.getElementById('filtroAdendum').value;
    const estado  = document.getElementById('filtroEstado').value;

    const params = new URLSearchParams({ pagina, q, adendum, estado, accion: 'lista' });
    const tbody  = document.getElementById('cuerpoTabla');
    tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:25px;color:#6b7280;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>';

    fetch(`cupos_periodo.php?${params}`)
        .then(r => r.text())
        .then(txt => {
            let resp;
            try { resp = JSON.parse(txt); } catch(e) { console.error(txt); return; }

            tbody.innerHTML = '';

            if (!resp.success) {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:25px;color:#ef4444;">❌ ${resp.message || 'Error'}</td></tr>`;
                return;
            }

            const lista      = resp.datos || [];
            const paginaR    = resp.pagina       || 1;
            const totalPag   = resp.totalPaginas || 1;
            const total      = resp.total        || 0;
            const porPagina  = resp.porPagina    || 15;
            const stats      = resp.stats        || {};

            // Actualizar stats
            document.getElementById('statTotal').textContent    = stats.total      || 0;
            document.getElementById('statConCupo').textContent  = stats.con_cupo   || 0;
            document.getElementById('statSinCupo').textContent  = stats.sin_cupo   || 0;
            document.getElementById('statKgTotal').textContent  = parseFloat(stats.kg_total || 0).toLocaleString('es-EC', {minimumFractionDigits:2});

            if (!lista.length) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:30px;color:#6b7280;">No se encontraron registros.</td></tr>';
                document.getElementById('paginacion').innerHTML       = '';
                document.getElementById('infoPaginacion').textContent = '';
                return;
            }

            const inicio = (paginaR - 1) * porPagina;
            lista.forEach((row, idx) => {
                const idLpa     = row.id_lpa;
                const cupoActual= parseFloat(row.volumen_produccion_estimado) || 0;
                const consumido = parseFloat(row.cupo_consumido)              || 0;
                const disponible= cupoActual - consumido;
                const pct       = cupoActual > 0 ? ((consumido / cupoActual) * 100).toFixed(1) : 0;

                // Si hay un cambio pendiente en caché, mostrar ese valor
                const valorInput = cambiosPendientes.hasOwnProperty(idLpa)
                    ? cambiosPendientes[idLpa]
                    : cupoActual;

                const esPendiente = cambiosPendientes.hasOwnProperty(idLpa);
                const estadoClass = (row.estado_lpa || '').toLowerCase();
                const tieneCupo = cupoActual > 0;
                const ad = row.adendum == 2
                    ? '<span class="badge-ad2">Adendum 2</span>'
                    : '<span class="badge-ad1">Adendum 1</span>';

                tbody.innerHTML += `
                <tr id="fila-${idLpa}" class="${tieneCupo ? 'fila-con-cupo' : ''}">
                    <td>${inicio + idx + 1}</td>
                    <td>${row.identificacion || '-'}</td>
                    <td><strong>${row.nombre_completo || '-'}</strong></td>
                    <td>${row.zona || '-'}</td>
                    <td style="text-align:right;">${parseFloat(row.area_cacao_ha || 0).toFixed(2)}</td>
                    <td style="text-align:center;">${ad}</td>
                    <td style="text-align:center;"><span class="estado ${estadoClass}">${row.estado_lpa || '-'}</span></td>
                    <td style="text-align:right;font-weight:600;color:#374151;">${cupoActual.toFixed(2)}</td>
                    <td>
                        <input
                            type="number"
                            class="cupo-input ${esPendiente ? 'modificado' : (cupoActual === 0 ? 'sin-cupo' : '')}"
                            id="cupo-${idLpa}"
                            value="${valorInput}"
                            step="0.01"
                            min="0"
                            data-id-lpa="${idLpa}"
                            data-cupo-original="${cupoActual}"
                            onchange="marcarCambio(${idLpa}, this)"
                            oninput="marcarCambio(${idLpa}, this)"
                        >
                    </td>
                    <td style="text-align:right;color:${pct >= 90 ? '#ef4444' : pct >= 70 ? '#f59e0b' : '#10b981'};font-weight:600;">
                        ${consumido.toFixed(2)}
                        <small style="color:#9ca3af;font-weight:400;">(${pct}%)</small>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        <button class="btn-icon azul" title="Ver detalle / imprimir"
                            onclick='abrirDetalle(${idLpa},${row.id_socio},"${escapa(row.identificacion)}","${escapa(row.nombre_completo)}",${cupoActual},${consumido})'>
                            <i class="fa fa-eye"></i>
                        </button>
                        <button class="btn-icon verde" title="Guardar este cupo"
                            onclick="guardarCupoUno(${idLpa})">
                            <i class="fa fa-save"></i>
                        </button>
                        <button class="btn-icon naranja" title="Imprimir Acuerdo Productor"
                            onclick="imprimirAcuerdo(${idLpa}, ${row.id_socio})">
                            <i class="fa fa-print"></i>
                        </button>
                    </td>
                </tr>`;
            });

            renderPaginacion(paginaR, totalPag, total, porPagina);
        })
        .catch(err => {
            document.getElementById('cuerpoTabla').innerHTML =
                '<tr><td colspan="11" style="text-align:center;padding:25px;color:#ef4444;">❌ Error al cargar. Ver consola.</td></tr>';
            console.error(err);
        });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function escapa(s) { return (s || '').replace(/"/g, '&quot;').replace(/'/g, "\\'"); }
function buscarConDelay() {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(() => cargarProductores(1), 500);
}
function limpiarFiltros() {
    document.getElementById('inputBuscar').value   = '';
    document.getElementById('filtroAdendum').value = '';
    document.getElementById('filtroEstado').value  = '';
    cargarProductores(1);
}

// ─── Marcar cambio pendiente ─────────────────────────────────────────────────
function marcarCambio(idLpa, inputEl) {
    const nuevo   = parseFloat(inputEl.value) || 0;
    const original= parseFloat(inputEl.dataset.cupoOriginal) || 0;
    if (nuevo !== original) {
        cambiosPendientes[idLpa] = nuevo;
        inputEl.classList.add('modificado');
        inputEl.classList.remove('sin-cupo');
    } else {
        delete cambiosPendientes[idLpa];
        inputEl.classList.remove('modificado');
        if (original === 0) inputEl.classList.add('sin-cupo');
    }
    actualizarBadgePendientes();
}

function actualizarBadgePendientes() {
    const n = Object.keys(cambiosPendientes).length;
    const badge = document.getElementById('badgePendientes');
    const texto = document.getElementById('textoPendientes');
    if (n > 0) {
        badge.style.display = 'flex';
        texto.textContent   = `${n} cambio${n > 1 ? 's' : ''} sin guardar`;
    } else {
        badge.style.display = 'none';
    }
}

// ─── Guardar cupo individual (botón de fila) ─────────────────────────────────
function guardarCupoUno(idLpa) {
    const input  = document.getElementById(`cupo-${idLpa}`);
    if (!input) return;
    const nuevo  = parseFloat(input.value);
    if (isNaN(nuevo) || nuevo < 0) { mostrarToast('Cupo inválido', 'error'); return; }

    fetch('cupos_guardar.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : `id_lpa=${idLpa}&nuevo_cupo=${nuevo}`
    })
    .then(r => r.json())
    .then(j => {
        if (j.success) {
            delete cambiosPendientes[idLpa];
            input.dataset.cupoOriginal = nuevo;
            input.classList.remove('modificado', 'sin-cupo');
            if (nuevo === 0) input.classList.add('sin-cupo');
            actualizarBadgePendientes();
            // Actualizar celda "Cupo Actual"
            const fila = document.getElementById(`fila-${idLpa}`);
            if (fila) fila.cells[7].textContent = nuevo.toFixed(2);
            mostrarToast('✅ Cupo guardado correctamente', 'success');
            cargarEstadisticas();
        } else {
            mostrarToast(j.message || 'Error al guardar', 'error');
        }
    })
    .catch(() => mostrarToast('Error de red', 'error'));
}

// ─── Guardar TODOS los cambios ────────────────────────────────────────────────
function guardarTodosCambios() {
    const ids = Object.keys(cambiosPendientes);
    if (!ids.length) { mostrarToast('No hay cambios pendientes', ''); return; }

    const payload = ids.map(id => ({ id_lpa: parseInt(id), nuevo_cupo: cambiosPendientes[id] }));

    fetch('cupos_guardar.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ lote: payload })
    })
    .then(r => r.json())
    .then(j => {
        if (j.success) {
            cambiosPendientes = {};
            actualizarBadgePendientes();
            mostrarToast(`✅ ${j.actualizados} cupo(s) aplicado(s)`, 'success');
            cargarProductores(paginaActual);
        } else {
            mostrarToast(j.message || 'Error', 'error');
        }
    })
    .catch(() => mostrarToast('Error de red', 'error'));
}

// ─── Cupo global ─────────────────────────────────────────────────────────────
function abrirModalCupoGlobal() {
    document.getElementById('cupoGlobalValor').value = '';
    document.getElementById('modalCupoGlobal').classList.add('active');
}

function aplicarCupoGlobal() {
    const val    = parseFloat(document.getElementById('cupoGlobalValor').value);
    const filtro = document.getElementById('cupoGlobalFiltro').value;
    if (!val || val <= 0) { mostrarToast('Ingrese un cupo válido', 'error'); return; }

    // Aplicar a todos los inputs visibles (o solo los con valor 0)
    const inputs = document.querySelectorAll('.cupo-input');
    inputs.forEach(inp => {
        const idLpa  = parseInt(inp.dataset.idLpa);
        const actual = parseFloat(inp.dataset.cupoOriginal) || 0;
        if (filtro === 'sin_cupo' && actual > 0) return;
        inp.value = val;
        marcarCambio(idLpa, inp);
    });

    cerrarModal('modalCupoGlobal');
    mostrarToast(`Cupo ${val} kg aplicado en tabla. Pulsa "Aplicar Cupos" para guardar.`, 'success');
}

// ─── Modal detalle individual ─────────────────────────────────────────────────
function abrirDetalle(idLpa, idSocio, cedula, nombre, cupoActual, consumido) {
    productoraActual = { idLpa, idSocio, cedula, nombre, cupoActual, consumido };

    const disponible = cupoActual - consumido;
    const pct        = cupoActual > 0 ? ((consumido / cupoActual) * 100).toFixed(1) : 0;
    const pctNum     = parseFloat(pct);

    document.getElementById('infoSocioDetalle').innerHTML = `
        <p><strong>Cédula:</strong> ${cedula}</p>
        <p><strong>Productor/a:</strong> ${nombre}</p>
        <p><strong>Cupo Actual:</strong> ${cupoActual.toFixed(2)} kg &nbsp;|&nbsp;
           <strong>Consumido:</strong> ${consumido.toFixed(2)} kg &nbsp;|&nbsp;
           <strong>Disponible:</strong> ${disponible.toFixed(2)} kg</p>`;

    document.getElementById('cupoIndividualValor').value = cupoActual;

    // Barra de progreso
    const wrap = document.getElementById('progressWrap');
    wrap.style.display = cupoActual > 0 ? 'block' : 'none';
    if (cupoActual > 0) {
        const fill = document.getElementById('cupoBarFill');
        fill.style.width    = Math.min(pctNum, 100) + '%';
        fill.className      = 'cupo-bar-fill' + (pctNum >= 90 ? ' danger' : pctNum >= 70 ? ' warn' : '');
        document.getElementById('pctLabel').textContent      = pct + '%';
        document.getElementById('consumidoLabel').textContent = consumido.toFixed(2) + ' kg';
        document.getElementById('totalLabel').textContent    = cupoActual.toFixed(2) + ' kg';
    }

    document.getElementById('modalDetalleCupo').classList.add('active');
}

function guardarCupoIndividual() {
    if (!productoraActual) return;
    const nuevo = parseFloat(document.getElementById('cupoIndividualValor').value);
    if (isNaN(nuevo) || nuevo < 0) { mostrarToast('Cupo inválido', 'error'); return; }

    // Sincronizar al input de la tabla
    const inputTabla = document.getElementById(`cupo-${productoraActual.idLpa}`);
    if (inputTabla) {
        inputTabla.value = nuevo;
        marcarCambio(productoraActual.idLpa, inputTabla);
    }

    guardarCupoUno(productoraActual.idLpa);
    cerrarModal('modalDetalleCupo');
}

function imprimirAcuerdoProdutor() {
    if (!productoraActual) return;
    imprimirAcuerdo(productoraActual.idLpa, productoraActual.idSocio);
}

// ─── Imprimir Acuerdo Productor ───────────────────────────────────────────────
function imprimirAcuerdo(idLpa, idSocio) {
    const url = `cupos_acuerdo_pdf.php?id_lpa=${idLpa}&id_socio=${idSocio}`;
    window.open(url, '_blank');
}

// ─── Exportar ─────────────────────────────────────────────────────────────────
function exportarExcel() {
    window.open('cupos_exportar.php', '_blank');
}

// ─── Estadísticas rápidas (sin recargar tabla) ───────────────────────────────
function cargarEstadisticas() {
    fetch('cupos_periodo.php?accion=stats')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.stats) {
                const s = data.stats;
                document.getElementById('statTotal').textContent    = s.total    || 0;
                document.getElementById('statConCupo').textContent  = s.con_cupo || 0;
                document.getElementById('statSinCupo').textContent  = s.sin_cupo || 0;
                document.getElementById('statKgTotal').textContent  = parseFloat(s.kg_total || 0).toLocaleString('es-EC', {minimumFractionDigits:2});
            }
        })
        .catch(() => {});
}

// ─── Paginación ───────────────────────────────────────────────────────────────
function renderPaginacion(pagina, totalPaginas, total, porPagina) {
    const div  = document.getElementById('paginacion');
    const info = document.getElementById('infoPaginacion');
    const desde = (pagina - 1) * porPagina + 1;
    const hasta = Math.min(pagina * porPagina, total);
    info.textContent = `Mostrando ${desde}–${hasta} de ${total} registros`;

    if (totalPaginas <= 1) { div.innerHTML = ''; return; }

    let html = `<button onclick="cargarProductores(1)" ${pagina===1?'disabled':''}>«</button>`;
    html    += `<button onclick="cargarProductores(${pagina-1})" ${pagina===1?'disabled':''}>‹</button>`;
    const rango = 2;
    for (let p = Math.max(1, pagina-rango); p <= Math.min(totalPaginas, pagina+rango); p++) {
        html += `<button onclick="cargarProductores(${p})" class="${p===pagina?'active':''}">${p}</button>`;
    }
    html += `<button onclick="cargarProductores(${pagina+1})" ${pagina===totalPaginas?'disabled':''}>›</button>`;
    html += `<button onclick="cargarProductores(${totalPaginas})" ${pagina===totalPaginas?'disabled':''}>»</button>`;
    div.innerHTML = html;
}

// ─── Toast ────────────────────────────────────────────────────────────────────
function mostrarToast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'show ' + (tipo || '');
    setTimeout(() => { t.className = ''; }, 3200);
}

// ─── Cerrar modal ─────────────────────────────────────────────────────────────
function cerrarModal(id) {
    document.getElementById(id).classList.remove('active');
}
</script>
</body>
</html>
