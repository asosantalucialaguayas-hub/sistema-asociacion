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
<title>Documentos de Socios</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php include 'layout/modals.php'; ?>
<style>
.app { display:flex; height:100vh; overflow:hidden; }
.sidebar { position:sticky; top:0; height:100vh; overflow-y:auto; flex-shrink:0; }
.content { flex:1; overflow-y:auto; height:100vh; }

.btn-primary   { background:#1f3a5f; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-secondary { background:#5f7c99; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-success   { background:#10b981; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-danger    { background:#ef4444; color:#fff; padding:10px 18px; border-radius:8px; border-radius:8px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary:hover   { background:#162e4a; }
.btn-secondary:hover { background:#4a6580; }
.btn-success:hover   { background:#059669; }
.btn-danger:hover    { background:#dc2626; }

.search-box { background:#f9fafb; padding:20px; border-radius:10px; margin-bottom:22px; }
.search-box h3 { margin:0 0 12px; color:#1f3a5f; font-size:16px; }
.search-row { display:flex; gap:12px; align-items:center; }
.search-input { flex:1; padding:10px 14px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; }

.socio-selected { background:#eff6ff; border:2px solid#3b82f6; padding:16px; border-radius:10px; margin-bottom:20px; display:none; }
.socio-selected.active { display:block; }
.socio-selected h4 { margin:0 0 8px; color:#1f3a5f; }
.socio-selected p { margin:4px 0; font-size:13px; color:#374151; }

.stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
.stat-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px; text-align:center; }
.stat-card h4 { margin:0 0 6px; font-size:11px; color:#6b7280; text-transform:uppercase; }
.stat-card p { margin:0; font-size:24px; font-weight:700; color:#1f3a5f; }

.btn-actions { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }

.table-container { width:100%; overflow-x:auto; }
.data-table { width:100%; border-collapse:collapse; font-size:13px; }
.data-table thead th { background:#1f3a5f; color:#fff; padding:11px 10px; text-align:left; white-space:nowrap; position:sticky; top:0; z-index:10; }
.data-table td { padding:10px; border-bottom:1px solid #e5e7eb; vertical-align:middle; }
.data-table tbody tr:hover { background:#f9fafb; }

.btn-icon { width:32px; height:32px; border-radius:6px; border:none; cursor:pointer; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; margin-right:4px; }
.btn-icon.azul   { background:#3b82f6; }
.btn-icon.verde  { background:#10b981; }
.btn-icon.rojo   { background:#ef4444; }
.btn-icon.azul:hover   { background:#2563eb; }
.btn-icon.verde:hover  { background:#059669; }
.btn-icon.rojo:hover   { background:#dc2626; }

.badge-tipo { padding:4px 10px; border-radius:4px; font-size:11px; font-weight:700; display:inline-block; }
.badge-acuerdo   { background:#dbeafe; color:#1d4ed8; }
.badge-cedula    { background:#fef3c7; color:#92400e; }
.badge-certif    { background:#d1fae5; color:#065f46; }
.badge-contrato  { background:#fce7f3; color:#9d174d; }
.badge-otro      { background:#f3f4f6; color:#374151; }

.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.55); z-index:999999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,.25); padding:28px; position:relative; max-width:680px; width:95%; max-height:90vh; overflow:auto; }
.close-btn { position:absolute; top:14px; right:14px; width:36px; height:36px; border-radius:50%; border:none; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.2); cursor:pointer; font-size:18px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:9px 11px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; box-sizing:border-box; }
.form-actions { margin-top:18px; display:flex; gap:10px; justify-content:flex-end; }

#toast { position:fixed; bottom:24px; right:24px; background:#1f3a5f; color:#fff; padding:12px 22px; border-radius:10px; font-size:14px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.25); z-index:9999999; transform:translateY(80px); opacity:0; transition:all .3s; }
#toast.show { transform:translateY(0); opacity:1; }
#toast.success { background:#10b981; }
#toast.error   { background:#ef4444; }

.preview-img { max-width:100px; max-height:100px; border-radius:6px; cursor:pointer; }
.modal-img-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.9); z-index:99999999; align-items:center; justify-content:center; }
.modal-img-overlay.active { display:flex; }
.modal-img-overlay img { max-width:90%; max-height:90vh; }
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
<h1><i class="fa fa-folder-open" style="color:#1f3a5f;margin-right:8px;"></i> Documentos de Socios</h1>

<!-- Búsqueda de socio -->
<div class="search-box">
    <h3>🔍 Buscar Socio</h3>
    <div class="search-row">
        <input type="text" id="inputBuscarSocio" class="search-input" placeholder="Escriba cédula o nombre del socio...">
        <button class="btn-primary" onclick="buscarSocio()"><i class="fa fa-search"></i> Buscar</button>
    </div>
    <div id="resultadosBusqueda" style="margin-top:12px;"></div>
</div>

<!-- Socio seleccionado -->
<div class="socio-selected" id="socioSeleccionado">
    <h4><i class="fa fa-user-check"></i> Socio Seleccionado</h4>
    <p><strong>Nombre:</strong> <span id="socioNombre">-</span></p>
    <p><strong>Cédula:</strong> <span id="socioCedula">-</span></p>
    <p><strong>Estado:</strong> <span id="socioEstado">-</span></p>
    <input type="hidden" id="socioIdSeleccionado">
</div>

<!-- Stats -->
<div class="stats-row" id="statsRow" style="display:none;">
    <div class="stat-card"><h4>Total Documentos</h4><p id="statTotal">0</p></div>
    <div class="stat-card"><h4>Acuerdos Firmados</h4><p id="statAcuerdos">0</p></div>
    <div class="stat-card"><h4>Otros Documentos</h4><p id="statOtros">0</p></div>
</div>

<!-- Botones de acción -->
<div class="btn-actions" id="botonesAccion" style="display:none;">
    <button class="btn-success" onclick="abrirModalSubir()">
        <i class="fa fa-upload"></i> Subir Documento
    </button>
</div>

<!-- Tabla de documentos -->
<div class="form-card" id="tablaDocumentos" style="display:none;">
<div class="table-container">
<table class="data-table">
<thead>
<tr>
    <th>#</th>
    <th>Tipo</th>
    <th>Nombre Archivo</th>
    <th>Periodo</th>
    <th>Fecha Subida</th>
    <th>Tamaño</th>
    <th>Observación</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTablaDocumentos">
    <tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7280;">
        Seleccione un socio para ver sus documentos
    </td></tr>
</tbody>
</table>
</div>
</div>

</section>
</main>
</div>

<!-- MODAL: Subir documento -->
<div id="modalSubir" class="modal-overlay">
<div class="modal-box">
<button class="close-btn" onclick="cerrarModal('modalSubir')">×</button>
<h2><i class="fa fa-upload"></i> Subir Documento</h2>
<form id="formSubir" enctype="multipart/form-data">
    <input type="hidden" id="uploadIdSocio" name="id_socio">
    <div class="form-group">
        <label>Tipo de Documento *</label>
        <select id="uploadTipo" name="tipo_documento" required>
            <option value="">Seleccionar</option>
            <option value="Acuerdo Productor Firmado">Acuerdo Productor Firmado</option>
            <option value="Cédula">Cédula</option>
            <option value="Certificado Bancario">Certificado Bancario</option>
            <option value="Contrato">Contrato</option>
            <option value="Certificación Orgánica">Certificación Orgánica</option>
            <option value="Carta Compromiso">Carta Compromiso</option>
            <option value="Otro">Otro</option>
        </select>
    </div>
    <div class="form-group">
        <label>Periodo *</label>
        <select id="uploadPeriodo" name="id_periodo" required>
            <option value="">Cargando periodos...</option>
        </select>
    </div>
    <div class="form-group">
        <label>Archivo (PDF, JPG, PNG) * <small style="color:#6b7280;">(Máx 10MB)</small></label>
        <input type="file" id="uploadArchivo" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>
    </div>
    <div class="form-group">
        <label>Observación</label>
        <textarea id="uploadObs" name="observacion" rows="3" placeholder="Opcional"></textarea>
    </div>
    <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="cerrarModal('modalSubir')">Cancelar</button>
        <button type="submit" class="btn-success"><i class="fa fa-upload"></i> Subir</button>
    </div>
</form>
</div>
</div>

<!-- MODAL: Ver imagen ampliada -->
<div id="modalImagen" class="modal-img-overlay" onclick="cerrarImagen()">
    <img id="imagenAmpliada" src="">
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
let socioActual = null;

window.onload = function() {
    cargarPeriodos();
};

// ─── Buscar socio ─────────────────────────────────────────────────────────────
document.getElementById('inputBuscarSocio').addEventListener('keydown', e => {
    if (e.key === 'Enter') buscarSocio();
});

async function buscarSocio() {
    const q = document.getElementById('inputBuscarSocio').value.trim();
    if (!q) { mostrarToast('Escriba algo para buscar', 'error'); return; }

    const div = document.getElementById('resultadosBusqueda');
    div.innerHTML = '<p style="color:#6b7280;">🔍 Buscando...</p>';

    try {
        const res  = await fetch(`documentos_socios_buscar.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();

        if (!data.success || !data.socios || !data.socios.length) {
            div.innerHTML = '<p style="color:#ef4444;">❌ No se encontraron socios</p>';
            return;
        }

        const html = data.socios.map(s => `
            <div style="padding:10px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;margin-top:8px;cursor:pointer;transition:all .2s;"
                 onclick='seleccionarSocio(${JSON.stringify(s).replace(/'/g, "\\'")})'
                 onmouseover="this.style.background='#f9fafb'"
                 onmouseout="this.style.background='#fff'">
                <strong>${s.nombre_completo}</strong> &nbsp;|&nbsp; CI: ${s.identificacion} &nbsp;|&nbsp;
                <span style="background:#${s.estado === 'activo' ? '10b981' : 'ef4444'};color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">${s.estado}</span>
            </div>
        `).join('');
        div.innerHTML = html;
    } catch(e) {
        div.innerHTML = '<p style="color:#ef4444;">Error al buscar</p>';
        console.error(e);
    }
}

function seleccionarSocio(socio) {
    socioActual = socio;
    document.getElementById('socioIdSeleccionado').value = socio.id_socio;
    document.getElementById('socioNombre').textContent    = socio.nombre_completo;
    document.getElementById('socioCedula').textContent    = socio.identificacion;
    document.getElementById('socioEstado').textContent    = socio.estado;
    document.getElementById('socioSeleccionado').classList.add('active');
    document.getElementById('statsRow').style.display         = 'grid';
    document.getElementById('botonesAccion').style.display    = 'flex';
    document.getElementById('tablaDocumentos').style.display  = 'block';
    document.getElementById('resultadosBusqueda').innerHTML   = '';
    document.getElementById('inputBuscarSocio').value         = '';
    cargarDocumentos();
}

// ─── Cargar documentos del socio ──────────────────────────────────────────────
async function cargarDocumentos() {
    if (!socioActual) return;
    const tbody = document.getElementById('cuerpoTablaDocumentos');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>';

    try {
        const res  = await fetch(`documentos_socios_listar.php?id_socio=${socioActual.id_socio}`);
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:20px;color:#ef4444;">❌ ${data.message || 'Error'}</td></tr>`;
            return;
        }

        const docs  = data.documentos || [];
        const stats = data.stats      || {};

        document.getElementById('statTotal').textContent    = stats.total    || 0;
        document.getElementById('statAcuerdos').textContent = stats.acuerdos || 0;
        document.getElementById('statOtros').textContent    = stats.otros    || 0;

        if (!docs.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7280;">📄 Sin documentos cargados</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        docs.forEach((d, i) => {
            const tipo = getTipoBadge(d.tipo_documento);
            const ext  = (d.ruta_archivo || '').split('.').pop().toLowerCase();
            const isImg = ['jpg', 'jpeg', 'png'].includes(ext);
            const isPdf = ext === 'pdf';

            let preview = '-';
            if (isImg) {
                preview = `<img src="${d.ruta_archivo}" class="preview-img" onclick="verImagen('${d.ruta_archivo}')" title="Click para ampliar">`;
            }

            tbody.innerHTML += `
            <tr>
                <td>${i + 1}</td>
                <td>${tipo}</td>
                <td><strong>${d.nombre_archivo || '-'}</strong></td>
                <td>${d.periodo || '-'}</td>
                <td>${d.fecha_carga ? d.fecha_carga.substring(0, 10) : '-'}</td>
                <td>${formatBytes(d.tamano_archivo)}</td>
                <td>${d.observacion || '-'}</td>
                <td style="white-space:nowrap;">
                    ${isPdf ? `<button class="btn-icon azul" title="Ver PDF" onclick="verPDF('${d.ruta_archivo}', '${d.nombre_archivo}')"><i class="fa fa-file-pdf"></i></button>` : ''}
                    ${isImg ? `<button class="btn-icon azul" title="Ver imagen" onclick="verImagen('${d.ruta_archivo}')"><i class="fa fa-image"></i></button>` : ''}
                    <button class="btn-icon verde" title="Descargar" onclick="descargar('${d.ruta_archivo}', '${d.nombre_archivo}')"><i class="fa fa-download"></i></button>
                    <button class="btn-icon rojo" title="Eliminar" onclick="confirmarEliminar(${d.id_documento}, '${d.nombre_archivo}')"><i class="fa fa-trash"></i></button>
                </td>
            </tr>`;
        });
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;color:#ef4444;">❌ Error al cargar</td></tr>';
        console.error(e);
    }
}

function getTipoBadge(tipo) {
    const t = (tipo || '').toLowerCase();
    if (t.includes('acuerdo'))       return '<span class="badge-tipo badge-acuerdo">Acuerdo Firmado</span>';
    if (t.includes('cedula'))        return '<span class="badge-tipo badge-cedula">Cédula</span>';
    if (t.includes('certif'))        return '<span class="badge-tipo badge-certif">Certificado</span>';
    if (t.includes('contrato'))      return '<span class="badge-tipo badge-contrato">Contrato</span>';
    return '<span class="badge-tipo badge-otro">' + tipo + '</span>';
}

function formatBytes(bytes) {
    if (!bytes || bytes == 0) return '-';
    const k = 1024;
    if (bytes < k) return bytes + ' B';
    if (bytes < k*k) return (bytes/k).toFixed(1) + ' KB';
    return (bytes/(k*k)).toFixed(2) + ' MB';
}

// ─── Subir documento ──────────────────────────────────────────────────────────
function abrirModalSubir() {
    if (!socioActual) return;
    document.getElementById('uploadIdSocio').value = socioActual.id_socio;
    document.getElementById('formSubir').reset();
    document.getElementById('uploadIdSocio').value = socioActual.id_socio;
    document.getElementById('modalSubir').classList.add('active');
}

document.getElementById('formSubir').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);

    try {
        const res  = await fetch('documentos_socios_subir.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            cerrarModal('modalSubir');
            mostrarToast('✅ Documento subido correctamente', 'success');
            cargarDocumentos();
        } else {
            mostrarToast(data.message || 'Error al subir', 'error');
        }
    } catch(e) {
        mostrarToast('Error de red', 'error');
        console.error(e);
    }
});

// ─── Ver PDF / Imagen ─────────────────────────────────────────────────────────
function verPDF(ruta, nombre) {
    window.open(ruta, '_blank');
}

function verImagen(ruta) {
    document.getElementById('imagenAmpliada').src = ruta;
    document.getElementById('modalImagen').classList.add('active');
}

function cerrarImagen() {
    document.getElementById('modalImagen').classList.remove('active');
}

function descargar(ruta, nombre) {
    const a = document.createElement('a');
    a.href = ruta;
    a.download = nombre;
    a.click();
}

// ─── Eliminar documento ───────────────────────────────────────────────────────
async function confirmarEliminar(id, nombre) {
    if (!confirm(`¿Eliminar "${nombre}"?`)) return;

    try {
        const res  = await fetch('documentos_socios_eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        });
        const data = await res.json();

        if (data.success) {
            mostrarToast('✅ Eliminado', 'success');
            cargarDocumentos();
        } else {
            mostrarToast(data.message || 'Error', 'error');
        }
    } catch(e) {
        mostrarToast('Error', 'error');
    }
}

// ─── Cargar periodos ──────────────────────────────────────────────────────────
async function cargarPeriodos() {
    try {
        const res  = await fetch('periodos_obtener.php');
        const data = await res.json();
        const sel  = document.getElementById('uploadPeriodo');

        if (data.success && data.periodos) {
            sel.innerHTML = '<option value="">Seleccionar periodo</option>' +
                data.periodos.map(p => `<option value="${p.id_periodo}">${p.nombre} (${p.estado})</option>`).join('');
        } else {
            sel.innerHTML = '<option value="">Sin periodos</option>';
        }
    } catch(e) {
        console.error(e);
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }

function mostrarToast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'show ' + (tipo || '');
    setTimeout(() => { t.className = ''; }, 3200);
}
</script>
</body>
</html>