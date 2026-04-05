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
<title>LPA</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php include 'layout/modals.php'; ?>

<style>
/* ── Sidebar fijo ── */
.app { display: flex; height: 100vh; overflow: hidden; }
.sidebar, nav.sidebar, aside.sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; flex-shrink: 0; }
.content { flex: 1; overflow-y: auto; height: 100vh; }

/* ── Botones ── */
.btn-actions { display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap; align-items:center; }
.btn-primary  { background:#1f3a5f; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; }
.btn-secondary{ background:#5f7c99; color:#fff; padding:10px 18px; border-radius:8px; border:none; font-weight:600; cursor:pointer; }
.btn-primary:hover  { background:#1f405f; }
.btn-secondary:hover{ background:#4a6580; }

/* ── Tabla CORREGIDA ── */
.table-container { width: 100%; overflow-x: auto; }
.data-table { 
    width:100%; 
    border-collapse:collapse; 
    font-size:13px; 
    table-layout: fixed; /* ESTO ARREGLA EL ANCHO */
}
.data-table thead th { 
    background:#1f3a5f; 
    color:#fff; 
    padding:12px 8px; 
    text-align:left; 
    white-space:nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}
.data-table td { 
    padding:10px 8px; 
    border-bottom:1px solid #e5e7eb; 
    vertical-align:middle; 
    word-wrap: break-word;
}
.data-table tbody tr:hover { background:#f9fafb; }

/* Anchos específicos para cada columna */
.data-table th:nth-child(1), .data-table td:nth-child(1) { width: 40px; }  /* # */
.data-table th:nth-child(2), .data-table td:nth-child(2) { width: 110px; } /* Cédula */
.data-table th:nth-child(3), .data-table td:nth-child(3) { width: 180px; } /* Nombre */
.data-table th:nth-child(4), .data-table td:nth-child(4) { width: 50px; }  /* Sexo */
.data-table th:nth-child(5), .data-table td:nth-child(5) { width: 100px; } /* Celular */
.data-table th:nth-child(6), .data-table td:nth-child(6) { width: 100px; } /* Fecha Nac */
.data-table th:nth-child(7), .data-table td:nth-child(7) { width: 110px; } /* Fecha Afil */
.data-table th:nth-child(8), .data-table td:nth-child(8) { width: 90px; }  /* Zona */
.data-table th:nth-child(9), .data-table td:nth-child(9) { width: 90px; }  /* Área */
.data-table th:nth-child(10), .data-table td:nth-child(10) { width: 100px; } /* Vol Prod */
.data-table th:nth-child(11), .data-table td:nth-child(11) { width: 100px; } /* Vol Entr */
.data-table th:nth-child(12), .data-table td:nth-child(12) { width: 130px; }  /* Adendum */
.data-table th:nth-child(13), .data-table td:nth-child(13) { width: 80px; }  /* Estado */
.data-table th:nth-child(14), .data-table td:nth-child(14) { width: 120px; } /* Acciones */

/* ── Botones de acción CORREGIDOS ── */
.btn-icon { 
    width:32px; 
    height:32px; 
    border-radius:6px; 
    border:none; 
    cursor:pointer; 
    color:#fff; 
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right:4px;
    font-size: 14px;
}
.btn-icon.btn-view { background:#3b82f6; }
.btn-icon.btn-view:hover { background:#2563eb; }
.btn-icon.btn-edit { background:#f59e0b; }
.btn-icon.btn-edit:hover { background:#d97706; }
.btn-icon.btn-delete { background:#ef4444; }
.btn-icon.btn-delete:hover { background:#dc2626; }

/* ── Modales ── */
.modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.55); z-index:999999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { display:block; background:#fff; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,.25); padding:25px; position:relative; max-width:800px; width:95%; max-height:90vh; overflow:auto; }
.close-btn { position:absolute; top:14px; right:14px; width:36px; height:36px; border-radius:50%; border:none; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,.25); cursor:pointer; font-size:18px; z-index:10001; }

/* ── Formularios ── */
.form-group { margin-bottom:15px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
.form-group input, .form-group select { width:100%; padding:9px 11px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; box-sizing:border-box; }
.form-actions { margin-top:20px; display:flex; gap:10px; justify-content:flex-end; }
.badge { background:#e5e7eb; padding:4px 8px; border-radius:4px; font-size:12px; color:#374151; }
.estado { padding:4px 12px; border-radius:4px; font-size:12px; font-weight:600; color:#fff; display:inline-block; }
.estado.activo  { background:#10b981; }
.estado.inactivo{ background:#ef4444; }
.meses-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:10px; }

/* ── Búsqueda socio ── */
.label-large { font-size:16px; font-weight:700; color:#1f3a5f; margin-bottom:6px; display:block; }
.search-input { flex:1; padding:10px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:16px; }
.search-btn { width:36px; height:36px; border-radius:6px; border:none; background:#1f3a5f; color:#fff; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
.search-btn i { font-size:14px; }

/* ── Toolbar búsqueda/filtros MEJORADO ── */
.toolbar { 
    display:flex; 
    gap:10px; 
    align-items:center; 
    margin-bottom:14px; 
    flex-wrap:wrap; 
    background: #f9fafb;
    padding: 15px;
    border-radius: 8px;
}
.toolbar input  { 
    padding:9px 12px; 
    border-radius:8px; 
    border:1px solid #d1d5db; 
    font-size:14px; 
    min-width:280px;
    flex: 1;
}
.toolbar select { 
    padding:9px 12px; 
    border-radius:8px; 
    border:1px solid #d1d5db; 
    font-size:14px; 
    min-width: 180px;
}

/* ── Badges adendum ── */
.badge-adendum { padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; display:inline-block; }
.badge-ad1 { background:#dbeafe; color:#1d4ed8; }
.badge-ad2 { background:#fef9c3; color:#854d0e; }

/* ── Paginación ── */
.paginacion { display:flex; align-items:center; gap:6px; margin-top:16px; justify-content:center; flex-wrap:wrap; }
.paginacion button { padding:7px 13px; border-radius:8px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-size:13px; font-weight:600; }
.paginacion button:hover   { background:#f3f4f6; }
.paginacion button.active  { background:#1f3a5f; color:#fff; border-color:#1f3a5f; }
.paginacion button:disabled{ opacity:.4; cursor:not-allowed; }
.info-paginacion { text-align:center; margin-top:6px; font-size:13px; color:#6b7280; }
</style>
</head>

<body>
<script src="layout/modal-message.js"></script>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= $_SESSION['usuario'] ?></span>
</header>

<section class="page">
<h1>Registro de datos (LPA)</h1>

<div class="btn-actions">
    <button class="btn-primary" onclick="abrirModalNuevaLPA()">
        <i class="fa fa-plus"></i> Nueva LPA
    </button>
    <a href="lpa_exportar.php">
        <button class="btn-secondary">
            <i class="fa fa-file-excel"></i> Exportar
        </button>
    </a>
</div>

<!-- BARRA BÚSQUEDA Y FILTROS MEJORADA -->
<div class="toolbar">
    <input 
        type="text" 
        id="inputBuscar" 
        placeholder="🔍 Buscar por cédula o nombre..." 
        oninput="buscarConDelay()"
    >
   <select id="filtroPeriodo" onchange="cargarLPA(1)">
    <option value="">Todos los períodos</option>
</select>
    <button class="btn-primary" onclick="cargarLPA(1)">
        <i class="fa fa-search"></i> Buscar
    </button>
    <button class="btn-secondary" onclick="limpiarFiltros()">
        <i class="fa fa-rotate-left"></i> Limpiar
    </button>
</div>

<div class="form-card">
<div class="table-container">
<table class="data-table">
<thead>
<tr>
    <th>#</th>
    <th>Cédula</th>
    <th>Productor/a</th>
    <th>Sexo</th>
    <th>Celular</th>
    <th>Fecha Nac.</th>
    <th>Fecha Afiliación</th>
    <th>Zona</th>
    <th>Área Cacao (Ha)</th>
    <th>Vol. Producción</th>
    <th>Vol. Entregado</th>
    <th>Período</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTabla">
    <tr><td colspan="14" style="text-align:center;padding:30px;color:#6b7280;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>
</tbody>
</table>
</div>

<div class="paginacion" id="paginacion"></div>
<div class="info-paginacion" id="infoPaginacion"></div>
</div>

</section>
</main>
</div>

<!-- MODAL NUEVA LPA -->
<div id="modalNuevaLPA" class="modal-overlay">
<div class="modal-box">
<button class="close-btn" onclick="cerrarModalNuevaLPA()">×</button>
<h2>Nuevo dato LPA</h2>
<form id="formNuevaLPA">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;align-items:end;">
    <input type="hidden" id="id_socio" name="id_socio">

    <div class="form-group">
        <label class="label-large">Buscar socio (cédula)</label>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="text" id="buscadorSocio" class="search-input" placeholder="Cédula">
            <button type="button" class="search-btn" id="btnBuscarSocio" title="Buscar">
                <i class="fa fa-search"></i>
            </button>
        </div>
    </div>
    <div class="form-group"><label>Año</label><input type="number" id="anio" name="anio" value="<?= date('Y') ?>" required></div>

    <div class="form-group"><label class="label-large">Cédula</label><input type="text" id="sel_identificacion" name="sel_identificacion"></div>
    <div class="form-group"><label class="label-large">Productor/a</label><input type="text" id="sel_nombre" name="sel_nombre"></div>

    <div class="form-group">
        <label>Sexo</label>
        <select id="sel_sexo" name="sel_sexo" class="form-control">
            <option value="">Seleccionar</option>
            <option value="M">Masculino</option>
            <option value="F">Femenino</option>
        </select>
    </div>

    <div class="form-group"><label>Celular</label><input type="text" id="sel_telefono" name="sel_telefono"></div>
    <div class="form-group"><label>Fecha de nacimiento</label><input type="date" id="sel_fecha_nacimiento" name="sel_fecha_nacimiento"></div>
    <div class="form-group"><label>Fecha de afiliación</label><input type="date" id="sel_fecha_ingreso" name="sel_fecha_ingreso"></div>

    <div class="form-group"><label>Zona *</label><input type="text" id="zona" name="zona" required></div>
    <div class="form-group"><label>Comunidad o Grupo *</label><input type="text" id="comunidad_grupo" name="comunidad_grupo" required></div>
    <div class="form-group"><label>En Acercamiento</label><select id="en_acercamiento" name="en_acercamiento"><option value="NO">NO</option><option value="SI">SI</option></select></div>
    <div class="form-group"><label>Otra Org. Fairtrade</label><select id="otra_org_fairtrade" name="otra_org_fairtrade"><option value="NO">NO</option><option value="SI">SI</option></select></div>
    <div class="form-group"><label>Área Total (Ha) *</label><input type="number" id="area_total_ha" name="area_total_ha" step="0.01" required></div>
    <div class="form-group"><label>Área Cacao (Ha) *</label><input type="number" id="area_cacao_ha" name="area_cacao_ha" step="0.01" required></div>
    <div class="form-group"><label>Matas/Ha *</label><input type="number" id="num_matas_ha" name="num_matas_ha" required></div>
    <div class="form-group"><label>Certificación Orgánica</label><select id="certificacion_organica" name="certificacion_organica"><option value="NO">NO</option><option value="SI">SI</option></select></div>
    <div class="form-group"><label>Vol. Producción Estimado *</label><input type="number" id="volumen_produccion_estimado" name="volumen_produccion_estimado" step="0.01" required></div>
    <div class="form-group"><label>Vol. Entregado Org. *</label><input type="number" id="volumen_entregado_org" name="volumen_entregado_org" step="0.01" required></div>
<div class="form-group" style="grid-column: span 2;">
    <label>Período *</label>
    <select id="periodo_adendum" name="periodo_adendum" required 
            onchange="aplicarPeriodoAdendum(this)">
        <option value="">Cargando...</option>
    </select>
    <input type="hidden" id="id_periodo" name="id_periodo">
    <input type="hidden" id="adendum" name="adendum" value="1">
</div>
</div>

<h4 style="margin-top:20px; color:#1f3a5f;">Producción Mensual (Ha)</h4>
<div class="meses-grid" id="mesesContainer"></div>

<div class="form-actions">
    <button type="button" class="btn-secondary" onclick="cerrarModalNuevaLPA()">Cancelar</button>
    <button type="submit" class="btn-primary">Guardar</button>
</div>
</form>
</div>
</div>

<!-- MODAL VER LPA -->
<div id="modalVerLPA" class="modal-overlay">
<div class="modal-box">
<button class="close-btn" onclick="cerrarModalVerLPA()">×</button>
<h2>Datos LPA</h2>
<div id="contenidoVerLPA"></div>
<div class="form-actions">
    <button type="button" class="btn-secondary" onclick="cerrarModalVerLPA()">Cerrar</button>
</div>
</div>
</div>

<!-- MODAL EDITAR LPA -->
<div id="modalEditarLPA" class="modal-overlay">
  <div class="modal-box">
    <button class="close-btn" onclick="cerrarModalEditarLPA()">×</button>
    <h2>Editar Datos LPA</h2>
    <div id="info_socio_editar" style="margin:0 0 15px 0; background:#f9fafb; padding:12px; border-radius:6px;"></div>
    <form id="formEditarLPA">
      <input type="hidden" id="id_lpa_editar" name="id_lpa">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
        <div class="form-group"><label>Zona *</label><input type="text" id="zona_editar" name="zona" required></div>
        <div class="form-group"><label>Comunidad o Grupo *</label><input type="text" id="comunidad_grupo_editar" name="comunidad_grupo" required></div>
        <div class="form-group"><label>En Acercamiento</label><select id="en_acercamiento_editar" name="en_acercamiento"><option value="NO">NO</option><option value="SI">SI</option></select></div>
        <div class="form-group"><label>Otra Org. Fairtrade</label><select id="otra_org_fairtrade_editar" name="otra_org_fairtrade"><option value="NO">NO</option><option value="SI">SI</option></select></div>
        <div class="form-group"><label>Área Total (Ha) *</label><input type="number" id="area_total_ha_editar" name="area_total_ha" step="0.01" required></div>
        <div class="form-group"><label>Área Cacao (Ha) *</label><input type="number" id="area_cacao_ha_editar" name="area_cacao_ha" step="0.01" required></div>
        <div class="form-group"><label>Matas/Ha *</label><input type="number" id="num_matas_ha_editar" name="num_matas_ha" required></div>
        <div class="form-group"><label>Certificación Orgánica</label><select id="certificacion_organica_editar" name="certificacion_organica"><option value="NO">NO</option><option value="SI">SI</option></select></div>
        <div class="form-group"><label>Vol. Producción Estimado *</label><input type="number" id="volumen_produccion_estimado_editar" name="volumen_produccion_estimado" step="0.01" required></div>
        <div class="form-group"><label>Vol. Entregado Org. *</label><input type="number" id="volumen_entregado_org_editar" name="volumen_entregado_org" step="0.01" required></div>
        <div class="form-group">
            <label>Adendum</label>
            <select id="adendum_editar" name="adendum">
                <option value="1">Adendum 1</option>
                <option value="2">Adendum 2</option>
            </select>
        </div>
      </div>
      <h4 style="margin-top:20px; color:#1f3a5f;">Producción Mensual (Ha)</h4>
      <div class="meses-grid">
        <div class="form-group"><label>Enero</label><input type="number" id="enero_editar" name="enero" step="0.01"></div>
        <div class="form-group"><label>Febrero</label><input type="number" id="febrero_editar" name="febrero" step="0.01"></div>
        <div class="form-group"><label>Marzo</label><input type="number" id="marzo_editar" name="marzo" step="0.01"></div>
        <div class="form-group"><label>Abril</label><input type="number" id="abril_editar" name="abril" step="0.01"></div>
        <div class="form-group"><label>Mayo</label><input type="number" id="mayo_editar" name="mayo" step="0.01"></div>
        <div class="form-group"><label>Junio</label><input type="number" id="junio_editar" name="junio" step="0.01"></div>
        <div class="form-group"><label>Julio</label><input type="number" id="julio_editar" name="julio" step="0.01"></div>
        <div class="form-group"><label>Agosto</label><input type="number" id="agosto_editar" name="agosto" step="0.01"></div>
        <div class="form-group"><label>Septiembre</label><input type="number" id="septiembre_editar" name="septiembre" step="0.01"></div>
        <div class="form-group"><label>Octubre</label><input type="number" id="octubre_editar" name="octubre" step="0.01"></div>
        <div class="form-group"><label>Noviembre</label><input type="number" id="noviembre_editar" name="noviembre" step="0.01"></div>
        <div class="form-group"><label>Diciembre</label><input type="number" id="diciembre_editar" name="diciembre" step="0.01"></div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="cerrarModalEditarLPA()">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL BUSCAR SOCIO -->
<div id="modalBuscarSocio" class="modal-overlay">
    <div class="modal-box">
        <button class="close-btn" onclick="cerrarModalBuscarSocio()">×</button>
        <h2>Buscar Socio</h2>
        <div style="display:flex;gap:8px;margin-bottom:10px;align-items:center;">
            <input id="inputBuscarSocio" class="search-input" placeholder="Cédula o nombre">
            <button class="search-btn" onclick="buscarSocio()"><i class="fa fa-search"></i></button>
        </div>
        <div id="resultBuscarSocio" style="max-height:300px;overflow:auto"></div>
    </div>
</div>

<script>
const MESES        = ['E','F','M','A','M2','J','JL','A2','S','O','N','D'];
const MESES_NOMBRE = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

let paginaActual = 1;
let buscarTimer  = null;

window.onload = function() {
    cargarLPA(1);
    cargarSocios();
    crearCamposMeses();
    cargarPeriodos();
    // Llenar filtro de períodos en toolbar
    fetch('periodos_obtener.php').then(r=>r.json()).then(data=>{
        const sel = document.getElementById('filtroPeriodo');
        if (!sel || !data.success) return;
        const ad1 = document.createElement('option');
        ad1.value = 'adendum_1'; ad1.textContent = 'Adendum 1'; sel.appendChild(ad1);
        const ad2 = document.createElement('option');
        ad2.value = 'adendum_2'; ad2.textContent = 'Adendum 2'; sel.appendChild(ad2);
        data.periodos.forEach(p => {
            const o = document.createElement('option');
            o.value = 'periodo_' + p.id_periodo;
            o.textContent = p.nombre;
            sel.appendChild(o);
        });
    });
};

// ── Búsqueda con delay MEJORADA ─────────────────────────────────────────────
function buscarConDelay() {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(() => {
        cargarLPA(1);
    }, 500); // Aumentado a 500ms para mejor experiencia
}

function limpiarFiltros() {
    document.getElementById('inputBuscar').value   = '';
    document.getElementById('filtroPeriodo').value = '';
    cargarLPA(1);
}

// ── Buscar socio desde campo principal ─────────────────────────────────────
document.getElementById('buscadorSocio')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const v = this.value || '';
        const modalInput = document.getElementById('inputBuscarSocio');
        if (modalInput) modalInput.value = v;
        abrirModalBuscarSocio();
        buscarSocio();
    }
});

document.getElementById('btnBuscarSocio')?.addEventListener('click', function() {
    const v = document.getElementById('buscadorSocio').value || '';
    const modalInput = document.getElementById('inputBuscarSocio');
    if (modalInput) modalInput.value = v;
    abrirModalBuscarSocio();
    buscarSocio();
});

// ── Cargar LPA con paginación MEJORADO ──────────────────────────────────────
function cargarLPA(pagina) {
    pagina       = pagina || paginaActual;
    paginaActual = pagina;

        // Obtenemos el texto de búsqueda y lo limpiamos
        const busqueda = (document.getElementById('inputBuscar')?.value || '').trim();
        const filtro    = (document.getElementById('filtroPeriodo')?.value || '');
        const esPeriodo = filtro.startsWith('periodo_');
        const esAdendum = filtro.startsWith('adendum_');
        const params = new URLSearchParams({
                pagina,
                q:           busqueda,
                id_periodo:  esPeriodo ? filtro.replace('periodo_', '') : '',
                adendum:     esAdendum ? filtro.replace('adendum_', '') : '',
                solo_adendum: esAdendum ? '1' : ''
        });
        const url = `lpa_obtener.php?${params.toString()}`;

    const tbody = document.getElementById('cuerpoTabla');
    tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;color:#6b7280;"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>';

    fetch(url)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text(); // Primero obtenemos el texto
        })
        .then(text => {
            // Intentamos parsear como JSON
            let resp;
            try {
                resp = JSON.parse(text);
            } catch (e) {
                console.error('Respuesta no es JSON:', text);
                throw new Error('Error al parsear respuesta del servidor');
            }

            tbody.innerHTML = '';

            // Verificar si hay error
            if (resp.error || resp.success === false) {
                const errorMsg = resp.message || 'Error desconocido';
                tbody.innerHTML = `<tr><td colspan="14" style="text-align:center;padding:30px;color:#ef4444;">❌ ${errorMsg}</td></tr>`;
                console.error('Error del servidor:', resp);
                return;
            }

            // Soporta array plano (legado) y objeto paginado (nuevo)
            let lista, paginaResp, totalPaginas, total, porPagina;
            if (Array.isArray(resp)) {
                lista        = resp;
                paginaResp   = 1;
                totalPaginas = 1;
                total        = resp.length;
                porPagina    = resp.length;
            } else {
                lista        = resp.datos        || [];
                paginaResp   = resp.pagina       || 1;
                totalPaginas = resp.totalPaginas || 1;
                total        = resp.total        || 0;
                porPagina    = resp.porPagina    || 15; // 15 por página
            }

            if (lista.length === 0) {
                tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:30px;color:#6b7280;">No se encontraron registros.</td></tr>';
                document.getElementById('paginacion').innerHTML       = '';
                document.getElementById('infoPaginacion').textContent = '';
                return;
            }

            const inicio = (paginaResp - 1) * porPagina;

            lista.forEach((row, idx) => {
                const estado = (row.estado_lpa || '').toLowerCase();

                const fechaNac        = row.fecha_nacimiento ? row.fecha_nacimiento.substring(0, 10) : '-';
                const fechaAfiliacion = row.fecha_ingreso    ? row.fecha_ingreso.substring(0, 10)    : '-';

                let nombreCompleto = '-';
                if (row.nombre_completo && row.nombre_completo.trim() !== '') {
                    nombreCompleto = row.nombre_completo;
                } else if ((row.nombres && row.nombres.trim() !== '') || (row.apellidos && row.apellidos.trim() !== '')) {
                    nombreCompleto = `${row.nombres || ''} ${row.apellidos || ''}`.trim();
                }

                const sexo     = (row.sexo ?? '').trim() || '-';
                const telefono = row.telefono && row.telefono.trim() !== '' ? row.telefono : '-';
                const esNuevo = parseInt(row.id_lpa) >= 432;
                const adBadge = esNuevo
                    ? `<span style="background:#d1fae5;color:#065f46;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-block;">${row.nombre_periodo || '-'}</span>`
                    : (row.adendum == 2
                        ? '<span style="background:#fef9c3;color:#854d0e;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-block;">Adendum 2</span>'
                        : '<span style="background:#dbeafe;color:#1d4ed8;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap;display:inline-block;">Adendum 1</span>');

                tbody.innerHTML += `
                    <tr>
                        <td>${inicio + idx + 1}</td>
                        <td>${row.identificacion || '-'}</td>
                        <td>${nombreCompleto}</td>
                        <td style="text-align:center;">${sexo}</td>
                        <td>${telefono}</td>
                        <td>${fechaNac}</td>
                        <td>${fechaAfiliacion}</td>
                        <td>${row.zona || '-'}</td>
                        <td style="text-align:right;">${row.area_cacao_ha || '-'}</td>
                        <td style="text-align:right;">${row.volumen_produccion_estimado || '-'}</td>
                        <td style="text-align:right;">${row.volumen_entregado_org || '-'}</td>
                        <td style="text-align:center;">${adBadge}</td>
                        <td style="text-align:center;"><span class="estado ${estado}">${row.estado_lpa || '-'}</span></td>
                        <td style="text-align:center;">
                            <button class="btn-icon btn-view" onclick="verLPA(${row.id_lpa})" title="Ver">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button class="btn-icon btn-edit" onclick="editarLPA(${row.id_lpa})" title="Editar">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-delete" onclick="confirmarEliminar(${row.id_lpa})" title="Eliminar">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
            });

            renderPaginacion(paginaResp, totalPaginas, total, porPagina);
        })
        .catch(err => {
            document.getElementById('cuerpoTabla').innerHTML =
                '<tr><td colspan="14" style="text-align:center;padding:20px;color:#ef4444;">❌ Error al cargar datos. Revise la consola.</td></tr>';
            console.error('cargarLPA error:', err);
            
            // Mostrar detalles del error
            if (err.message) {
                console.error('Mensaje de error:', err.message);
            }
        });
}

// ── Paginación ──────────────────────────────────────────────────────────────
function renderPaginacion(pagina, totalPaginas, total, porPagina) {
    const div  = document.getElementById('paginacion');
    const info = document.getElementById('infoPaginacion');
    const desde = (pagina - 1) * porPagina + 1;
    const hasta = Math.min(pagina * porPagina, total);
    info.textContent = `Mostrando ${desde}–${hasta} de ${total} registros`;

    if (totalPaginas <= 1) { div.innerHTML = ''; return; }

    let html = `<button onclick="cargarLPA(1)" ${pagina===1?'disabled':''}>«</button>`;
    html    += `<button onclick="cargarLPA(${pagina - 1})" ${pagina===1?'disabled':''}>‹</button>`;

    const rango = 2;
    for (let p = Math.max(1, pagina - rango); p <= Math.min(totalPaginas, pagina + rango); p++) {
        html += `<button onclick="cargarLPA(${p})" class="${p === pagina ? 'active' : ''}">${p}</button>`;
    }

    html += `<button onclick="cargarLPA(${pagina + 1})" ${pagina===totalPaginas?'disabled':''}>›</button>`;
    html += `<button onclick="cargarLPA(${totalPaginas})" ${pagina===totalPaginas?'disabled':''}>»</button>`;
    div.innerHTML = html;
}

// ── Cargar socios en select (por si se usa) ─────────────────────────────────
function cargarSocios() {
    const select = document.getElementById('id_socio');
    if (!select || select.tagName.toUpperCase() !== 'SELECT') return;
    fetch('socios_consulta.php?api=true')
        .then(r => r.json())
        .then(datos => {
            select.innerHTML = '<option value="">-- Seleccionar Socio --</option>';
            datos.forEach(socio => {
                const nombreCompleto = socio.nombre_completo || (socio.nombres + ' ' + socio.apellidos);
                select.innerHTML += `<option value="${socio.id_socio}">${socio.identificacion} - ${nombreCompleto}</option>`;
            });
        })
        .catch(err => console.error('Error cargando socios:', err));
}

// ── Campos meses ────────────────────────────────────────────────────────────
function crearCamposMeses() {
    const container = document.getElementById('mesesContainer');
    container.innerHTML = '';
    const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    meses.forEach((mes, idx) => {
        container.innerHTML += `
            <div class="form-group">
                <label>${MESES_NOMBRE[idx]}</label>
                <input type="number" name="${mes}" class="mes-input" step="0.01" placeholder="0.00">
            </div>`;
    });
}

// ── Modales ─────────────────────────────────────────────────────────────────
function cerrarTodosLosModalesLPA() {
    ['modalNuevaLPA','modalVerLPA','modalEditarLPA','modalBuscarSocio'].forEach(id => {
        const m = document.getElementById(id);
        if (m) m.classList.remove('active');
    });
}

function abrirModalNuevaLPA() {
    cerrarTodosLosModalesLPA();
    document.getElementById('modalNuevaLPA').classList.add('active');
    document.getElementById('formNuevaLPA').reset();
}
function cerrarModalNuevaLPA()    { document.getElementById('modalNuevaLPA').classList.remove('active'); }
function cerrarModalVerLPA()      { document.getElementById('modalVerLPA').classList.remove('active'); }
function cerrarModalEditarLPA()   { document.getElementById('modalEditarLPA').classList.remove('active'); }
function cerrarModalBuscarSocio() { document.getElementById('modalBuscarSocio').classList.remove('active'); }

// ── Ver LPA ─────────────────────────────────────────────────────────────────
function verLPA(id) {
    cerrarTodosLosModalesLPA();
    fetch(`lpa_ver.php?id=${id}`)
        .then(r => { if (!r.ok) throw new Error('Error en la respuesta del servidor'); return r.text(); })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (!data.success) { mostrarMensaje('Error', data.message || 'No se encontró LPA', 'error'); return; }
                const l = data.lpa;
                const fechaNac    = l.fecha_nacimiento ? l.fecha_nacimiento.substring(0, 10) : '-';
                const fechaIngreso = l.fecha_ingreso   ? l.fecha_ingreso.substring(0, 10)    : '-';
                const nombreCompleto = l.nombre_completo || '-';
                const cont = document.getElementById('contenidoVerLPA');
                cont.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <p><strong>Cédula:</strong> ${l.identificacion || '-'}</p>
                        <p><strong>Productor/a:</strong> ${nombreCompleto}</p>
                        <p><strong>Sexo:</strong> ${l.sexo || l.sexo_socio || '-'}</p>
                        <p><strong>Celular:</strong> ${l.telefono || l.celular || '-'}</p>
                        <p><strong>Fecha Nacimiento:</strong> ${fechaNac}</p>
                        <p><strong>Fecha Afiliación:</strong> ${fechaIngreso}</p>
                        <p><strong>Año:</strong> ${l.anio || '-'}</p>
                        <p><strong>Adendum:</strong> ${l.adendum || '1'}</p>
                        <p><strong>Zona:</strong> ${l.zona || '-'}</p>
                        <p><strong>Comunidad/Grupo:</strong> ${l.comunidad_grupo || '-'}</p>
                        <p><strong>En Acercamiento:</strong> ${l.en_acercamiento || '-'}</p>
                        <p><strong>Otra Org. Fairtrade:</strong> ${l.otra_org_fairtrade || '-'}</p>
                        <p><strong>Certificación Orgánica:</strong> ${l.certificacion_organica || '-'}</p>
                        <p><strong>Área Total (Ha):</strong> ${l.area_total_ha || '-'}</p>
                        <p><strong>Área Cacao (Ha):</strong> ${l.area_cacao_ha || '-'}</p>
                        <p><strong>Matas/Ha:</strong> ${l.num_matas_ha || '-'}</p>
                        <p><strong>Vol. Producción Estimado:</strong> ${l.volumen_produccion_estimado || '-'}</p>
                        <p><strong>Vol. Entregado Org:</strong> ${l.volumen_entregado_org || '-'}</p>
                        <p><strong>Estado:</strong> <span class="estado ${(l.estado_lpa||'').toLowerCase()}">${l.estado_lpa || '-'}</span></p>
                    </div>
                    <h4 style="margin-top:20px;color:#1f3a5f;border-top:2px solid #e5e7eb;padding-top:15px;">Producción Mensual (Ha)</h4>
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:10px;">
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Enero:</strong> ${l.enero || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Febrero:</strong> ${l.febrero || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Marzo:</strong> ${l.marzo || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Abril:</strong> ${l.abril || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Mayo:</strong> ${l.mayo || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Junio:</strong> ${l.junio || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Julio:</strong> ${l.julio || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Agosto:</strong> ${l.agosto || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Septiembre:</strong> ${l.septiembre || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Octubre:</strong> ${l.octubre || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Noviembre:</strong> ${l.noviembre || 0}</div>
                        <div style="padding:8px;background:#f9fafb;border-radius:6px;"><strong>Diciembre:</strong> ${l.diciembre || 0}</div>
                    </div>`;
                document.getElementById('modalVerLPA').classList.add('active');
            } catch(e) {
                console.error('Error al parsear JSON:', e, text);
                mostrarMensaje('Error', 'Error: El servidor no devolvió un JSON válido.', 'error');
            }
        })
        .catch(err => { console.error(err); mostrarMensaje('Error', 'Error cargando detalle: ' + err.message, 'error'); });
}

// ── Editar LPA ───────────────────────────────────────────────────────────────
function editarLPA(id) {
    cerrarTodosLosModalesLPA();
    fetch(`lpa_editar.php?id=${id}`)
        .then(r => { if (!r.ok) throw new Error('Error en la respuesta del servidor'); return r.text(); })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (!data.success) { mostrarMensaje('Error', data.message || 'No se encontró LPA', 'error'); return; }
                const l = data.lpa;
                const campos = {
                    'id_lpa_editar':                     l.id_lpa || '',
                    'zona_editar':                       l.zona || '',
                    'comunidad_grupo_editar':            l.comunidad_grupo || '',
                    'en_acercamiento_editar':            l.en_acercamiento || 'NO',
                    'otra_org_fairtrade_editar':         l.otra_org_fairtrade || 'NO',
                    'area_total_ha_editar':              l.area_total_ha || '',
                    'area_cacao_ha_editar':              l.area_cacao_ha || '',
                    'num_matas_ha_editar':               l.num_matas_ha || '',
                    'certificacion_organica_editar':     l.certificacion_organica || 'NO',
                    'volumen_produccion_estimado_editar':l.volumen_produccion_estimado || '',
                    'volumen_entregado_org_editar':      l.volumen_entregado_org || '',
                    'adendum_editar':                    l.adendum || '1',
                    'enero_editar':      l.enero      || 0,
                    'febrero_editar':    l.febrero    || 0,
                    'marzo_editar':      l.marzo      || 0,
                    'abril_editar':      l.abril      || 0,
                    'mayo_editar':       l.mayo       || 0,
                    'junio_editar':      l.junio      || 0,
                    'julio_editar':      l.julio      || 0,
                    'agosto_editar':     l.agosto     || 0,
                    'septiembre_editar': l.septiembre || 0,
                    'octubre_editar':    l.octubre    || 0,
                    'noviembre_editar':  l.noviembre  || 0,
                    'diciembre_editar':  l.diciembre  || 0
                };
                for (const [campoId, valor] of Object.entries(campos)) {
                    const elemento = document.getElementById(campoId);
                    if (elemento) elemento.value = valor;
                }
                const info = document.getElementById('info_socio_editar');
                if (info) info.innerHTML = `
                    <p><strong>Cédula:</strong> ${l.identificacion || '-'}</p>
                    <p><strong>Nombre:</strong> ${l.nombre_completo || '-'}</p>
                    <p><strong>Año:</strong> ${l.anio || '-'}</p>`;
                document.getElementById('modalEditarLPA').classList.add('active');
            } catch(e) {
                console.error('Error al parsear JSON:', e);
                mostrarMensaje('Error', 'Error: El servidor no devolvió un JSON válido.', 'error');
            }
        })
        .catch(err => { console.error('Error en fetch:', err); mostrarMensaje('Error', 'Error al cargar datos para editar: ' + err.message, 'error'); });
}

// ── Eliminar ─────────────────────────────────────────────────────────────────
function confirmarEliminar(id) {
    mostrarConfirmacion('Eliminar LPA', '¿Eliminar este registro LPA?', function() {
        fetch('lpa_eliminar.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(id)
        })
        .then(r => r.json())
        .then(j => {
            if (j.success) { mostrarMensaje('Éxito', 'Eliminado', 'success', () => cargarLPA(paginaActual)); }
            else { mostrarMensaje('Error', j.message || 'Error', 'error'); }
        })
        .catch(() => mostrarMensaje('Error', 'Error', 'error'));
    });
}

// ── Modal buscar socio ────────────────────────────────────────────────────────
function abrirModalBuscarSocio() {
    document.getElementById('modalBuscarSocio').classList.add('active');
    document.getElementById('inputBuscarSocio')?.focus();
}

async function buscarSocio() {
    let q = (document.getElementById('inputBuscarSocio') || {value: ''}).value.trim();
    q = q.replace(/\s+/g, '');
    if (!q) return;
    const div = document.getElementById('resultBuscarSocio');
    div.innerHTML = 'Buscando...';
    try {
        const res  = await fetch('socios_buscar.php?q=' + encodeURIComponent(q), {credentials: 'same-origin'});
        const text = await res.text();
        let js;
        try { js = JSON.parse(text); }
        catch(err) { console.error('Respuesta no JSON:', text); div.innerHTML = '<p>Error al procesar respuesta del servidor</p>'; return; }
        if (js.error) { console.error('Error servidor:', js); div.innerHTML = '<p>Error del servidor: ' + (js.message || js.error) + '</p>'; return; }
        if (!Array.isArray(js)) { div.innerHTML = 'Error'; return; }
        if (js.length === 0) { div.innerHTML = '<p>No se encontraron socios</p>'; return; }
        if (js.length === 1) {
            const s = js[0];
            if (s.id_socio) { seleccionarSocio(s.id_socio); }
            else {
                document.getElementById('id_socio').value           = '';
                document.getElementById('sel_identificacion').value = s.identificacion;
                document.getElementById('sel_nombre').value         = s.nombre_completo;
                cerrarModalBuscarSocio();
            }
            div.innerHTML = '<p>Socio seleccionado automáticamente.</p>';
            return;
        }
        div.innerHTML = js.map(s =>
            `<div style="padding:8px;border-bottom:1px solid #eee;cursor:pointer" onclick="seleccionarSocio(${s.id_socio ? s.id_socio : 0},'${encodeURIComponent(s.identificacion)}','${encodeURIComponent(s.nombre_completo)}')">`+
            `<strong>${s.identificacion}</strong> — ${s.nombre_completo}</div>`
        ).join('');
    } catch(e) { console.error(e); div.innerHTML = 'Error'; }
}

function seleccionarSocio(id, identificacion, nombreCompleto) {
    if (id && id !== 0) {
        fetch('socios_consulta_detalle.php?id=' + id)
            .then(r => r.json())
            .then(s => {
                if (!s || !s.id_socio) { mostrarMensaje('Error', 'No se encontró el socio', 'error'); return; }
                llenarCamposSocio(s);
            })
            .catch(err => { console.error(err); mostrarMensaje('Error', 'Error al cargar socio', 'error'); });
    } else {
        if (identificacion) identificacion = decodeURIComponent(identificacion);
        if (nombreCompleto) nombreCompleto  = decodeURIComponent(nombreCompleto);
        document.getElementById('id_socio').value             = '';
        document.getElementById('sel_identificacion').value   = identificacion || '';
        document.getElementById('sel_nombre').value           = nombreCompleto  || '';
        document.getElementById('zona').value                 = '';
        document.getElementById('comunidad_grupo').value      = '';
        document.getElementById('sel_sexo').value             = '';
        document.getElementById('sel_telefono').value         = '';
        document.getElementById('sel_fecha_nacimiento').value = '';
        document.getElementById('sel_fecha_ingreso').value    = '';
    }
    cerrarModalBuscarSocio();
}

function llenarCamposSocio(s) {
    document.getElementById('id_socio').value             = s.id_socio || '';
    document.getElementById('sel_identificacion').value   = s.identificacion || '';
    document.getElementById('sel_nombre').value           = s.nombre_completo || '';
    document.getElementById('zona').value                 = s.zona || '';
    document.getElementById('comunidad_grupo').value      = s.comunidad_grupo || '';
    document.getElementById('sel_sexo').value             = s.sexo || '';
    document.getElementById('sel_telefono').value         = s.telefono || '';
    document.getElementById('sel_fecha_nacimiento').value = s.fecha_nacimiento ? s.fecha_nacimiento.substring(0, 10) : '';
    document.getElementById('sel_fecha_ingreso').value    = s.fecha_ingreso    ? s.fecha_ingreso.substring(0, 10)    : '';
    cerrarModalBuscarSocio();
}

// ── Guardar nueva LPA ─────────────────────────────────────────────────────────
document.getElementById('formNuevaLPA')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const cedula = (document.getElementById('sel_identificacion')?.value || '').trim();
    if (!cedula) { mostrarMensaje('Advertencia', 'Debe buscar y seleccionar un socio.', 'info'); return; }
    const form = new FormData(this);
    try {
        const res = await fetch('lpa_guardar.php', {method: 'POST', body: form});
        const j   = await res.json();
        if (j.success) {
            cerrarModalNuevaLPA();
            mostrarMensaje('Éxito', 'LPA guardada', 'success', () => cargarLPA(paginaActual));
        } else {
            mostrarMensaje('Error', j.message || 'Error', 'error');
        }
    } catch(err) { console.error(err); mostrarMensaje('Error', 'Error al guardar', 'error'); }
});

// ── Guardar edición LPA ───────────────────────────────────────────────────────
document.getElementById('formEditarLPA')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const idLpa = document.getElementById('id_lpa_editar').value;
    if (!idLpa) { mostrarMensaje('Error', 'Error: ID de LPA no encontrado', 'error'); return; }
    const form = new FormData(this);
    try {
        const res = await fetch('lpa_editar.php', {method: 'POST', body: form});
        const j   = await res.json();
        if (j.success) {
            cerrarModalEditarLPA();
            mostrarMensaje('Éxito', 'LPA actualizada', 'success', () => cargarLPA(paginaActual));
        } else {
            mostrarMensaje('Error', j.message || 'Error', 'error');
        }
    } catch(err) { console.error(err); mostrarMensaje('Error', 'Error al actualizar', 'error'); }
});

// ── Distribución automática meses ─────────────────────────────────────────────
const DIST = {
    enero:0.15, febrero:0.10, marzo:0.08, abril:0.05,
    mayo:0.03,  junio:0.02,   julio:0.02, agosto:0.02,
    septiembre:0.03, octubre:0.05, noviembre:0.20, diciembre:0.25
};

document.getElementById('volumen_produccion_estimado')?.addEventListener('input', function() {
    const total = parseFloat(this.value) || 0;
    if (total <= 0) return;
    document.querySelectorAll('.mes-input').forEach(input => {
        const n = input.getAttribute('name');
        if (DIST[n]) input.value = (total * DIST[n]).toFixed(2);
    });
});

document.getElementById('volumen_produccion_estimado_editar')?.addEventListener('input', function() {
    const total = parseFloat(this.value) || 0;
    if (total <= 0) return;
    Object.keys(DIST).forEach(mes => {
        const input = document.getElementById(`${mes}_editar`);
        if (input) input.value = (total * DIST[mes]).toFixed(2);
    });
});
// ── Periodos y Adendum ─────────────────────────────────────────────────────
function cargarPeriodos() {
    fetch('periodos_obtener.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('periodo_adendum');
            if (!sel || !data.success) return;
            sel.innerHTML = '';
            data.periodos.forEach(p => {
                const o = document.createElement('option');
                o.value = p.id_periodo;
                o.textContent = p.nombre + (p.estado === 'ABIERTO' ? ' ✅' : '');
                if (p.estado === 'ABIERTO') o.selected = true;
                sel.appendChild(o);
            });
            aplicarPeriodoAdendum(sel);
        });
}

function aplicarPeriodoAdendum(sel) {
    document.getElementById('id_periodo').value = sel.value || '';
    document.getElementById('adendum').value    = '1';
}
</script>
</body>
</html>