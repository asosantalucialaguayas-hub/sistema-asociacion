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
    $id_doc = (int)($_POST['id_doc'] ?? 0);
    
    if (!$id_doc) {
        throw new Exception('ID de documento inválido');
    }
    
    // Obtener info del documento
    $stmt = $pdo->prepare("SELECT * FROM contrato_periodo_documento WHERE id_doc = ?");
    $stmt->execute([$id_doc]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        throw new Exception('Documento no encontrado');
    }
    
    // Eliminar archivo físico
    $ruta_archivo = __DIR__ . '/' . $doc['archivo_ruta'];
    if (file_exists($ruta_archivo)) {
        unlink($ruta_archivo);
    }
    
    // Eliminar registro de BD
    $stmt = $pdo->prepare("DELETE FROM contrato_periodo_documento WHERE id_doc = ?");
    $stmt->execute([$id_doc]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento eliminado exitosamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

