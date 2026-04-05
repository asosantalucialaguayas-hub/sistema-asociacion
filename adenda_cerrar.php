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
    $id_periodo = (int)($_POST['id_periodo'] ?? 0);
    
    if (!$id_periodo) {
        throw new Exception('ID de período inválido');
    }
    
    // Verificar que existe una adenda activa
    $adenda = get_adenda_activa($pdo, $id_periodo);
    if (!$adenda) {
        throw new Exception('No hay adenda activa para cerrar');
    }
    
    $pdo->beginTransaction();
    
    // Cerrar adenda activa
    $stmt = $pdo->prepare("
        UPDATE periodo_adendas 
        SET estado = 'CERRADA', fecha_fin = CURDATE() 
        WHERE id_periodo = ? AND estado = 'ACTIVA'
    ");
    $stmt->execute([$id_periodo]);
    
    // Marcar período sin adenda activa
    $stmt = $pdo->prepare("
        UPDATE periodo_comercializacion 
        SET adenda_activa = FALSE, 
            fecha_adenda_fin = CURDATE() 
        WHERE id_periodo = ?
    ");
    $stmt->execute([$id_periodo]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Adenda #{$adenda['numero_adenda']} cerrada exitosamente. Ya no se permiten nuevas inscripciones."
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}