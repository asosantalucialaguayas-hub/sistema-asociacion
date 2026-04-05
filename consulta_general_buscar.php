<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

try {
    $q = $_GET['q'] ?? '';
    
    if (empty($q)) {
        echo json_encode(['success' => false, 'message' => 'Ingrese texto']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT 
            id_socio,
            identificacion,
            COALESCE(nombre_completo, CONCAT(nombres, ' ', apellidos)) AS nombre_completo,
            telefono,
            sexo,
            estado
        FROM socios
        WHERE (identificacion LIKE ? OR nombre_completo LIKE ? OR CONCAT(nombres, ' ', apellidos) LIKE ?)
        ORDER BY COALESCE(nombre_completo, CONCAT(nombres, ' ', apellidos)) ASC
        LIMIT 50
    ");
    
    $search = '%' . $q . '%';
    $stmt->execute([$search, $search, $search]);
    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'socios'  => $socios
    ]);

} catch (PDOException $e) {
    error_log("Error buscar: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>