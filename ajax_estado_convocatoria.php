<?php
// ============================================================
// ajax_estado_convocatoria.php
// ============================================================
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol']??'', ['admin','secretario'])) {
    echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$id     = intval($data['id'] ?? 0);
$estado = $data['estado'] ?? '';
$permitidos = ['programada','activa','cerrada','cancelada'];

if (!$id || !in_array($estado, $permitidos)) {
    echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
}

$stmt = $pdo->prepare("UPDATE convocatorias SET estado = ? WHERE id = ?");
$stmt->execute([$estado, $id]);
echo json_encode(['ok'=>true,'msg'=>'Estado actualizado']);
