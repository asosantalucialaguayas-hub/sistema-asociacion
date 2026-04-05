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

try {
    $id_registro = intval($_POST['id'] ?? 0);
    
    if ($id_registro <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de registro inválido']);
        exit;
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // 1. OBTENER datos del registro a eliminar
    // Usamos peso_neto_kg que es la columna correcta
    $sqlGet = "SELECT id_socio, id_lpa, peso_neto_kg 
               FROM tabla_consolidado_detalle 
               WHERE id_consolidado_detalle = :id_registro";
    
    $stmtGet = $pdo->prepare($sqlGet);
    $stmtGet->execute([':id_registro' => $id_registro]);
    $registro = $stmtGet->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        throw new Exception("Registro no encontrado");
    }
    
    $id_socio = $registro['id_socio'];
    $id_lpa = $registro['id_lpa'];
    $peso_kg = floatval($registro['peso_neto_kg']);
    
    // 2. RESTAR de tabla_ventas (revertir el incremento)
    // Usamos solo cantidad_kg (campo canónico)
    $sqlUpdate = "UPDATE tabla_ventas 
                  SET cantidad_kg = cantidad_kg - :peso_kg
                  WHERE id_socio = :id_socio AND id_lpa = :id_lpa";
    
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':peso_kg' => $peso_kg,
        ':id_socio' => $id_socio,
        ':id_lpa' => $id_lpa
    ]);
    
    // 3. Verificar que no queden valores negativos
    $sqlCheck = "SELECT cantidad_kg
                 FROM tabla_ventas 
                 WHERE id_socio = :id_socio AND id_lpa = :id_lpa";

    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([':id_socio' => $id_socio, ':id_lpa' => $id_lpa]);
    $ventaActual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($ventaActual) {
        if ($ventaActual['cantidad_kg'] < 0) {
        }
        
        // Si queda en 0, eliminar el registro de ventas
        if ($ventaActual['cantidad_kg'] == 0) {
            $sqlDeleteVenta = "DELETE FROM tabla_ventas 
                              WHERE id_socio = :id_socio AND id_lpa = :id_lpa";
            $stmtDelVenta = $pdo->prepare($sqlDeleteVenta);
            $stmtDelVenta->execute([':id_socio' => $id_socio, ':id_lpa' => $id_lpa]);
        }
    }
    
    // 4. ELIMINAR el registro de consolidado_detalle
    $sqlDelete = "DELETE FROM tabla_consolidado_detalle 
                  WHERE id_consolidado_detalle = :id_registro";
    
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute([':id_registro' => $id_registro]);
    
    // Confirmar transacción
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Registro eliminado correctamente'
    ]);
    
} catch (Exception $e) {
    // Revertir en caso de error
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>