<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once 'config/conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Consultar datos principales de LPA
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            s.identificacion,
            s.nombre_completo,
            s.sexo as sexo_socio,
            s.telefono
        FROM tabla_lpa l
        INNER JOIN socios s ON s.id_socio = l.id_socio
        WHERE l.id_lpa = :id
        LIMIT 1
    ");
    
    $stmt->execute([':id' => $id]);
    $lpa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lpa) {
        echo json_encode(['success' => false, 'message' => 'LPA no encontrada']);
        exit;
    }
    
    // Consultar detalle mensual (si existe tabla detalle)
    $stmtDetalle = $pdo->prepare("
        SELECT mes, estimado_mes, volumen_entregado_mes
        FROM tabla_lpa_detalle
        WHERE id_lpa = :id
        ORDER BY 
            FIELD(mes, 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                       'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre')
    ");
    
    $stmtDetalle->execute([':id' => $id]);
    $detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'lpa' => $lpa,
        'detalle' => $detalle
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}