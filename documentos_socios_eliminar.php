<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        exit;
    }

    // Obtener ruta del archivo antes de eliminar
    $stmt = $pdo->prepare("SELECT ruta_archivo FROM documentos_productores WHERE id_documento = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }

    // Eliminar de BD
    $stmt = $pdo->prepare("DELETE FROM documentos_productores WHERE id_documento = ?");
    $stmt->execute([$id]);

    // Eliminar archivo físico
    $rutaFisica = __DIR__ . '/' . $doc['ruta_archivo'];
    if (file_exists($rutaFisica)) {
        @unlink($rutaFisica);
    }

    echo json_encode(['success' => true, 'message' => 'Documento eliminado']);

} catch (PDOException $e) {
    error_log("Error al eliminar documento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
}
?>