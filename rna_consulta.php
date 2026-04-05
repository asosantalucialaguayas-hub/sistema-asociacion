<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";

/* =========================
   LISTADO RNA (BASE)
========================= */
$sql = "
SELECT 
    p.id_persona,
    p.cedula,
    p.nombres,
    p.apellidos,
    p.genero,
    p.fecha_nacimiento
FROM rna_persona p
ORDER BY p.id_persona DESC
";
$lista = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registro RNA</title>

<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.btn-actions{display:flex;gap:10px;margin-bottom:15px}
.btn-primary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
.btn-secondary{background:#1f3a5f;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
.btn-primary:hover{background:#1f405f}
.btn-secondary:hover{background:#16304d}

.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table thead th{background:#1f3a5f;color:#fff;padding:12px}
.data-table td{padding:10px;border-bottom:1px solid #e5e7eb}
.data-table tbody tr:hover{background:#f9fafb}

.btn-icon{width:34px;height:34px;border-radius:6px;border:none;cursor:pointer;background:#2563eb;color:#fff}
.btn-icon:hover{background:#1d4ed8}

/* MODAL */
.modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:12px;box-shadow:0 25px 60px rgba(0,0,0,.25);padding:25px;position:relative}
.modal-xl{max-width:1200px;width:95%;height:85vh;overflow:auto}
.close-btn{position:absolute;top:14px;right:14px;width:36px;height:36px;border-radius:50%;border:none;background:#fff;box-shadow:0 6px 16px rgba(0,0,0,.25);cursor:pointer;font-size:18px;z-index:10001}

.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.form-group label{font-size:13px;font-weight:600;color:#374151}
.form-group input,.form-group select{width:100%;padding:9px 11px;border-radius:8px;border:1px solid #d1d5db;font-size:14px}
.section-title{grid-column:1/-1;background:#f1f5f9;padding:10px;border-radius:8px;font-weight:700;color:#1f3a5f;margin-top:10px}
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
<h1>Registro RNA</h1>

<div class="btn-actions">
    <button class="btn-primary" onclick="limpiarFormularioRNA()">
        <i class="fa fa-plus"></i> Nuevo
    </button>
    <a href="rna_exportar.php">
    <button class="btn-secondary">
        <i class="fa fa-file-excel"></i> Exportar
    </button>
</a>
    <button class="btn-secondary" onclick="abrirModalImportarRNA()">
    <i class="fa fa-upload"></i> Importar
</button>

</div>

<div class="form-card">
<table class="data-table">
<thead>
<tr>
    <th>#</th>
    <th>Cédula</th>
    <th>Nombres</th>
    <th>Género</th>
    <th>Fecha nac.</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach($lista as $i=>$r): ?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= htmlspecialchars($r['cedula']) ?></td>
    <td><?= htmlspecialchars($r['nombres'].' '.$r['apellidos']) ?></td>
    <td><?= $r['genero'] ?></td>
    <td><?= $r['fecha_nacimiento'] ?></td>
    <td>
        <button class="btn-icon" onclick="verRNA(<?= $r['id_persona'] ?>)">
            <i class="fa fa-eye"></i>
        </button>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</section>
</main>
</div>

<!-- MODAL RNA -->
<div id="modalRNA" class="modal-overlay">
<div class="modal-box modal-xl">
<button class="close-btn" onclick="cerrarModalRNA()">×</button>

<h2>Registro Nacional Agropecuario (RNA)</h2>

<form id="formRNA">
<div class="form-grid">

<div class="section-title">Datos básicos</div>
<div class="form-group"><label>Número</label><input name="numero" id="numero"></div>
<div class="form-group"><label>Cédula *</label><input name="cedula" id="cedula"></div>
<div class="form-group"><label>Nombres *</label><input name="nombres" id="nombres"></div>
<div class="form-group"><label>Apellidos *</label><input name="apellidos" id="apellidos"></div>
<div class="form-group"><label>Género</label>
    <select name="genero" id="genero"><option value="">-- Seleccione --</option><option value="M">Masculino</option><option value="F">Femenino</option></select>
</div>
<div class="form-group"><label>Fecha nacimiento</label><input type="date" name="fecha_nacimiento" id="fecha_nacimiento"></div>

<div class="section-title">Contacto y acceso</div>
<div class="form-group"><label>Celular</label><input name="celular" id="celular"></div>
<div class="form-group"><label>Correo electrónico</label><input name="correo" id="correo"></div>
<div class="form-group"><label>Contraseña correo</label>
    <div style="display:flex;gap:8px;align-items:center">
        <input type="password" name="correo_password" id="correo_password" style="flex:1">
        <button type="button" id="toggle_correo" onclick="togglePassword('correo_password','toggle_correo')" class="btn-icon" title="Mostrar/Ocultar">
            <i class="fa fa-eye"></i>
        </button>
    </div>
</div>
<div class="form-group"><label>Usuario RNA</label><input name="usuario_rna" id="usuario_rna"></div>
<div class="form-group"><label>Contraseña RNA</label>
    <div style="display:flex;gap:8px;align-items:center">
        <input type="password" name="contrasena_rna" id="contrasena_rna" style="flex:1">
        <button type="button" id="toggle_rna" onclick="togglePassword('contrasena_rna','toggle_rna')" class="btn-icon" title="Mostrar/Ocultar">
            <i class="fa fa-eye"></i>
        </button>
    </div>
</div>
<div class="form-group"><label>Se registra como</label><input name="registro_como" id="registro_como"></div>
   

<div class="section-title">Ubicación actual</div>
<div class="form-group"><label>Provincia</label><input name="provincia" id="provincia"></div>
<div class="form-group"><label>Cantón</label><input name="canton" id="canton"></div>
<div class="form-group"><label>Parroquia</label><input name="parroquia" id="parroquia"></div>
<div class="form-group"><label>Recinto</label><input name="recinto" id="recinto"></div>
<div class="form-group"><label>Referencia</label><input name="referencia" id="referencia"></div>

<div class="section-title">Datos personales ampliados</div>
<div class="form-group"><label>Instrucción formal</label><input name="instruccion_formal" id="instruccion_formal"></div>
<div class="form-group"><label>Cuantos años</label><input name="anios_educacion" id="anios_educacion"></div>
<div class="form-group"><label>Autoidentificación</label><input name="autoidentificacion" id="autoidentificacion"></div>
<div class="form-group"><label>Nacionalidad</label><input name="nacionalidad" id="nacionalidad"></div>
<div class="form-group"><label>Lugar nacimiento</label><input name="lugar_nacimiento" id="lugar_nacimiento"></div>
<div class="form-group"><label>Situación movilidad</label><input name="situacion_movilidad" id="situacion_movilidad"></div>

<div class="section-title">Predio</div>
<div class="form-group"><label>Nombre del predio</label><input name="nombre_predio" id="nombre_predio"></div>
<div class="form-group"><label>Provincia (Predio)</label><input name="provincia_predio" id="provincia_predio"></div>
<div class="form-group"><label>Cantón (Predio)</label><input name="canton_predio" id="canton_predio"></div>
<div class="form-group"><label>Parroquia (Predio)</label><input name="parroquia_predio" id="parroquia_predio"></div>
<div class="form-group"><label>Recinto (Predio)</label><input name="recinto_predio" id="recinto_predio"></div>
<div class="form-group"><label>Vive en el predio</label><select name="vive_predio" id="vive_predio"><option value="">-- Seleccione --</option><option value="SI">SI</option><option value="NO">NO</option></select></div>
<div class="form-group"><label>Forma de tenencia</label><input name="forma_tenencia" id="forma_tenencia"></div>

<div class="section-title">Georreferenciación</div>
<div class="form-group"><label>X</label><input name="x" id="x"></div>
<div class="form-group"><label>Y</label><input name="y" id="y"></div>
<div class="form-group"><label>Z</label><input name="z" id="z"></div>
<div class="form-group"><label>Has</label><input name="has" id="has"></div>

<div class="section-title">Actividad</div>
<div class="form-group"><label>Principal ingreso</label><input name="principal_ingreso" id="principal_ingreso"></div>
<div class="form-group"><label>Actividad</label><input name="actividad" id="actividad"></div>
<div class="form-group"><label>Rubro</label><input name="rubro" id="rubro"></div>
</div>
</form>
<div class="form-actions">
    <button type="submit" form="formRNA">
        <i class="fa fa-save"></i> Guardar datos
    </button>
</div>

</div>
</div>

<!-- MODAL VER RNA -->
<div id="modalVerRNA" class="modal-overlay">
  <div class="modal-box modal-xl">
    <button class="close-btn" onclick="cerrarModalVerRNA()">×</button>

    <h2>Detalle RNA</h2>

    <form id="formEditarRNA">
      <input type="hidden" name="id_persona" id="ver_id_persona">

      <div class="form-grid">
        <div class="section-title">Datos básicos</div>
        <div class="form-group"><label>Número</label><input name="numero" id="ver_numero" readonly></div>
        <div class="form-group"><label>Cédula</label><input name="cedula" id="ver_cedula"></div>
        <div class="form-group"><label>Nombres</label><input name="nombres" id="ver_nombres"></div>
        <div class="form-group"><label>Apellidos</label><input name="apellidos" id="ver_apellidos"></div>
        <div class="form-group"><label>Género</label><select name="genero" id="ver_genero"><option value="">-- Seleccione --</option><option value="M">Masculino</option><option value="F">Femenino</option></select></div>
        <div class="form-group"><label>Fecha nacimiento</label><input type="date" name="fecha_nacimiento" id="ver_fecha_nacimiento"></div>

        <div class="section-title">Contacto y acceso</div>
        <div class="form-group"><label>Celular</label><input name="celular" id="ver_celular"></div>
        <div class="form-group"><label>Correo electrónico</label><input name="correo" id="ver_correo"></div>
        <div class="form-group"><label>Contraseña correo</label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="password" name="correo_password" id="ver_correo_password" style="flex:1">
                <button type="button" id="toggle_ver_correo" onclick="togglePassword('ver_correo_password','toggle_ver_correo')" class="btn-icon" title="Mostrar/Ocultar">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="form-group"><label>Usuario RNA</label><input name="usuario_rna" id="ver_usuario_rna"></div>
        <div class="form-group"><label>Contraseña RNA</label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="password" name="contrasena_rna" id="ver_contrasena_rna" style="flex:1">
                <button type="button" id="toggle_ver_rna" onclick="togglePassword('ver_contrasena_rna','toggle_ver_rna')" class="btn-icon" title="Mostrar/Ocultar">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="form-group"><label>Se registra como</label><input name="registro_como" id="ver_registro_como"></div>

        <div class="section-title">Ubicación actual</div>
        <div class="form-group"><label>Provincia</label><input name="provincia" id="ver_provincia"></div>
        <div class="form-group"><label>Cantón</label><input name="canton" id="ver_canton"></div>
        <div class="form-group"><label>Parroquia</label><input name="parroquia" id="ver_parroquia"></div>
        <div class="form-group"><label>Recinto</label><input name="recinto" id="ver_recinto"></div>
        <div class="form-group"><label>Referencia</label><input name="referencia" id="ver_referencia"></div>

        <div class="section-title">Datos personales ampliados</div>
        <div class="form-group"><label>Instrucción formal</label><input name="instruccion_formal" id="ver_instruccion_formal"></div>
        <div class="form-group"><label>Cuantos años</label><input name="anios_educacion" id="ver_anios_educacion"></div>
        <div class="form-group"><label>Autoidentificación</label><input name="autoidentificacion" id="ver_autoidentificacion"></div>
        <div class="form-group"><label>Nacionalidad</label><input name="nacionalidad" id="ver_nacionalidad"></div>
        <div class="form-group"><label>Lugar nacimiento</label><input name="lugar_nacimiento" id="ver_lugar_nacimiento"></div>
        <div class="form-group"><label>Situación movilidad</label><input name="situacion_movilidad" id="ver_situacion_movilidad"></div>
        <div class="form-group"><label>Estado completitud</label><input name="estado_completitud" id="ver_estado_completitud" readonly></div>
        <div class="form-group"><label>Fecha registro</label><input name="fecha_registro" id="ver_fecha_registro" readonly></div>

        <div class="section-title">Predio</div>
        <div class="form-group"><label>Nombre del predio</label><input name="nombre_predio" id="ver_nombre_predio"></div>
        <div class="form-group"><label>Provincia (Predio)</label><input name="provincia_predio" id="ver_provincia_predio"></div>
        <div class="form-group"><label>Cantón (Predio)</label><input name="canton_predio" id="ver_canton_predio"></div>
        <div class="form-group"><label>Parroquia (Predio)</label><input name="parroquia_predio" id="ver_parroquia_predio"></div>
        <div class="form-group"><label>Recinto (Predio)</label><input name="recinto_predio" id="ver_recinto_predio"></div>
        <div class="form-group"><label>Nombre del predio</label><input name="nombre_predio2" id="ver_nombre_predio2"></div>
        <div class="form-group"><label>Vive en el predio</label><select name="vive_predio" id="ver_vive_predio"><option value="">-- Seleccione --</option><option value="SI">SI</option><option value="NO">NO</option></select></div>
        <div class="form-group"><label>Forma de tenencia</label><input name="forma_tenencia" id="ver_forma_tenencia"></div>

        <div class="section-title">Georreferenciación</div>
        <div class="form-group"><label>X</label><input name="x" id="ver_x"></div>
        <div class="form-group"><label>Y</label><input name="y" id="ver_y"></div>
        <div class="form-group"><label>Z</label><input name="z" id="ver_z"></div>
        <div class="form-group"><label>Has</label><input name="has" id="ver_has"></div>

        <div class="section-title">Actividad</div>
        <div class="form-group"><label>Principal ingreso</label><input name="principal_ingreso" id="ver_principal_ingreso"></div>
        <div class="form-group"><label>Actividad</label><input name="actividad" id="ver_actividad"></div>
        <div class="form-group"><label>Rubro</label><input name="rubro" id="ver_rubro"></div>
      </div>

      <div class="form-actions" style="margin-top:20px;display:flex;gap:12px">
        <button type="submit" class="btn-primary">
          <i class="fa fa-save"></i> Guardar cambios
        </button>

        <button type="button" class="btn-secondary" onclick="eliminarRNA()">
          <i class="fa fa-trash"></i> Eliminar
        </button>
      </div>
    </form>
  </div>
</div>
<!-- MODAL IMPORTAR RNA -->
<div id="modalImportarRNA" class="modal-overlay">
    <div class="modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3>📊 Importar datos RNA desde Excel</h3>
            <button onclick="cerrarModalImportarRNA()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">&times;</button>
        </div>

        <div class="modal-requisitos">
            <p><strong>📋 Requisitos del Excel:</strong></p>
            <p>
                • Primera fila = nombres de columnas<br>
                • Campo <strong>cedula</strong> es obligatorio<br>
                • Formato: .xlsx o .xls<br>
                • Los demás campos son opcionales
            </p>
        </div>

        <div style="margin:20px 0;">
            <label for="archivoExcelRNA" style="display:block; margin-bottom:10px; font-weight:600; color:#1f3a5f;">Seleccionar archivo:</label>
            <input type="file" id="archivoExcelRNA" accept=".xlsx,.xls" />
        </div>

        <div id="fileInfo" style="margin:10px 0; font-size:12px; color:#666; min-height:20px;"></div>

        <div class="modal-buttons">
            <button class="btn-secondary" onclick="cerrarModalImportarRNA()">Cancelar</button>
            <button class="btn-primary" id="btnImportarRNA" onclick="importarRNA()">Importar</button>
        </div>
    </div>
</div>

<script>
function abrirModalRNA(){document.getElementById('modalRNA').classList.add('active')}
function cerrarModalRNA(){document.getElementById('modalRNA').classList.remove('active')}
function limpiarFormularioRNA(){
    document.getElementById('formRNA').reset();
    abrirModalRNA();
}
</script>

<script>
document.getElementById('formRNA').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch('rna_guardar.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            mostrarMensaje('Éxito', 'RNA guardado correctamente', 'success', () => location.reload());
        } else {
            mostrarMensaje('Error', data.message || 'Error al guardar RNA', 'error');
            console.error(data.error);
        }
    } catch (error) {
        console.error(error);
        mostrarMensaje('Error', 'Error de conexión', 'error');
    }
});
</script>

<!-- eliminarRNA: eliminado viejo; función unificada está al final del archivo -->
<script>
async function verRNA(id) {
    try {
        const response = await fetch(`rna_ver.php?id=${id}`);
        const data = await response.json();

        if (!data.success) {
            console.error('rna_ver response:', data);
            mostrarMensaje('Error', (data.message || 'No se pudo cargar la información') + (data.error ? ' Detalles: ' + data.error : ''), 'error');
            return;
        }

        /* PERSONA */
        document.getElementById('ver_id_persona').value = data.persona.id_persona || '';
        document.getElementById('ver_numero').value = data.persona.id_persona || '';
        document.getElementById('ver_cedula').value = data.persona.cedula || '';
        document.getElementById('ver_nombres').value = data.persona.nombres || '';
        document.getElementById('ver_apellidos').value = data.persona.apellidos || '';
        document.getElementById('ver_genero').value = data.persona.genero || '';
        document.getElementById('ver_fecha_nacimiento').value = data.persona.fecha_nacimiento || '';
        document.getElementById('ver_celular').value = data.persona.celular || '';
        document.getElementById('ver_correo').value = data.persona.correo || '';
        document.getElementById('ver_correo_password').value = data.persona.contrasena_correo || '';
        document.getElementById('ver_registro_como').value = data.persona.se_registra_como || '';
        document.getElementById('ver_nacionalidad').value = data.persona.nacionalidad || '';
        document.getElementById('ver_autoidentificacion').value = data.persona.autoidentificacion || '';
        document.getElementById('ver_instruccion_formal').value = data.persona.instruccion_formal || '';
        document.getElementById('ver_anios_educacion').value = data.persona.anios_educacion || '';
        document.getElementById('ver_lugar_nacimiento').value = data.persona.lugar_nacimiento || '';
        document.getElementById('ver_situacion_movilidad').value = data.persona.situacion_movilidad || '';
        document.getElementById('ver_estado_completitud').value = data.persona.estado_completitud || '';
        document.getElementById('ver_fecha_registro').value = data.usuario.fecha_registro || '';

        /* Usuario RNA */
        if (data.usuario) {
            document.getElementById('ver_usuario_rna').value = data.usuario.usuario_rna || '';
            document.getElementById('ver_contrasena_rna').value = data.usuario.contrasena_rna || '';
        } else {
            document.getElementById('ver_usuario_rna').value = '';
            document.getElementById('ver_contrasena_rna').value = '';
        }

        /* DOMICILIO */
        if (data.domicilio) {
            document.getElementById('ver_provincia').value = data.domicilio.provincia || '';
            document.getElementById('ver_canton').value = data.domicilio.canton || '';
            document.getElementById('ver_parroquia').value = data.domicilio.parroquia || '';
            document.getElementById('ver_recinto').value = data.domicilio.recinto || '';
            document.getElementById('ver_referencia').value = data.domicilio.referencia || '';
        }

        /* PREDIO */
        if (data.predio) {
            document.getElementById('ver_nombre_predio').value = data.predio.nombre_predio || '';
            document.getElementById('ver_nombre_predio2').value = data.predio.nombre_predio || '';
            document.getElementById('ver_provincia_predio').value = data.predio.provincia || '';
            document.getElementById('ver_canton_predio').value = data.predio.canton || '';
            document.getElementById('ver_parroquia_predio').value = data.predio.parroquia || '';
            document.getElementById('ver_recinto_predio').value = data.predio.recinto || '';
            document.getElementById('ver_vive_predio').value = data.predio.vive_en_predio || '';
            document.getElementById('ver_forma_tenencia').value = data.predio.forma_tenencia || '';
        }

        /* GEO */
        if (data.geo) {
            document.getElementById('ver_x').value = data.geo.x || '';
            document.getElementById('ver_y').value = data.geo.y || '';
            document.getElementById('ver_z').value = data.geo.z || '';
        }

        /* ACTIVIDAD */
        if (data.actividad) {
            document.getElementById('ver_principal_ingreso').value = data.actividad.principal_ingreso || '';
            document.getElementById('ver_actividad').value = data.actividad.actividad || '';
            document.getElementById('ver_rubro').value = data.actividad.rubro || '';
        }

        /* Mostrar area_has desde predio (se guarda en rna_predio) */
        if (data.predio) {
            document.getElementById('ver_has').value = data.predio.area_has || '';
        }

        document.getElementById('modalVerRNA').classList.add('active');

    } catch (error) {
        console.error(error);
        mostrarMensaje('Error', 'Error al obtener datos del RNA', 'error');
    }
}

function cerrarModalVerRNA(){
    document.getElementById('modalVerRNA').classList.remove('active');
}

/* ENVIAR CAMBIOS DE EDICIÓN */
document.getElementById('formEditarRNA').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('rna_actualizar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarMensaje('Éxito', 'RNA actualizado correctamente', 'success', () => location.reload());
        } else {
            console.error('rna_actualizar response:', data);
            mostrarMensaje('Error', (data.message || 'Error al actualizar') + (data.error ? ' Detalles: ' + data.error : ''), 'error');
        }
    } catch (error) {
        console.error(error);
        mostrarMensaje('Error', 'Error al actualizar RNA', 'error');
    }
});
</script>

<script>
function togglePassword(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const btn = document.getElementById(iconId);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        if (btn) btn.querySelector('i').classList.remove('fa-eye'), btn.querySelector('i').classList.add('fa-eye-slash');
    } else {
        inp.type = 'password';
        if (btn) btn.querySelector('i').classList.remove('fa-eye-slash'), btn.querySelector('i').classList.add('fa-eye');
    }
}
</script>
<script>
function eliminarRNA(idPersona) {
    const id = typeof idPersona !== 'undefined' && idPersona ? idPersona : (document.getElementById('ver_id_persona') ? document.getElementById('ver_id_persona').value : null);
    if (!id) {
        mostrarMensaje('Advertencia', 'ID no especificado', 'info');
        return;
    }

    mostrarConfirmacion('Eliminar registro', '¿Seguro que deseas eliminar este registro?', function() {
        fetch('rna_eliminar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarMensaje('Éxito', 'Registro eliminado correctamente', 'success', () => location.reload());
            } else {
                mostrarMensaje('Error', data.message || 'No se pudo eliminar', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            mostrarMensaje('Error', 'Error de conexión', 'error');
        });
    });
}
</script>
<script>
function abrirModalImportarRNA() {
    document.getElementById('modalImportarRNA').classList.add('active');
}

function cerrarModalImportarRNA() {
    document.getElementById('modalImportarRNA').classList.remove('active');
}

function importarRNA() {
    const fileInput = document.getElementById('archivoExcelRNA');
    const btnImportar = document.getElementById('btnImportarRNA');
    
    if (!fileInput.files.length) {
        mostrarMensaje('Advertencia', '⚠️ Seleccione un archivo Excel (.xlsx o .xls)', 'info');
        return;
    }

    const file = fileInput.files[0];
    if (!file.name.match(/\.(xlsx|xls)$/i)) {
        mostrarMensaje('Advertencia', '⚠️ El archivo debe ser Excel (.xlsx o .xls)', 'info');
        return;
    }

    // Mostrar cargando
    btnImportar.disabled = true;
    btnImportar.textContent = '⏳ Importando...';

    const formData = new FormData();
    formData.append('archivo', file);

    fetch('rna_importar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error HTTP ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            mostrarMensaje('Éxito', '✅ ' + data.message, 'success', () => location.reload());
            cerrarModalImportarRNA();
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarMensaje('Error', '❌ Error: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        mostrarMensaje('Error', '❌ Error al importar: ' + err.message, 'error');
    })
    .finally(() => {
        // Restaurar botón
        btnImportar.disabled = false;
        btnImportar.textContent = 'Importar';
    });
}

// Mostrar nombre del archivo seleccionado
document.getElementById('archivoExcelRNA')?.addEventListener('change', function() {
    const fileInfo = document.getElementById('fileInfo');
    if (this.files.length) {
        fileInfo.innerHTML = '📄 <strong>' + this.files[0].name + '</strong> (' + (this.files[0].size / 1024).toFixed(2) + ' KB)';
    }
});
</script>

</body>
</html>
