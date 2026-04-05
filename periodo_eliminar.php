<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once "config/conexion.php";
require_once "helpers/periodo.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $id_periodo = (int)($_POST['id_periodo'] ?? 0);
    
    if (!$id_periodo) {
        throw new Exception('ID de período inválido');
    }
    
    // Verificar que el período existe
    $stmt = $pdo->prepare("SELECT * FROM periodo_comercializacion WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    $periodo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$periodo) {
        throw new Exception('Período no encontrado');
    }
    
    // 🔥 INICIAR TRANSACCIÓN
    $pdo->beginTransaction();
    
    // 1. Eliminar documentos de contratos
    $stmt = $pdo->prepare("
        DELETE FROM contrato_periodo_documento 
        WHERE id_contrato IN (
            SELECT id_contrato 
            FROM contrato_periodo 
            WHERE id_periodo = ?
        )
    ");
    $stmt->execute([$id_periodo]);
    
    // 2. Eliminar contratos del período
    $stmt = $pdo->prepare("DELETE FROM contrato_periodo WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    
    // 3. Eliminar LPAs asociadas
    $stmt = $pdo->prepare("DELETE FROM tabla_lpa WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    
    // 4. Eliminar acuerdos de productores
    $stmt = $pdo->prepare("DELETE FROM acuerdo_productor WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    
    // 5. Eliminar pagos de inscripción
    $stmt = $pdo->prepare("DELETE FROM pago_inscripcion WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    
    // 6. Finalmente, eliminar el período
    $stmt = $pdo->prepare("DELETE FROM periodo_comercializacion WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    
    // 🔥 CONFIRMAR TRANSACCIÓN
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Período '{$periodo['nombre']}' y todos sus datos asociados fueron eliminados"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Revertir cambios si hay error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}