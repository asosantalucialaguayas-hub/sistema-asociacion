<?php
/**
 * Crear y abrir un nuevo período de comercialización
 */
session_start();

// Validar sesión
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once "config/conexion.php";
require_once "helpers/periodo.php";

header('Content-Type: application/json; charset=utf-8');

try {
    // Recibir datos
    $nombre = trim($_POST['nombre'] ?? '');
    $fecha_apertura = trim($_POST['fecha_apertura'] ?? '');
    
    // Validaciones
    if (empty($nombre)) {
        throw new Exception('El nombre del período es obligatorio');
    }
    
    if (empty($fecha_apertura)) {
        throw new Exception('La fecha de apertura es obligatoria');
    }
    
    // Verificar si ya existe un período con ese nombre
    $stmt = $pdo->prepare("SELECT id_periodo FROM periodo_comercializacion WHERE nombre = ?");
    $stmt->execute([$nombre]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un período con ese nombre');
    }
    
    // 🔥 INICIAR TRANSACCIÓN
    $pdo->beginTransaction();
    
    // 🔒 CERRAR TODOS LOS PERÍODOS ABIERTOS (garantiza solo uno abierto)
    $stmt = $pdo->prepare("
        UPDATE periodo_comercializacion 
        SET estado = 'CERRADO', 
            fecha_cierre = CURDATE() 
        WHERE estado = 'ABIERTO'
    ");
    $stmt->execute();
    
    // ✅ INSERTAR NUEVO PERÍODO COMO ABIERTO
    $stmt = $pdo->prepare("
        INSERT INTO periodo_comercializacion 
        (nombre, fecha_apertura, estado) 
        VALUES (?, ?, 'ABIERTO')
    ");
    
    $stmt->execute([$nombre, $fecha_apertura]);
    
    $id_nuevo = $pdo->lastInsertId();
    
    // 🔥 CONFIRMAR TRANSACCIÓN
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Período '$nombre' creado y abierto exitosamente",
        'data' => [
            'id_periodo' => $id_nuevo,
            'nombre' => $nombre
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Revertir si hay error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}