<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false,'message'=>'No autenticado']); exit; }
require "config/conexion.php";
if (!isset($pdo)) { echo json_encode(['success'=>false,'message'=>'Sin conexión']); exit; }

try {
    $id = $_POST['id'] ?? null;
    if (!$id) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }

    $st = $pdo->prepare("DELETE FROM tabla_ventas_externas WHERE id_venta_externa = :id");
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    echo json_encode(['success'=>true,'message'=>'Eliminado']);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>