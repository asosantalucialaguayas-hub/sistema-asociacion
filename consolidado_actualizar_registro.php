<?php
require "layout/bootstrap.php";
include "layout/selector-periodo.php";
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require "config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    $id_registro = $_POST['id_registro'] ?? null;
    $fecha_compra = $_POST['fecha_compra'] ?? null;
    $documento = $_POST['documento'] ?? null;
    $numero_documento = $_POST['numero_documento'] ?? null;
    $ticket = $_POST['ticket'] ?? null;
    $producto = $_POST['producto'] ?? null;
    $peso_kg = $_POST['peso_neto_kg'] ?? null;
    $peso_qq = $_POST['peso_neto_qq'] ?? null;
    $precio_kg = $_POST['precio_kg'] ?? null;
    $total_usd = $_POST['total_usd'] ?? null;
    
    if (!$id_registro || !$fecha_compra || !$documento || !$numero_documento || 
        !$ticket || !$producto || !$peso_kg || !$precio_kg) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit;
    }
    
    $peso_kg = floatval($peso_kg);
    if ($peso_kg <= 0) {
        echo json_encode(['success' => false, 'message' => 'El peso debe ser mayor a 0']);
        exit;
    }
    
    // Obtener datos del registro para actualizar ventas
    $sqlObtener = "SELECT id_socio, id_lpa, ticket FROM tabla_consolidado_detalle WHERE id_consolidado_detalle = :id";
    $stmtObtener = $pdo->prepare($sqlObtener);
    $stmtObtener->bindValue(':id', $id_registro, PDO::PARAM_INT);
    $stmtObtener->execute();
    $registro = $stmtObtener->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // 1. Actualizar tabla_consolidado_detalle
        $sql = "
            UPDATE tabla_consolidado_detalle SET
                fecha_compra = :fecha_compra,
                documento = :documento,
                numero_documento = :numero_documento,
                ticket = :ticket,
                producto = :producto,
                peso_neto_kg = :peso_kg,
                peso_neto_qq = :peso_qq,
                precio_kg = :precio_kg,
                total_usd = :total_usd
            WHERE id_consolidado_detalle = :id_registro
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_registro', $id_registro, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_compra', $fecha_compra, PDO::PARAM_STR);
        $stmt->bindValue(':documento', $documento, PDO::PARAM_STR);
        $stmt->bindValue(':numero_documento', $numero_documento, PDO::PARAM_STR);
        $stmt->bindValue(':ticket', $ticket, PDO::PARAM_STR);
        $stmt->bindValue(':producto', $producto, PDO::PARAM_STR);
        $stmt->bindValue(':peso_kg', $peso_kg, PDO::PARAM_STR);
        $stmt->bindValue(':peso_qq', $peso_qq, PDO::PARAM_STR);
        $stmt->bindValue(':precio_kg', $precio_kg, PDO::PARAM_STR);
        $stmt->bindValue(':total_usd', $total_usd, PDO::PARAM_STR);
        $stmt->execute();
        
        // 2. Actualizar en tabla_ventas
        $sqlUpdateVenta = "
            UPDATE tabla_ventas SET
                fecha_venta = :fecha_venta,
                cantidad_vende = :cantidad_vende,
                precio_kg = :precio_kg,
                total = :total,
                floid = :floid,
                observacion = :observacion
            WHERE id_lpa = :id_lpa 
            AND observacion LIKE CONCAT('%', :ticket_old, '%')
        ";
        
        $stmtUpdateVenta = $pdo->prepare($sqlUpdateVenta);
        $stmtUpdateVenta->bindValue(':id_lpa', $registro['id_lpa'], PDO::PARAM_INT);
        $stmtUpdateVenta->bindValue(':fecha_venta', $fecha_compra, PDO::PARAM_STR);
        $stmtUpdateVenta->bindValue(':cantidad_vende', $peso_kg, PDO::PARAM_STR);
        $stmtUpdateVenta->bindValue(':precio_kg', $precio_kg, PDO::PARAM_STR);
        $stmtUpdateVenta->bindValue(':total', $total_usd, PDO::PARAM_STR);
        $stmtUpdateVenta->bindValue(':floid', $ticket, PDO::PARAM_STR);
        $stmtUpdateVenta->bindValue(':observacion', "Consolidado: $documento $numero_documento - Ticket: $ticket", PDO::PARAM_STR);
        $stmtUpdateVenta->bindValue(':ticket_old', $registro['ticket'], PDO::PARAM_STR);
        $stmtUpdateVenta->execute();
        
        // Confirmar transacción
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Registro y venta actualizados exitosamente']);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch(PDOException $e) {
    error_log("Error en consolidado_actualizar_registro.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>