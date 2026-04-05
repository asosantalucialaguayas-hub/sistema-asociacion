<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    die('No autenticado');
}

require "config/conexion.php";

if (!isset($pdo) || $pdo === null) {
    die('Error de conexión');
}

try {
    // Query para obtener TODO el consolidado con detalles
    $sql = "
        SELECT 
            s.identificacion AS 'Cédula',
            s.nombre_completo AS 'Productor/a',
            cd.fecha_compra AS 'Fecha',
            cd.documento AS 'Documento',
            cd.numero_documento AS 'Número',
            cd.ticket AS 'Ticket',
            cd.producto AS 'Producto',
            cd.peso_neto_kg AS 'Peso Neto KG',
            cd.peso_neto_qq AS 'Peso Neto QQ',
            cd.precio_kg AS 'Precio/KG',
            cd.total_usd AS 'Total USD'
        FROM tabla_consolidado_detalle cd
        INNER JOIN socios s ON cd.id_socio = s.id_socio
        ORDER BY cd.fecha_compra DESC, s.nombre_completo ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Headers para Excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Consolidado_Compras_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    
    echo "<table border='1'>";
    echo "<tr><td colspan='11' style='text-align:center;font-size:18px;font-weight:bold;background-color:#1f3a5f;color:#fff'>INFORME DE COMPRAS DE CACAO</td></tr>";
    echo "<tr><td colspan='11' style='text-align:center'>Kilogramos y Quintales</td></tr>";
    echo "<tr><td colspan='11' style='text-align:center'>Generado: " . date('d/m/Y H:i:s') . "</td></tr>";
    echo "<tr><td colspan='11'></td></tr>";
    
    if (!empty($datos)) {
        // Encabezados
        echo "<tr style='background-color:#1f3a5f;color:#fff;font-weight:bold'>";
        foreach (array_keys($datos[0]) as $header) {
            echo "<td>" . htmlspecialchars($header) . "</td>";
        }
        echo "</tr>";
        
        // Datos
        $totalKg = 0;
        $totalQQ = 0;
        $totalUSD = 0;
        
        foreach ($datos as $row) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                if ($key === 'Peso Neto KG') {
                    $totalKg += floatval($value);
                    echo "<td>" . number_format(floatval($value), 2) . "</td>";
                } elseif ($key === 'Peso Neto QQ') {
                    $totalQQ += floatval($value);
                    echo "<td>" . number_format(floatval($value), 2) . "</td>";
                } elseif ($key === 'Total USD' || $key === 'Precio/KG') {
                    if ($key === 'Total USD') $totalUSD += floatval($value);
                    echo "<td>" . number_format(floatval($value), 2) . "</td>";
                } else {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
            }
            echo "</tr>";
        }
        
        // Totales
        echo "<tr style='background-color:#f3f4f6;font-weight:bold'>";
        echo "<td colspan='7' style='text-align:right'>TOTALES</td>";
        echo "<td>" . number_format($totalKg, 2) . "</td>";
        echo "<td>" . number_format($totalQQ, 2) . "</td>";
        echo "<td></td>";
        echo "<td>" . number_format($totalUSD, 2) . "</td>";
        echo "</tr>";
    } else {
        echo "<tr><td colspan='11' style='text-align:center'>No hay datos para exportar</td></tr>";
    }
    
    echo "</table>";
    
} catch(PDOException $e) {
    error_log("Error en consolidado_exportar_real.php: " . $e->getMessage());
    die('Error al exportar');
}
?>