<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    die('No autenticado');
}

require __DIR__ . "/config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    die('Error de conexión');
}

try {
    $mes = $_GET['mes'] ?? null;
    $anio = $_GET['anio'] ?? date('Y');
    
    if (!$mes) {
        die('Mes requerido');
    }
    
    $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    
    $nombreMes = $meses[$mes] ?? $mes;
    
    // Query igual que consolidado_obtener.php
    $sql = "
        SELECT 
            s.identificacion AS 'Cédula',
            s.nombre_completo AS 'Productor/a',
            
            IFNULL(
                (SELECT SUM(v.cantidad_vende)
                 FROM tabla_ventas v
                 INNER JOIN tabla_lpa l2 ON v.id_lpa = l2.id_lpa
                 WHERE l2.id_socio = s.id_socio
                 AND MONTH(v.fecha_venta) = :mes
                 AND YEAR(v.fecha_venta) = :anio
                ), 0
            ) AS 'Ventas Diarias (Kg)',
            
            IFNULL(
                (SELECT ca.peso_kg
                 FROM tabla_consolidado_acopio ca
                 WHERE ca.id_socio = s.id_socio
                 AND ca.mes = :mes2
                 AND ca.anio = :anio2
                ), 0
            ) AS 'Informe Acopio (Kg)'
            
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
    
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Headers para Excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Consolidado_' . $nombreMes . '_' . $anio . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    
    echo "<table border='1'>";
    echo "<tr><td colspan='8' style='text-align:center;font-size:18px;font-weight:bold;background-color:#1f3a5f;color:#fff'>CONSOLIDADO DE COMPRAS - $nombreMes $anio</td></tr>";
    echo "<tr><td colspan='8' style='text-align:center'>Generado: " . date('d/m/Y H:i:s') . "</td></tr>";
    echo "<tr><td colspan='8'></td></tr>";
    
    if (!empty($datos)) {
        echo "<tr style='background-color:#1f3a5f;color:#fff;font-weight:bold'>";
        echo "<td>Cédula</td>";
        echo "<td>Productor/a</td>";
        echo "<td>Ventas Diarias (Kg)</td>";
        echo "<td>Ventas Diarias (QQ)</td>";
        echo "<td>Informe Acopio (Kg)</td>";
        echo "<td>Informe Acopio (QQ)</td>";
        echo "<td>Diferencia (Kg)</td>";
        echo "<td>Diferencia (QQ)</td>";
        echo "</tr>";
        
        $totalVentasKg = 0;
        $totalAcopioKg = 0;
        
        foreach ($datos as $row) {
            $ventasKg = floatval($row['Ventas Diarias (Kg)']);
            $acopioKg = floatval($row['Informe Acopio (Kg)']);
            $ventasQQ = round($ventasKg / 45.36, 2);
            $acopioQQ = round($acopioKg / 45.36, 2);
            $difKg = $acopioKg - $ventasKg;
            $difQQ = $acopioQQ - $ventasQQ;
            
            $totalVentasKg += $ventasKg;
            $totalAcopioKg += $acopioKg;
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Cédula']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Productor/a']) . "</td>";
            echo "<td>" . number_format($ventasKg, 2) . "</td>";
            echo "<td>" . number_format($ventasQQ, 2) . "</td>";
            echo "<td>" . number_format($acopioKg, 2) . "</td>";
            echo "<td>" . number_format($acopioQQ, 2) . "</td>";
            echo "<td style='color:" . ($difKg >= 0 ? 'green' : 'red') . "'>" . number_format($difKg, 2) . "</td>";
            echo "<td style='color:" . ($difQQ >= 0 ? 'green' : 'red') . "'>" . number_format($difQQ, 2) . "</td>";
            echo "</tr>";
        }
        
        $totalVentasQQ = round($totalVentasKg / 45.36, 2);
        $totalAcopioQQ = round($totalAcopioKg / 45.36, 2);
        $totalDifKg = $totalAcopioKg - $totalVentasKg;
        $totalDifQQ = $totalAcopioQQ - $totalVentasQQ;
        
        echo "<tr style='background-color:#f3f4f6;font-weight:bold'>";
        echo "<td colspan='2'>TOTALES</td>";
        echo "<td>" . number_format($totalVentasKg, 2) . "</td>";
        echo "<td>" . number_format($totalVentasQQ, 2) . "</td>";
        echo "<td>" . number_format($totalAcopioKg, 2) . "</td>";
        echo "<td>" . number_format($totalAcopioQQ, 2) . "</td>";
        echo "<td>" . number_format($totalDifKg, 2) . "</td>";
        echo "<td>" . number_format($totalDifQQ, 2) . "</td>";
        echo "</tr>";
    } else {
        echo "<tr><td colspan='8' style='text-align:center'>No hay datos</td></tr>";
    }
    
    echo "</table>";
    
} catch(PDOException $e) {
    error_log("Error en consolidado_exportar.php: " . $e->getMessage());
    die('Error al exportar');
}
?>