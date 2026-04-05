<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

require "config/conexion.php";

$id_solicitud = $_POST['id_solicitud'] ?? null;
$estado = $_POST['estado'] ?? null;

// Validar entrada
if (!$id_solicitud || !in_array($estado, ['PENDIENTE', 'APROBADO', 'RECHAZADO'])) {
    die(json_encode(['success' => false, 'message' => 'Parámetros inválidos']));
}

try {
    $sql = "UPDATE solicitud_ingreso SET estado_solicitud = ? WHERE id_solicitud = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$estado, $id_solicitud]);
    
    echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
