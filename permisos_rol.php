<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";
require_once __DIR__ . "/auditoria_helper.php";

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$usuario_sesion    = $_SESSION['usuario'] ?? 'desconocido';

if (!$id_usuario_sesion || !tienePermiso($pdo, $id_usuario_sesion, 'gestion_usuarios', 'puede_modificar')) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;"><h2>⛔ Acceso denegado</h2><p>Solo el administrador puede gestionar permisos.</p><a href="dashboard.php">← Volver</a></div>');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar'])) {
    $id_rol=(int)$_POST['id_rol'];
    $modulos_ids=$pdo->query("SELECT id_modulo FROM modulos")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM permisos_rol WHERE id_rol=?")->execute([$id_rol]);
        $stmt=$pdo->prepare("INSERT INTO permisos_rol (id_rol,id_modulo,puede_ver,puede_agregar,puede_modificar,puede_eliminar) VALUES (?,?,?,?,?,?)");
        foreach($modulos_ids as $id_mod){
            $stmt->execute([$id_rol,$id_mod,
                isset($_POST["ver_{$id_mod}"])?1:0,
                isset($_POST["agregar_{$id_mod}"])?1:0,
                isset($_POST["modificar_{$id_mod}"])?1:0,
                isset($_POST["eliminar_{$id_mod}"])?1:0]);
        }
        $pdo->commit();
        $nr=$pdo->prepare("SELECT nombre_rol FROM roles WHERE id_rol=?");$nr->execute([$id_rol]);$nr=$nr->fetchColumn();
        registrarLog($pdo,$id_usuario_sesion,$usuario_sesion,'UPDATE','Permisos por Rol',"Actualizó permisos del rol '$nr'");
        $msg="✅ Permisos actualizados correctamente.";
    } catch(Exception $e){ $pdo->rollBack(); $msg="❌ Error: ".$e->getMessage(); }
}

