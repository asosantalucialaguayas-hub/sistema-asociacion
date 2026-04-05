<?php
/**
 * Abrir (o reabrir) un período específico
 */
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
    $id_periodo = (int)($_POST['id_periodo'] ?? 0);
    
    if (!$id_periodo) {
        throw new Exception('ID de período inválido');
    }
    
    // Verificar que el período existe
    $periodo = get_periodo_by_id($pdo, $id_periodo);
    
    if (!$periodo) {
        throw new Exception('Período no encontrado');
    }
    
    if ($periodo['estado'] === 'ABIERTO') {
        throw new Exception('El período ya está abierto');
    }
    
    // Abrir el período (el trigger cerrará otros automáticamente)
    if (!abrir_periodo($pdo, $id_periodo)) {
        throw new Exception('Error al abrir el período');
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Período '{$periodo['nombre']}' abierto exitosamente"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}