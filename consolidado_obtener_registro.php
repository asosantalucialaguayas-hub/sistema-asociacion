<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once "config/conexion.php";

// Verificar conexión
if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $sql = "SELECT * FROM tabla_consolidado_detalle WHERE id_consolidado_detalle = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'registro' => $registro
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>