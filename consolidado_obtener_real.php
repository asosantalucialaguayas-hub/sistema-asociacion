<?php
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
    // Consulta adaptada a TU estructura real de BD
    $sql = "SELECT 
                s.id_socio,
                s.identificacion,
                -- Usar la columna nombre_completo existente en la tabla socios
                COALESCE(s.nombre_completo, CONCAT(COALESCE(s.nombres, ''), ' ', COALESCE(s.apellidos, ''))) as nombre_completo,
                l.id_lpa,
                -- Convertir volumen (KG) a QQ para mostrar Cupo Total en QQ
                (IFNULL(l.volumen_produccion_estimado, 0) / 45.36) as cupo_total_qq,

                -- Ventas diarias ACUMULADAS en QQ: sumar cantidad_kg (en KG) y convertir a QQ
                COALESCE(
                    (SELECT SUM(v.cantidad_kg)
                     FROM tabla_ventas v
                     WHERE v.id_socio = s.id_socio 
                       AND v.id_lpa = l.id_lpa) / 45.36, 
                    0
                ) as ventas_diarias_qq
            FROM socios s
            INNER JOIN tabla_lpa l ON s.id_socio = l.id_socio
            WHERE s.estado = 1
            ORDER BY s.nombre_completo";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt;
    
    $socios = [];
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $ventasDiariasQQ = floatval($row['ventas_diarias_qq']);
        $cupoTotalQQ = floatval($row['cupo_total_qq']);
        
        // El "faltante" inicial es 0 (el usuario ingresará lo que quiera agregar)
        $faltanteAgregar = 0;
        
        $socios[] = [
            'id_socio' => $row['id_socio'],
            'identificacion' => $row['identificacion'],
            'nombre_completo' => $row['nombre_completo'],
            'id_lpa' => $row['id_lpa'],
            
            // TODO EN QUINTALES
            'cupo_total' => $cupoTotalQQ,
            'ventas_diarias' => $ventasDiariasQQ,
            
            // Faltante a agregar (incremento) - inicialmente 0
            'faltante_agregar' => $faltanteAgregar
        ];
    }
    
    echo json_encode([
        'success' => true,
        'socios' => $socios,
        'total' => count($socios)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>