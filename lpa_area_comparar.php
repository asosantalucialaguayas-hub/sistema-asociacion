<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
require "config/conexion.php";

header('Content-Type: application/json');

try {
    // Trae por socio: su cédula, nombre, área_cacao_ha del LPA más reciente
    $sql = "
        SELECT 
    s.id_socio,
    s.identificacion,
    CONCAT(s.nombres, ' ', s.apellidos) AS nombre_completo,
    s.zona,
    l.area_cacao_ha
FROM socios s
LEFT JOIN (
    SELECT DISTINCT ON (id_socio) id_socio, area_cacao_ha
    FROM lpa
    ORDER BY id_socio, id_lpa DESC
) l ON l.id_socio = s.id_socio
WHERE s.estado = 'activo'
ORDER BY s.apellidos, s.nombres
    ";
    $result = pg_query($conn, $sql);
    $socios = [];
    while ($row = pg_fetch_assoc($result)) {
        $socios[$row['id_socio']] = [
            'id_socio'       => $row['id_socio'],
            'identificacion' => $row['identificacion'],
            'nombre'         => $row['nombre_completo'],
            'zona'           => $row['zona'] ?? '',
            'area_cacao_ha'  => $row['area_cacao_ha'] !== null ? floatval($row['area_cacao_ha']) : null,
        ];
    }
    
    // Trae el total de hectáreas KML por socio (desde ubicaciones)
    $sql2 = "
        SELECT id_socio, SUM(hectareas) AS total_ha_kml, COUNT(*) AS total_archivos
        FROM ubicaciones_kml
        WHERE hectareas IS NOT NULL
        GROUP BY id_socio
    ";
    $result2 = pg_query($conn, $sql2);
    while ($row = pg_fetch_assoc($result2)) {
        $id = $row['id_socio'];
        if (isset($socios[$id])) {
            $socios[$id]['total_ha_kml']   = floatval($row['total_ha_kml']);
            $socios[$id]['total_archivos'] = intval($row['total_archivos']);
        }
    }

    // Calcular diferencia y estado
    $lista = [];
    foreach ($socios as $s) {
        $lpa = $s['area_cacao_ha'];
        $kml = $s['total_ha_kml'] ?? null;
        $diff = null;
        $estado = 'sin_datos';
        if ($lpa !== null && $kml !== null) {
            $diff = round($kml - $lpa, 3);
            if (abs($diff) <= 0.05)        $estado = 'igual';
            elseif ($diff > 0)             $estado = 'exceso';
            else                           $estado = 'deficit';
        } elseif ($lpa !== null && $kml === null) {
            $estado = 'sin_kml';
        } elseif ($lpa === null && $kml !== null) {
            $estado = 'sin_lpa';
        }
        $lista[] = array_merge($s, [
            'total_ha_kml'   => $kml,
            'total_archivos' => $s['total_archivos'] ?? 0,
            'diferencia'     => $diff,
            'estado'         => $estado,
        ]);
    }

    echo json_encode(['success' => true, 'datos' => $lista]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}