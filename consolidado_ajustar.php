<?php
require "layout/bootstrap.php";
include "layout/selector-periodo.php";
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require __DIR__ . "/config/conexion.php";
require_once "helpers/periodo.php"; // 🔒 candado
header('Content-Type: application/json; charset=utf-8');
$periodo = require_periodo_abierto_json($pdo); // 🔒 candado de período

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    $id_consolidado = $_POST['id_consolidado'] ?? null;
    $valor_correcto = $_POST['valor_correcto'] ?? null;
    $observacion = $_POST['observacion'] ?? '';
    
    if (!$id_consolidado || !$valor_correcto) {
        echo json_encode(['success' => false, 'message' => 'Datos requeridos']);
        exit;
    }
    
    $valor_correcto = floatval($valor_correcto);
    if ($valor_correcto < 0) {
        echo json_encode(['success' => false, 'message' => 'El valor no puede ser negativo']);
        exit;
    }
    
    // Actualizar el valor en tabla_consolidado_acopio
    $peso_qq = round($valor_correcto / 45.36, 2);
    
    $sql = "
        UPDATE tabla_consolidado_acopio 
        SET 
            peso_kg = :peso_kg,
            peso_qq = :peso_qq,
            observacion_ajuste = :observacion,
            fecha_ajuste = NOW(),
            usuario_ajuste = :usuario
        WHERE id_consolidado = :id_consolidado
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_consolidado', $id_consolidado, PDO::PARAM_INT);
    $stmt->bindValue(':peso_kg', $valor_correcto, PDO::PARAM_STR);
    $stmt->bindValue(':peso_qq', $peso_qq, PDO::PARAM_STR);
    $stmt->bindValue(':observacion', $observacion, PDO::PARAM_STR);
    $stmt->bindValue(':usuario', $_SESSION['usuario'], PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Ajuste aplicado exitosamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al aplicar ajuste']);
    }
    
} catch(PDOException $e) {
    error_log("Error en consolidado_ajustar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>