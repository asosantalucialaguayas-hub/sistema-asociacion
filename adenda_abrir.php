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
    $motivo = trim($_POST['motivo'] ?? 'Ingreso de nuevos socios');
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
    
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
    
    // Verificar si ya hay una adenda activa
    $adenda_existente = get_adenda_activa($pdo, $id_periodo);
    if ($adenda_existente) {
        throw new Exception('Ya existe una adenda activa para este período');
    }
    
    $pdo->beginTransaction();
    
    // Obtener número de adenda
    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as numero FROM periodo_adendas WHERE id_periodo = ?");
    $stmt->execute([$id_periodo]);
    $numero_adenda = $stmt->fetchColumn();
    
    // Marcar período con adenda activa
    $stmt = $pdo->prepare("
        UPDATE periodo_comercializacion 
        SET adenda_activa = TRUE, 
            fecha_adenda_inicio = ? 
        WHERE id_periodo = ?
    ");
    $stmt->execute([$fecha_inicio, $id_periodo]);
    
    // Registrar adenda
    $stmt = $pdo->prepare("
        INSERT INTO periodo_adendas 
        (id_periodo, numero_adenda, fecha_inicio, motivo, estado) 
        VALUES (?, ?, ?, ?, 'ACTIVA')
    ");
    $stmt->execute([$id_periodo, $numero_adenda, $fecha_inicio, $motivo]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Adenda #{$numero_adenda} abierta exitosamente. Ahora se pueden registrar nuevas inscripciones."
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}