<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header("Location: /auth/login.php"); exit; }
require __DIR__ . "/layout/bootstrap.php";
require_once __DIR__ . "/auditoria_helper.php";

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$usuario_sesion    = $_SESSION['usuario'] ?? 'desconocido';

if (!$id_usuario_sesion || !tienePermiso($pdo, $id_usuario_sesion, 'logs_sistema', 'puede_ver')) {
    die('<div style="text-align:center;margin-top:80px;font-family:sans-serif;"><h2>⛔ Acceso denegado</h2><a href="dashboard.php">← Volver</a></div>');
}
registrarLog($pdo, $id_usuario_sesion, $usuario_sesion, 'VIEW', 'Logs del Sistema', 'Consultó los logs de auditoría');

$f_usuario = trim($_GET['f_usuario'] ?? '');
$f_accion  = trim($_GET['f_accion']  ?? '');
$f_modulo  = trim($_GET['f_modulo']  ?? '');
$f_desde   = $_GET['f_desde'] ?? date('Y-m-01');
$f_hasta   = $_GET['f_hasta'] ?? date('Y-m-d');
$pagina    = max(1,(int)($_GET['pagina'] ?? 1));
$por_pagina = 30;
$offset = ($pagina-1)*$por_pagina;

$where=['fecha BETWEEN :desde AND :hasta'];
$params=[':desde'=>$f_desde.' 00:00:00',':hasta'=>$f_hasta.' 23:59:59'];
if($f_usuario){$where[]='usuario LIKE :usuario';$params[':usuario']="%$f_usuario%";}
if($f_accion) {$where[]='accion = :accion';    $params[':accion']=$f_accion;}
if($f_modulo) {$where[]='modulo LIKE :modulo'; $params[':modulo']="%$f_modulo%";}
$whereSQL=implode(' AND ',$where);

$stmtC=$pdo->prepare("SELECT COUNT(*) FROM auditoria_logs WHERE $whereSQL");
$stmtC->execute($params); $total_logs=$stmtC->fetchColumn();
$total_pag=ceil($total_logs/$por_pagina);

$stmtL=$pdo->prepare("SELECT * FROM auditoria_logs WHERE $whereSQL ORDER BY fecha DESC LIMIT $por_pagina OFFSET $offset");
$stmtL->execute($params); $logs=$stmtL->fetchAll(PDO::FETCH_ASSOC);

