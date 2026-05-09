<?php
// ============================================================
// ajax_eliminar_asistencia.php
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header('Content-Type:application/json'); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

$rol       = $_SESSION['rol'] ?? $_SESSION['tipo_usuario'] ?? 'viewer';
$id_usr    = (int)($_SESSION['id_usuario']??0);
if ($id_usr && function_exists('tienePermiso') && isset($pdo)) {
    $puede_eliminar = tienePermiso($pdo, $id_usr, 'asistencia', 'puede_eliminar');
} else {
    $puede_eliminar = in_array(strtolower($rol), ['admin','secretario','presidente','superadmin']) || $id_usr===1;
}
if (!$puede_eliminar) { echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit; }

$data = json_decode(file_get_contents('php://input'),true)??[];
$id   = intval($data['id']??0);
if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

$st = $pdo->prepare("DELETE FROM conv_asistencia WHERE id=?");
$st->execute([$id]);
echo json_encode(['ok'=>$st->rowCount()>0,'msg'=>'Eliminado']);