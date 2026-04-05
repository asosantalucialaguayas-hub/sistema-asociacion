<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";
require_once __DIR__ . "/auditoria_helper.php";

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$usuario_sesion    = $_SESSION['usuario'] ?? 'desconocido';

if (!$id_usuario_sesion || !tienePermiso($pdo, $id_usuario_sesion, 'gestion_usuarios', 'puede_ver')) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;">
         <h2>⛔ Acceso denegado</h2><p>No tienes permiso para ver esta sección.</p>
         <a href="dashboard.php">← Volver al dashboard</a></div>');
}

$puede_modificar = tienePermiso($pdo, $id_usuario_sesion, 'gestion_usuarios', 'puede_modificar');
registrarLog($pdo, $id_usuario_sesion, $usuario_sesion, 'VIEW', 'Gestión de Usuarios', 'Accedió a la página de gestión de usuarios');

$mensaje = ''; $tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_modificar) {
    $accion_post = $_POST['accion'] ?? '';

    if ($accion_post === 'cambiar_estado') {
        $id_target = (int)$_POST['id_usuario'];
        $nuevo_estado = $_POST['nuevo_estado'];
        if (in_array($nuevo_estado, ['activo', 'inactivo'])) {
            $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?")->execute([$nuevo_estado, $id_target]);
            $s2 = $pdo->prepare("SELECT usuario FROM usuarios WHERE id_usuario = ?");
            $s2->execute([$id_target]); $nombre_target = $s2->fetchColumn();
            registrarLog($pdo, $id_usuario_sesion, $usuario_sesion, 'UPDATE', 'Gestión de Usuarios', "Cambió estado de '$nombre_target' a '$nuevo_estado'");
            $mensaje = "Estado del usuario actualizado correctamente."; $tipo_msg = 'success';
        }
    }

    if ($accion_post === 'asignar_rol') {
        $id_target = (int)$_POST['id_usuario'];
        $id_rol    = (int)$_POST['id_rol'];
        if ($id_target === $id_usuario_sesion && $id_rol !== 1) {
            $mensaje = "⚠ No puedes cambiar tu propio rol de administrador."; $tipo_msg = 'warning';
        } else {
            $pdo->prepare("DELETE FROM usuario_rol WHERE id_usuario = ?")->execute([$id_target]);
            $pdo->prepare("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?, ?)")->execute([$id_target, $id_rol]);
            $s2 = $pdo->prepare("SELECT usuario FROM usuarios WHERE id_usuario = ?"); $s2->execute([$id_target]); $nombre_target = $s2->fetchColumn();
            $s3 = $pdo->prepare("SELECT nombre_rol FROM roles WHERE id_rol = ?"); $s3->execute([$id_rol]); $nombre_rol = $s3->fetchColumn();
            registrarLog($pdo, $id_usuario_sesion, $usuario_sesion, 'UPDATE', 'Gestión de Usuarios', "Asignó rol '$nombre_rol' al usuario '$nombre_target'");
            $mensaje = "Rol asignado correctamente."; $tipo_msg = 'success';
        }
    }
}

