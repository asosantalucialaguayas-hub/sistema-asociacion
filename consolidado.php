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
<title>Consolidado de Compras</title>

<link rel="stylesheet" href="/css/dashboard.css">
<link rel="stylesheet" href="/css/style.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">

<?php include 'layout/modals.php'; ?>

<style>
.btn-actions{display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;align-items:center}
.btn-primary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn-secondary{background:#6b7280;color:#fff;padding:8px 16px;border-radius:6px;border:none;font-weight:600;cursor:pointer}
.btn-success{background:#10b981;color:#fff;padding:8px 16px;border-radius:6px;border:none;font-weight:600;cursor:pointer;font-size:13px}
.btn-success:hover{background:#059669}
.btn-icon{width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;color:#fff;display:inline-flex;align-items:center;justify-content:center}

.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:999999;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:12px;padding:25px;position:relative;width:95%;max-height:90vh;overflow:auto;box-shadow:0 25px 60px rgba(0,0,0,.3)}
.close-btn{position:absolute;top:14px;right:14px;width:36px;height:36px;border-radius:50%;border:none;background:#fff;box-shadow:0 6px 16px rgba(0,0,0,.25);cursor:pointer;font-size:18px;z-index:10001}

.data-table{width:100%;border-collapse:collapse;font-size:12px}
.data-table thead th{background:#1f3a5f;color:#fff;padding:10px;text-align:center;position:sticky;top:0;z-index:10}
.data-table td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:center}
.data-table tbody tr:hover{background:#f9fafb}

.input-consolidado{width:100px;padding:6px;border-radius:6px;border:1px solid #d1d5db;text-align:center;font-size:13px}
.diferencia{font-weight:700}
.diferencia.positiva{color:#10b981}
.diferencia.negativa{color:#ef4444}

.panel-detalle{display:none;background:#f9fafb;border:2px solid #1f3a5f;border-radius:8px;padding:20px;margin-top:20px}
.panel-detalle.active{display:block}
.panel-detalle h3{margin:0 0 15px 0;color:#1f3a5f}

.form-group{margin-bottom:15px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:5px}
.form-group input,.form-group select{width:100%;padding:9px 11px;border-radius:8px;border:1px solid #d1d5db;font-size:14px;box-sizing:border-box}

.search-box{display:flex;gap:8px;align-items:center;margin-bottom:15px}
.search-input{flex:1;padding:10px 12px;border-radius:8px;border:1px solid #d1d5db;font-size:14px}
.search-btn{width:40px;height:40px;border-radius:6px;border:none;background:#1f3a5f;color:#fff;cursor:pointer}

.table-container{max-height:500px;overflow:auto}
</style>
</head>

<body>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
<?php include __DIR__."/layout/sidebar.php"; ?>

<main class="content">
<header class="topbar">
    <span>Bienvenido, <?= $_SESSION['usuario'] ?></span>
</header>

<section class="page">
<h1>Consolidado de Compras</h1>

<div class="btn-actions">
    <button class="btn-primary" onclick="exportarConsolidado()">
        <i class="fa fa-file-excel"></i> Exportar Excel
    </button>
</div>

<div class="search-box">
    <input type="text" id="buscador" class="search-input" placeholder="Buscar por cédula o nombre del productor">
    <button class="search-btn" onclick="buscarSocio()">
        <i class="fa fa-search"></i>
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
    <th>Cupo Total (QQ)</th>
    <th>Vendido (QQ)</th>
    <th>AGREGAR (QQ)</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTabla">
    <!-- Cargado por JavaScript -->
</tbody>
</table>
</div>
</div>

<!-- PANEL DETALLE REGISTRO -->
<div id="panelDetalle" class="panel-detalle">
<h3>Registrar Detalle de Compra - <span id="nombreProductorPanel"></span></h3>

<form id="formDetalleConsolidado">
<input type="hidden" id="id_socio_detalle" name="id_socio">
<input type="hidden" id="id_lpa_detalle" name="id_lpa">

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;">
    <div class="form-group">
        <label>Fecha *</label>
        <input type="date" id="fecha_compra" name="fecha_compra" value="<?=date('Y-m-d')?>" required>
    </div>
    
    <div class="form-group">
        <label>Documento *</label>
        <input type="text" id="documento" name="documento" placeholder="COM-FACT-C" required>
    </div>
    
    <div class="form-group">
        <label>Número *</label>
        <input type="text" id="numero_documento" name="numero_documento" placeholder="001100-000000067" required>
    </div>
    
    <div class="form-group">
        <label>Ticket *</label>
        <input type="text" id="ticket" name="ticket" placeholder="12466" required>
    </div>
    
    <div class="form-group" style="grid-column:1/-1">
        <label>Producto *</label>
        <input type="text" id="producto" name="producto" value="CACAO ANSN FAIRTRADE (FISICAMENTE RASTREABLE) EN B" required>
    </div>
    
    <div class="form-group">
        <label>Peso Neto QQ (Quintales a agregar) *</label>
        <input type="number" id="peso_neto_qq" name="peso_neto_qq" step="0.01" placeholder="Ej: 2.5" required>
    </div>
    
    <div class="form-group">
        <label>Precio por KG ($) (opcional)</label>
        <input type="number" id="precio_kg_consolidado" name="precio_kg" step="0.01">
    </div>
    
    <div class="form-group">
        <label>Total USD *</label>
        <input type="number" id="total_usd" name="total_usd" step="0.01" required style="font-weight:700;color:#1f3a5f">
    </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
    <button type="button" class="btn-secondary" onclick="cerrarPanelDetalle()">Cancelar</button>
    <button type="submit" class="btn-success">Guardar Registro</button>
</div>
</form>

</div>

<!-- MODAL VER DETALLE CONSOLIDADO -->
<div id="modalDetalleConsolidado" class="modal-overlay">
<div class="modal-box" style="max-width:1200px">
<button class="close-btn" onclick="cerrarModalDetalle()">×</button>

<h2>Historial de Compras - <span id="nombreProductorModal"></span></h2>

<div id="infoSocioModal" style="margin:0 0 15px 0;background:#f9fafb;padding:12px;border-radius:6px;"></div>

<div style="background:#1f3a5f;color:#fff;padding:15px;border-radius:8px;margin-bottom:20px;display:grid;grid-template-columns:repeat(3,1fr);gap:15px">
    <div style="text-align:center">
        <h4 style="margin:0;font-size:14px">Total KG</h4>
        <p id="totalKgModal" style="margin:5px 0 0 0;font-size:24px;font-weight:700">0.00</p>
    </div>
    <div style="text-align:center">
        <h4 style="margin:0;font-size:14px">Total QQ</h4>
        <p id="totalQQModal" style="margin:5px 0 0 0;font-size:24px;font-weight:700">0.00</p>
    </div>
    <div style="text-align:center">
        <h4 style="margin:0;font-size:14px">Total USD</h4>
        <p id="totalUSDModal" style="margin:5px 0 0 0;font-size:24px;font-weight:700">$0.00</p>
    </div>
</div>

<div style="max-height:400px;overflow:auto">
<table class="data-table">
<thead>
<tr>
    <th>#</th>
    <th>Fecha</th>
    <th>Documento</th>
    <th>Número</th>
    <th>Ticket</th>
    <th>Peso KG</th>
    <th>Peso QQ</th>
    <th>Precio/KG</th>
    <th>Total USD</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody id="tablaDetalleConsolidado">
    <!-- Cargado por JavaScript -->
</tbody>
</table>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
    <button type="button" class="btn-secondary" onclick="cerrarModalDetalle()">Cerrar</button>
</div>

</div>
</div>

<!-- MODAL EDITAR REGISTRO -->
<div id="modalEditarRegistro" class="modal-overlay">
<div class="modal-box">
<button class="close-btn" onclick="cerrarModalEditar()">×</button>

<h2>Editar Registro de Compra</h2>

<form id="formEditarRegistro">
<input type="hidden" id="id_registro_editar" name="id_registro">

<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:15px;">
    <div class="form-group">
        <label>Fecha *</label>
        <input type="date" id="fecha_compra_editar" name="fecha_compra" required>
    </div>
    
    <div class="form-group">
        <label>Documento *</label>
        <input type="text" id="documento_editar" name="documento" required>
    </div>
    
    <div class="form-group">
        <label>Número *</label>
        <input type="text" id="numero_documento_editar" name="numero_documento" required>
    </div>
    
    <div class="form-group">
        <label>Ticket *</label>
        <input type="text" id="ticket_editar" name="ticket" required>
    </div>
    
    <div class="form-group" style="grid-column:1/-1">
        <label>Producto *</label>
        <input type="text" id="producto_editar" name="producto" required>
    </div>
    
    <div class="form-group">
        <label>Peso Neto KG *</label>
        <input type="number" id="peso_neto_kg_editar" name="peso_neto_kg" step="0.01" required oninput="calcularQQEditar()">
    </div>
    
    <div class="form-group">
        <label>Peso Neto QQ</label>
        <input type="number" id="peso_neto_qq_editar" name="peso_neto_qq" step="0.01" readonly style="background:#f3f4f6">
    </div>
    
    <div class="form-group">
        <label>Precio por KG ($) *</label>
        <input type="number" id="precio_kg_editar" name="precio_kg" step="0.01" required oninput="calcularTotalEditar()">
    </div>
    
    <div class="form-group">
        <label>Total USD</label>
        <input type="number" id="total_usd_editar" name="total_usd" step="0.01" readonly style="background:#f3f4f6;font-weight:700">
    </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
    <button type="button" class="btn-secondary" onclick="cerrarModalEditar()">Cancelar</button>
    <button type="submit" class="btn-success">Guardar Cambios</button>
</div>
</form>

</div>
</div>

</section>
</main>
</div>

<script>
let todosLosSocios = [];

window.onload = function() {
    cargarConsolidado();
};

function cargarConsolidado() {
    fetch('consolidado_obtener_real.php')
        .then(r => {
            if (!r.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return r.text();
        })
        .then(text => {
            console.log('Respuesta del servidor:', text);
            const datos = JSON.parse(text);
            if (!datos.success) {
                mostrarMensaje('Error', datos.message || 'Error al cargar datos', 'error');
                return;
            }
            
            todosLosSocios = datos.socios || [];
            renderizarTabla(todosLosSocios);
        })
        .catch(err => {
            console.error(err);
            mostrarMensaje('Error', 'Error al cargar consolidado', 'error');
        });
}

function renderizarTabla(socios) {
    const tbody = document.getElementById('cuerpoTabla');
    tbody.innerHTML = '';

    if (socios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#6b7280">No hay socios registrados</td></tr>';
        return;
    }

    socios.forEach((socio, idx) => {
        const cupoTotalQQ = parseFloat(socio.cupo_total) || 0;
        const vendidoQQ = parseFloat(socio.ventas_diarias) || 0;

        // El input es "AGREGAR (QQ)" - incremento que el usuario quiere agregar
        const faltante = (typeof socio.faltante_agregar !== 'undefined')
            ? (parseFloat(socio.faltante_agregar) || 0)
            : 0;

        tbody.innerHTML += `
            <tr>
                <td>${idx + 1}</td>
                <td>${socio.identificacion || '-'}</td>
                <td style="text-align:left">${socio.nombre_completo || '-'}</td>
                <td style="font-weight:600">${cupoTotalQQ.toFixed(2)}</td>
                <td style="color:#6366f1;font-weight:600">${vendidoQQ.toFixed(2)}</td>

                <td>
                    <input type="number" 
                           class="input-consolidado" 
                           id="acopio_${socio.id_socio}" 
                           value="${faltante.toFixed(2)}" 
                           step="0.01"
                           placeholder="QQ a agregar"
                           onchange="actualizarFaltante(${socio.id_socio})">
                </td>

                <td>
                    <button class="btn-success" onclick='verDetalleConsolidado(${socio.id_socio}, "${socio.identificacion}", "${socio.nombre_completo}")' title="Ver Detalle" style="margin-right:5px">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button class="btn-success" onclick='aplicarConsolidado(${socio.id_socio}, ${socio.id_lpa}, "${socio.identificacion}", "${socio.nombre_completo}")'>
                        <i class="fa fa-check"></i> Aplicar
                    </button>
                </td>
            </tr>
        `;
    });
}

function buscarSocio() {
    const busqueda = document.getElementById('buscador').value.toLowerCase().trim();
    
    if (!busqueda) {
        renderizarTabla(todosLosSocios);
        return;
    }
    
    const filtrados = todosLosSocios.filter(s => 
        (s.identificacion || '').toLowerCase().includes(busqueda) ||
        (s.nombre_completo || '').toLowerCase().includes(busqueda)
    );
    
    renderizarTabla(filtrados);
}

function actualizarFaltante(idSocio) {
    const socio = todosLosSocios.find(s => s.id_socio == idSocio);
    if (!socio) return;

    const input = document.getElementById(`acopio_${idSocio}`);
    const faltante = parseFloat((input?.value || '0').toString().replace(',', '.')) || 0;

    // Guardamos el valor como "faltante a agregar" (en QQ)
    socio.faltante_agregar = faltante;

    const difElement = document.getElementById(`dif_${idSocio}`);
    difElement.textContent = `${faltante >= 0 ? '+' : ''}${faltante.toFixed(2)}`;
    difElement.className = 'diferencia ' + (faltante > 0 ? 'positiva' : (faltante < 0 ? 'negativa' : ''));
}

function aplicarConsolidado(idSocio, idLpa, cedula, nombre) {
    const input = document.getElementById(`acopio_${idSocio}`);

    // ESTE VALOR ES EN QQ (quintales) - lo que el usuario quiere AGREGAR
    const faltanteQQ = parseFloat((input?.value || '0').toString().replace(',', '.')) || 0;

    if (faltanteQQ <= 0) {
        mostrarMensaje('Validación', 'Por favor ingresa el valor a AGREGAR en QQ (debe ser mayor a 0)', 'info');
        return;
    }

    // Abrir panel
    document.getElementById('panelDetalle').classList.add('active');
    document.getElementById('nombreProductorPanel').textContent = nombre;

    document.getElementById('id_socio_detalle').value = idSocio;
    document.getElementById('id_lpa_detalle').value = idLpa;

    // IMPORTANTE: peso_kg_detalle en realidad lleva QQ para compatibilidad con el backend
    // Mostrar el valor QQ en el formulario (editable por el usuario)
    document.getElementById('peso_neto_qq').value = '';
    document.getElementById('precio_kg_consolidado').value = '';
    document.getElementById('total_usd').value = '';

    document.getElementById('panelDetalle').scrollIntoView({ behavior: 'smooth' });
}

function cerrarPanelDetalle() {
    document.getElementById('panelDetalle').classList.remove('active');
    document.getElementById('formDetalleConsolidado').reset();
}

// Nota: eliminada la función de cálculo automático. El usuario ingresa el Total USD manualmente.

function exportarConsolidado() {
    window.open('consolidado_exportar_real.php', '_blank');
}

function cerrarModalDetalle() {
    document.getElementById('modalDetalleConsolidado').classList.remove('active');
}

function cerrarModalEditar() {
    document.getElementById('modalEditarRegistro').classList.remove('active');
}

async function verDetalleConsolidado(idSocio, cedula, nombre) {
    document.getElementById('modalDetalleConsolidado').classList.add('active');
    document.getElementById('nombreProductorModal').textContent = nombre;

    document.getElementById('infoSocioModal').innerHTML = `
        <p style="margin:0"><strong>Cédula:</strong> ${cedula}</p>
        <p style="margin:5px 0 0 0"><strong>Productor/a:</strong> ${nombre}</p>
    `;

    try {
        const res = await fetch(`consolidado_detalle.php?id_socio=${idSocio}`);
        const data = await res.json();

        if (!data.success) {
            mostrarMensaje('Error', data.message || 'Error al cargar detalle', 'error');
            return;
        }

        const registros = data.registros || [];
        const tbody = document.getElementById('tablaDetalleConsolidado');
        tbody.innerHTML = '';

        let totalKg = 0;
        let totalQQ = 0;
        let totalUSD = 0;

        if (registros.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:#6b7280">No hay registros</td></tr>';
        } else {
            registros.forEach((reg, idx) => {
                totalKg += parseFloat(reg.peso_neto_kg) || 0;
                totalQQ += parseFloat(reg.peso_neto_qq) || 0;
                totalUSD += parseFloat(reg.total_usd) || 0;

                tbody.innerHTML += `
                    <tr>
                        <td>${idx + 1}</td>
                        <td>${reg.fecha_compra ? reg.fecha_compra.substring(0,10) : '-'}</td>
                        <td>${reg.documento || '-'}</td>
                        <td>${reg.numero_documento || '-'}</td>
                        <td>${reg.ticket || '-'}</td>
                        <td>${(parseFloat(reg.peso_neto_kg) || 0).toFixed(2)}</td>
                        <td>${(parseFloat(reg.peso_neto_qq) || 0).toFixed(2)}</td>
                        <td>${(parseFloat(reg.precio_kg) || 0).toFixed(2)}</td>
                        <td style="font-weight:700;color:#1f3a5f">${(parseFloat(reg.total_usd) || 0).toFixed(2)}</td>
                        <td>
                            <button class="btn-icon" style="width:30px;height:30px;background:#2563eb" onclick="editarRegistro(${reg.id_consolidado_detalle})" title="Editar">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn-icon" style="width:30px;height:30px;background:#ef4444" onclick="eliminarRegistro(${reg.id_consolidado_detalle})" title="Eliminar">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        document.getElementById('totalKgModal').textContent = totalKg.toFixed(2);
        document.getElementById('totalQQModal').textContent = totalQQ.toFixed(2);
        document.getElementById('totalUSDModal').textContent = '$' + totalUSD.toFixed(2);

    } catch (err) {
        console.error(err);
        mostrarMensaje('Error', 'Error al cargar detalle', 'error');
    }
}

async function editarRegistro(idRegistro) {
    try {
        const res = await fetch(`consolidado_obtener_registro.php?id=${idRegistro}`);
        const data = await res.json();
        
        if (!data.success) {
            mostrarMensaje('Error', data.message || 'Error al cargar registro', 'error');
            return;
        }
        
        const reg = data.registro;
        
        document.getElementById('id_registro_editar').value = reg.id_consolidado_detalle;
        document.getElementById('fecha_compra_editar').value = reg.fecha_compra ? reg.fecha_compra.substring(0,10) : '';
        document.getElementById('documento_editar').value = reg.documento || '';
        document.getElementById('numero_documento_editar').value = reg.numero_documento || '';
        document.getElementById('ticket_editar').value = reg.ticket || '';
        document.getElementById('producto_editar').value = reg.producto || '';
        document.getElementById('peso_neto_kg_editar').value = reg.peso_neto_kg || '';
        document.getElementById('peso_neto_qq_editar').value = reg.peso_neto_qq || '';
        document.getElementById('precio_kg_editar').value = reg.precio_kg || '';
        document.getElementById('total_usd_editar').value = reg.total_usd || '';
        
        document.getElementById('modalEditarRegistro').classList.add('active');
        
    } catch(err) {
        console.error(err);
        mostrarMensaje('Error', 'Error al cargar registro', 'error');
    }
}

// Eliminadas las funciones de cálculo automático en edición: el usuario ingresará los valores.

async function eliminarRegistro(idRegistro) {
    mostrarConfirmacion('Eliminar registro', '¿Está seguro de eliminar este registro? Esta acción no se puede deshacer.', function() {
        (async () => {
            try {
                const res = await fetch('consolidado_eliminar_registro.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + idRegistro
                });
                
                const data = await res.json();
                
                if (data.success) {
                    mostrarMensaje('Éxito', 'Registro eliminado exitosamente', 'success', () => {
                        cerrarModalDetalle();
                        cargarConsolidado();
                    });
                } else {
                    mostrarMensaje('Error', data.message || 'Error al eliminar', 'error');
                }
            } catch(err) {
                console.error(err);
                mostrarMensaje('Error', 'Error al eliminar registro', 'error');
            }
        })();
    });
}

document.getElementById('buscador')?.addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
        e.preventDefault();
        buscarSocio();
    }
});

document.getElementById('formDetalleConsolidado')?.addEventListener('submit', async function(e){
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const res = await fetch('consolidado_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
            mostrarMensaje('Éxito', 'Registro guardado exitosamente', 'success', () => {
                cerrarPanelDetalle();
                cargarConsolidado();
            });
        } else {
            mostrarMensaje('Error', data.message || 'Error al guardar', 'error');
        }
    } catch(err) {
        console.error(err);
        mostrarMensaje('Error', 'Error al guardar registro', 'error');
    }
});
</script>
</body>
</html>