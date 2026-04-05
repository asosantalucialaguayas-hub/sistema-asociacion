<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once "config/conexion.php";
require_once "helpers/periodo.php";

header('Content-Type: application/json; charset=utf-8');

try {
    // Obtener período abierto
    $periodo = get_periodo_abierto($pdo);
    
    if (!$periodo) {
        throw new Exception('No hay ningún período abierto para cerrar');
    }
    
    // Cerrar el período (y cualquier adenda activa)
    if (!cerrar_periodo_actual($pdo)) {
        throw new Exception('Error al cerrar el período');
    }
    
    // Cerrar adendas activas si existen
    $stmt = $pdo->prepare("
        UPDATE periodo_adendas 
        SET estado = 'CERRADA', fecha_fin = CURDATE() 
        WHERE id_periodo = ? AND estado = 'ACTIVA'
    ");
    $stmt->execute([$periodo['id_periodo']]);
    
    echo json_encode([
        'success' => true,
        'message' => "Período '{$periodo['nombre']}' cerrado exitosamente. Las ventas, LPAs y documentos seguirán funcionando normalmente."
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}