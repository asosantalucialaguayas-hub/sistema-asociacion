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
    $id_socio = $_GET['id_socio'] ?? null;
    
    if (!$id_socio) {
        echo json_encode(['success' => false, 'message' => 'ID de socio requerido']);
        exit;
    }

    // Obtener documentos de la tabla documentos_productores
    $stmt = $pdo->prepare("
        SELECT 
            d.id_documento,
            d.tipo_documento,
            d.nombre_archivo,
            d.ruta_archivo,
            d.tamano_archivo,
            d.observacion,
            d.fecha_carga,
            p.nombre AS periodo
        FROM documentos_productores d
        LEFT JOIN periodo_comercializacion p ON d.id_periodo = p.id_periodo
        WHERE d.id_socio = ?
        ORDER BY d.fecha_carga DESC
    ");
    $stmt->execute([$id_socio]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $total = count($documentos);
    $acuerdos = 0;
    foreach ($documentos as $doc) {
        if (stripos($doc['tipo_documento'], 'acuerdo') !== false) {
            $acuerdos++;
        }
    }
    $otros = $total - $acuerdos;
    
    echo json_encode([
        'success'    => true,
        'documentos' => $documentos,
        'stats'      => [
            'total'    => $total,
            'acuerdos' => $acuerdos,
            'otros'    => $otros
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error al listar documentos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar documentos']);
}
?>