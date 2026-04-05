<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'notificaciones' => [], 'total' => 0]);
    exit;
}

require __DIR__ . "/config/conexion.php";
require_once __DIR__ . "/helpers/periodo.php";

try {
    $periodoAbierto = get_periodo_abierto($pdo);
    $periodo_id     = $periodoAbierto ? (int)$periodoAbierto['id_periodo'] : 0;
    $notificaciones = [];

    // ── 1. SOCIOS SIN LPA ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT s.id_socio,
               COALESCE(s.nombre_completo, CONCAT(s.nombres, ' ', s.apellidos)) AS nombre,
               s.identificacion
        FROM socios s
        WHERE s.estado = 'activo'
          AND NOT EXISTS (
              SELECT 1 FROM tabla_lpa l
              WHERE l.id_socio = s.id_socio AND l.id_periodo = :periodo_id
          )
        ORDER BY s.id_socio DESC
    ");
    $stmt->execute([':periodo_id' => $periodo_id]);
    $sinLpa = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sinLpa as $s) {
        $notificaciones[] = [
            'tipo'     => 'sin_lpa',
            'icono'    => 'fa-chart-line',
            'titulo'   => 'Sin LPA asignada',
            'mensaje'  => $s['nombre'] . ' (' . $s['identificacion'] . ') no tiene LPA en el período actual.',
            'url'      => 'lpa_consulta.php?buscar=' . urlencode($s['identificacion']),
            'id_socio' => $s['id_socio'],
            'cedula'   => $s['identificacion'],
        ];
    }

    // ── 2. SOCIOS APROBADOS SIN DOCUMENTOS ───────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT si.id_solicitud, si.identificacion, si.nombres_completos AS nombre
        FROM solicitud_ingreso si
        WHERE si.id_periodo = :periodo_id AND si.estado_solicitud = 'APROBADO'
          AND NOT EXISTS (SELECT 1 FROM documentos_socios d WHERE d.id_solicitud = si.id_solicitud)
        ORDER BY si.id_solicitud DESC
    ");
    $stmt->execute([':periodo_id' => $periodo_id]);
    $sinDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sinDocs as $s) {
        $notificaciones[] = [
            'tipo'         => 'sin_documentos',
            'icono'        => 'fa-folder-open',
            'titulo'       => 'Sin documentos',
            'mensaje'      => $s['nombre'] . ' (' . $s['identificacion'] . ') aprobado sin documentos subidos.',
            'url'          => 'socios_consulta.php?q=' . urlencode($s['identificacion']),
            'id_solicitud' => $s['id_solicitud'],
            'cedula'       => $s['identificacion'],
        ];
    }

    // ── 3. LPAs SIN CUPO ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT l.id_lpa, l.id_socio,
               COALESCE(s.nombre_completo, CONCAT(s.nombres, ' ', s.apellidos)) AS nombre,
               s.identificacion
        FROM tabla_lpa l
        LEFT JOIN socios s ON s.id_socio = l.id_socio
        WHERE l.id_periodo = :periodo_id
          AND (l.volumen_produccion_estimado IS NULL OR l.volumen_produccion_estimado = 0)
        ORDER BY l.id_lpa DESC
    ");
    $stmt->execute([':periodo_id' => $periodo_id]);
    $sinCupo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sinCupo as $s) {
        $notificaciones[] = [
            'tipo'     => 'sin_cupo',
            'icono'    => 'fa-scale-balanced',
            'titulo'   => 'Sin cupo asignado',
            'mensaje'  => ($s['nombre'] ?? 'Socio') . ' (' . ($s['identificacion'] ?? '-') . ') tiene LPA pero sin cupo.',
            'url'      => 'cupos_lpa.php?buscar=' . urlencode($s['identificacion'] ?? ''),
            'id_lpa'   => $s['id_lpa'],
            'cedula'   => $s['identificacion'] ?? '',
        ];
    }

    // ── 4. SOLICITUDES PENDIENTES ─────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT si.id_solicitud, si.identificacion, si.nombres_completos AS nombre
        FROM solicitud_ingreso si
        WHERE si.id_periodo = :periodo_id AND si.estado_solicitud = 'PENDIENTE'
        ORDER BY si.id_solicitud DESC
    ");
    $stmt->execute([':periodo_id' => $periodo_id]);
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pendientes as $s) {
        $notificaciones[] = [
            'tipo'         => 'pendiente_aprobacion',
            'icono'        => 'fa-user-clock',
            'titulo'       => 'Pendiente de aprobación',
            'mensaje'      => $s['nombre'] . ' (' . $s['identificacion'] . ') tiene solicitud pendiente.',
            'url'          => 'socios_consulta.php?q=' . urlencode($s['identificacion']),
            'id_solicitud' => $s['id_solicitud'],
            'cedula'       => $s['identificacion'],
        ];
    }

    // ── 5. SOCIOS SIN COORDENADAS KML ─────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT s.id_socio,
               COALESCE(s.nombre_completo, CONCAT(s.nombres, ' ', s.apellidos)) AS nombre,
               s.identificacion
        FROM socios s
        WHERE s.estado = 'activo'
          AND NOT EXISTS (
              SELECT 1 FROM socio_ubicaciones u WHERE u.id_socio = s.id_socio
          )
        ORDER BY s.id_socio DESC
    ");
    $stmt->execute();
    $sinCoords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sinCoords as $s) {
        $notificaciones[] = [
            'tipo'     => 'sin_coordenadas',
            'icono'    => 'fa-map-location-dot',
            'titulo'   => 'Sin coordenadas KML',
            'mensaje'  => $s['nombre'] . ' (' . $s['identificacion'] . ') no tiene archivo KML/KMZ subido.',
            'url'      => 'ubicaciones_consulta.php?q=' . urlencode($s['identificacion']),
            'id_socio' => $s['id_socio'],
            'cedula'   => $s['identificacion'],
        ];
    }

    echo json_encode([
        'success'        => true,
        'total'          => count($notificaciones),
        'resumen'        => [
            'sin_lpa'              => count($sinLpa),
            'sin_documentos'       => count($sinDocs),
            'sin_cupo'             => count($sinCupo),
            'pendiente_aprobacion' => count($pendientes),
            'sin_coordenadas'      => count($sinCoords),
        ],
        'notificaciones' => $notificaciones,
        'periodo'        => $periodoAbierto['nombre'] ?? '',
    ]);

} catch (PDOException $e) {
    error_log("notificaciones_api: " . $e->getMessage());
    echo json_encode(['success' => false, 'notificaciones' => [], 'total' => 0]);
}
?>