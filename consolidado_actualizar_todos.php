<?php
require "layout/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $mes = $input['mes'] ?? null;
    $anio = $input['anio'] ?? null;
    
    if (!$mes || !$anio) {
        echo json_encode(['success' => false, 'message' => 'Mes y año requeridos']);
        exit;
    }
    
    // Obtener todos los registros del consolidado que tienen diferencia
    $sqlObtener = "
        SELECT 
            ca.id_consolidado,
            ca.id_socio,
            ca.peso_kg AS acopio_kg,
            l.id_lpa,
            IFNULL(
                (SELECT SUM(v.cantidad_vende)
                 FROM tabla_ventas v
                 WHERE v.id_lpa = l.id_lpa
                 AND MONTH(v.fecha_venta) = :mes
                 AND YEAR(v.fecha_venta) = :anio
                ), 0
            ) AS ventas_kg
        FROM tabla_consolidado_acopio ca
        INNER JOIN tabla_lpa l ON ca.id_socio = l.id_socio
        WHERE ca.mes = :mes2
        AND ca.anio = :anio2
        AND l.estado_lpa = 'ACTIVO'
    ";
    
    $stmtObtener = $pdo->prepare($sqlObtener);
    $stmtObtener->bindValue(':mes', $mes, PDO::PARAM_STR);
    $stmtObtener->bindValue(':anio', $anio, PDO::PARAM_STR);
    $stmtObtener->bindValue(':mes2', $mes, PDO::PARAM_STR);
    $stmtObtener->bindValue(':anio2', $anio, PDO::PARAM_STR);
    $stmtObtener->execute();
    
    $registros = $stmtObtener->fetchAll(PDO::FETCH_ASSOC);
    $actualizados = 0;
    
    foreach ($registros as $reg) {
        $diferencia = $reg['acopio_kg'] - $reg['ventas_kg'];
        
        // Solo actualizar si hay diferencia significativa (> 0.5 kg)
        if (abs($diferencia) > 0.5) {
            // Crear una venta de ajuste
            $sqlInsertVenta = "
                INSERT INTO tabla_ventas (
                    id_socio,
                    id_lpa,
                    fecha_venta,
                    cantidad_vende,
                    precio_kg,
                    total,
                    floid,
                    sucursal,
                    observacion,
                    fecha_registro
                ) VALUES (
                    :id_socio,
                    :id_lpa,
                    LAST_DAY(CONCAT(:anio, '-', :mes, '-01')),
                    :cantidad,
                    0,
                    0,
                    'AJUSTE-AUTO',
                    'Ajuste Consolidado',
                    'Ajuste automático por diferencia con informe de acopio',
                    NOW()
                )
            ";
            
            $stmtInsert = $pdo->prepare($sqlInsertVenta);
            $stmtInsert->bindValue(':id_socio', $reg['id_socio'], PDO::PARAM_INT);
            $stmtInsert->bindValue(':id_lpa', $reg['id_lpa'], PDO::PARAM_INT);
            $stmtInsert->bindValue(':anio', $anio, PDO::PARAM_STR);
            $stmtInsert->bindValue(':mes', $mes, PDO::PARAM_STR);
            $stmtInsert->bindValue(':cantidad', abs($diferencia), PDO::PARAM_STR);
            $stmtInsert->execute();
            
            $actualizados++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'actualizados' => $actualizados,
        'message' => "Se actualizaron $actualizados registros"
    ]);
    
} catch(PDOException $e) {
    error_log("Error en consolidado_actualizar_todos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>