$roles=$pdo->query("SELECT * FROM roles ORDER BY id_rol")->fetchAll(PDO::FETCH_ASSOC);
$id_rol_sel=isset($_GET['rol'])?(int)$_GET['rol']:($roles[0]['id_rol']??1);
$modulos=$pdo->prepare("
    SELECT m.*,
           COALESCE(pr.puede_ver,0) as puede_ver,
           COALESCE(pr.puede_agregar,0) as puede_agregar,
           COALESCE(pr.puede_modificar,0) as puede_modificar,
           COALESCE(pr.puede_eliminar,0) as puede_eliminar
    FROM modulos m
    LEFT JOIN permisos_rol pr ON m.id_modulo=pr.id_modulo AND pr.id_rol=?
    ORDER BY m.orden ASC");
$modulos->execute([$id_rol_sel]);$modulos=$modulos->fetchAll(PDO::FETCH_ASSOC);
$padres=array_filter($modulos,fn($m)=>$m['padre']===null);
$hijos =array_filter($modulos,fn($m)=>$m['padre']!==null);
$nombre_rol_sel='';foreach($roles as $r)if($r['id_rol']==$id_rol_sel)$nombre_rol_sel=$r['nombre_rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><title>Permisos por Rol</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.rol-tabs{display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap}
.rol-tab{padding:10px 20px;border-radius:10px;background:#fff;border:2px solid #e5e7eb;color:#6b7280;text-decoration:none;font-size:.88rem;font-weight:600;display:flex;align-items:center;gap:8px;transition:all .2s;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.rol-tab:hover{border-color:#1e3a8a;color:#1e3a8a}
.rol-tab.activo{background:linear-gradient(135deg,#1e3a8a,#3b82f6);border-color:#1e3a8a;color:#fff;box-shadow:0 4px 12px rgba(30,58,138,.3)}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 24px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:space-between}
.card-header h2{margin:0;font-size:1.05rem;display:flex;align-items:center;gap:8px}
table{width:100%;border-collapse:collapse}
thead{background:#f9fafb}
th{padding:11px 14px;text-align:center;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:700;border-bottom:1px solid #f3f4f6}
th:first-child{text-align:left}
td{padding:10px 14px;border-top:1px solid #f9fafb;vertical-align:middle}
td:not(:first-child){text-align:center}
.fila-padre{background:#f9fafb}
.fila-padre td{font-weight:700;color:#1f3a5f;font-size:.9rem}
.fila-hijo td:first-child{padding-left:36px;color:#374151;font-size:.87rem}
.fila-hijo:hover td{background:#f0f7ff}
.check-wrap{display:flex;justify-content:center}
.check-custom{position:relative;display:inline-block;width:22px;height:22px}
.check-custom input{opacity:0;width:0;height:0}
.check-custom span{position:absolute;inset:0;background:#e5e7eb;border-radius:6px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;border:2px solid transparent}
.check-custom:hover span{border-color:#3b82f6}
.check-custom input:checked+span{background:linear-gradient(135deg,#1e3a8a,#3b82f6);border-color:#1e3a8a}
.check-custom input:checked+span::after{content:'✓';color:#fff;font-size:13px;font-weight:700}
.col-ver      {background:#eff6ff}
.col-agregar  {background:#f0fdf4}
.col-modificar{background:#fffbeb}
.col-eliminar {background:#fef2f2}
.th-ver       {color:#1d4ed8!important}
.th-agregar   {color:#166534!important}
.th-modificar {color:#92400e!important}
.th-eliminar  {color:#dc2626!important}
.acciones-masivas{display:flex;gap:8px;flex-wrap:wrap}
.btn-masa{padding:5px 12px;border:1px solid rgba(255,255,255,.4);border-radius:7px;background:rgba(255,255,255,.15);font-size:.8rem;cursor:pointer;color:#fff;transition:all .2s;font-weight:600}
.btn-masa:hover{background:rgba(255,255,255,.3)}
.btn-guardar{padding:12px 28px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(30,58,138,.3);transition:all .2s}
.btn-guardar:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(30,58,138,.4)}
.alert-ok {background:#dcfce7;color:#166534;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:.9rem;border-left:4px solid #16a34a}
.alert-err{background:#fee2e2;color:#dc2626;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:.9rem;border-left:4px solid #dc2626}
.icono-mod{margin-right:8px;color:#1e3a8a;width:16px;text-align:center}
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

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <i class="fa fa-shield-halved" style="font-size:1.8rem;color:#1e3a8a;"></i>
    <h1 style="margin:0;font-size:1.7rem;color:#1f3a5f;">Permisos por Rol</h1>
  </div>

  <?php if($msg): ?><div class="<?= str_starts_with($msg,'✅')?'alert-ok':'alert-err' ?>"><?= $msg ?></div><?php endif; ?>

  <div class="rol-tabs">
    <?php foreach($roles as $r): ?>
    <a href="?rol=<?= $r['id_rol'] ?>" class="rol-tab <?= $r['id_rol']==$id_rol_sel?'activo':'' ?>">
      <i class="fa fa-shield-halved"></i> <?= htmlspecialchars($r['nombre_rol']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <form method="POST">
    <input type="hidden" name="id_rol" value="<?= $id_rol_sel ?>">
    <input type="hidden" name="guardar" value="1">
    <div class="card">
      <div class="card-header">
        <h2><i class="fa fa-list-check"></i> Permisos para: <strong><?= htmlspecialchars($nombre_rol_sel) ?></strong></h2>
        <div class="acciones-masivas">
          <button type="button" class="btn-masa" onclick="marcarTodo(true)">✅ Todo</button>
          <button type="button" class="btn-masa" onclick="marcarTodo(false)">❌ Ninguno</button>
          <button type="button" class="btn-masa" onclick="soloVer()">👁 Solo ver</button>
        </div>
      </div>
      <table>
        <thead><tr>
          <th style="text-align:left;width:42%;">Módulo</th>
          <th class="col-ver  th-ver"    >👁 Ver</th>
          <th class="col-agregar th-agregar">➕ Agregar</th>
          <th class="col-modificar th-modificar">✏ Modificar</th>
          <th class="col-eliminar th-eliminar">🗑 Eliminar</th>
        </tr></thead>
        <tbody>
        <?php foreach($padres as $padre):
            $hijos_padre=array_filter($hijos,fn($h)=>$h['padre']===$padre['clave']); ?>
          <tr class="fila-padre">
            <td><i class="<?= $padre['icono'] ?> icono-mod"></i><?= htmlspecialchars($padre['nombre']) ?>
            <?php if(!empty($hijos_padre)): ?><span style="color:#9ca3af;font-size:.72rem;margin-left:6px;">(<?= count($hijos_padre) ?> sub)</span><?php endif; ?></td>
            <?php if(empty($hijos_padre)): ?>
              <?php foreach(['ver','agregar','modificar','eliminar'] as $tipo): ?>
              <td class="col-<?= $tipo ?>"><div class="check-wrap"><label class="check-custom">
                <input type="checkbox" name="<?= $tipo ?>_<?= $padre['id_modulo'] ?>" class="chk chk-<?= $tipo ?>" <?= $padre["puede_$tipo"]?'checked':'' ?>>
                <span></span></label></div></td>
              <?php endforeach; ?>
            <?php else: ?>
              <td colspan="4" style="text-align:center;color:#9ca3af;font-size:.78rem;background:#f9fafb;">— configura los submódulos abajo —</td>
            <?php endif; ?>
          </tr>
          <?php foreach($hijos_padre as $hijo): ?>
          <tr class="fila-hijo">
            <td><i class="<?= $hijo['icono'] ?> icono-mod" style="margin-left:10px;"></i><?= htmlspecialchars($hijo['nombre']) ?></td>
            <?php foreach(['ver','agregar','modificar','eliminar'] as $tipo): ?>
            <td class="col-<?= $tipo ?>"><div class="check-wrap"><label class="check-custom">
              <input type="checkbox" name="<?= $tipo ?>_<?= $hijo['id_modulo'] ?>" class="chk chk-<?= $tipo ?>" <?= $hijo["puede_$tipo"]?'checked':'' ?>>
              <span></span></label></div></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <a href="gestion_usuarios.php" style="color:#6b7280;text-decoration:none;font-size:.9rem;display:flex;align-items:center;gap:6px;"><i class="fa fa-arrow-left"></i> Volver</a>
      <button type="submit" class="btn-guardar"><i class="fa fa-floppy-disk"></i> Guardar Permisos</button>
    </div>
  </form>

</section>
</main>
</div>
<script>
function marcarTodo(e){document.querySelectorAll('.chk').forEach(c=>c.checked=e);}
function soloVer(){
    ['agregar','modificar','eliminar'].forEach(t=>document.querySelectorAll('.chk-'+t).forEach(c=>c.checked=false));
    document.querySelectorAll('.chk-ver').forEach(c=>c.checked=true);
}
document.querySelectorAll('.chk-agregar,.chk-modificar,.chk-eliminar').forEach(chk=>{
    chk.addEventListener('change',function(){
        if(this.checked){const v=this.closest('tr').querySelector('.chk-ver');if(v)v.checked=true;}
    });
});
</script>
</body>
</html>