$usuarios = $pdo->query("
    SELECT u.id_usuario, u.usuario, u.estado, r.id_rol, r.nombre_rol
    FROM usuarios u
    LEFT JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
    LEFT JOIN roles r ON ur.id_rol = r.id_rol
    ORDER BY u.id_usuario ASC
")->fetchAll(PDO::FETCH_ASSOC);

$roles     = $pdo->query("SELECT * FROM roles ORDER BY id_rol")->fetchAll(PDO::FETCH_ASSOC);
$total     = count($usuarios);
$activos   = count(array_filter($usuarios, fn($u) => $u['estado'] === 'activo'));
$inactivos = $total - $activos;
$sin_rol   = count(array_filter($usuarios, fn($u) => !$u['id_rol']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.page-header{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.page-header h1{font-size:1.7rem;color:#1f3a5f;margin:0}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:28px}
.stat-card{background:#fff;border-radius:12px;padding:20px 22px;box-shadow:0 2px 8px rgba(0,0,0,.07);display:flex;align-items:center;gap:16px;transition:transform .2s}
.stat-card:hover{transform:translateY(-3px)}
.stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff}
.stat-icon.blue  {background:linear-gradient(135deg,#1e3a8a,#3b82f6)}
.stat-icon.green {background:linear-gradient(135deg,#047857,#10b981)}
.stat-icon.red   {background:linear-gradient(135deg,#c2410c,#ef4444)}
.stat-icon.orange{background:linear-gradient(135deg,#d97706,#f59e0b)}
.stat-val{font-size:1.8rem;font-weight:700;color:#1f3a5f;line-height:1}
.stat-lbl{font-size:.8rem;color:#6b7280;margin-top:2px}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px}
.card-header{padding:18px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between}
.card-header h2{margin:0;font-size:1.1rem;color:#1f3a5f;display:flex;align-items:center;gap:8px}
.search-box{position:relative}
.search-box input{padding:8px 12px 8px 34px;border:1px solid #e5e7eb;border-radius:8px;font-size:.88rem;outline:none;width:220px}
.search-box input:focus{border-color:#1e3a8a}
.search-box i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af}
table{width:100%;border-collapse:collapse}
thead{background:#f9fafb}
th{padding:12px 16px;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:600;border-bottom:1px solid #f3f4f6}
td{padding:13px 16px;border-top:1px solid #f9fafb;font-size:.9rem;color:#374151;vertical-align:middle}
tr:hover td{background:#f9fafb}
.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.76rem;font-weight:600}
.badge-activo  {background:#dcfce7;color:#16a34a}
.badge-inactivo{background:#fee2e2;color:#dc2626}
.badge-rol     {background:#e0e7ff;color:#4338ca}
.badge-sinrol  {background:#fef3c7;color:#92400e}
.btn{padding:7px 14px;border:none;border-radius:7px;cursor:pointer;font-size:.83rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:all .2s;text-decoration:none}
.btn-sm{padding:5px 10px;font-size:.78rem}
.btn-success {background:linear-gradient(135deg,#047857,#10b981);color:#fff}
.btn-success:hover{opacity:.9}
.btn-danger  {background:linear-gradient(135deg,#c2410c,#ef4444);color:#fff}
.btn-danger:hover{opacity:.9}
.btn-primary {background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff}
.btn-primary:hover{opacity:.9}
.btn-secondary{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}
.btn-secondary:hover{background:#e5e7eb}
select.rol-select{padding:6px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:.83rem;color:#374151;background:#f9fafb;cursor:pointer}
.alert{padding:14px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:.9rem}
.alert-success{background:#dcfce7;color:#166534;border-left:4px solid #16a34a}
.alert-warning{background:#fef3c7;color:#92400e;border-left:4px solid #f59e0b}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:32px;width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-box h3{margin:0 0 8px;color:#1f3a5f;font-size:1.2rem}
.modal-box p{color:#6b7280;font-size:.9rem;margin-bottom:22px}
.modal-actions{display:flex;gap:10px;justify-content:flex-end}
.avatar{width:36px;height:36px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;flex-shrink:0}
@media(max-width:768px){.stats-row{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<script src="layout/modal-message.js"></script>
<div class="app">
<?php include __DIR__ . "/layout/sidebar.php"; ?>
<main class="content">
<header class="topbar">
    <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></span>
</header>
<section class="page">

  <div class="page-header">
    <i class="fa fa-user-gear" style="font-size:1.8rem;color:#1e3a8a;"></i>
    <h1>Gestión de Usuarios</h1>
  </div>

  <?php if ($mensaje): ?>
  <div class="alert alert-<?= $tipo_msg ?>">
    <i class="fa fa-<?= $tipo_msg==='success'?'circle-check':'triangle-exclamation' ?>"></i>
    <?= htmlspecialchars($mensaje) ?>
  </div>
  <?php endif; ?>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-icon blue"><i class="fa fa-users"></i></div><div><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total Usuarios</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fa fa-circle-check"></i></div><div><div class="stat-val"><?= $activos ?></div><div class="stat-lbl">Activos</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fa fa-ban"></i></div><div><div class="stat-val"><?= $inactivos ?></div><div class="stat-lbl">Inactivos</div></div></div>
    <div class="stat-card"><div class="stat-icon orange"><i class="fa fa-triangle-exclamation"></i></div><div><div class="stat-val"><?= $sin_rol ?></div><div class="stat-lbl">Sin Rol</div></div></div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-list" style="color:#1e3a8a;"></i> Listado de Usuarios</h2>
      <div class="search-box">
        <i class="fa fa-search"></i>
        <input type="text" id="buscarUsuario" placeholder="Buscar usuario..." onkeyup="filtrarTabla()">
      </div>
    </div>
    <table id="tablaUsuarios">
      <thead>
        <tr>
          <th>#</th><th>Usuario</th><th>Estado</th><th>Rol Actual</th>
          <?php if ($puede_modificar): ?><th>Cambiar Rol</th><th>Acciones</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td style="color:#9ca3af;font-size:.85rem;"><?= $u['id_usuario'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="avatar"><?= strtoupper(substr($u['usuario'],0,1)) ?></div>
              <div><strong><?= htmlspecialchars($u['usuario']) ?></strong>
              <?php if ($u['id_usuario']===$id_usuario_sesion): ?><span style="font-size:.7rem;color:#3b82f6;margin-left:4px;">(tú)</span><?php endif; ?></div>
            </div>
          </td>
          <td><span class="badge badge-<?= $u['estado'] ?>"><i class="fa fa-<?= $u['estado']==='activo'?'circle-check':'ban' ?>"></i> <?= ucfirst($u['estado']) ?></span></td>
          <td><?php if ($u['nombre_rol']): ?><span class="badge badge-rol"><i class="fa fa-shield-halved"></i> <?= htmlspecialchars($u['nombre_rol']) ?></span><?php else: ?><span class="badge badge-sinrol"><i class="fa fa-triangle-exclamation"></i> Sin rol</span><?php endif; ?></td>
          <?php if ($puede_modificar): ?>
          <td>
            <form method="POST" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="accion" value="asignar_rol">
              <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
              <select name="id_rol" class="rol-select">
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id_rol'] ?>" <?= $r['id_rol']==$u['id_rol']?'selected':'' ?>><?= htmlspecialchars($r['nombre_rol']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Guardar</button>
            </form>
          </td>
          <td>
            <?php $es_yo=$u['id_usuario']===$id_usuario_sesion; $nuevo=$u['estado']==='activo'?'inactivo':'activo'; ?>
            <?php if (!$es_yo): ?>
            <button class="btn <?= $u['estado']==='activo'?'btn-danger':'btn-success' ?> btn-sm"
              onclick="confirmarCambioEstado(<?= $u['id_usuario'] ?>,'<?= htmlspecialchars($u['usuario']) ?>','<?= $nuevo ?>')">
              <i class="fa fa-<?= $u['estado']==='activo'?'ban':'circle-check' ?>"></i>
              <?= $u['estado']==='activo'?'Dar de baja':'Reactivar' ?>
            </button>
            <?php else: ?><span style="color:#9ca3af;font-size:.8rem;">— tu cuenta —</span><?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="logs_sistema.php" class="btn btn-secondary"><i class="fa fa-clock-rotate-left"></i> Ver Logs</a>
    <a href="permisos_rol.php" class="btn btn-primary"><i class="fa fa-shield-halved"></i> Permisos por Rol</a>
  </div>

</section>
</main>
</div>

<div class="modal-overlay" id="modalEstado">
  <div class="modal-box">
    <h3 id="modalTitulo"></h3>
    <p id="modalTexto"></p>
    <form method="POST">
      <input type="hidden" name="accion" value="cambiar_estado">
      <input type="hidden" name="id_usuario" id="modalIdUsuario">
      <input type="hidden" name="nuevo_estado" id="modalNuevoEstado">
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal()"><i class="fa fa-xmark"></i> Cancelar</button>
        <button type="submit" class="btn" id="modalBtnConfirmar"><i class="fa fa-check"></i> Confirmar</button>
      </div>
    </form>
  </div>
</div>
<script>
function confirmarCambioEstado(id,nombre,nuevoEstado){
    document.getElementById('modalIdUsuario').value=id;
    document.getElementById('modalNuevoEstado').value=nuevoEstado;
    const esBaja=nuevoEstado==='inactivo';
    document.getElementById('modalTitulo').textContent=esBaja?'⚠ Dar de baja usuario':'✅ Reactivar usuario';
    document.getElementById('modalTexto').textContent=esBaja?`¿Dar de baja a "${nombre}"? No podrá iniciar sesión.`:`¿Reactivar el acceso de "${nombre}"?`;
    const btn=document.getElementById('modalBtnConfirmar');
    btn.className='btn '+(esBaja?'btn-danger':'btn-success');
    btn.innerHTML=esBaja?'<i class="fa fa-ban"></i> Dar de baja':'<i class="fa fa-circle-check"></i> Reactivar';
    document.getElementById('modalEstado').classList.add('open');
}
function cerrarModal(){document.getElementById('modalEstado').classList.remove('open');}
function filtrarTabla(){
    const f=document.getElementById('buscarUsuario').value.toLowerCase();
    document.querySelectorAll('#tablaUsuarios tbody tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(f)?'':'none');
}
</script>
</body>
</html>