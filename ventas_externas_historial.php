<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false,'message'=>'No autenticado']); exit; }
require "config/conexion.php";
if (!isset($pdo)) { echo json_encode(['success'=>false,'message'=>'Sin conexión']); exit; }

try {
    $id_socio = $_GET['id_socio'] ?? null;
    $id_lpa   = $_GET['id_lpa']   ?? null;
    if (!$id_socio) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }

    $sql = "SELECT * FROM tabla_ventas_externas
            WHERE id_socio = :id_socio" . ($id_lpa ? " AND id_lpa = :id_lpa" : "") . "
            ORDER BY fecha_venta DESC";

    $st = $pdo->prepare($sql);
    $st->bindValue(':id_socio', $id_socio, PDO::PARAM_INT);
    if ($id_lpa) $st->bindValue(':id_lpa', $id_lpa, PDO::PARAM_INT);
    $st->execute();

    echo json_encode(['success'=>true, 'ventas'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