$acciones=$pdo->query("SELECT DISTINCT accion FROM auditoria_logs ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
$colores=['LOGIN' =>['bg'=>'#dcfce7','color'=>'#166534','icon'=>'fa-right-to-bracket'],
          'LOGOUT'=>['bg'=>'#f1f5f9','color'=>'#475569','icon'=>'fa-right-from-bracket'],
          'INSERT'=>['bg'=>'#dbeafe','color'=>'#1d4ed8','icon'=>'fa-plus-circle'],
          'UPDATE'=>['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-pen-to-square'],
          'DELETE'=>['bg'=>'#fee2e2','color'=>'#dc2626','icon'=>'fa-trash'],
          'VIEW'  =>['bg'=>'#f0fdf4','color'=>'#15803d','icon'=>'fa-eye']];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><title>Logs del Sistema</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/modal-message.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.filtros-card{background:#fff;border-radius:12px;padding:20px 24px;margin-bottom:22px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.filtros-card h3{margin:0 0 14px;font-size:.85rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.filtros-grid{display:grid;grid-template-columns:repeat(5,1fr) auto;gap:12px;align-items:end}
.filtro-group label{display:block;font-size:.75rem;color:#6b7280;margin-bottom:4px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.filtro-group input,.filtro-group select{width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:.87rem;color:#374151;background:#f9fafb;outline:none;box-sizing:border-box}
.filtro-group input:focus,.filtro-group select:focus{border-color:#1e3a8a}
.btn-filtrar{padding:9px 18px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;border:none;border-radius:8px;font-size:.87rem;cursor:pointer;font-weight:600;white-space:nowrap;display:inline-flex;align-items:center;gap:6px}
.btn-limpiar{padding:9px 12px;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-size:.87rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between}
.card-header h2{margin:0;font-size:1.1rem;color:#1f3a5f;display:flex;align-items:center;gap:8px}
.total-badge{background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600}
table{width:100%;border-collapse:collapse}
thead{background:#f9fafb}
th{padding:11px 14px;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;font-weight:600;border-bottom:1px solid #f3f4f6}
td{padding:11px 14px;border-top:1px solid #f9fafb;font-size:.86rem;color:#374151;vertical-align:middle}
tr:hover td{background:#f9fafb}
.accion-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.73rem;font-weight:700}
.user-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#1e3a8a,#3b82f6);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.75rem;margin-right:7px;vertical-align:middle}
.desc-cell{max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6b7280}
.ip-cell{font-family:monospace;font-size:.78rem;color:#9ca3af}
.paginacion{display:flex;justify-content:center;align-items:center;gap:6px;padding:18px}
.pag-btn{padding:6px 12px;border:1px solid #e5e7eb;border-radius:7px;background:#fff;color:#1e3a8a;cursor:pointer;font-size:.85rem;text-decoration:none;transition:all .2s}
.pag-btn:hover,.pag-btn.activo{background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;border-color:#1e3a8a}
.pag-info{color:#6b7280;font-size:.85rem}
.vacio{text-align:center;padding:60px;color:#9ca3af}
.vacio i{font-size:3rem;display:block;margin-bottom:12px;color:#e5e7eb}
@media(max-width:900px){.filtros-grid{grid-template-columns:repeat(2,1fr)}}
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
    <i class="fa fa-clock-rotate-left" style="font-size:1.8rem;color:#1e3a8a;"></i>
    <h1 style="margin:0;font-size:1.7rem;color:#1f3a5f;">Logs del Sistema</h1>
  </div>

  <div class="filtros-card">
    <h3><i class="fa fa-filter"></i> Filtros de búsqueda</h3>
    <form method="GET">
      <div class="filtros-grid">
        <div class="filtro-group"><label>Usuario</label><input type="text" name="f_usuario" value="<?= htmlspecialchars($f_usuario) ?>" placeholder="Buscar..."></div>
        <div class="filtro-group"><label>Acción</label>
          <select name="f_accion"><option value="">Todas</option>
          <?php foreach($acciones as $a): ?><option value="<?= $a ?>" <?= $a===$f_accion?'selected':'' ?>><?= $a ?></option><?php endforeach; ?>
          </select></div>
        <div class="filtro-group"><label>Módulo</label><input type="text" name="f_modulo" value="<?= htmlspecialchars($f_modulo) ?>" placeholder="Nombre..."></div>
        <div class="filtro-group"><label>Desde</label><input type="date" name="f_desde" value="<?= $f_desde ?>"></div>
        <div class="filtro-group"><label>Hasta</label><input type="date" name="f_hasta" value="<?= $f_hasta ?>"></div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn-filtrar"><i class="fa fa-search"></i> Filtrar</button>
          <a href="logs_sistema.php" class="btn-limpiar"><i class="fa fa-broom"></i></a>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-list" style="color:#1e3a8a;"></i> Registros de Actividad</h2>
      <span class="total-badge"><?= number_format($total_logs) ?> registros</span>
    </div>
    <?php if(empty($logs)): ?>
    <div class="vacio"><i class="fa fa-inbox"></i>No se encontraron registros con esos filtros.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Fecha / Hora</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Descripción</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach($logs as $log): $c=$colores[$log['accion']]??['bg'=>'#f3f4f6','color'=>'#374151','icon'=>'fa-circle']; ?>
        <tr>
          <td style="color:#9ca3af;font-size:.78rem;"><?= $log['id_log'] ?></td>
          <td style="white-space:nowrap;"><strong style="color:#1f3a5f;"><?= date('d/m/Y',strtotime($log['fecha'])) ?></strong><br><span style="color:#6b7280;font-size:.78rem;"><?= date('H:i:s',strtotime($log['fecha'])) ?></span></td>
          <td><span class="user-avatar"><?= strtoupper(substr($log['usuario'],0,1)) ?></span><?= htmlspecialchars($log['usuario']) ?></td>
          <td><span class="accion-badge" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;"><i class="fa <?= $c['icon'] ?>"></i> <?= $log['accion'] ?></span></td>
          <td style="font-size:.83rem;"><?= htmlspecialchars($log['modulo']) ?></td>
          <td class="desc-cell" title="<?= htmlspecialchars($log['descripcion']) ?>"><?= htmlspecialchars($log['descripcion']) ?></td>
          <td class="ip-cell"><?= htmlspecialchars($log['ip']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if($total_pag>1): ?>
    <div class="paginacion">
      <?php
        $qs=http_build_query(array_merge($_GET,['pagina'=>max(1,$pagina-1)]));
        echo $pagina>1?"<a href='?$qs' class='pag-btn'>← Anterior</a>":"<span class='pag-btn' style='opacity:.4'>← Anterior</span>";
        for($i=max(1,$pagina-2);$i<=min($total_pag,$pagina+2);$i++){
            $qs=http_build_query(array_merge($_GET,['pagina'=>$i]));
            echo "<a href='?$qs' class='pag-btn".($i===$pagina?' activo':'')."'>$i</a>";
        }
        $qs=http_build_query(array_merge($_GET,['pagina'=>min($total_pag,$pagina+1)]));
        echo $pagina<$total_pag?"<a href='?$qs' class='pag-btn'>Siguiente →</a>":"<span class='pag-btn' style='opacity:.4'>Siguiente →</span>";
      ?>
      <span class="pag-info">Página <?= $pagina ?> de <?= $total_pag ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <div><a href="gestion_usuarios.php" style="color:#1e3a8a;text-decoration:none;font-size:.9rem;"><i class="fa fa-arrow-left"></i> Volver a Gestión de Usuarios</a></div>

</section>
</main>
</div>
</body>
</html>