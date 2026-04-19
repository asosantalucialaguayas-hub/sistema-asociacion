<?php
// ubicaciones_resumen_global.php
// Endpoint optimizado: devuelve TODO en una sola llamada
// en vez de N×2 requests individuales desde el frontend.

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";
header('Content-Type: application/json; charset=utf-8');

try {
    // ── 1. Totales globales (una sola query) ──────────────────────────────
    $stStats = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM socios WHERE estado = 'activo') AS total_socios,
            (SELECT COUNT(DISTINCT su.id_socio)
               FROM socio_ubicaciones su
               INNER JOIN socios s ON s.id_socio = su.id_socio AND s.estado = 'activo'
            ) AS socios_con_kml,
            (SELECT COUNT(*)
               FROM socio_ubicaciones su
               INNER JOIN socios s ON s.id_socio = su.id_socio AND s.estado = 'activo'
            ) AS total_archivos
    ");
    $stats = $stStats->fetch(PDO::FETCH_ASSOC);

    $total_socios   = (int)$stats['total_socios'];
    $socios_con_kml = (int)$stats['socios_con_kml'];
    $socios_sin_kml = $total_socios - $socios_con_kml;

    // ── 2. Todos los archivos con atributos en UNA query ─────────────────
    // Traemos: datos del socio + datos del archivo + atributos BD
    // Una fila por archivo (no por socio)
    $stArchivos = $pdo->query("
        SELECT
            s.id_socio,
            s.identificacion,
            COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_completo,
            COALESCE(l.zona,'')            AS zona,
            COALESCE(l.comunidad_grupo,'') AS comunidad,
            COALESCE(l.adendum,0)          AS adendum,
            u.id_ubicacion,
            u.codigo_archivo,
            u.nombre_archivo,
            u.tipo_archivo,
            u.atributos,
            u.descripcion,
            u.fecha_subida
        FROM socios s
        INNER JOIN socio_ubicaciones u ON u.id_socio = s.id_socio
        LEFT JOIN (
            SELECT lj.id_socio, lj.zona, lj.comunidad_grupo, lj.adendum
            FROM tabla_lpa lj
            INNER JOIN (
                SELECT id_socio, MAX(id_lpa) AS max_id
                FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio
            ) mx ON lj.id_socio = mx.id_socio AND lj.id_lpa = mx.max_id
        ) l ON l.id_socio = s.id_socio
        WHERE s.estado = 'activo'
        ORDER BY s.identificacion ASC, u.codigo_archivo ASC
    ");
    $archivos = $stArchivos->fetchAll(PDO::FETCH_ASSOC);

    // ── 3. Agrupar por socio y extraer hectáreas desde atributos BD ──────
    $sociosMap = [];

    foreach ($archivos as $a) {
        $sid = $a['id_socio'];

        if (!isset($sociosMap[$sid])) {
            $sociosMap[$sid] = [
                'id_socio'       => $sid,
                'identificacion' => $a['identificacion'],
                'nombre'         => $a['nombre_completo'],
                'zona'           => $a['zona'],
                'comunidad'      => $a['comunidad'],
                'adendum'        => (int)$a['adendum'],
                'lotes'          => [],
                'totalHa'        => 0.0,
            ];
        }

        // Extraer hectáreas de los atributos guardados en BD
        $hectareas = null;
        if (!empty($a['atributos'])) {
            $atrs = json_decode($a['atributos'], true);
            if (is_array($atrs)) {
                foreach ($atrs as $atr) {
                    $k = strtolower($atr['k'] ?? '');
                    $tipo = $atr['tipo'] ?? '';
                    if ($tipo === 'area' || str_contains($k,'area') || str_contains($k,'área') ||
                        str_contains($k,'hectarea') || str_contains($k,'ha')) {
                        $v = floatval($atr['v'] ?? 0);
                        if ($v > 0) { $hectareas = $v; break; }
                    }
                }
            }
        }

        $codigo = $a['codigo_archivo'] ?: $a['nombre_archivo'];

        $sociosMap[$sid]['lotes'][] = [
            'id_ubicacion' => (int)$a['id_ubicacion'],
            'codigo'       => $codigo,
            'hectareas'    => $hectareas,
            'descripcion'  => $a['descripcion'] ?? '',
            'fecha'        => $a['fecha_subida'] ?? '',
        ];

        if ($hectareas !== null && $hectareas > 0) {
            $sociosMap[$sid]['totalHa'] += $hectareas;
        }
    }

    // ── 4. Socios activos SIN KML ─────────────────────────────────────────
    // Los incluimos con lotes vacíos para el resumen
    $stSinKml = $pdo->query("
        SELECT
            s.id_socio,
            s.identificacion,
            COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_completo,
            COALESCE(l.zona,'')            AS zona,
            COALESCE(l.comunidad_grupo,'') AS comunidad,
            COALESCE(l.adendum,0)          AS adendum
        FROM socios s
        LEFT JOIN (
            SELECT lj.id_socio, lj.zona, lj.comunidad_grupo, lj.adendum
            FROM tabla_lpa lj
            INNER JOIN (
                SELECT id_socio, MAX(id_lpa) AS max_id
                FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio
            ) mx ON lj.id_socio = mx.id_socio AND lj.id_lpa = mx.max_id
        ) l ON l.id_socio = s.id_socio
        WHERE s.estado = 'activo'
          AND NOT EXISTS (
              SELECT 1 FROM socio_ubicaciones u2 WHERE u2.id_socio = s.id_socio
          )
        ORDER BY s.identificacion ASC
    ");
    $sinKml = $stSinKml->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sinKml as $s) {
        $sociosMap[$s['id_socio']] = [
            'id_socio'       => (int)$s['id_socio'],
            'identificacion' => $s['identificacion'],
            'nombre'         => $s['nombre_completo'],
            'zona'           => $s['zona'],
            'comunidad'      => $s['comunidad'],
            'adendum'        => (int)$s['adendum'],
            'lotes'          => [],
            'totalHa'        => 0.0,
        ];
    }

    // ── 5. Ordenar por identificación ─────────────────────────────────────
    $socios = array_values($sociosMap);
    usort($socios, fn($a,$b) => strcmp($a['identificacion'], $b['identificacion']));

    // ── 6. Calcular total ha global ───────────────────────────────────────
    $totalHaGlobal = array_sum(array_column($socios, 'totalHa'));

    // ── 7. Calcular códigos usados y libres ───────────────────────────────
    // CORRECCIÓN: los códigos van de SLC-001 hasta el máximo EXISTENTE
    // Solo se muestran libres dentro de ese rango, no más allá
    $usados = [];
    $maxNum = 0;
    foreach ($socios as $s) {
        foreach ($s['lotes'] as $l) {
            if (preg_match('/^SLC-(\d+)/i', $l['codigo'], $m)) {
                $n = (int)$m[1];
                $usados[$n] = true;
                if ($n > $maxNum) $maxNum = $n;
            }
        }
    }

    // Libres = los que faltan entre 1 y maxNum
    // No agregamos más allá del máximo para no inflar el número
    $libres = [];
    for ($i = 1; $i <= $maxNum; $i++) {
        if (!isset($usados[$i])) {
            $libres[] = 'SLC-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        }
    }

    // ── 8. Respuesta ──────────────────────────────────────────────────────
    echo json_encode([
        'success'        => true,
        'stats'          => [
            'total_socios'   => $total_socios,
            'socios_con_kml' => $socios_con_kml,
            'socios_sin_kml' => $socios_sin_kml,
            'total_ha'       => round($totalHaGlobal, 4),
            'codigos_libres' => count($libres),
        ],
        'socios'         => $socios,
        'codigos_libres' => $libres,
        'codigos_usados' => count($usados),
        'max_codigo'     => $maxNum,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
