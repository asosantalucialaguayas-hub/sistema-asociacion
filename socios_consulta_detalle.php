<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'no_session']);
    exit;
}
require "config/conexion.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id_socio,
            s.identificacion,
            s.nombre_completo,
            s.sexo,
            s.telefono,
            s.fecha_nacimiento,
            s.fecha_ingreso,
            s.direccion,
            s.correo,
            a.provincia as zona,
            COALESCE(a.sector, a.parroquia, '') as comunidad_grupo
        FROM socios s
        LEFT JOIN acuerdo_productor a ON a.cedula = s.identificacion
        WHERE s.id_socio = :id
        LIMIT 1
    ");
    
    $stmt->execute([':id' => $id]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($socio) {
        echo json_encode($socio);
    } else {
        echo json_encode(['error' => 'Socio no encontrado']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
}