<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require "config/conexion.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Consulta General - Socios</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.search-container{background:#fff;padding:20px;border-radius:12px;margin-bottom:25px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.search-box{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.search-input{flex:1;min-width:280px;padding:12px 18px;border-radius:10px;border:1px solid #d1d5db;font-size:14px}
.search-input:focus{outline:none;border-color:#1f3a5f;box-shadow:0 0 0 3px rgba(31,58,95,.1)}
.btn-search{background:#1f3a5f;color:#fff;padding:12px 24px;border-radius:10px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn-search:hover{background:#16304d}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:20px}
.socio-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.08);cursor:pointer;transition:all .3s;position:relative;overflow:hidden}
.socio-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.15)}
.socio-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#1f3a5f,#3b82f6)}
.card-header{display:flex;align-items:center;gap:15px;margin-bottom:12px}
.card-foto{width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb}
.card-foto.placeholder{background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;font-weight:700}
.card-info h3{margin:0 0 4px;font-size:16px;color:#1f3a5f}
.card-info p{margin:0;font-size:13px;color:#6b7280}
.card-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
.stat-mini{background:#f9fafb;padding:8px;border-radius:6px;text-align:center}
.stat-mini span{display:block;font-size:11px;color:#6b7280;margin-bottom:2px}
.stat-mini strong{font-size:15px;color:#1f3a5f}
.estado-badge{position:absolute;top:12px;right:12px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase}
.estado-badge.activo{background:#d1fae5;color:#065f46}
.estado-badge.inactivo{background:#fee2e2;color:#991b1b}
.paginacion{display:flex;align-items:center;gap:6px;justify-content:center;flex-wrap:wrap;margin-top:20px}
.paginacion button{padding:8px 14px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:13px;font-weight:600}
.paginacion button:hover:not(:disabled){background:#f3f4f6}
.paginacion button.active{background:#1f3a5f;color:#fff;border-color:#1f3a5f}
.paginacion button:disabled{opacity:.4;cursor:not-allowed}
.info-paginacion{text-align:center;margin-top:10px;font-size:13px;color:#6b7280}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999999;align-items:center;justify-content:center;padding:20px}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:16px;width:95%;max-width:1100px;max-height:92vh;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.3);display:flex;flex-direction:column;position:relative}
.modal-close{position:absolute;top:16px;right:16px;width:38px;height:38px;border-radius:50%;background:#fff;border:none;box-shadow:0 4px 12px rgba(0,0,0,.2);cursor:pointer;font-size:20px;z-index:10;display:flex;align-items:center;justify-content:center}
.modal-close:hover{background:#f3f4f6}
.modal-header-info{background:linear-gradient(135deg,#1f3a5f,#3b82f6);color:#fff;padding:26px 30px;display:flex;align-items:center;gap:20px}
/* ── FOTO HEADER ── */
.foto-wrapper{position:relative;width:100px;height:100px;flex-shrink:0}
.modal-foto{width:100px;height:100px;border-radius:50%;border:4px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;background:rgba(255,255,255,.2);overflow:hidden}
.modal-foto img{width:100%;height:100%;object-fit:cover;border-radius:50%;cursor:pointer;transition:opacity .2s}
.modal-foto img:hover{opacity:.85}
.foto-btns{display:flex;gap:7px;margin-top:8px;flex-wrap:wrap}
.btn-foto-mini{padding:5px 10px;border-radius:6px;border:none;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:all .2s}
.btn-foto-ver{background:rgba(255,255,255,.25);color:#fff}
.btn-foto-ver:hover{background:rgba(255,255,255,.4)}
.btn-foto-subir{background:rgba(255,255,255,.9);color:#1f3a5f}
.btn-foto-subir:hover{background:#fff}
.foto-loading{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;color:#fff;font-size:22px}
.foto-loading.active{display:flex}
#inputFoto{display:none}
/* ── LIGHTBOX FOTO ── */
.foto-lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999999;align-items:center;justify-content:center}
.foto-lightbox.active{display:flex}
.foto-lightbox img{max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.foto-lightbox-close{position:absolute;top:20px;right:24px;color:#fff;font-size:36px;cursor:pointer;line-height:1}
.foto-lightbox-close:hover{color:#ccc}
.modal-nombre h2{margin:0 0 6px;font-size:24px}
.modal-nombre p{margin:0;font-size:13px;opacity:.85}
.tabs-container{display:flex;background:#f9fafb;border-bottom:2px solid #e5e7eb;padding:0 20px;overflow-x:auto;flex-shrink:0}
.tab-btn{padding:14px 18px;border:none;background:transparent;cursor:pointer;font-weight:600;font-size:13px;color:#6b7280;border-bottom:3px solid transparent;white-space:nowrap;transition:all .2s}
.tab-btn.active{color:#1f3a5f;border-bottom-color:#1f3a5f}
.tab-btn:hover{color:#1f3a5f}
.modal-content{flex:1;overflow-y:auto;padding:24px 28px}
.tab-panel{display:none}
.tab-panel.active{display:block}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:16px}
.info-item{background:#f9fafb;padding:13px 15px;border-radius:8px;border:1.5px solid #e5e7eb}
.info-item label{display:block;font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;font-weight:700}
.info-item p{margin:0;font-size:14px;color:#1f3a5f;font-weight:600}
.cupo-visual{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:16px 0}
.cupo-box{background:#f9fafb;padding:18px;border-radius:10px;text-align:center;border:2px solid #e5e7eb}
.cupo-box h4{margin:0 0 6px;font-size:11px;color:#6b7280;text-transform:uppercase}
.cupo-box p{margin:0;font-size:26px;font-weight:700;color:#1f3a5f}
.cupo-box.consumido p{color:#ef4444}
.cupo-box.disponible p{color:#10b981}
.data-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:12px}
.data-table thead th{background:#1f3a5f;color:#fff;padding:11px;text-align:left}
.data-table td{padding:10px;border-bottom:1px solid #e5e7eb}
.data-table tbody tr:hover{background:#f9fafb}
.doc-list{list-style:none;padding:0;margin:0}
.doc-item{background:#f9fafb;padding:13px;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.btn-doc{background:#1f3a5f;color:#fff;padding:7px 14px;border-radius:6px;border:none;cursor:pointer;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.no-data{text-align:center;padding:36px;color:#6b7280}
.no-data i{font-size:44px;margin-bottom:10px;opacity:.3;display:block}
/* ── SECCIONES EDITABLES ── */
.edit-section{background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;margin-bottom:20px;overflow:hidden;transition:border-color .25s,box-shadow .25s}
.edit-section.editing{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.edit-section.saved{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.1)}
.section-header{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;background:#f9fafb;border-bottom:1.5px solid #e5e7eb}
.section-title{font-size:12px;font-weight:700;color:#1f3a5f;text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:8px}
.section-actions{display:flex;gap:7px;align-items:center}
.btn-edit-sec{background:#fff;color:#2563eb;border:1.5px solid #2563eb;padding:6px 13px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .2s}
.btn-edit-sec:hover{background:#2563eb;color:#fff}
.btn-save-sec{background:#10b981;color:#fff;border:none;padding:6px 13px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;display:none;align-items:center;gap:5px}
.btn-save-sec:hover{background:#059669}
.btn-cancel-sec{background:#fff;color:#6b7280;border:1.5px solid #d1d5db;padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:none}
.btn-cancel-sec:hover{background:#f3f4f6}
.section-body{padding:18px}
.field-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:12px}
.field-group{display:flex;flex-direction:column;gap:4px}
.field-group label{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px}
.field-val{font-size:14px;font-weight:600;color:#1f3a5f;padding:9px 12px;background:#f9fafb;border-radius:7px;border:1.5px solid transparent;min-height:38px;display:flex;align-items:center}
.field-input{font-size:14px;font-weight:600;color:#1f3a5f;padding:9px 12px;border-radius:7px;border:1.5px solid #d1d5db;width:100%;box-sizing:border-box;display:none;transition:border-color .2s}
.field-input:focus{outline:none;border-color:#2563eb;background:#eff6ff}
.edit-section.editing .field-val{display:none}
.edit-section.editing .field-input{display:block}
.cacao-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.cacao-card{border:2px solid #e5e7eb;border-radius:10px;padding:15px}
.cacao-card.nacional{border-color:#10b981;background:#f0fdf4}
.cacao-card.ccn51{border-color:#3b82f6;background:#eff6ff}
.cacao-card h5{margin:0 0 12px;font-size:13px;font-weight:700}
.cacao-card.nacional h5{color:#065f46}
.cacao-card.ccn51 h5{color:#1d4ed8}
/* ── ALERTA SIN ACUERDO ── */
.alert-sin-acuerdo{background:#fff7ed;border:2px solid #f97316;border-radius:12px;padding:20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:14px}
.alert-sin-acuerdo .alert-icon{font-size:28px;flex-shrink:0;color:#ea580c}
.alert-sin-acuerdo h4{margin:0 0 6px;color:#9a3412;font-size:15px}
.alert-sin-acuerdo p{margin:0 0 12px;font-size:13px;color:#7c2d12}
.btn-crear-acuerdo{background:#ea580c;color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.btn-crear-acuerdo:hover{background:#c2410c}
/* ── FORM CREAR ACUERDO ── */
.form-crear-acuerdo{background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;display:none}
.form-crear-acuerdo h4{margin:0 0 16px;color:#1f3a5f;font-size:14px;text-transform:uppercase;letter-spacing:.4px}
.form-crear-acuerdo .field-input{display:block;margin-bottom:0}
.fc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:12px;margin-bottom:16px}
.fc-btns{display:flex;gap:10px;flex-wrap:wrap}
.btn-fc-save{background:#10b981;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.btn-fc-save:hover{background:#059669}
.btn-fc-cancel{background:#f3f4f6;color:#374151;border:1px solid #d1d5db;padding:10px 16px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer}
/* ── KML / UBICACIONES ── */
.kml-section{background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;padding:18px;margin-top:16px}
.kml-section h4{margin:0 0 14px;color:#1f3a5f;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:8px}
.kml-list{list-style:none;padding:0;margin:0}
.kml-item{background:#fff;padding:12px 15px;border-radius:8px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;border:1px solid #e5e7eb}
.kml-item-info strong{display:block;font-size:13px;color:#1f3a5f}
.kml-item-info small{font-size:11px;color:#6b7280}
.kml-btns{display:flex;gap:6px}
.btn-kml{padding:6px 11px;border-radius:6px;border:none;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px}
.btn-kml-ver{background:#3b82f6;color:#fff}
.btn-kml-ver:hover{background:#2563eb}
.btn-kml-dl{background:#10b981;color:#fff}
.btn-kml-dl:hover{background:#059669}
.btn-kml-del{background:#ef4444;color:#fff}
.btn-kml-del:hover{background:#dc2626}
.btn-kml-zip{background:#f59e0b;color:#fff;padding:8px 16px;border-radius:7px;border:none;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;margin-bottom:12px}
.btn-kml-zip:hover{background:#d97706}
/* ── COORDENADAS ── */
.coord-section{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:14px 18px;margin-bottom:14px}
.coord-section label{font-size:10px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.4px}
.coord-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px}
/* ── Toast ── */
#toast{position:fixed;bottom:24px;right:24px;background:#1f3a5f;color:#fff;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:9999999;transform:translateY(80px);opacity:0;transition:all .3s}
#toast.show{transform:translateY(0);opacity:1}
#toast.success{background:#10b981}
#toast.error{background:#ef4444}
</style>
</head>
<body>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>
<section class="page">
<h1><i class="fa fa-search" style="color:#1f3a5f;margin-right:8px;"></i> Consulta General de Socios</h1>

<div class="search-container">
    <div class="search-box">
        <input type="text" id="inputBuscar" class="search-input" placeholder="🔍 Buscar por cédula o nombre...">
        <button class="btn-search" onclick="buscarSocios()"><i class="fa fa-search"></i> Buscar</button>
    </div>
    <div id="resultadoInfo" style="margin-top:10px;font-size:13px;color:#6b7280;"></div>
</div>

<div class="cards-grid" id="cardsGrid">
    <div class="no-data"><i class="fa fa-spinner fa-spin"></i><p>Cargando...</p></div>
</div>
<div class="paginacion" id="paginacion"></div>
<div class="info-paginacion" id="infoPaginacion"></div>
</section>
</main>
</div>

<!-- ═══ LIGHTBOX FOTO ═══ -->
<div id="fotoLightbox" class="foto-lightbox" onclick="cerrarLightbox()">
    <span class="foto-lightbox-close" onclick="cerrarLightbox()">×</span>
    <img id="fotoLightboxImg" src="" alt="Foto socio">
</div>

<!-- ═══ MODAL DETALLE ═══ -->
<div id="modalDetalle" class="modal-overlay">
<div class="modal-box">
    <button class="modal-close" onclick="cerrarModal()">×</button>

    <!-- HEADER -->
    <div class="modal-header-info">
        <div>
            <div class="foto-wrapper">
                <div id="modalFoto" class="modal-foto">?</div>
                <div class="foto-loading" id="fotoLoading"><i class="fa fa-spinner fa-spin"></i></div>
            </div>
            <div class="foto-btns">
                <button class="btn-foto-mini btn-foto-ver" onclick="verFotoGrande()"><i class="fa fa-expand"></i> Ver</button>
                <button class="btn-foto-mini btn-foto-subir" onclick="document.getElementById('inputFoto').click()"><i class="fa fa-upload"></i> Subir</button>
            </div>
            <input type="file" id="inputFoto" accept="image/jpeg,image/png,image/webp" onchange="subirFoto(this)">
        </div>
        <div class="modal-nombre">
            <h2 id="modalNombre">-</h2>
            <p id="modalCedulaEstado">-</p>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <button class="tab-btn active" onclick="cambiarTab(this,'tabDatos')"><i class="fa fa-user"></i> Datos</button>
        <button class="tab-btn" onclick="cambiarTab(this,'tabAcuerdo')"><i class="fa fa-handshake"></i> Acuerdo</button>
        <button class="tab-btn" onclick="cambiarTab(this,'tabLPA')"><i class="fa fa-chart-line"></i> LPA &amp; Cupos</button>
        <button class="tab-btn" onclick="cambiarTab(this,'tabVentas')"><i class="fa fa-dollar-sign"></i> Ventas</button>
        <button class="tab-btn" onclick="cambiarTab(this,'tabDocs')"><i class="fa fa-folder"></i> Documentos</button>
        <button class="tab-btn" onclick="cambiarTab(this,'tabUbicaciones')"><i class="fa fa-map-marker-alt"></i> Ubicaciones</button>
    </div>

    <div class="modal-content">

        <!-- ═══ TAB DATOS ═══ -->
        <div id="tabDatos" class="tab-panel active">
            <input type="hidden" id="editIdSocio">

            <!-- Nombre editable -->
            <div class="edit-section" id="secNombre">
                <div class="section-header">
                    <span class="section-title"><i class="fa fa-signature"></i> Nombre del Socio</span>
                    <div class="section-actions">
                        <button class="btn-edit-sec"   onclick="activarEdicion('secNombre')"><i class="fa fa-pen"></i> Editar</button>
                        <button class="btn-save-sec"   onclick="guardarSeccion('nombre','secNombre')"><i class="fa fa-save"></i> Guardar</button>
                        <button class="btn-cancel-sec" onclick="cancelarEdicion('secNombre')">Cancelar</button>
                    </div>
                </div>
                <div class="section-body">
                    <div class="field-row">
                        <div class="field-group" style="grid-column:1/-1">
                            <label>Nombre Completo</label>
                            <div class="field-val" id="v_nombre_completo">-</div>
                            <input class="field-input" id="i_nombre_completo" placeholder="Apellido Nombre">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información personal -->
            <div class="edit-section" id="secSocio">
                <div class="section-header">
                    <span class="section-title"><i class="fa fa-user"></i> Información Personal</span>
                    <div class="section-actions">
                        <button class="btn-edit-sec"   onclick="activarEdicion('secSocio')"><i class="fa fa-pen"></i> Editar</button>
                        <button class="btn-save-sec"   onclick="guardarSeccion('socio','secSocio')"><i class="fa fa-save"></i> Guardar</button>
                        <button class="btn-cancel-sec" onclick="cancelarEdicion('secSocio')">Cancelar</button>
                    </div>
                </div>
                <div class="section-body">
                    <div class="field-row">
                        <div class="field-group">
                            <label>Identificación</label>
                            <div class="field-val" id="v_identificacion">-</div>
                            <input class="field-input" id="i_identificacion" disabled style="opacity:.6">
                        </div>
                        <div class="field-group">
                            <label>Teléfono</label>
                            <div class="field-val" id="v_telefono">-</div>
                            <input class="field-input" id="i_telefono">
                        </div>
                        <div class="field-group">
                            <label>Email</label>
                            <div class="field-val" id="v_email">-</div>
                            <input class="field-input" id="i_email" type="email">
                        </div>
                        <div class="field-group">
                            <label>Sexo</label>
                            <div class="field-val" id="v_sexo">-</div>
                            <select class="field-input" id="i_sexo">
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Fecha Nacimiento</label>
                            <div class="field-val" id="v_fecha_nacimiento">-</div>
                            <input class="field-input" id="i_fecha_nacimiento" type="date">
                        </div>
                        <div class="field-group">
                            <label>Fecha Afiliación</label>
                            <div class="field-val" id="v_fecha_ingreso">-</div>
                            <input class="field-input" id="i_fecha_ingreso" type="date">
                        </div>
                        <div class="field-group">
                            <label>Estado</label>
                            <div class="field-val" id="v_estado">-</div>
                            <select class="field-input" id="i_estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="field-group" style="grid-column:1/-1">
                            <label>Dirección</label>
                            <div class="field-val" id="v_direccion">-</div>
                            <input class="field-input" id="i_direccion">
                        </div>
                    </div>
                </div>
            </div>


        </div>

        <!-- ═══ TAB ACUERDO ═══ -->
        <div id="tabAcuerdo" class="tab-panel">
            <div id="alertaSinAcuerdo" class="alert-sin-acuerdo" style="display:none">
                <div class="alert-icon"><i class="fa fa-triangle-exclamation"></i></div>
                <div>
                    <h4>⚠️ Sin acuerdo en el periodo actual</h4>
                    <p id="alertaSinAcuerdoMsg">Este socio no tiene acuerdo registrado para el periodo activo.</p>
                    <button class="btn-crear-acuerdo" onclick="mostrarFormCrear()">
                        <i class="fa fa-plus"></i> Crear acuerdo para este periodo
                    </button>
                </div>
            </div>

            <div id="formCrearAcuerdo" class="form-crear-acuerdo">
                <h4><i class="fa fa-plus-circle"></i> Nuevo Acuerdo — Periodo Actual</h4>
                <div class="fc-grid">
                    <div class="field-group"><label>Provincia</label><input class="field-input" id="fc_provincia" placeholder="Guayas"></div>
                    <div class="field-group"><label>Cantón</label><input class="field-input" id="fc_canton" placeholder="El Empalme"></div>
                    <div class="field-group"><label>Parroquia</label><input class="field-input" id="fc_parroquia"></div>
                    <div class="field-group"><label>Sector</label><input class="field-input" id="fc_sector"></div>
                </div>
                <div class="cacao-grid" style="margin-bottom:16px">
                    <div class="cacao-card nacional">
                        <h5>🌱 Cacao Nacional</h5>
                        <div class="field-group" style="margin-bottom:10px"><label>Hectáreas</label><input class="field-input" id="fc_cn_ha" type="number" step="0.01" min="0" value="0"></div>
                        <div class="field-group"><label>Estimado (QQ)</label><input class="field-input" id="fc_cn_qq" type="number" step="0.01" min="0" value="0"></div>
                    </div>
                    <div class="cacao-card ccn51">
                        <h5>🌿 Cacao CCN51</h5>
                        <div class="field-group" style="margin-bottom:10px"><label>Hectáreas</label><input class="field-input" id="fc_cc_ha" type="number" step="0.01" min="0" value="0"></div>
                        <div class="field-group"><label>Estimado (QQ)</label><input class="field-input" id="fc_cc_qq" type="number" step="0.01" min="0" value="0"></div>
                    </div>
                </div>
                <div class="fc-grid">
                    <div class="field-group"><label>Posee Riego</label><select class="field-input" id="fc_riego"><option value="NO">NO</option><option value="SI">SI</option></select></div>
                    <div class="field-group"><label>Fertilización (veces/año)</label><input class="field-input" id="fc_fert" type="number" min="0" max="12" value="2"></div>
                    <div class="field-group"><label>Fecha Firma</label><input class="field-input" id="fc_fecha_firma" type="date"></div>
                </div>
                <div class="fc-btns">
                    <button class="btn-fc-save" onclick="crearAcuerdo()"><i class="fa fa-save"></i> Guardar Acuerdo</button>
                    <button class="btn-fc-cancel" onclick="ocultarFormCrear()">Cancelar</button>
                </div>
            </div>

            <div id="seccionesAcuerdo">
                <div class="edit-section" id="secUbicacion">
                    <div class="section-header">
                        <span class="section-title"><i class="fa fa-map-marker-alt"></i> Ubicación de la Finca</span>
                        <div class="section-actions">
                            <button class="btn-edit-sec"   onclick="activarEdicion('secUbicacion')"><i class="fa fa-pen"></i> Editar</button>
                            <button class="btn-save-sec"   onclick="guardarSeccion('ubicacion','secUbicacion')"><i class="fa fa-save"></i> Guardar</button>
                            <button class="btn-cancel-sec" onclick="cancelarEdicion('secUbicacion')">Cancelar</button>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="field-row">
                            <div class="field-group"><label>Provincia</label><div class="field-val" id="v_provincia">-</div><input class="field-input" id="i_provincia"></div>
                            <div class="field-group"><label>Cantón</label><div class="field-val" id="v_canton">-</div><input class="field-input" id="i_canton"></div>
                            <div class="field-group"><label>Parroquia</label><div class="field-val" id="v_parroquia">-</div><input class="field-input" id="i_parroquia"></div>
                            <div class="field-group"><label>Sector / Recinto</label><div class="field-val" id="v_sector">-</div><input class="field-input" id="i_sector"></div>
                        </div>
                    </div>
                </div>

                <div class="edit-section" id="secCacao">
                    <div class="section-header">
                        <span class="section-title"><i class="fa fa-seedling"></i> Tipo de Cacao y Hectáreas</span>
                        <div class="section-actions">
                            <button class="btn-edit-sec"   onclick="activarEdicion('secCacao')"><i class="fa fa-pen"></i> Editar</button>
                            <button class="btn-save-sec"   onclick="guardarSeccion('cacao','secCacao')"><i class="fa fa-save"></i> Guardar</button>
                            <button class="btn-cancel-sec" onclick="cancelarEdicion('secCacao')">Cancelar</button>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="cacao-grid">
                            <div class="cacao-card nacional">
                                <h5>🌱 Cacao Nacional</h5>
                                <div class="field-group" style="margin-bottom:10px"><label>Hectáreas</label><div class="field-val" id="v_cn_ha">0</div><input class="field-input" id="i_cn_ha" type="number" step="0.01" min="0"></div>
                                <div class="field-group"><label>Estimado Producción (QQ)</label><div class="field-val" id="v_cn_qq">0</div><input class="field-input" id="i_cn_qq" type="number" step="0.01" min="0"></div>
                            </div>
                            <div class="cacao-card ccn51">
                                <h5>🌿 Cacao CCN51</h5>
                                <div class="field-group" style="margin-bottom:10px"><label>Hectáreas</label><div class="field-val" id="v_cc_ha">0</div><input class="field-input" id="i_cc_ha" type="number" step="0.01" min="0"></div>
                                <div class="field-group"><label>Estimado Producción (QQ)</label><div class="field-val" id="v_cc_qq">0</div><input class="field-input" id="i_cc_qq" type="number" step="0.01" min="0"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="edit-section" id="secOtros">
                    <div class="section-header">
                        <span class="section-title"><i class="fa fa-sliders"></i> Otros Datos del Acuerdo</span>
                        <div class="section-actions">
                            <button class="btn-edit-sec"   onclick="activarEdicion('secOtros')"><i class="fa fa-pen"></i> Editar</button>
                            <button class="btn-save-sec"   onclick="guardarSeccion('otros','secOtros')"><i class="fa fa-save"></i> Guardar</button>
                            <button class="btn-cancel-sec" onclick="cancelarEdicion('secOtros')">Cancelar</button>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="field-row">
                            <div class="field-group"><label>N° Acuerdo</label><div class="field-val" id="v_numero_acuerdo">-</div><input class="field-input" id="i_numero_acuerdo" disabled style="opacity:.6"></div>
                            <div class="field-group"><label>Posee Riego</label><div class="field-val" id="v_posee_riego">-</div><select class="field-input" id="i_posee_riego"><option value="NO">NO</option><option value="SI">SI</option></select></div>
                            <div class="field-group"><label>Fertilización (veces/año)</label><div class="field-val" id="v_periodo_fert">-</div><input class="field-input" id="i_periodo_fert" type="number" min="0" max="12"></div>
                            <div class="field-group"><label>Fecha Firma</label><div class="field-val" id="v_fecha_firma">-</div><input class="field-input" id="i_fecha_firma" type="date"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ TAB LPA ═══ -->
        <div id="tabLPA" class="tab-panel">
            <h3 style="margin:0 0 14px;color:#1f3a5f">📊 LPA y Cupos</h3>
            <div id="lpaInfo"></div>
        </div>

        <!-- ═══ TAB VENTAS ═══ -->
        <div id="tabVentas" class="tab-panel">
            <h3 style="margin:0 0 14px;color:#1f3a5f">💰 Historial de Ventas</h3>
            <div id="ventasInfo"></div>
        </div>

        <!-- ═══ TAB DOCS ═══ -->
        <div id="tabDocs" class="tab-panel">
            <h3 style="margin:0 0 14px;color:#1f3a5f">📄 Documentos</h3>
            <div id="docsInfo"></div>
        </div>

        <!-- ═══ TAB UBICACIONES / KML ═══ -->
        <div id="tabUbicaciones" class="tab-panel">
            <h3 style="margin:0 0 14px;color:#1f3a5f">🗺️ Ubicaciones y Archivos KML</h3>
            <div id="ubicacionesInfo">
                <div class="no-data"><i class="fa fa-spinner fa-spin"></i><p>Cargando...</p></div>
            </div>
        </div>

    </div>
</div>
</div>

<!-- ═══ MODAL MAPA KML ═══ -->
<div id="modalMapa" class="modal-overlay" style="z-index:9999998;">
<div style="background:#fff;border-radius:16px;width:95%;max-width:1000px;height:82vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.35);overflow:hidden;position:relative;">
    <div style="background:linear-gradient(135deg,#1f3a5f,#3b82f6);color:#fff;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <span style="font-size:15px;font-weight:700;display:flex;align-items:center;gap:10px;">
            <i class="fa fa-map"></i>
            <span id="mapaModalTitle">Mapa KML</span>
        </span>
        <button onclick="cerrarModalMapa()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;">×</button>
    </div>
    <div id="mapaModalContainer" style="flex:1;width:100%;min-height:0;"></div>
</div>
</div>

<div id="toast"></div>

<script>
let paginaActual  = 1;
let socioActualId = null;
let snapshots     = {};
let fotoRutaActual = null;

window.onload = () => cargarSocios(1);

/* ══════════════ LISTADO / BÚSQUEDA ══════════════ */
async function cargarSocios(pagina) {
    paginaActual = pagina;
    const grid = document.getElementById('cardsGrid');
    grid.innerHTML = '<div class="no-data"><i class="fa fa-spinner fa-spin"></i><p>Cargando...</p></div>';
    try {
        const res  = await fetch(`consulta_general_listar.php?pagina=${pagina}`);
        const data = await res.json();
        if (!data.success || !data.socios?.length) {
            grid.innerHTML = '<div class="no-data"><i class="fa fa-inbox"></i><p>Sin socios</p></div>';
            return;
        }
        renderCards(data.socios);
        renderPaginacion(data.pagina, data.totalPaginas, data.total);
        document.getElementById('resultadoInfo').textContent = `${data.total} socios registrados`;
    } catch(e) {
        grid.innerHTML = '<div class="no-data"><i class="fa fa-exclamation-triangle"></i><p>Error</p></div>';
    }
}

document.getElementById('inputBuscar').addEventListener('keydown', e => { if(e.key==='Enter') buscarSocios(); });

async function buscarSocios() {
    const q = document.getElementById('inputBuscar').value.trim();
    if (!q) { cargarSocios(1); return; }
    const grid = document.getElementById('cardsGrid');
    grid.innerHTML = '<div class="no-data"><i class="fa fa-spinner fa-spin"></i><p>Buscando...</p></div>';
    document.getElementById('paginacion').innerHTML = '';
    document.getElementById('infoPaginacion').textContent = '';
    try {
        const res  = await fetch(`consulta_general_buscar.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success || !data.socios?.length) {
            grid.innerHTML = '<div class="no-data"><i class="fa fa-user-slash"></i><p>No encontrado</p></div>';
            return;
        }
        renderCards(data.socios);
        document.getElementById('resultadoInfo').textContent = `${data.socios.length} resultado(s)`;
    } catch(e) {
        grid.innerHTML = '<div class="no-data"><i class="fa fa-exclamation-triangle"></i><p>Error</p></div>';
    }
}

function renderCards(socios) {
    const grid = document.getElementById('cardsGrid');
    grid.innerHTML = '';
    socios.forEach(s => {
        const ini  = s.nombre_completo.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
        const foto = s.foto_ruta
            ? `<img src="${s.foto_ruta}" class="card-foto">`
            : `<div class="card-foto placeholder">${ini}</div>`;
        const card = document.createElement('div');
        card.className = 'socio-card';
        card.onclick   = () => verDetalle(s.id_socio);
        card.innerHTML = `
            <span class="estado-badge ${s.estado}">${s.estado}</span>
            <div class="card-header">${foto}
                <div class="card-info"><h3>${s.nombre_completo}</h3>
                    <p><i class="fa fa-id-card"></i> ${s.identificacion}</p>
                </div>
            </div>
            <div class="card-stats">
                <div class="stat-mini"><span>Teléfono</span><strong>${s.telefono||'-'}</strong></div>
                <div class="stat-mini"><span>Sexo</span><strong>${s.sexo||'-'}</strong></div>
            </div>`;
        grid.appendChild(card);
    });
}

function renderPaginacion(pagina, totalPaginas, total) {
    const div  = document.getElementById('paginacion');
    const info = document.getElementById('infoPaginacion');
    info.textContent = `Mostrando ${(pagina-1)*15+1}–${Math.min(pagina*15,total)} de ${total}`;
    if (totalPaginas <= 1) { div.innerHTML=''; return; }
    let h = `<button onclick="cargarSocios(1)" ${pagina===1?'disabled':''}>«</button>`;
    h += `<button onclick="cargarSocios(${pagina-1})" ${pagina===1?'disabled':''}>‹</button>`;
    for (let p=Math.max(1,pagina-2); p<=Math.min(totalPaginas,pagina+2); p++)
        h += `<button onclick="cargarSocios(${p})" class="${p===pagina?'active':''}">${p}</button>`;
    h += `<button onclick="cargarSocios(${pagina+1})" ${pagina===totalPaginas?'disabled':''}>›</button>`;
    h += `<button onclick="cargarSocios(${totalPaginas})" ${pagina===totalPaginas?'disabled':''}>»</button>`;
    div.innerHTML = h;
}

/* ══════════════ VER DETALLE ══════════════ */
async function verDetalle(idSocio) {
    socioActualId = idSocio;
    document.getElementById('modalDetalle').classList.add('active');
    ['secNombre','secSocio','secUbicacion','secCacao','secOtros'].forEach(id => cancelarEdicion(id));
    ocultarFormCrear();
    try {
        const res  = await fetch(`consulta_general_detalle.php?id_socio=${idSocio}`);
        const data = await res.json();
        if (!data.success) { toast('Error al cargar','error'); return; }
        llenarModal(data);
    } catch(e) { toast('Error de conexión','error'); }
}

function llenarModal(data) {
    const s = data.socio;
    const a = data.acuerdo;
    const l = data.lpa;

    // ── Normalizar foto: puede llegar null, "", "null", o ruta real ──
    const fotoRaw = (s.foto_ruta || '').toString().trim();
    fotoRutaActual = (fotoRaw && fotoRaw !== 'null') ? fotoRaw : null;

    const ini    = s.nombre_completo.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
    const fotoEl = document.getElementById('modalFoto');
    fotoEl.style.fontSize = '';
    if (fotoRutaActual) {
        fotoEl.innerHTML = `<img src="${fotoRutaActual}?v=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;cursor:pointer" onclick="verFotoGrande()" onerror="this.parentElement.innerHTML='${ini}';this.parentElement.style.fontSize='36px';fotoRutaActual=null;">`;
    } else {
        fotoEl.innerHTML = ini;
        fotoEl.style.fontSize = '36px';
    }
    document.getElementById('modalNombre').textContent       = s.nombre_completo;
    document.getElementById('modalCedulaEstado').textContent = `CI: ${s.identificacion} | Estado: ${s.estado}`;
    document.getElementById('editIdSocio').value             = s.id_socio;

    /* ── Nombre ── */
    setField('nombre_completo', s.nombre_completo);

    /* ── Datos personales ── */
    setField('identificacion',   s.identificacion);
    setField('telefono',         s.telefono);
    setField('email',            s.email || s.correo);
    setField('sexo',             s.sexo);
    setField('fecha_nacimiento', s.fecha_nacimiento);
    setField('estado',           s.estado);
    setField('direccion',        s.direccion);

    /* ── Fecha afiliación: primero tabla_lpa.fecha_ingreso, fallback socios.fecha_ingreso ── */
    const fechaAfil = (l && l.fecha_ingreso) ? l.fecha_ingreso : (s.fecha_ingreso || '');
    setField('fecha_ingreso', fechaAfil);

    /* ── Alerta sin acuerdo ── */
    const alerta = document.getElementById('alertaSinAcuerdo');
    if (!data.tiene_acuerdo_periodo) {
        alerta.style.display = 'flex';
        document.getElementById('alertaSinAcuerdoMsg').textContent = data.acuerdo_vacio
            ? 'Este socio NO tiene ningún acuerdo registrado. Debes crear el acuerdo del periodo actual.'
            : 'Este socio no tiene acuerdo para el periodo activo. Los datos mostrados son del último acuerdo anterior.';
        if (a) {
            document.getElementById('fc_provincia').value = a.provincia  || '';
            document.getElementById('fc_canton').value    = a.canton     || '';
            document.getElementById('fc_parroquia').value = a.parroquia  || '';
            document.getElementById('fc_sector').value    = a.sector     || '';
            document.getElementById('fc_cn_ha').value     = a.cacao_nacional_has || '0';
            document.getElementById('fc_cn_qq').value     = a.estimado_produccion_nacional || '0';
            document.getElementById('fc_cc_ha').value     = a.cacao_ccn51_has || '0';
            document.getElementById('fc_cc_qq').value     = a.estimado_produccion_ccn51 || '0';
            document.getElementById('fc_riego').value     = a.posee_riego || 'NO';
            document.getElementById('fc_fert').value      = a.periodo_de_fertilizacion || '2';
        }
        document.getElementById('fc_fecha_firma').value = new Date().toISOString().split('T')[0];
    } else {
        alerta.style.display = 'none';
    }

    /* ── Acuerdo ── */
    if (a) {
        setField('provincia',      a.provincia);
        setField('canton',         a.canton);
        setField('parroquia',      a.parroquia);
        setField('sector',         a.sector);
        setField('cn_ha',          a.cacao_nacional_has);
        setField('cn_qq',          a.estimado_produccion_nacional);
        setField('cc_ha',          a.cacao_ccn51_has);
        setField('cc_qq',          a.estimado_produccion_ccn51);
        setField('numero_acuerdo', a.numero_acuerdo);
        setField('posee_riego',    a.posee_riego);
        setField('periodo_fert',   a.periodo_de_fertilizacion);
        setField('fecha_firma',    a.fecha_firma);
    }

    /* ── LPA ── */
    if (l) {
        const cT = parseFloat(l.cupo_total)||0;
        const cC = parseFloat(l.cupo_consumido)||0;
        document.getElementById('lpaInfo').innerHTML = `
            <div class="cupo-visual">
                <div class="cupo-box"><h4>Cupo Total</h4><p>${cT.toFixed(2)} kg</p></div>
                <div class="cupo-box consumido"><h4>Consumido</h4><p>${cC.toFixed(2)} kg</p></div>
                <div class="cupo-box disponible"><h4>Disponible</h4><p>${(cT-cC).toFixed(2)} kg</p></div>
            </div>
            <div class="info-grid">
                <div class="info-item"><label>ID LPA</label><p>${l.id_lpa}</p></div>
                <div class="info-item"><label>Zona</label><p>${l.zona||'-'}</p></div>
                <div class="info-item"><label>Área Cacao (Ha)</label><p>${l.area_cacao_ha||'-'}</p></div>
                <div class="info-item"><label>Estado</label><p>${l.estado_lpa}</p></div>
                <div class="info-item"><label>Fecha Ingreso/Afiliación</label><p>${l.fecha_ingreso||'-'}</p></div>
            </div>`;
    } else {
        document.getElementById('lpaInfo').innerHTML = '<div class="no-data"><i class="fa fa-inbox"></i><p>Sin LPA asignado</p></div>';
    }

    /* ── Ventas ── */
    const ventas = data.ventas||[];
    if (!ventas.length) {
        document.getElementById('ventasInfo').innerHTML = '<div class="no-data"><i class="fa fa-shopping-cart"></i><p>Sin ventas</p></div>';
    } else {
        let h = '<table class="data-table"><thead><tr><th>#</th><th>Fecha</th><th>Tipo</th><th>KG</th><th>Precio</th><th>Total</th><th>Lugar</th></tr></thead><tbody>';
        ventas.forEach((v,i) => {
            h += `<tr><td>${i+1}</td><td>${(v.fecha_venta||'').substring(0,10)}</td><td>${v.tipo||'-'}</td>
                <td>${parseFloat(v.cantidad||0).toFixed(2)}</td><td>$${parseFloat(v.precio_kg||0).toFixed(2)}</td>
                <td><strong>$${parseFloat(v.total||0).toFixed(2)}</strong></td>
                <td>${v.sucursal||v.comprador||'-'}</td></tr>`;
        });
        document.getElementById('ventasInfo').innerHTML = h + '</tbody></table>';
    }

    /* ── Documentos ── */
    const docs = data.documentos||[];
    if (!docs.length) {
        document.getElementById('docsInfo').innerHTML = '<div class="no-data"><i class="fa fa-folder-open"></i><p>Sin documentos</p></div>';
    } else {
        let h = '<ul class="doc-list">';
        docs.forEach(d => {
            h += `<li class="doc-item">
                <div><strong>${d.tipo_documento}</strong><br>
                <small style="color:#6b7280">${d.nombre_archivo} | ${(d.tamano_archivo/1024).toFixed(1)} KB</small></div>
                <a href="${d.ruta_archivo}" target="_blank" class="btn-doc"><i class="fa fa-eye"></i> Ver</a>
            </li>`;
        });
        document.getElementById('docsInfo').innerHTML = h + '</ul>';
    }

    /* ── Ubicaciones KML (se carga al abrir ese tab) ── */
    document.getElementById('ubicacionesInfo').innerHTML = '<div class="no-data"><i class="fa fa-spinner fa-spin"></i><p>Cargando...</p></div>';
}

/* ══════════════ TAB UBICACIONES / KML ══════════════ */
async function cargarUbicaciones(idSocio) {
    const cont = document.getElementById('ubicacionesInfo');
    cont.innerHTML = '<div class="no-data"><i class="fa fa-spinner fa-spin"></i><p>Cargando...</p></div>';
    try {
        const res  = await fetch(`ubicaciones_api.php?accion=listar&id_socio=${idSocio}`);
        const data = await res.json();

        if (!data.success || !(data.datos && data.datos.length)) {
            cont.innerHTML = '<div class="no-data"><i class="fa fa-map-marker-alt"></i><p>Sin archivos KML/KMZ para este socio</p></div>';
            return;
        }

        // La API retorna data.datos con campo id_ubicacion como PK
        const archivos = data.datos;
        let h = `<button class="btn-kml-zip" onclick="exportarZip(${idSocio})">
                    <i class="fa fa-file-archive"></i> Exportar todos (ZIP)
                 </button>`;
        h += '<ul class="kml-list">';
        archivos.forEach(f => {
            const idUbic = f.id_ubicacion;
            const tipo   = (f.tipo_archivo || 'kml').toUpperCase();
            const fecha  = (f.fecha_subida || '').substring(0, 10);
            const user   = f.subido_por   || '';
            const nombre = f.nombre_archivo || 'archivo';
            const ruta   = f.ruta_archivo   || '';
            h += `<li class="kml-item">
                    <div class="kml-item-info">
                        <strong><i class="fa fa-map" style="color:#3b82f6;margin-right:5px"></i>${nombre}</strong>
                        <small>${tipo} &middot; ${fecha} &middot; ${user}</small>
                    </div>
                    <div class="kml-btns">
                        <button class="btn-kml btn-kml-ver"
                            onclick="verKmlEnMapa(${idUbic})">
                            <i class="fa fa-map"></i> Ver
                        </button>
                        <a href="${ruta}" download="${nombre}" class="btn-kml btn-kml-dl">
                            <i class="fa fa-download"></i> Descargar
                        </a>
                        <button class="btn-kml btn-kml-del"
                            onclick="eliminarKml(${idUbic},${idSocio})">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                  </li>`;
        });
        h += '</ul>';
        cont.innerHTML = h;
    } catch(e) {
        console.error('cargarUbicaciones error:', e);
        cont.innerHTML = '<div class="no-data"><i class="fa fa-exclamation-triangle"></i><p>Error al cargar ubicaciones</p></div>';
    }
}

function verKmlEnMapa(idUbicacion) {
    // Abrir modal interno con mapa Leaflet
    const modal = document.getElementById('modalMapa');
    modal.classList.add('active');
    document.getElementById('mapaModalContainer').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;flex-direction:column;gap:12px;"><i class="fa fa-spinner fa-spin" style="font-size:32px;"></i><span>Cargando mapa...</span></div>';

    fetch(`ubicaciones_api.php?accion=leer_kml&id_ubicacion=${idUbicacion}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('mapaModalContainer').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ef4444;"><i class="fa fa-triangle-exclamation"></i>&nbsp;' + (data.message||'Error') + '</div>';
                return;
            }
            document.getElementById('mapaModalTitle').textContent = data.nombre || 'Mapa KML';
            renderMapaKmlModal(atob(data.kml));
        })
        .catch(() => {
            document.getElementById('mapaModalContainer').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ef4444;">Error de conexión</div>';
        });
}

function renderMapaKmlModal(kmlContent) {
    document.getElementById('mapaModalContainer').innerHTML = '<div id="mapaLeafletModal" style="width:100%;height:100%;"></div>';

    function iniciarMapa() {
        const map = L.map('mapaLeafletModal').setView([-1.5, -79.5], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
        const layer = omnivore.kml.parse(kmlContent, null, L.geoJson(null, {
            style: { color: '#1f3a5f', weight: 2.5, fillOpacity: 0.25, fillColor: '#3b82f6' },
            pointToLayer: (f, latlng) => L.circleMarker(latlng, { radius: 8, fillColor: '#3b82f6', color: '#fff', weight: 2, fillOpacity: 0.9 })
        })).on('ready', function() {
            try { if (layer.getBounds().isValid()) map.fitBounds(layer.getBounds(), {padding:[30,30]}); } catch(e){}
            setTimeout(() => map.invalidateSize(), 200);
        }).addTo(map);
        // Guardar referencia para destruir al cerrar
        window._mapaModalInstance = map;
    }

    if (window.L && window.omnivore) { iniciarMapa(); return; }
    const loadLeaflet = (cb) => {
        if (!document.getElementById('leafletCSS2')) {
            const css = document.createElement('link');
            css.id = 'leafletCSS2'; css.rel = 'stylesheet';
            css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
            document.head.appendChild(css);
        }
        if (!window.L) {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
            s.onload = () => {
                const s2 = document.createElement('script');
                s2.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-omnivore/0.3.4/leaflet-omnivore.min.js';
                s2.onload = cb; document.head.appendChild(s2);
            };
            document.head.appendChild(s);
        } else if (!window.omnivore) {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-omnivore/0.3.4/leaflet-omnivore.min.js';
            s.onload = cb; document.head.appendChild(s);
        }
    };
    loadLeaflet(iniciarMapa);
}

function cerrarModalMapa() {
    document.getElementById('modalMapa').classList.remove('active');
    if (window._mapaModalInstance) { window._mapaModalInstance.remove(); window._mapaModalInstance = null; }
}

function exportarZip(idSocio) {
    // Misma API, accion=exportar_socio
    window.open(`ubicaciones_api.php?accion=exportar_socio&id_socio=${idSocio}`, '_blank');
}

async function eliminarKml(idUbicacion, idSocio) {
    if (!confirm('¿Eliminar este archivo KML?')) return;
    try {
        // La API espera accion=eliminar con POST id_ubicacion (no id_archivo)
        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('id_ubicacion', idUbicacion);
        const res  = await fetch('ubicaciones_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) { toast('Archivo eliminado', 'success'); cargarUbicaciones(idSocio); }
        else toast(data.message || 'Error al eliminar', 'error');
    } catch(e) { toast('Error de conexión', 'error'); }
}

/* ══════════════ FOTO ══════════════ */
function verFotoGrande() {
    if (!fotoRutaActual) { toast('Este socio no tiene foto aún','error'); return; }
    const lb = document.getElementById('fotoLightbox');
    document.getElementById('fotoLightboxImg').src = fotoRutaActual + '?v=' + Date.now();
    lb.classList.add('active');
}
function cerrarLightbox() {
    document.getElementById('fotoLightbox').classList.remove('active');
}

async function subirFoto(input) {
    if (!input.files || !input.files[0]) return;
    if (!socioActualId) return;
    const loader = document.getElementById('fotoLoading');
    loader.classList.add('active');
    const formData = new FormData();
    formData.append('id_socio', socioActualId);
    formData.append('foto', input.files[0]);
    try {
        const res  = await fetch('consulta_general_foto.php', { method:'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            fotoRutaActual = data.foto_ruta;
            document.getElementById('modalFoto').innerHTML =
                `<img src="${data.foto_ruta}?v=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;cursor:pointer" onclick="verFotoGrande()">`;
            toast('📸 Foto actualizada correctamente', 'success');
            input.value = '';
        } else {
            toast(data.message || 'Error al subir foto', 'error');
        }
    } catch(e) {
        toast('Error de conexión al subir foto', 'error');
    } finally {
        loader.classList.remove('active');
    }
}

/* ══════════════ CREAR ACUERDO ══════════════ */
function mostrarFormCrear() { document.getElementById('formCrearAcuerdo').style.display = 'block'; }
function ocultarFormCrear()  { document.getElementById('formCrearAcuerdo').style.display = 'none'; }

async function crearAcuerdo() {
    const id = parseInt(document.getElementById('editIdSocio').value);
    if (!id) return;
    const payload = {
        id_socio:                     id,
        provincia:                    document.getElementById('fc_provincia').value,
        canton:                       document.getElementById('fc_canton').value,
        parroquia:                    document.getElementById('fc_parroquia').value,
        sector:                       document.getElementById('fc_sector').value,
        cacao_nacional_has:           parseFloat(document.getElementById('fc_cn_ha').value)||0,
        estimado_produccion_nacional: parseFloat(document.getElementById('fc_cn_qq').value)||0,
        cacao_ccn51_has:              parseFloat(document.getElementById('fc_cc_ha').value)||0,
        estimado_produccion_ccn51:    parseFloat(document.getElementById('fc_cc_qq').value)||0,
        posee_riego:                  document.getElementById('fc_riego').value,
        periodo_fertilizacion:        document.getElementById('fc_fert').value,
        fecha_firma:                  document.getElementById('fc_fecha_firma').value,
    };
    try {
        const res  = await fetch('consulta_general_crear_acuerdo.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            toast('✅ ' + data.message, 'success');
            ocultarFormCrear();
            setTimeout(() => verDetalle(id), 800);
        } else {
            toast(data.message || 'Error al crear acuerdo', 'error');
        }
    } catch(e) { toast('Error de conexión', 'error'); }
}

/* ══════════════ HELPERS EDICIÓN ══════════════ */
function setField(id, val) {
    val = val || '';
    const vEl = document.getElementById('v_' + id);
    const iEl = document.getElementById('i_' + id);
    if (vEl) vEl.textContent = val || '-';
    if (iEl) {
        if (iEl.tagName === 'SELECT') {
            iEl.value = val;
        } else {
            iEl.value = val;
        }
    }
}

function activarEdicion(secId) {
    const sec = document.getElementById(secId);
    if (!sec) return;
    sec.classList.add('editing'); sec.classList.remove('saved');
    sec.querySelector('.btn-edit-sec').style.display   = 'none';
    sec.querySelector('.btn-save-sec').style.display   = 'inline-flex';
    sec.querySelector('.btn-cancel-sec').style.display = 'inline-flex';
    snapshots[secId] = {};
    sec.querySelectorAll('.field-input').forEach(el => { snapshots[secId][el.id] = el.value; });
}

function cancelarEdicion(secId) {
    const sec = document.getElementById(secId);
    if (!sec) return;
    sec.classList.remove('editing','saved');
    sec.querySelector('.btn-edit-sec').style.display   = 'inline-flex';
    sec.querySelector('.btn-save-sec').style.display   = 'none';
    sec.querySelector('.btn-cancel-sec').style.display = 'none';
    if (snapshots[secId]) {
        Object.entries(snapshots[secId]).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        });
    }
}

async function guardarSeccion(tipo, secId) {
    const id = parseInt(document.getElementById('editIdSocio').value);
    if (!id) return;
    let payload = { seccion: tipo, id_socio: id };

    if (tipo === 'nombre') {
        payload.nombre_completo = document.getElementById('i_nombre_completo').value;
    } else if (tipo === 'socio') {
        payload.telefono         = document.getElementById('i_telefono').value;
        payload.correo           = document.getElementById('i_email').value;
        payload.sexo             = document.getElementById('i_sexo').value;
        payload.fecha_nacimiento = document.getElementById('i_fecha_nacimiento').value;
        payload.fecha_ingreso    = document.getElementById('i_fecha_ingreso').value;
        payload.direccion        = document.getElementById('i_direccion').value;
        payload.estado           = document.getElementById('i_estado').value;
    } else if (tipo === 'coordenadas') {
        payload.latitud  = document.getElementById('i_latitud').value;
        payload.longitud = document.getElementById('i_longitud').value;
    } else if (tipo === 'ubicacion') {
        payload.provincia = document.getElementById('i_provincia').value;
        payload.canton    = document.getElementById('i_canton').value;
        payload.parroquia = document.getElementById('i_parroquia').value;
        payload.sector    = document.getElementById('i_sector').value;
    } else if (tipo === 'cacao') {
        payload.cacao_nacional_has           = parseFloat(document.getElementById('i_cn_ha').value)||0;
        payload.estimado_produccion_nacional = parseFloat(document.getElementById('i_cn_qq').value)||0;
        payload.cacao_ccn51_has              = parseFloat(document.getElementById('i_cc_ha').value)||0;
        payload.estimado_produccion_ccn51    = parseFloat(document.getElementById('i_cc_qq').value)||0;
    } else if (tipo === 'otros') {
        payload.posee_riego           = document.getElementById('i_posee_riego').value;
        payload.periodo_fertilizacion = document.getElementById('i_periodo_fert').value;
        payload.fecha_firma           = document.getElementById('i_fecha_firma').value;
    }

    try {
        const res  = await fetch('consulta_general_editar.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            actualizarVistas(tipo, payload);
            const sec = document.getElementById(secId);
            sec.classList.remove('editing'); sec.classList.add('saved');
            sec.querySelector('.btn-edit-sec').style.display   = 'inline-flex';
            sec.querySelector('.btn-save-sec').style.display   = 'none';
            sec.querySelector('.btn-cancel-sec').style.display = 'none';
            toast('✅ ' + data.message, 'success');
            setTimeout(() => sec.classList.remove('saved'), 2500);
            // Si cambió el nombre, actualizar header
            if (tipo === 'nombre') {
                document.getElementById('modalNombre').textContent = payload.nombre_completo;
                setField('nombre_completo', payload.nombre_completo);
            }
        } else {
            toast(data.message || 'Error al guardar', 'error');
        }
    } catch(e) { toast('Error de conexión', 'error'); }
}

function actualizarVistas(tipo, p) {
    if (tipo === 'nombre') {
        setField('nombre_completo', p.nombre_completo);
    } else if (tipo === 'socio') {
        setField('telefono', p.telefono); setField('email', p.correo);
        setField('sexo', p.sexo); setField('fecha_nacimiento', p.fecha_nacimiento);
        setField('fecha_ingreso', p.fecha_ingreso);
        setField('direccion', p.direccion); setField('estado', p.estado);
        document.getElementById('modalCedulaEstado').textContent =
            document.getElementById('modalCedulaEstado').textContent.replace(/Estado:\s*\w+/, 'Estado: ' + p.estado);
    } else if (tipo === 'coordenadas') {
        setField('latitud', p.latitud); setField('longitud', p.longitud);
    } else if (tipo === 'ubicacion') {
        setField('provincia', p.provincia); setField('canton', p.canton);
        setField('parroquia', p.parroquia); setField('sector', p.sector);
    } else if (tipo === 'cacao') {
        setField('cn_ha', p.cacao_nacional_has); setField('cn_qq', p.estimado_produccion_nacional);
        setField('cc_ha', p.cacao_ccn51_has);    setField('cc_qq', p.estimado_produccion_ccn51);
    } else if (tipo === 'otros') {
        setField('posee_riego', p.posee_riego); setField('periodo_fert', p.periodo_fertilizacion);
        setField('fecha_firma', p.fecha_firma);
    }
}

/* ══════════════ TABS ══════════════ */
function cambiarTab(btnEl, tabId) {
    document.querySelectorAll('.tab-btn').forEach(b   => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btnEl.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    // Cargar KML al abrir ese tab
    if (tabId === 'tabUbicaciones' && socioActualId) {
        cargarUbicaciones(socioActualId);
    }
}

/* ══════════════ MODAL ══════════════ */
function cerrarModal() { document.getElementById('modalDetalle').classList.remove('active'); }
document.getElementById('modalDetalle').addEventListener('click', e => { if(e.target.id==='modalDetalle') cerrarModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') { cerrarModal(); cerrarLightbox(); } });

/* ══════════════ TOAST ══════════════ */
function toast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'show '+(tipo||'');
    setTimeout(() => { t.className=''; }, 3200);
}
</script>
</body>
</html>