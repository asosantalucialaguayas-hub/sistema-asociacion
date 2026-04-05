<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once "config/conexion.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $id_periodo = (int)($_GET['id_periodo'] ?? 0);
    
    if (!$id_periodo) {
        throw new Exception('ID de período inválido');
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM contrato_periodo_documento 
        WHERE id_periodo = ? 
        ORDER BY subido_en DESC
    ");
    
    $stmt->execute([$id_periodo]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'documentos' => $documentos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}