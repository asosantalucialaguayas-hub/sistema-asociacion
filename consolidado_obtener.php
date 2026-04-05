<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require "config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    // Consolidado debe compararse contra el MISMO periodo del informe del acopio
    $mes  = date('m');
    $anio = date('Y');

    $sql = "
        SELECT 
            s.id_socio,
            s.identificacion,
            s.nombre_completo,
            l.id_lpa,

            IFNULL(l.volumen_produccion_estimado, 0) AS cupo_total,

            -- Ventas del mismo mes/año
            IFNULL(
                (SELECT SUM(v.cantidad_vende)
                 FROM tabla_ventas v
                 WHERE v.id_lpa = l.id_lpa
                 AND MONTH(v.fecha_venta) = :mes
                 AND YEAR(v.fecha_venta) = :anio
                ), 0
            ) AS ventas_diarias,

            -- Informe del acopio (tabla_consolidado_acopio)
            IFNULL(
                (SELECT ca.peso_kg
                 FROM tabla_consolidado_acopio ca
                 WHERE ca.id_socio = s.id_socio
                 AND ca.mes = :mes2
                 AND ca.anio = :anio2
                 ORDER BY ca.id_consolidado DESC
                 LIMIT 1
                ), 0
            ) AS consolidado_acopio

        FROM socios s
        INNER JOIN tabla_lpa l ON s.id_socio = l.id_socio
        WHERE l.estado_lpa = 'ACTIVO'
        ORDER BY s.nombre_completo ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':mes', $mes, PDO::PARAM_STR);
    $stmt->bindValue(':anio', $anio, PDO::PARAM_STR);
    $stmt->bindValue(':mes2', $mes, PDO::PARAM_STR);
    $stmt->bindValue(':anio2', $anio, PDO::PARAM_STR);
    $stmt->execute();

    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'mes' => $mes,
        'anio' => $anio,
        'socios' => $socios
    ]);

} catch(PDOException $e) {
    error_log("Error en consolidado_obtener_real.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener datos']);
}
?>
