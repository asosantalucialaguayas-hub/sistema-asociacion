<?php
require "layout/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    $id_venta = $_GET['id'] ?? null;
    
    if (!$id_venta) {
        echo json_encode(['success' => false, 'message' => 'ID de venta requerido']);
        exit;
    }
    
    $sql = "
        SELECT 
            v.*
        FROM tabla_ventas v
        WHERE v.id_venta = :id_venta
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
    $stmt->execute();
    
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'venta' => $venta
    ]);
    
} catch(PDOException $e) {
    error_log("Error en ventas_detalle.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener detalle']);
}
?>