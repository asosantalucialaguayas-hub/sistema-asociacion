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
    $id_venta = $_POST['id'] ?? null;
    
    if (!$id_venta) {
        echo json_encode(['success' => false, 'message' => 'ID de venta requerido']);
        exit;
    }
    
    // Obtener la ruta de la factura antes de eliminar
    $sqlFactura = "SELECT factura FROM tabla_ventas WHERE id_venta = :id_venta";
    $stmtFactura = $pdo->prepare($sqlFactura);
    $stmtFactura->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
    $stmtFactura->execute();
    $venta = $stmtFactura->fetch(PDO::FETCH_ASSOC);
    
    // Eliminar el registro
    $sql = "DELETE FROM tabla_ventas WHERE id_venta = :id_venta";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Intentar eliminar el archivo de factura si existe
        if ($venta && $venta['factura']) {
            $rutaFactura = __DIR__ . '/' . $venta['factura'];
            if (file_exists($rutaFactura)) {
                @unlink($rutaFactura);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Venta eliminada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la venta']);
    }
    
} catch(PDOException $e) {
    error_log("Error en ventas_eliminar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
?>