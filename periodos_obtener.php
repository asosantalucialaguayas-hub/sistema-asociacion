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
    // Buscar en la tabla periodo_comercializacion (sistema nuevo)
    $stmt = $pdo->query("
        SELECT 
            id_periodo, 
            nombre, 
            fecha_apertura AS fecha_inicio,
            fecha_cierre AS fecha_fin,
            estado
        FROM periodo_comercializacion
        ORDER BY fecha_apertura DESC
    ");
    
    $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success'  => true,
        'periodos' => $periodos
    ]);

} catch (PDOException $e) {
    error_log("Error al obtener periodos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar periodos']);
}
?>