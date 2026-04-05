<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}

require_once 'config/conexion.php';

// Nombre del archivo
$filename = "Estimacion_Produccion_" . date('Y-m-d') . ".xls";

// Headers para descargar Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

try {
    $stmt = $pdo->query("
        SELECT 
            l.*,
            s.identificacion,
            s.nombre_completo,
            s.nombres,
            s.apellidos,
            s.sexo,
            s.telefono
        FROM tabla_lpa l
        INNER JOIN socios s ON s.id_socio = l.id_socio
        WHERE l.estado_lpa = 'activo'
        ORDER BY l.zona, l.comunidad_grupo, s.nombre_completo
    ");
    
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Iniciar tabla HTML para Excel
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr style="background-color:#1f3a5f;color:white;font-weight:bold">';
    echo '<th>N°</th>';
    echo '<th>Zona</th>';
    echo '<th>Comunidad o Grupo</th>';
    echo '<th>Cédula</th>';
    echo '<th>Productor/a</th>';
    echo '<th>Sexo</th>';
    echo '<th>Celular</th>';
    echo '<th>Vol. Producción</th>';
    echo '<th>Enero</th>';
    echo '<th>Febrero</th>';
    echo '<th>Marzo</th>';
    echo '<th>Abril</th>';
    echo '<th>Mayo</th>';
    echo '<th>Junio</th>';
    echo '<th>Julio</th>';
    echo '<th>Agosto</th>';
    echo '<th>Septiembre</th>';
    echo '<th>Octubre</th>';
    echo '<th>Noviembre</th>';
    echo '<th>Diciembre</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $totales = [
        'produccion' => 0,
        'enero' => 0, 'febrero' => 0, 'marzo' => 0, 'abril' => 0,
        'mayo' => 0, 'junio' => 0, 'julio' => 0, 'agosto' => 0,
        'septiembre' => 0, 'octubre' => 0, 'noviembre' => 0, 'diciembre' => 0
    ];
    
    foreach ($datos as $idx => $row) {
        $nombreCompleto = $row['nombre_completo'] ?? ($row['nombres'] . ' ' . $row['apellidos']);
        
        // Sumar totales
        $totales['produccion'] += floatval($row['volumen_produccion_estimado'] ?? 0);
        $totales['enero'] += floatval($row['enero'] ?? 0);
        $totales['febrero'] += floatval($row['febrero'] ?? 0);
        $totales['marzo'] += floatval($row['marzo'] ?? 0);
        $totales['abril'] += floatval($row['abril'] ?? 0);
        $totales['mayo'] += floatval($row['mayo'] ?? 0);
        $totales['junio'] += floatval($row['junio'] ?? 0);
        $totales['julio'] += floatval($row['julio'] ?? 0);
        $totales['agosto'] += floatval($row['agosto'] ?? 0);
        $totales['septiembre'] += floatval($row['septiembre'] ?? 0);
        $totales['octubre'] += floatval($row['octubre'] ?? 0);
        $totales['noviembre'] += floatval($row['noviembre'] ?? 0);
        $totales['diciembre'] += floatval($row['diciembre'] ?? 0);
        
        echo '<tr>';
        echo '<td>' . ($idx + 1) . '</td>';
        echo '<td>' . ($row['zona'] ?? '-') . '</td>';
        echo '<td>' . ($row['comunidad_grupo'] ?? '-') . '</td>';
        echo '<td>' . ($row['identificacion'] ?? '-') . '</td>';
        echo '<td>' . $nombreCompleto . '</td>';
        echo '<td>' . ($row['sexo'] ?? '-') . '</td>';
        echo '<td>' . ($row['telefono'] ?? '-') . '</td>';
        echo '<td>' . number_format($row['volumen_produccion_estimado'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['enero'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['febrero'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['marzo'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['abril'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['mayo'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['junio'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['julio'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['agosto'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['septiembre'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['octubre'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['noviembre'] ?? 0, 2) . '</td>';
        echo '<td>' . number_format($row['diciembre'] ?? 0, 2) . '</td>';
        echo '</tr>';
    }
    
    // Fila de totales
    echo '<tr style="background-color:#f3f4f6;font-weight:bold">';
    echo '<td colspan="7" style="text-align:right">TOTAL:</td>';
    echo '<td>' . number_format($totales['produccion'], 2) . '</td>';
    echo '<td>' . number_format($totales['enero'], 2) . '</td>';
    echo '<td>' . number_format($totales['febrero'], 2) . '</td>';
    echo '<td>' . number_format($totales['marzo'], 2) . '</td>';
    echo '<td>' . number_format($totales['abril'], 2) . '</td>';
    echo '<td>' . number_format($totales['mayo'], 2) . '</td>';
    echo '<td>' . number_format($totales['junio'], 2) . '</td>';
    echo '<td>' . number_format($totales['julio'], 2) . '</td>';
    echo '<td>' . number_format($totales['agosto'], 2) . '</td>';
    echo '<td>' . number_format($totales['septiembre'], 2) . '</td>';
    echo '<td>' . number_format($totales['octubre'], 2) . '</td>';
    echo '<td>' . number_format($totales['noviembre'], 2) . '</td>';
    echo '<td>' . number_format($totales['diciembre'], 2) . '</td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}