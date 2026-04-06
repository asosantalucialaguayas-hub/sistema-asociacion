<?php
// ============================================================
// ajax_eliminar_asistencia.php
// ============================================================
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol']??'', ['admin','secretario'])) {
    echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = intval($data['id'] ?? 0);
if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

$stmt = $pdo->prepare("DELETE FROM asistencia WHERE id = ?");
$stmt->execute([$id]);
echo json_encode(['ok'=>$stmt->rowCount()>0,'msg'=>'Eliminado']);
