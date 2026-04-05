<?php
/**
 * Consultar estado del período actual (para frontend)
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
    $periodo = get_periodo_abierto($pdo);
    
    if ($periodo) {
        // Obtener estadísticas
        $stats = get_estadisticas_periodo($pdo, $periodo['id_periodo']);
        
        echo json_encode([
            'success' => true,
            'hay_periodo_abierto' => true,
            'periodo' => [
                'id_periodo' => $periodo['id_periodo'],
                'nombre' => $periodo['nombre'],
                'fecha_apertura' => $periodo['fecha_apertura'],
                'fecha_cierre' => $periodo['fecha_cierre'],
                'estado' => 'ABIERTO',
                'estadisticas' => $stats
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'hay_periodo_abierto' => false,
            'periodo' => null,
            'message' => 'No hay período abierto actualmente'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar período'
    ], JSON_UNESCAPED_UNICODE);
}