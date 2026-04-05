<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";

try {
    $id_documento = intval($_POST['id_documento'] ?? 0);
    $id_solicitud = intval($_POST['id_solicitud'] ?? 0);
    
    if ($id_documento <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de documento inválido']);
        exit;
    }
    
    // Obtener información del documento
    $sqlGet = "SELECT ruta_archivo FROM documentos_socios WHERE id_documento = :id_documento";
    $stmtGet = $pdo->prepare($sqlGet);
    $stmtGet->execute([':id_documento' => $id_documento]);
    $doc = $stmtGet->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }
    
    // Eliminar archivo físico si existe
    $rutaArchivo = __DIR__ . '/' . $doc['ruta_archivo'];
    if (file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }
    
    // Eliminar registro de la base de datos
    $sqlDelete = "DELETE FROM documentos_socios WHERE id_documento = :id_documento";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute([':id_documento' => $id_documento]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento eliminado exitosamente'
    ]);
    
} catch (Exception $e) {
    error_log("Error en eliminar_documento.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar: ' . $e->getMessage()
    ]);
}
?>
