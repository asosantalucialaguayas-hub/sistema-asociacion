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
    $q = $_GET['q'] ?? '';
    
    if (empty($q)) {
        echo json_encode(['success' => false, 'message' => 'Ingrese texto de búsqueda']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_socio, identificacion, nombre_completo, estado
        FROM socios
        WHERE (identificacion LIKE ? OR nombre_completo LIKE ?)
        ORDER BY nombre_completo ASC
        LIMIT 10
    ");
    
    $search = '%' . $q . '%';
    $stmt->execute([$search, $search]);
    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'socios'  => $socios
    ]);

} catch (PDOException $e) {
    error_log("Error en buscar socios: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al buscar']);
}
?>