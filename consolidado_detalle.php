<?php
require "layout/bootstrap.php";
include "layout/selector-periodo.php";
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

$id_socio = intval($_GET['id_socio'] ?? 0);

if ($id_socio <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de socio inválido']);
    exit;
}

try {
    $sql = "SELECT 
                id_consolidado_detalle,
                fecha_compra,
                documento,
                numero_documento,
                ticket,
                producto,
                peso_neto_qq,
                peso_neto_kg,
                precio_kg,
                total_usd
            FROM tabla_consolidado_detalle
            WHERE id_socio = :id_socio
            ORDER BY fecha_compra DESC, fecha_registro DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_socio' => $id_socio]);
    
    $registros = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $registros[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'registros' => $registros
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>