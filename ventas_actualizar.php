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
    $id_venta = $_POST['id_venta'] ?? null;
    $fecha_venta = $_POST['fecha_venta'] ?? null;
    $cantidad_vende = $_POST['cantidad_vende'] ?? null;
    $precio_kg = $_POST['precio_kg'] ?? null;
    $total = $_POST['total'] ?? null;
    $floid = $_POST['floid'] ?? null;
    $sucursal = $_POST['sucursal'] ?? null;
    $observacion = $_POST['observacion'] ?? '';
    
    if (!$id_venta || !$fecha_venta || !$cantidad_vende || !$precio_kg || !$floid || !$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit;
    }
    
    $cantidad_vende = floatval($cantidad_vende);
    if ($cantidad_vende <= 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad debe ser mayor a 0']);
        exit;
    }
    
    $sucursales_validas = ['El Empalme', 'Buena Fe', 'Quinsaloma (Matriz)'];
    if (!in_array($sucursal, $sucursales_validas)) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no válida']);
        exit;
    }
    
    $sql = "
        UPDATE tabla_ventas SET
            fecha_venta = :fecha_venta,
            cantidad_vende = :cantidad_vende,
            precio_kg = :precio_kg,
            total = :total,
            floid = :floid,
            sucursal = :sucursal,
            observacion = :observacion
        WHERE id_venta = :id_venta
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_venta', $id_venta, PDO::PARAM_INT);
    $stmt->bindValue(':fecha_venta', $fecha_venta, PDO::PARAM_STR);
    $stmt->bindValue(':cantidad_vende', $cantidad_vende, PDO::PARAM_STR);
    $stmt->bindValue(':precio_kg', $precio_kg, PDO::PARAM_STR);
    $stmt->bindValue(':total', $total, PDO::PARAM_STR);
    $stmt->bindValue(':floid', $floid, PDO::PARAM_STR);
    $stmt->bindValue(':sucursal', $sucursal, PDO::PARAM_STR);
    $stmt->bindValue(':observacion', $observacion, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Venta actualizada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la venta']);
    }
    
} catch(PDOException $e) {
    error_log("Error en ventas_actualizar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>