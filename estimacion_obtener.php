<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode([]);
    exit;
}

require_once 'config/conexion.php';

try {
    $stmt = $pdo->query("
        SELECT 
            l.*,
            s.identificacion,
            s.nombre_completo,
            s.nombres,
            s.apellidos,
            s.sexo,
            s.telefono
        FROM tabla_lpa l
        INNER JOIN socios s ON s.id_socio = l.id_socio
        WHERE l.estado_lpa = 'activo'
        ORDER BY l.zona, l.comunidad_grupo, s.nombre_completo
    ");
    
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($datos);
    
} catch (Exception $e) {
    echo json_encode([]);
}