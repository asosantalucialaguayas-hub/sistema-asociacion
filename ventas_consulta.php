<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require "config/conexion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ventas - Gestión de Cupos</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.btn-actions{display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;align-items:center}
.btn-primary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-success{background:#10b981;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-warning{background:#f59e0b;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-pink{background:#9d174d;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-primary:hover{background:#162e4a}.btn-success:hover{background:#059669}
.btn-warning:hover{background:#d97706}.btn-pink:hover{background:#831843}

.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table thead th{background:#1f3a5f;color:#fff;padding:10px;text-align:left}
.data-table td{padding:9px;border-bottom:1px solid #e5e7eb}
.data-table tbody tr:hover{background:#f9fafb}
.btn-icon{width:32px;height:32px;border-radius:6px;border:none;cursor:pointer;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px}

.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:999999;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:12px;padding:25px;position:relative;width:95%;max-height:92vh;overflow:auto;box-shadow:0 25px 60px rgba(0,0,0,.3)}
.close-btn{position:absolute;top:-14px;right:-14px;width:36px;height:36px;border-radius:50%;border:none;background:#fff;box-shadow:0 6px 16px rgba(0,0,0,.25);cursor:pointer;font-size:18px;z-index:10}

.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:9px 11px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;box-sizing:border-box}
.form-actions{margin-top:18px;display:flex;gap:10px;justify-content:flex-end}

.progress-bar{width:100%;height:18px;background:#e5e7eb;border-radius:10px;overflow:hidden;position:relative;min-width:120px}
.progress-fill{height:100%;background:linear-gradient(90deg,#10b981,#059669);transition:width .3s}
.progress-fill.warning{background:linear-gradient(90deg,#f59e0b,#d97706)}
.progress-fill.danger{background:linear-gradient(90deg,#ef4444,#dc2626)}
.progress-text{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:10px;font-weight:700;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.4)}

.cupo-info{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.cupo-card{padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;text-align:center}
.cupo-card h4{margin:0 0 4px;font-size:11px;color:#6b7280;text-transform:uppercase}
.cupo-card p{margin:0;font-size:22px;font-weight:700;color:#1f3a5f}

.badge-cupo{padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700}
.badge-cupo.disponible{background:#d1fae5;color:#065f46}
.badge-cupo.agotado{background:#fee2e2;color:#991b1b}
.badge-cupo.bajo{background:#fef3c7;color:#92400e}

.search-filter{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.search-input{padding:9px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;min-width:220px}
.filter-select{padding:9px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:13px}

/* ── PAGINACIÓN ── */
.paginacion{display:flex;align-items:center;gap:6px;margin-top:16px;justify-content:center;flex-wrap:wrap}
.paginacion button{padding:7px 13px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:13px;font-weight:600;transition:all .2s}
.paginacion button:hover:not(:disabled){background:#f3f4f6}
.paginacion button.active{background:#1f3a5f;color:#fff;border-color:#1f3a5f}
.paginacion button:disabled{opacity:.4;cursor:not-allowed}
.info-paginacion{text-align:center;margin-top:6px;font-size:13px;color:#6b7280}

.preview-table{width:100%;border-collapse:collapse;font-size:11px}
.preview-table th{background:#1f3a5f;color:#fff;padding:8px;text-align:center}
.preview-table td{padding:7px;border-bottom:1px solid #e5e7eb;text-align:center}
.preview-ok{background:#d1fae5!important}
.preview-warn{background:#fef3c7!important}

.tab-btn{padding:8px 20px;border:none;border-radius:6px 6px 0 0;cursor:pointer;font-weight:600;font-size:13px;background:#e5e7eb;color:#6b7280}
.tab-btn.active{background:#1f3a5f;color:#fff}
.tab-content{display:none}
.tab-content.active{display:block}

.header-acopio{background:linear-gradient(135deg,#1e40af,#2563eb);color:#fff;padding:12px 16px;border-radius:8px;margin-bottom:15px}
.header-externa{background:linear-gradient(135deg,#9d174d,#be185d);color:#fff;padding:12px 16px;border-radius:8px;margin-bottom:15px}
.header-acopio h3,.header-externa h3{margin:0;font-size:16px}
.header-acopio p,.header-externa p{margin:4px 0 0;font-size:12px;opacity:.85}

.pdf-modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999999;align-items:center;justify-content:center;flex-direction:column}
.pdf-modal-overlay.active{display:flex}
.pdf-modal-inner{background:#1f1f1f;border-radius:10px;width:92%;height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.6)}
.pdf-modal-header{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;background:#111;color:#fff}
.pdf-modal-header span{font-size:14px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%}
.pdf-modal-header div{display:flex;gap:8px}
.pdf-modal-header a,.pdf-modal-header button{padding:7px 14px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-dl{background:#10b981;color:#fff}
.btn-cl{background:#ef4444;color:#fff}
.pdf-frame{flex:1;border:none;width:100%}
</style>
</head>
<body>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar"><span>Bienvenido, <?= $_SESSION['usuario'] ?></span></header>

<section class="page">
<h1>Gestión de Ventas y Cupos</h1>

<div class="btn-actions">
    <div class="search-filter">
        <select id="filtroMes" class="filter-select" onchange="cargarVentas(1)">
            <option value="">Todos los meses</option>
            <option value="01">Enero</option><option value="02">Febrero</option>
            <option value="03">Marzo</option><option value="04">Abril</option>
            <option value="05">Mayo</option><option value="06">Junio</option>
            <option value="07">Julio</option><option value="08">Agosto</option>
            <option value="09">Septiembre</option><option value="10">Octubre</option>
            <option value="11">Noviembre</option><option value="12">Diciembre</option>
        </select>
        <select id="filtroAnio" class="filter-select" onchange="cargarVentas(1)">
            <?php for($i=date('Y');$i>=2020;$i--): ?>
            <option value="<?=$i?>" <?=$i==date('Y')?'selected':''?>><?=$i?></option>
            <?php endfor; ?>
        </select>
        <input type="text" id="buscador" class="search-input" placeholder="Buscar por cédula o nombre">
        <button class="btn-primary" onclick="cargarVentas(1)"><i class="fa fa-search"></i></button>
    </div>
    <button class="btn-primary" onclick="exportarExcel()"><i class="fa fa-file-excel"></i> Exportar</button>
    <button class="btn-warning" onclick="abrirModalImportar()"><i class="fa fa-upload"></i> Importar Excel</button>
</div>

<div class="form-card">
<table class="data-table">
<thead>
<tr>
    <th>#</th><th>Cédula</th><th>Productor/a</th><th>ID LPA</th>
    <th>Cupo Total</th><th>Consumido</th><th>Disponible</th>
    <th>Progreso</th><th>Estado</th><th>Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTabla"></tbody>
</table>

<!-- PAGINACIÓN -->
<div class="paginacion" id="paginacion"></div>
<div class="info-paginacion" id="infoPaginacion"></div>
</div>
</section>
</main>
</div>

<!-- MODAL: VENTA ACOPIO -->
<div id="modalAcopio" class="modal-overlay">
<div class="modal-box" style="max-width:780px">
<button class="close-btn" onclick="cerrarModal('modalAcopio')">×</button>
<div class="header-acopio">
    <h3>🏭 Registrar Venta de Acopio</h3>
    <p>Esta venta <strong>descuenta el cupo</strong> del productor</p>
</div>
<div id="infoSocioAcopio" style="margin:0 0 12px;background:#eff6ff;padding:10px;border-radius:6px;font-size:13px"></div>
<div class="cupo-info">
    <div class="cupo-card"><h4>Cupo Total</h4><p id="cTotalA">0 Kg</p></div>
    <div class="cupo-card"><h4>Consumido</h4><p id="cConsumidoA" style="color:#ef4444">0 Kg</p></div>
    <div class="cupo-card"><h4>Disponible</h4><p id="cDispA" style="color:#10b981">0 Kg</p></div>
</div>
<form id="formAcopio">
<input type="hidden" id="a_id_lpa"    name="id_lpa">
<input type="hidden" id="a_id_socio"  name="id_socio">
<input type="hidden" id="a_cupo_disp" name="cupo_disponible">
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
    <div class="form-group"><label>Fecha de Venta *</label><input type="date" id="a_fecha" name="fecha_venta" required></div>
    <div class="form-group"><label>Cantidad KG *</label><input type="number" id="a_kg" name="cantidad_vende" step="0.0001" min="0.001" required oninput="calcA()"></div>
    <div class="form-group"><label>QQ (auto)</label><input type="number" id="a_qq" name="cantidad_qq" step="0.0001" readonly style="background:#f3f4f6"></div>
    <div class="form-group"><label>Precio / KG *</label><input type="number" id="a_precio" name="precio_kg" step="0.01" min="0.01" required oninput="calcA()"></div>
    <div class="form-group"><label>Total USD (auto)</label><input type="number" id="a_total" name="total" step="0.01" readonly style="background:#f3f4f6;font-weight:700"></div>
    <div class="form-group"><label>Prima</label><input type="number" id="a_prima" name="prima" step="0.01" value="0"></div>
    <div class="form-group"><label>Sucursal *</label>
        <select id="a_sucursal" name="sucursal" required>
            <option value="">Seleccionar</option>
            <option value="El Empalme">El Empalme</option>
            <option value="Buena Fe">Buena Fe</option>
            <option value="Quinsaloma (Matriz)">Quinsaloma (Matriz)</option>
        </select>
    </div>
    <div class="form-group"><label>Comprador</label><input type="text" id="a_comprador" name="destino" placeholder="RISTOKCACAO"></div>
    <div class="form-group"><label>FLOID</label><input type="text" id="a_floid" name="floid"></div>
    <div class="form-group"><label>Punto Emisión</label><input type="text" id="a_pto_e" name="punto_emision" placeholder="001"></div>
    <div class="form-group"><label>Punto Venta</label><input type="text" id="a_pto_v" name="punto_venta" placeholder="100"></div>
    <div class="form-group"><label>N° Documento</label><input type="text" id="a_numero" name="numero_doc"></div>
    <div class="form-group" style="grid-column:span 3"><label>Producto</label><input type="text" id="a_producto" name="descripcion" value="CACAO ANSN FAIRTRADE FISICAMENTE RASTREABLE"></div>
    <div class="form-group" style="grid-column:span 2"><label>Observación</label><textarea id="a_obs" name="observacion" rows="2"></textarea></div>
    <div class="form-group"><label>Factura PDF</label><input type="file" id="a_factura" name="factura" accept=".pdf"><small style="color:#6b7280">Opcional · Máx 5MB</small></div>
</div>
<div class="form-actions">
    <button type="button" class="btn-primary" onclick="cerrarModal('modalAcopio')">Cancelar</button>
    <button type="submit" class="btn-success"><i class="fa fa-save"></i> Guardar Venta Acopio</button>
</div>
</form>
</div>
</div>

<!-- MODAL: VENTA EXTERNA -->
<div id="modalExterna" class="modal-overlay">
<div class="modal-box" style="max-width:780px">
<button class="close-btn" onclick="cerrarModal('modalExterna')">×</button>
<div class="header-externa">
    <h3>🌐 Registrar Venta Externa</h3>
    <p>El socio vende por fuera — <strong>NO descuenta cupo</strong></p>
</div>
<div id="infoSocioExterna" style="margin:0 0 12px;background:#fdf2f8;padding:10px;border-radius:6px;font-size:13px"></div>
<form id="formExterna">
<input type="hidden" id="e_id_lpa"   name="id_lpa">
<input type="hidden" id="e_id_socio" name="id_socio">
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
    <div class="form-group"><label>Fecha de Venta *</label><input type="date" id="e_fecha" name="fecha_venta" required></div>
    <div class="form-group"><label>Cantidad KG *</label><input type="number" id="e_kg" name="cantidad_kg" step="0.0001" min="0.001" required oninput="calcE()"></div>
    <div class="form-group"><label>QQ (auto)</label><input type="number" id="e_qq" name="qq" step="0.0001" readonly style="background:#f3f4f6"></div>
    <div class="form-group"><label>Precio / KG *</label><input type="number" id="e_precio" name="precio_kg" step="0.01" min="0.01" required oninput="calcE()"></div>
    <div class="form-group"><label>Total USD (auto)</label><input type="number" id="e_total" name="total" step="0.01" readonly style="background:#f3f4f6;font-weight:700"></div>
    <div class="form-group"><label>Prima</label><input type="number" id="e_prima" name="prima" step="0.01" value="0"></div>
    <div class="form-group"><label>Comprador / Lugar *</label><input type="text" id="e_comprador" name="comprador" placeholder="Nombre del comprador externo" required></div>
    <div class="form-group"><label>FLOID</label><input type="text" id="e_floid" name="floid"></div>
    <div class="form-group"><label>N° Documento</label><input type="text" id="e_numero" name="numero_doc"></div>
    <div class="form-group"><label>Punto Emisión</label><input type="text" id="e_pto_e" name="punto_emision" placeholder="001"></div>
    <div class="form-group"><label>Punto Venta</label><input type="text" id="e_pto_v" name="punto_venta" placeholder="100"></div>
    <div class="form-group"><label>Producto</label><input type="text" id="e_producto" name="producto" value="CACAO ANSN FAIRTRADE FISICAMENTE RASTREABLE"></div>
    <div class="form-group" style="grid-column:span 3"><label>Observación</label><textarea id="e_obs" name="observacion" rows="2"></textarea></div>
</div>
<div class="form-actions">
    <button type="button" class="btn-primary" onclick="cerrarModal('modalExterna')">Cancelar</button>
    <button type="submit" class="btn-pink"><i class="fa fa-save"></i> Guardar Venta Externa</button>
</div>
</form>
</div>
</div>

<!-- MODAL: HISTORIAL -->
<div id="modalHistorial" class="modal-overlay">
<div class="modal-box" style="max-width:1200px">
<button class="close-btn" onclick="cerrarModal('modalHistorial')">×</button>
<h2>Historial — <span id="nomHist"></span></h2>
<div id="infoHist" style="margin:0 0 12px;background:#f9fafb;padding:10px;border-radius:6px;font-size:13px"></div>
<div style="display:flex;gap:4px;border-bottom:2px solid #1f3a5f;margin-bottom:0">
    <button class="tab-btn active" onclick="cambiarTab('tabA',this)">🏭 Ventas Acopio</button>
    <button class="tab-btn"        onclick="cambiarTab('tabE',this)">🌐 Ventas Externas</button>
</div>
<div id="tabA" class="tab-content active" style="padding:12px 0">
    <div style="background:#1f3a5f;color:#fff;padding:10px;border-radius:8px;margin-bottom:10px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center">
        <div><small>Total KG</small><div id="hAkg"  style="font-size:20px;font-weight:700">0.00</div></div>
        <div><small>Total QQ</small><div id="hAqq"  style="font-size:20px;font-weight:700">0.0000</div></div>
        <div><small>Total USD</small><div id="hAusd" style="font-size:20px;font-weight:700">$0.00</div></div>
    </div>
    <div style="max-height:320px;overflow:auto">
    <table class="data-table"><thead>
    <tr><th>#</th><th>Fecha</th><th>N° Doc</th><th>KG</th><th>QQ</th><th>Precio</th><th>Total</th><th>Prima</th><th>Comprador</th><th>FLOID</th><th>Sucursal</th><th>Factura</th><th>Acc.</th></tr>
    </thead><tbody id="tbA"></tbody></table>
    </div>
</div>
<div id="tabE" class="tab-content" style="padding:12px 0">
    <div style="background:#9d174d;color:#fff;padding:10px;border-radius:8px;margin-bottom:10px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center">
        <div><small>Total KG</small><div id="hEkg"  style="font-size:20px;font-weight:700">0.00</div></div>
        <div><small>Total QQ</small><div id="hEqq"  style="font-size:20px;font-weight:700">0.0000</div></div>
        <div><small>Total USD</small><div id="hEusd" style="font-size:20px;font-weight:700">$0.00</div></div>
    </div>
    <div style="max-height:320px;overflow:auto">
    <table class="data-table"><thead>
    <tr><th>#</th><th>Fecha</th><th>N° Doc</th><th>KG</th><th>QQ</th><th>Precio</th><th>Total</th><th>Prima</th><th>Comprador</th><th>FLOID</th><th>Acc.</th></tr>
    </thead><tbody id="tbE"></tbody></table>
    </div>
</div>
<div class="form-actions">
    <button type="button" class="btn-primary" onclick="cerrarModal('modalHistorial')">Cerrar</button>
</div>
</div>
</div>

<!-- MODAL: VER FACTURA PDF -->
<div id="modalPDF" class="pdf-modal-overlay">
    <div class="pdf-modal-inner">
        <div class="pdf-modal-header">
            <span id="pdfNombre">Factura</span>
            <div>
                <a id="pdfDescargar" href="#" download class="btn-dl"><i class="fa fa-download"></i> Descargar</a>
                <button class="btn-cl" onclick="cerrarPDF()"><i class="fa fa-times"></i> Cerrar</button>
            </div>
        </div>
        <iframe id="pdfFrame" class="pdf-frame" src=""></iframe>
    </div>
</div>

<!-- MODAL: IMPORTAR EXCEL -->
<div id="modalImportar" class="modal-overlay">
<div class="modal-box" style="max-width:1100px">
<button class="close-btn" onclick="cerrarModal('modalImportar')">×</button>
<h2>Importar Ventas desde Excel</h2>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px;margin-bottom:15px;font-size:13px">
    <strong>📋 Formato esperado:</strong>
    <code>PRODUCTOR | FECHA | Pto.Emisión | Pto.Venta | NÚMERO | PRODUCTO | QQ | KG | PRECIO | TOTAL | PRIMA | COMPRADOR | FLOID</code>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:15px">
    <div class="form-group"><label>Tipo de Ventas *</label>
        <select id="imp_tipo" onchange="toggleSucursalImport()">
            <option value="ACOPIO">🏭 Acopio (descuenta cupo)</option>
            <option value="EXTERNA">🌐 Externa (NO descuenta cupo)</option>
        </select>
    </div>
    <div class="form-group" id="divImpSuc"><label>Sucursal *</label>
        <select id="imp_sucursal">
            <option value="El Empalme">El Empalme</option>
            <option value="Buena Fe">Buena Fe</option>
            <option value="Quinsaloma (Matriz)">Quinsaloma (Matriz)</option>
        </select>
    </div>
    <div class="form-group"><label>Archivo Excel *</label><input type="file" id="imp_archivo" accept=".xlsx,.xls" onchange="previewExcel()"></div>
</div>
<div id="previewContainer" style="display:none">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0;color:#1f3a5f">Vista Previa — <span id="previewCount">0</span> registros</h3>
        <div style="font-size:12px;display:flex;gap:8px">
            <span style="background:#d1fae5;padding:3px 8px;border-radius:4px">✅ Encontrado</span>
            <span style="background:#fef3c7;padding:3px 8px;border-radius:4px">⚠️ No encontrado</span>
        </div>
    </div>
    <div style="max-height:380px;overflow:auto">
    <table class="preview-table" id="tablaPreview">
    <thead><tr><th>#</th><th>Productor</th><th>Fecha</th><th>N°</th><th>QQ</th><th>KG</th><th>Precio</th><th>Total</th><th>Prima</th><th>Comprador</th><th>FLOID</th><th>Estado</th></tr></thead>
    <tbody id="tbPreview"></tbody>
    </table>
    </div>
    <div class="form-actions">
        <button type="button" class="btn-primary" onclick="cerrarModal('modalImportar')">Cancelar</button>
        <button type="button" class="btn-success" onclick="confirmarImportacion()"><i class="fa fa-check"></i> Confirmar e Importar</button>
    </div>
</div>
</div>
</div>

<script>
let datosPreview = [];
let paginaActual = 1;  // ← NUEVO
let buscarTimer  = null; // ← NUEVO

function formatMoney(val) {
    const n = parseFloat(val) || 0;
    return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatNum(val, dec=2) {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('es-EC', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

window.onload = () => cargarVentas(1);

// ── Tabla principal CON PAGINACIÓN ──────────────────────────────────────────
function cargarVentas(pagina) {
    pagina = pagina || paginaActual;
    paginaActual = pagina;

    const mes    = document.getElementById('filtroMes').value;
    const anio   = document.getElementById('filtroAnio').value;
    const buscar = document.getElementById('buscador').value.trim();

    const url = `ventas_obtener.php?pagina=${pagina}&mes=${encodeURIComponent(mes)}&anio=${encodeURIComponent(anio)}&buscar=${encodeURIComponent(buscar)}`;
    const tbody = document.getElementById('cuerpoTabla');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:#6b7280"><i class="fa fa-spinner fa-spin"></i> Cargando...</td></tr>';

    fetch(url).then(r=>r.text()).then(txt=>{
        let resp;
        try { resp = JSON.parse(txt); } catch(e){ console.error(txt); return; }

        tbody.innerHTML = '';

        // Soporte retrocompatible: si el backend devuelve array plano (sin paginación)
        let lista, paginaResp, totalPaginas, total, porPagina;
        if (Array.isArray(resp)) {
            lista = resp; paginaResp = 1; totalPaginas = 1; total = resp.length; porPagina = resp.length;
        } else {
            lista        = resp.datos        || [];
            paginaResp   = resp.pagina       || 1;
            totalPaginas = resp.totalPaginas || 1;
            total        = resp.total        || 0;
            porPagina    = resp.porPagina    || 15;
        }

        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:#6b7280">Sin registros</td></tr>';
            document.getElementById('paginacion').innerHTML = '';
            document.getElementById('infoPaginacion').textContent = '';
            return;
        }

        const inicio = (paginaResp - 1) * porPagina;

        lista.forEach((row, idx) => {
            const cT=parseFloat(row.cupo_total)||0,
                  cC=parseFloat(row.cupo_consumido)||0,
                  cD=parseFloat(row.cupo_disponible)||0;
            const pct=cT>0?((cC/cT)*100).toFixed(1):0;
            const pCls=pct>=90?'danger':pct>=70?'warning':'';
            const badge=pct>=90?'agotado':pct>=70?'bajo':'disponible';
            const lbl  =pct>=90?'AGOTADO':pct>=70?'BAJO':'DISPONIBLE';
            const ced  =(row.identificacion||'').replace(/"/g,'&quot;');
            const nom  =(row.nombre_completo||'').replace(/"/g,'&quot;');
            tbody.innerHTML+=`
            <tr>
                <td>${inicio+idx+1}</td><td>${ced}</td><td>${nom}</td><td>${row.id_lpa||'-'}</td>
                <td><strong>${formatNum(cT)}</strong></td>
                <td style="color:#ef4444;font-weight:600">${formatNum(cC)}</td>
                <td style="color:#10b981;font-weight:600">${formatNum(cD)}</td>
                <td style="min-width:130px">
                    <div class="progress-bar">
                        <div class="progress-fill ${pCls}" style="width:${pct}%"></div>
                        <span class="progress-text">${pct}%</span>
                    </div>
                </td>
                <td><span class="badge-cupo ${badge}">${lbl}</span></td>
                <td style="white-space:nowrap">
                    <button class="btn-icon" style="background:#10b981" title="Ver historial"
                        onclick='verHistorial(${row.id_lpa},${row.id_socio},"${ced}","${nom}")'>
                        <i class="fa fa-eye"></i></button>
                    <button class="btn-icon" style="background:#2563eb" title="Venta Acopio"
                        onclick='abrirAcopio(${row.id_lpa},${row.id_socio},"${ced}","${nom}",${cT},${cC},${cD})'>
                        <i class="fa fa-industry"></i></button>
                    <button class="btn-icon" style="background:#9d174d" title="Venta Externa"
                        onclick='abrirExterna(${row.id_lpa},${row.id_socio},"${ced}","${nom}")'>
                        <i class="fa fa-globe"></i></button>
                </td>
            </tr>`;
        });

        renderPaginacion(paginaResp, totalPaginas, total, porPagina);

    }).catch(err=>console.error(err));
}

// ── Renderizar botones de paginación ────────────────────────────────────────
function renderPaginacion(pagina, totalPaginas, total, porPagina) {
    const div  = document.getElementById('paginacion');
    const info = document.getElementById('infoPaginacion');
    const desde = (pagina - 1) * porPagina + 1;
    const hasta = Math.min(pagina * porPagina, total);
    info.textContent = `Mostrando ${desde}–${hasta} de ${total} registros`;

    if (totalPaginas <= 1) { div.innerHTML = ''; return; }

    let html = `<button onclick="cargarVentas(1)" ${pagina===1?'disabled':''}>«</button>`;
    html    += `<button onclick="cargarVentas(${pagina-1})" ${pagina===1?'disabled':''}>‹</button>`;

    const rango = 2;
    for (let p = Math.max(1, pagina-rango); p <= Math.min(totalPaginas, pagina+rango); p++) {
        html += `<button onclick="cargarVentas(${p})" class="${p===pagina?'active':''}">${p}</button>`;
    }

    html += `<button onclick="cargarVentas(${pagina+1})" ${pagina===totalPaginas?'disabled':''}>›</button>`;
    html += `<button onclick="cargarVentas(${totalPaginas})" ${pagina===totalPaginas?'disabled':''}>»</button>`;
    div.innerHTML = html;
}

// ── Modal Acopio ────────────────────────────────────────────────────────────
function abrirAcopio(idLpa,idSocio,ced,nom,cT,cC,cD){
    document.getElementById('a_id_lpa').value    = idLpa;
    document.getElementById('a_id_socio').value  = idSocio;
    document.getElementById('a_cupo_disp').value = cD.toFixed(2);
    document.getElementById('a_fecha').value     = hoy();
    document.getElementById('cTotalA').textContent    = formatNum(cT)+' Kg';
    document.getElementById('cConsumidoA').textContent= formatNum(cC)+' Kg';
    document.getElementById('cDispA').textContent     = formatNum(cD)+' Kg';
    document.getElementById('infoSocioAcopio').innerHTML=
        `<strong>Cédula:</strong> ${ced} &nbsp;|&nbsp; <strong>Productor/a:</strong> ${nom}`;
    document.getElementById('formAcopio').reset();
    document.getElementById('a_id_lpa').value    = idLpa;
    document.getElementById('a_id_socio').value  = idSocio;
    document.getElementById('a_cupo_disp').value = cD.toFixed(2);
    document.getElementById('a_fecha').value     = hoy();
    document.getElementById('modalAcopio').classList.add('active');
}
function calcA(){
    const kg=parseFloat(document.getElementById('a_kg').value)||0;
    const pr=parseFloat(document.getElementById('a_precio').value)||0;
    document.getElementById('a_qq').value    = (kg/45.36).toFixed(4);
    document.getElementById('a_total').value = (kg*pr).toFixed(2);
}
document.getElementById('formAcopio').addEventListener('submit', async function(e){
    e.preventDefault();
    const kg=parseFloat(document.getElementById('a_kg').value)||0;
    const cD=parseFloat(document.getElementById('a_cupo_disp').value)||0;
    const suc=document.getElementById('a_sucursal').value;
    if (!suc)  { alert('Seleccione una sucursal'); return; }
    if (kg<=0) { alert('Ingrese una cantidad válida'); return; }
    if (kg>cD) { alert(`La cantidad (${formatNum(kg)} Kg) excede el cupo disponible (${formatNum(cD)} Kg)`); return; }
    const fd=new FormData(this);
    try {
        const res=await fetch('ventas_guardar.php',{method:'POST',body:fd});
        const data=await res.json();
        if (data.success){ alert('✅ Venta de acopio registrada'); cerrarModal('modalAcopio'); cargarVentas(paginaActual); }
        else alert(data.message||'Error al guardar');
    } catch(err){ console.error(err); alert('Error al guardar'); }
});

// ── Modal Externa ───────────────────────────────────────────────────────────
function abrirExterna(idLpa,idSocio,ced,nom){
    document.getElementById('e_id_lpa').value   = idLpa;
    document.getElementById('e_id_socio').value = idSocio;
    document.getElementById('e_fecha').value    = hoy();
    document.getElementById('infoSocioExterna').innerHTML=
        `<strong>Cédula:</strong> ${ced} &nbsp;|&nbsp; <strong>Productor/a:</strong> ${nom}`;
    document.getElementById('formExterna').reset();
    document.getElementById('e_id_lpa').value   = idLpa;
    document.getElementById('e_id_socio').value = idSocio;
    document.getElementById('e_fecha').value    = hoy();
    document.getElementById('modalExterna').classList.add('active');
}
function calcE(){
    const kg=parseFloat(document.getElementById('e_kg').value)||0;
    const pr=parseFloat(document.getElementById('e_precio').value)||0;
    document.getElementById('e_qq').value    = (kg/45.36).toFixed(4);
    document.getElementById('e_total').value = (kg*pr).toFixed(2);
}
document.getElementById('formExterna').addEventListener('submit', async function(e){
    e.preventDefault();
    const kg=parseFloat(document.getElementById('e_kg').value)||0;
    const cmp=document.getElementById('e_comprador').value.trim();
    if (kg<=0) { alert('Ingrese una cantidad válida'); return; }
    if (!cmp)  { alert('Ingrese el comprador/lugar'); return; }
    const fd=new FormData(this);
    try {
        const res=await fetch('ventas_externas_guardar.php',{method:'POST',body:fd});
        const data=await res.json();
        if (data.success){ alert('✅ Venta externa registrada'); cerrarModal('modalExterna'); cargarVentas(paginaActual); }
        else alert(data.message||'Error al guardar');
    } catch(err){ console.error(err); alert('Error al guardar'); }
});

// ── Historial ───────────────────────────────────────────────────────────────
async function verHistorial(idLpa,idSocio,ced,nom){
    document.getElementById('nomHist').textContent = nom;
    document.getElementById('infoHist').innerHTML  =
        `<strong>Cédula:</strong> ${ced} &nbsp;|&nbsp; <strong>Productor/a:</strong> ${nom}`;
    document.getElementById('modalHistorial').classList.add('active');
    try{
        const r=await fetch(`ventas_historial.php?id_lpa=${idLpa}`);
        const d=await r.json();
        renderTabAcopio('tbA', d.success?d.ventas:[], 'hAkg','hAqq','hAusd');
    }catch(e){console.error(e);}
    try{
        const r=await fetch(`ventas_externas_historial.php?id_socio=${idSocio}&id_lpa=${idLpa}`);
        const d=await r.json();
        renderTabExterna('tbE', d.success?d.ventas:[], 'hEkg','hEqq','hEusd');
    }catch(e){console.error(e);}
}
function renderTabAcopio(tbodyId, rows, idKg, idQq, idUsd){
    const tbody=document.getElementById(tbodyId);
    tbody.innerHTML='';
    let tKg=0,tQq=0,tUsd=0;
    if(!rows.length){
        tbody.innerHTML=`<tr><td colspan="13" style="text-align:center;padding:15px;color:#6b7280">Sin registros</td></tr>`;
    } else {
        rows.forEach((v,i)=>{
            const kg=parseFloat(v.cantidad_vende||v.cantidad_kg||0);
            const qq=parseFloat(v.cantidad_qq||v.qq||0);
            const usd=parseFloat(v.total||0);
            tKg+=kg; tQq+=qq; tUsd+=usd;
            const comprador=v.destino||v.comprador||'-';
            let btnFactura='-';
            if(v.factura&&v.factura.trim()!==''){
                const rutaPDF='/'+v.factura.replace(/^\//,'');
                const nombreArchivo=v.factura.split('/').pop();
                btnFactura=`<button class="btn-icon" style="background:#6366f1" title="Ver factura PDF"
                    onclick="verFactura('${rutaPDF}','${nombreArchivo}')"><i class="fa fa-eye"></i></button>`;
            }
            tbody.innerHTML+=`
            <tr>
                <td>${i+1}</td><td>${(v.fecha_venta||'-').substring(0,10)}</td>
                <td>${v.numero_doc||v.factura_num||'-'}</td>
                <td>${formatNum(kg)}</td><td>${formatNum(qq,4)}</td>
                <td>${formatMoney(v.precio_kg)}</td><td><strong>${formatMoney(usd)}</strong></td>
                <td>${formatNum(v.prima||0)}</td><td>${comprador}</td>
                <td>${v.floid||'-'}</td><td>${v.sucursal||'-'}</td><td>${btnFactura}</td>
                <td><button class="btn-icon" style="background:#ef4444" title="Eliminar"
                    onclick="eliminarVenta(${v.id_venta},'acopio')"><i class="fa fa-trash"></i></button></td>
            </tr>`;
        });
    }
    document.getElementById(idKg).textContent=formatNum(tKg);
    document.getElementById(idQq).textContent=formatNum(tQq,4);
    document.getElementById(idUsd).textContent=formatMoney(tUsd);
}
function renderTabExterna(tbodyId, rows, idKg, idQq, idUsd){
    const tbody=document.getElementById(tbodyId);
    tbody.innerHTML='';
    let tKg=0,tQq=0,tUsd=0;
    if(!rows.length){
        tbody.innerHTML=`<tr><td colspan="11" style="text-align:center;padding:15px;color:#6b7280">Sin registros</td></tr>`;
    } else {
        rows.forEach((v,i)=>{
            const kg=parseFloat(v.cantidad_kg||0);
            const qq=parseFloat(v.qq||0);
            const usd=parseFloat(v.total||0);
            tKg+=kg; tQq+=qq; tUsd+=usd;
            tbody.innerHTML+=`
            <tr>
                <td>${i+1}</td><td>${(v.fecha_venta||'-').substring(0,10)}</td>
                <td>${v.numero_doc||'-'}</td><td>${formatNum(kg)}</td><td>${formatNum(qq,4)}</td>
                <td>${formatMoney(v.precio_kg)}</td><td><strong>${formatMoney(usd)}</strong></td>
                <td>${formatNum(v.prima||0)}</td><td>${v.comprador||'-'}</td><td>${v.floid||'-'}</td>
                <td><button class="btn-icon" style="background:#ef4444" title="Eliminar"
                    onclick="eliminarVenta(${v.id_venta_externa},'externa')"><i class="fa fa-trash"></i></button></td>
            </tr>`;
        });
    }
    document.getElementById(idKg).textContent=formatNum(tKg);
    document.getElementById(idQq).textContent=formatNum(tQq,4);
    document.getElementById(idUsd).textContent=formatMoney(tUsd);
}

// ── Ver Factura PDF ─────────────────────────────────────────────────────────
function verFactura(ruta,nombre){
    document.getElementById('pdfNombre').textContent=nombre;
    document.getElementById('pdfFrame').src=ruta;
    document.getElementById('pdfDescargar').href=ruta;
    document.getElementById('pdfDescargar').download=nombre;
    document.getElementById('modalPDF').classList.add('active');
}
function cerrarPDF(){
    document.getElementById('pdfFrame').src='';
    document.getElementById('modalPDF').classList.remove('active');
}

// ── Eliminar venta ──────────────────────────────────────────────────────────
async function eliminarVenta(id,tipo){
    if(!confirm('¿Eliminar esta venta?')) return;
    const ep=tipo==='acopio'?'ventas_eliminar.php':'ventas_externas_eliminar.php';
    try{
        const res=await fetch(ep,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${id}`});
        const data=await res.json();
        if(data.success){ alert('Eliminado'); cerrarModal('modalHistorial'); cargarVentas(paginaActual); }
        else alert(data.message||'Error');
    }catch(e){alert('Error');}
}

// ── Tabs ────────────────────────────────────────────────────────────────────
function cambiarTab(tabId,btn){
    document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

// ── Importar Excel ──────────────────────────────────────────────────────────
function abrirModalImportar(){
    datosPreview=[];
    document.getElementById('imp_archivo').value='';
    document.getElementById('previewContainer').style.display='none';
    document.getElementById('tbPreview').innerHTML='';
    document.getElementById('modalImportar').classList.add('active');
}
function toggleSucursalImport(){
    const tipo=document.getElementById('imp_tipo').value;
    document.getElementById('divImpSuc').style.display=tipo==='ACOPIO'?'block':'none';
}
async function previewExcel(){
    const file=document.getElementById('imp_archivo').files[0];
    if(!file) return;
    const fd=new FormData();
    fd.append('archivo',file);
    try{
        const res=await fetch('ventas_importar_preview.php',{method:'POST',body:fd});
        const data=await res.json();
        if(!data.success){ alert(data.message||'Error al leer Excel'); return; }
        datosPreview=data.registros||[];
        document.getElementById('previewCount').textContent=datosPreview.length;
        document.getElementById('previewContainer').style.display='block';
        const tbody=document.getElementById('tbPreview');
        tbody.innerHTML='';
        datosPreview.forEach((r,i)=>{
            const cls=r.id_socio?'preview-ok':'preview-warn';
            tbody.innerHTML+=`
            <tr class="${cls}">
                <td>${i+1}</td><td>${r.productor}</td><td>${r.fecha}</td>
                <td>${r.numero}</td><td>${r.qq}</td><td>${r.kg}</td>
                <td>${formatMoney(r.precio)}</td><td>${formatMoney(r.total)}</td>
                <td>${r.prima}</td><td>${r.comprador}</td><td>${r.floid}</td>
                <td>${r.id_socio?'✅ '+r.nombre_socio:'⚠️ No encontrado'}</td>
            </tr>`;
        });
    }catch(err){console.error(err);alert('Error al procesar Excel');}
}
async function confirmarImportacion(){
    const tipo=document.getElementById('imp_tipo').value;
    const sucursal=document.getElementById('imp_sucursal').value;
    const validos=datosPreview.filter(r=>r.id_socio);
    if(!validos.length){ alert('No hay registros válidos'); return; }
    if(!confirm(`Se importarán ${validos.length} registros. ¿Continuar?`)) return;
    try{
        const res=await fetch('ventas_importar_guardar.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({registros:validos,tipo,sucursal})
        });
        const data=await res.json();
        if(data.success){ alert(`✅ Importados: ${data.importados} registros`); cerrarModal('modalImportar'); cargarVentas(paginaActual); }
        else alert(data.message||'Error al importar');
    }catch(err){console.error(err);alert('Error al importar');}
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function cerrarModal(id){ document.getElementById(id).classList.remove('active'); }
function hoy(){ return new Date().toISOString().split('T')[0]; }
function exportarExcel(){
    const mes=document.getElementById('filtroMes').value;
    const anio=document.getElementById('filtroAnio').value;
    window.open(`ventas_exportar.php?mes=${mes}&anio=${anio}`,'_blank');
}

// Buscar al presionar Enter o escribir (con delay)
document.getElementById('buscador').addEventListener('keydown', e => { if(e.key==='Enter') cargarVentas(1); });
document.getElementById('buscador').addEventListener('input', () => {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(() => cargarVentas(1), 500);
});
</script>
</body>
</html>