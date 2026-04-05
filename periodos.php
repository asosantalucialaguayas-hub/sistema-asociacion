<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require "config/conexion.php";
require "helpers/periodo.php";

/* Periodo activo */
$periodoSeleccionado = get_periodo_abierto($pdo);

/* Historial */
$periodos = get_all_periodos($pdo);

/* Obtener documentos de cada período */
function get_documentos_periodo($pdo, $id_periodo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM contrato_periodo_documento 
            WHERE id_periodo = ? 
            ORDER BY subido_en DESC
        ");
        $stmt->execute([$id_periodo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Períodos de Comercialización</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php include 'layout/modals.php'; ?>

<style>
.btn-actions{display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap}
.btn-primary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;transition:all 0.3s;text-decoration:none;display:inline-block}
.btn-secondary{background:#6c757d;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;transition:all 0.3s}
.btn-danger{background:#ef4444;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;transition:all 0.3s}
.btn-success{background:#10b981;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;transition:all 0.3s}
.btn-info{background:#0ea5e9;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;transition:all 0.3s}
.btn-warning{background:#ff9800;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;transition:all 0.3s}
.btn-primary:hover{background:#16304d}
.btn-secondary:hover{background:#5a6268}
.btn-danger:hover{background:#dc2626}
.btn-success:hover{background:#059669}
.btn-info:hover{background:#0284c7}
.btn-warning:hover{background:#f57c00}
.btn-sm{padding:6px 12px;font-size:12px;margin:2px}

.data-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:15px}
.data-table thead th{background:#1f3a5f;color:#fff;padding:12px;text-align:left}
.data-table td{padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:middle}
.data-table tbody tr:hover{background:#f9fafb}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
.form-group label{display:block;font-size:13px;font-weight:700;color:#1f3a5f;margin-bottom:6px}
.form-group input, .form-group select, .form-group textarea{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:14px;box-sizing:border-box}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus{outline:none;border-color:#1f3a5f;box-shadow:0 0 0 3px rgba(31,58,95,0.1)}

.badge{background:#e5e7eb;padding:4px 10px;border-radius:999px;font-size:12px;color:#374151;font-weight:700}
.badge-adenda{background:#ff9800;padding:4px 10px;border-radius:999px;font-size:12px;color:#fff;font-weight:700}
.estado{padding:4px 12px;border-radius:999px;font-size:12px;font-weight:800;color:#fff;display:inline-block}
.estado.abierto{background:#10b981}
.estado.cerrado{background:#ef4444}

.info-line{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:8px 0}
.info-line span{font-size:13px;color:#374151}

.alert{padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid}
.alert-warning{background:#fff3cd;color:#856404;border-color:#ffc107}
.alert-info{background:#e7f3ff;color:#004085;border-color:#0066cc}

/* Modales */
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-content{background:white;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,0.3)}
.modal-content.modal-large{max-width:700px}
.modal-header{margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.modal-header h2{margin:0;color:#1f3a5f;font-size:24px}
.modal-close{background:none;border:none;font-size:24px;color:#6c757d;cursor:pointer;padding:0}
.modal-close:hover{color:#1f3a5f}
.modal-body{margin-bottom:20px}
.modal-footer{display:flex;gap:10px;justify-content:flex-end}

/* Lista de documentos */
.doc-list{list-style:none;padding:0;margin:15px 0}
.doc-item{padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.doc-item:hover{background:#f3f4f6}
.doc-info h4{margin:0 0 5px 0;font-size:14px;color:#1f3a5f}
.doc-info p{margin:0;font-size:12px;color:#6c757d}
</style>
</head>

<body>
<script src="layout/modal-message.js"></script>

<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
</header>

<section class="page">
<h1><i class="fa-solid fa-calendar-days"></i> Períodos de Comercialización</h1>

<!-- BLOQUE: PERÍODO ACTIVO -->
<div class="form-card">

<?php if (!$periodoSeleccionado): ?>
    <!-- NO HAY PERÍODO ABIERTO -->
    <div class="alert alert-warning">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> No hay período abierto</strong>
        <p style="margin:5px 0 0 0">Las funciones principales del sistema están bloqueadas. Crea un nuevo período para continuar.</p>
    </div>

    <div class="btn-actions">
        <button type="button" class="btn-success" onclick="mostrarModalNuevoPeriodo()">
            <i class="fa-solid fa-plus"></i> Crear Nuevo Período
        </button>
    </div>

<?php else: ?>
    <!-- HAY PERÍODO ABIERTO -->
    <h2 style="margin:0 0 12px 0;">Período Activo</h2>

    <div class="info-line">
        <span class="badge"><?= htmlspecialchars($periodoSeleccionado['nombre']) ?></span>
        <span>Apertura: <strong><?= date('d/m/Y', strtotime($periodoSeleccionado['fecha_apertura'])) ?></strong></span>
        <span>Estado: <span class="estado abierto">ABIERTO</span></span>
        <?php if ($periodoSeleccionado['adenda_activa']): ?>
            <span class="badge-adenda">📝 Adenda Activa</span>
        <?php endif; ?>
    </div>

    <div class="btn-actions">
        <button type="button" class="btn-primary" onclick="mostrarModalSubirDoc(<?= $periodoSeleccionado['id_periodo'] ?>)">
            <i class="fa-solid fa-upload"></i> Subir Documento
        </button>
        
        <button type="button" class="btn-info" onclick="mostrarModalVerDocs(<?= $periodoSeleccionado['id_periodo'] ?>, '<?= htmlspecialchars($periodoSeleccionado['nombre'], ENT_QUOTES) ?>')">
            <i class="fa-solid fa-eye"></i> Ver Documentos
        </button>

        <?php if ($periodoSeleccionado['adenda_activa']): ?>
            <button type="button" class="btn-warning" onclick="cerrarAdenda(<?= $periodoSeleccionado['id_periodo'] ?>)">
                <i class="fa-solid fa-lock"></i> Cerrar Adenda
            </button>
        <?php else: ?>
            <button type="button" class="btn-warning" onclick="mostrarModalAdenda(<?= $periodoSeleccionado['id_periodo'] ?>)">
                <i class="fa-solid fa-file-circle-plus"></i> Abrir Adenda
            </button>
        <?php endif; ?>
        
        <button type="button" class="btn-danger" onclick="cerrarPeriodoActual()">
            <i class="fa-solid fa-lock"></i> Cerrar Período
        </button>
    </div>

    <div style="margin-top:8px;">
        <span class="badge">Nota:</span>
        <span style="font-size:13px;color:#374151;">
            Solo puede existir 1 período ABIERTO a la vez.
        </span>
    </div>
<?php endif; ?>

</div>

<!-- BLOQUE: HISTORIAL -->
<div class="form-card">
    <h2 style="margin:0 0 12px 0;">Historial de Períodos</h2>

    <?php if (empty($periodos)): ?>
        <p style="color:#6c757d;padding:20px 0">No hay períodos registrados.</p>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>Nombre</th>
                <th style="width:120px">Apertura</th>
                <th style="width:120px">Cierre</th>
                <th style="width:100px">Estado</th>
                <th style="width:250px;text-align:center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($periodos as $i => $p): ?>
                <?php $est = strtolower($p['estado']); ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha_apertura'])) ?></td>
                    <td><?= $p['fecha_cierre'] ? date('d/m/Y', strtotime($p['fecha_cierre'])) : '-' ?></td>
                    <td>
                        <span class="estado <?= $est ?>">
                            <?= htmlspecialchars($p['estado']) ?>
                        </span>
                    </td>
                    <td style="text-align:center">
                        <!-- Botón Ver Documentos (OJO) -->
                        <button 
                            class="btn btn-info btn-sm" 
                            onclick="mostrarModalVerDocs(<?= $p['id_periodo'] ?>, '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>')"
                            title="Ver documentos">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        
                        <?php if ($p['estado'] === 'CERRADO'): ?>
                            <button 
                                class="btn btn-success btn-sm" 
                                onclick="abrirPeriodo(<?= $p['id_periodo'] ?>, '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>')"
                                title="Reabrir período">
                                <i class="fa-solid fa-unlock"></i> Abrir
                            </button>
                        <?php endif; ?>
                        
                        <button 
                            class="btn btn-danger btn-sm" 
                            onclick="eliminarPeriodo(<?= $p['id_periodo'] ?>, '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>')"
                            title="Eliminar período">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</section>
</main>
</div>

<!-- MODAL: CREAR NUEVO PERÍODO -->
<div id="modalNuevoPeriodo" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa-solid fa-calendar-plus"></i> Nuevo Período</h2>
            <button class="modal-close" onclick="cerrarModalNuevoPeriodo()"><i class="fa-solid fa-times"></i></button>
        </div>
        
        <?php if ($periodoSeleccionado): ?>
        <div class="alert alert-info" style="margin-bottom:20px">
            <strong><i class="fa-solid fa-info-circle"></i> Atención:</strong>
            El período actual "<strong><?= htmlspecialchars($periodoSeleccionado['nombre']) ?></strong>" se cerrará automáticamente.
        </div>
        <?php endif; ?>
        
        <form id="formNuevoPeriodo">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre del Período *</label>
                    <input 
                        type="text" 
                        name="nombre" 
                        id="nombre" 
                        placeholder="Ejemplo: CONTRATO 2027" 
                        required
                        autocomplete="off"
                    >
                </div>
                
                <div class="form-group">
                    <label>Fecha de Apertura *</label>
                    <input 
                        type="date" 
                        name="fecha_apertura" 
                        id="fecha_apertura" 
                        value="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="cerrarModalNuevoPeriodo()">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-success" id="btnCrearPeriodo">
                    <i class="fa-solid fa-check"></i> Crear Período
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: SUBIR DOCUMENTO -->
<div id="modalSubirDoc" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa-solid fa-upload"></i> Subir Documento</h2>
            <button class="modal-close" onclick="cerrarModalSubirDoc()"><i class="fa-solid fa-times"></i></button>
        </div>
        
        <form id="formSubirDoc" enctype="multipart/form-data">
            <input type="hidden" name="id_periodo" id="id_periodo_doc">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo de Documento *</label>
                    <select name="tipo" required>
                        <option value="CONTRATO">Contrato Principal</option>
                        <option value="ADENDA">Adenda al Contrato</option>
                        <option value="COMPLEMENTARIO">Documento Complementario</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Título del Documento *</label>
                    <input 
                        type="text" 
                        name="titulo" 
                        placeholder="Ej: Contrato de Comercialización 2026" 
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label>Archivo PDF *</label>
                    <input 
                        type="file" 
                        name="archivo" 
                        accept=".pdf"
                        required
                    >
                    <small style="color:#6c757d">Solo archivos PDF (máx. 10MB)</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="cerrarModalSubirDoc()">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-primary" id="btnSubirDoc">
                    <i class="fa-solid fa-upload"></i> Subir Documento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: VER DOCUMENTOS -->
<div id="modalVerDocs" class="modal-overlay">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2><i class="fa-solid fa-folder-open"></i> Documentos del Período</h2>
            <button class="modal-close" onclick="cerrarModalVerDocs()"><i class="fa-solid fa-times"></i></button>
        </div>
        
        <div class="modal-body">
            <p id="periodoNombreDocs" style="color:#6c757d;margin-bottom:15px"></p>
            <div id="listaDocumentos">
                <p style="text-align:center;color:#6c757d">Cargando...</p>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="cerrarModalVerDocs()">
                <i class="fa-solid fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<!-- MODAL: ABRIR ADENDA -->
<div id="modalAdenda" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa-solid fa-file-circle-plus"></i> Abrir Adenda</h2>
            <button class="modal-close" onclick="cerrarModalAdenda()"><i class="fa-solid fa-times"></i></button>
        </div>
        
        <form id="formAdenda">
            <input type="hidden" name="id_periodo" id="id_periodo_adenda">
            
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>ℹ️ ¿Qué es una adenda?</strong>
                    <p style="margin:5px 0 0 0">Permite abrir temporalmente el período para inscribir nuevos socios sin crear un período nuevo.</p>
                </div>
                
                <div class="form-group">
                    <label>Motivo de la Adenda *</label>
                    <textarea 
                        name="motivo" 
                        rows="3"
                        placeholder="Ej: Ingreso de nuevos socios - Junio 2026"
                        required
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label>Fecha de Inicio</label>
                    <input 
                        type="date" 
                        name="fecha_inicio" 
                        value="<?= date('Y-m-d') ?>"
                    >
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="cerrarModalAdenda()">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-warning" id="btnAbrirAdenda">
                    <i class="fa-solid fa-check"></i> Abrir Adenda
                </button>
            </div>
        </form>
    </div>
</div>



<script>
// ============================================
// HELPER: CONFIRMAR ACCIÓN CON MODAL BONITO
// ============================================
function confirmarAccion(titulo, mensaje, callback) {
    const modalConfirm = document.createElement('div');
    modalConfirm.className = 'modal-overlay active';
    modalConfirm.style.zIndex = '10000';
    
    modalConfirm.innerHTML = `
        <div class="modal-content" style="max-width:450px">
            <div class="modal-header">
                <h2 style="color:#ef4444;margin:0">
                    <i class="fa-solid fa-triangle-exclamation"></i> ${titulo}
                </h2>
            </div>
            <div class="modal-body">
                <p style="white-space:pre-line;line-height:1.6">${mensaje}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="btnCancelarConfirm">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn-danger" id="btnAceptarConfirm">
                    <i class="fa-solid fa-check"></i> Aceptar
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modalConfirm);
    
    modalConfirm.querySelector('#btnCancelarConfirm').addEventListener('click', () => {
        modalConfirm.remove();
    });
    
    modalConfirm.querySelector('#btnAceptarConfirm').addEventListener('click', () => {
        modalConfirm.remove();
        callback();
    });
    
    const onKeyDown = (e) => {
        if (e.key === 'Escape') {
            modalConfirm.remove();
            document.removeEventListener('keydown', onKeyDown);
        }
    };
    document.addEventListener('keydown', onKeyDown);
    
    modalConfirm.addEventListener('click', (e) => {
        if (e.target === modalConfirm) {
            modalConfirm.remove();
        }
    });
}

// ============================================
// MODAL: CREAR NUEVO PERÍODO
// ============================================
function mostrarModalNuevoPeriodo() {
    document.getElementById('modalNuevoPeriodo').classList.add('active');
    document.getElementById('nombre').focus();
}

function cerrarModalNuevoPeriodo() {
    document.getElementById('modalNuevoPeriodo').classList.remove('active');
    document.getElementById('formNuevoPeriodo').reset();
}

document.getElementById('formNuevoPeriodo').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = document.getElementById('btnCrearPeriodo');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creando...';
    
    try {
        const response = await fetch('periodo_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            cerrarModalNuevoPeriodo();
            window.mostrarMensaje('✅ Período Creado', data.message, 'success', 3000, () => {
                window.location.reload();
            });
        } else {
            window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Crear Período';
        }
    } catch (error) {
        console.error('Error:', error);
        window.mostrarMensaje('❌ Error de Conexión', 'No se pudo conectar con el servidor', 'error', 5000);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Crear Período';
    }
});

// ============================================
// MODAL: SUBIR DOCUMENTO
// ============================================
function mostrarModalSubirDoc(idPeriodo) {
    document.getElementById('id_periodo_doc').value = idPeriodo;
    document.getElementById('modalSubirDoc').classList.add('active');
}

function cerrarModalSubirDoc() {
    document.getElementById('modalSubirDoc').classList.remove('active');
    document.getElementById('formSubirDoc').reset();
}

document.getElementById('formSubirDoc').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = document.getElementById('btnSubirDoc');
    
    // Validar archivo
    const archivo = formData.get('archivo');
    if (!archivo || archivo.size === 0) {
        window.mostrarMensaje('❌ Error', 'Debes seleccionar un archivo PDF', 'error', 3000);
        return;
    }
    
    if (archivo.size > 10 * 1024 * 1024) {
        window.mostrarMensaje('❌ Error', 'El archivo no debe superar 10MB', 'error', 3000);
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';
    
    try {
        const response = await fetch('documento_subir.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            cerrarModalSubirDoc();
            window.mostrarMensaje('✅ Documento Subido', data.message, 'success', 3000, () => {
                window.location.reload();
            });
        } else {
            window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-upload"></i> Subir Documento';
        }
    } catch (error) {
        console.error('Error:', error);
        window.mostrarMensaje('❌ Error de Conexión', 'No se pudo subir el documento', 'error', 5000);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-upload"></i> Subir Documento';
    }
});

// ============================================
// MODAL: VER DOCUMENTOS (OJO)
// ============================================
async function mostrarModalVerDocs(idPeriodo, nombrePeriodo) {
    document.getElementById('periodoNombreDocs').textContent = `Período: ${nombrePeriodo}`;
    document.getElementById('modalVerDocs').classList.add('active');
    
    // Cargar documentos
    const listaDiv = document.getElementById('listaDocumentos');
    listaDiv.innerHTML = '<p style="text-align:center;color:#6c757d"><i class="fa-solid fa-spinner fa-spin"></i> Cargando documentos...</p>';
    
    try {
        const response = await fetch(`documentos_obtener.php?id_periodo=${idPeriodo}`);
        const data = await response.json();
        
        if (data.success && data.documentos.length > 0) {
            let html = '<ul class="doc-list">';
            
            data.documentos.forEach(doc => {
                const fecha = new Date(doc.subido_en).toLocaleDateString('es-ES');
                const tipo = doc.tipo === 'CONTRATO' ? '📄' : doc.tipo === 'ADENDA' ? '📝' : '📎';
                
                html += `
                    <li class="doc-item">
                        <div class="doc-info">
                            <h4>${tipo} ${doc.titulo}</h4>
                            <p>Tipo: ${doc.tipo} | Subido: ${fecha} | Tamaño: ${(doc.tamano / 1024).toFixed(1)} KB</p>
                        </div>
                        <div>
                            <a href="${doc.archivo_ruta}" target="_blank" class="btn btn-primary btn-sm" title="Abrir documento">
                                <i class="fa-solid fa-external-link-alt"></i> Abrir
                            </a>
                            <button 
                                class="btn btn-danger btn-sm" 
                                onclick="eliminarDocumento(${doc.id_doc}, '${doc.titulo.replace(/'/g, "\\'")}')"
                                title="Eliminar documento">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </li>
                `;
            });
            
            html += '</ul>';
            listaDiv.innerHTML = html;
        } else {
            listaDiv.innerHTML = '<p style="text-align:center;color:#6c757d;padding:20px">📭 No hay documentos en este período</p>';
        }
    } catch (error) {
        console.error('Error:', error);
        listaDiv.innerHTML = '<p style="text-align:center;color:#ef4444">❌ Error al cargar documentos</p>';
    }
}

function cerrarModalVerDocs() {
    document.getElementById('modalVerDocs').classList.remove('active');
}

// Eliminar documento
async function eliminarDocumento(idDoc, titulo) {
    confirmarAccion(
        '¿Eliminar Documento?',
        `¿Estás seguro de eliminar "${titulo}"?\n\nEsta acción no se puede deshacer.`,
        async () => {
            try {
                const formData = new FormData();
                formData.append('id_doc', idDoc);
                
                const res = await fetch('documento_eliminar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    window.mostrarMensaje('✅ Eliminado', data.message, 'success', 2000, () => {
                        window.location.reload();
                    });
                } else {
                    window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
                }
            } catch (error) {
                window.mostrarMensaje('❌ Error', 'Error de conexión', 'error', 5000);
            }
        }
    );
}

// ============================================
// MODAL: ABRIR ADENDA
// ============================================
function mostrarModalAdenda(idPeriodo) {
    document.getElementById('id_periodo_adenda').value = idPeriodo;
    document.getElementById('modalAdenda').classList.add('active');
}

function cerrarModalAdenda() {
    document.getElementById('modalAdenda').classList.remove('active');
    document.getElementById('formAdenda').reset();
}

document.getElementById('formAdenda').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = document.getElementById('btnAbrirAdenda');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Abriendo...';
    
    try {
        const response = await fetch('adenda_abrir.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            cerrarModalAdenda();
            window.mostrarMensaje('✅ Adenda Abierta', data.message, 'success', 3000, () => {
                window.location.reload();
            });
        } else {
            window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Abrir Adenda';
        }
    } catch (error) {
        console.error('Error:', error);
        window.mostrarMensaje('❌ Error de Conexión', 'No se pudo abrir la adenda', 'error', 5000);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Abrir Adenda';
    }
});

// ============================================
// CERRAR ADENDA
// ============================================
async function cerrarAdenda(idPeriodo) {
    confirmarAccion(
        '¿Cerrar Adenda?',
        '¿Cerrar la adenda activa?\n\nYa no se podrán registrar nuevas inscripciones.',
        async () => {
            try {
                const formData = new FormData();
                formData.append('id_periodo', idPeriodo);
                
                const res = await fetch('adenda_cerrar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    window.mostrarMensaje('✅ Adenda Cerrada', data.message, 'success', 3000, () => {
                        window.location.reload();
                    });
                } else {
                    window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
                }
            } catch (error) {
                window.mostrarMensaje('❌ Error', 'Error de conexión', 'error', 5000);
            }
        }
    );
}

// ============================================
// CERRAR PERÍODO ACTUAL
// ============================================
async function cerrarPeriodoActual() {
    confirmarAccion(
        '¿Cerrar Período?',
        '¿Seguro que deseas CERRAR el período actual?\n\nEsto bloqueará inscripciones y asignación de cupos.\nLas ventas, LPAs y documentos seguirán funcionando.',
        async () => {
            try {
                const res = await fetch('periodo_cerrar.php', { method: 'POST' });
                const data = await res.json();
                
                if (data.success) {
                    window.mostrarMensaje('✅ Período Cerrado', data.message, 'success', 3000, () => {
                        window.location.reload();
                    });
                } else {
                    window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
                }
            } catch (error) {
                window.mostrarMensaje('❌ Error', 'Error de conexión', 'error', 5000);
            }
        }
    );
}

// ============================================
// ABRIR PERÍODO CERRADO
// ============================================
async function abrirPeriodo(id, nombre) {
    confirmarAccion(
        '¿Abrir Período?',
        `¿Abrir el período "${nombre}"?\n\nSe cerrará automáticamente cualquier período abierto.`,
        async () => {
            const formData = new FormData();
            formData.append('id_periodo', id);
            
            try {
                const res = await fetch('periodo_abrir.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    window.mostrarMensaje('✅ Período Abierto', data.message, 'success', 3000, () => {
                        window.location.reload();
                    });
                } else {
                    window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
                }
            } catch (error) {
                window.mostrarMensaje('❌ Error', 'Error de conexión', 'error', 5000);
            }
        }
    );
}

// ============================================
// ELIMINAR PERÍODO
// ============================================
async function eliminarPeriodo(id, nombre) {
    confirmarAccion(
        '¿ELIMINAR Período?',
        `⚠️ ¿ELIMINAR el período "${nombre}"?\n\nEsta acción NO se puede deshacer y eliminará:\n\n• El período\n• Todos los contratos asociados\n• Documentos del período\n• LPAs del período\n• Acuerdos de productores\n• Pagos de inscripción\n\n¿Estás completamente seguro?`,
        async () => {
            const formData = new FormData();
            formData.append('id_periodo', id);
            
            try {
                const res = await fetch('periodo_eliminar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    window.mostrarMensaje('✅ Eliminado', data.message, 'success', 3000, () => {
                        window.location.reload();
                    });
                } else {
                    window.mostrarMensaje('❌ Error', data.message, 'error', 5000);
                }
            } catch (error) {
                console.error('Error:', error);
                window.mostrarMensaje('❌ Error', 'Error de conexión', 'error', 5000);
            }
        }
    );
}

// ============================================
// CERRAR MODALES CON ESC Y CLICK FUERA
// ============================================
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>

</body>
</html>