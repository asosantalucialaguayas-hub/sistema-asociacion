<?php
// ubicaciones_resumen_global.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";
header('Content-Type: application/json; charset=utf-8');

try {
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
            u.ruta_archivo,
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

        // FIX: leer area desde atributos BD soportando coma y punto decimal
        $hectareas = null;
        if (!empty($a['atributos'])) {
            $atrs = json_decode($a['atributos'], true);
            if (is_array($atrs)) {
                foreach ($atrs as $atr) {
                    $k    = strtolower($atr['k'] ?? '');
                    $tipo = $atr['tipo'] ?? '';
                    if ($tipo === 'area' || str_contains($k,'area') || str_contains($k,'área') ||
                        str_contains($k,'hectarea') || $k === 'ha') {
                        $v = str_replace(',', '.', (string)($atr['v'] ?? ''));
                        $v = floatval($v);
                        if ($v > 0) { $hectareas = $v; break; }
                    }
                }
            }
        }

        // Si no tiene atributos con area, calcular desde KML fisico
        if ($hectareas === null && !empty($a['ruta_archivo'])) {
            $rutaFisica = __DIR__ . '/' . $a['ruta_archivo'];
            if (file_exists($rutaFisica)) {
                $contenido = file_get_contents($rutaFisica);
                if (strtolower($a['tipo_archivo'] ?? '') === 'kmz') {
                    $zk = new ZipArchive();
                    if ($zk->open($rutaFisica) === true) {
                        for ($ki = 0; $ki < $zk->numFiles; $ki++) {
                            $nz = $zk->getNameIndex($ki);
                            if (strtolower(pathinfo($nz, PATHINFO_EXTENSION)) === 'kml') {
                                $contenido = $zk->getFromIndex($ki); break;
                            }
                        }
                        $zk->close();
                    }
                }
                $hectareas = calcularAreaKMLphp($contenido);
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

    $socios = array_values($sociosMap);
    usort($socios, fn($a,$b) => strcmp($a['identificacion'], $b['identificacion']));

    $totalHaGlobal = array_sum(array_column($socios, 'totalHa'));

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

    $libres = [];
    for ($i = 1; $i <= $maxNum; $i++) {
        if (!isset($usados[$i])) {
            $libres[] = 'SLC-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        }
    }

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

function calcularAreaKMLphp(string $kml): ?float {
    try {
        $doc = new DOMDocument();
        @$doc->loadXML($kml);
        $puntos = [];
        $polys = $doc->getElementsByTagName('Polygon');
        if ($polys->length > 0) {
            foreach ($polys as $poly) {
                $outerEls = $poly->getElementsByTagName('outerBoundaryIs');
                if ($outerEls->length > 0) {
                    $coordEls = $outerEls->item(0)->getElementsByTagName('coordinates');
                    if ($coordEls->length > 0) {
                        $raw = trim($coordEls->item(0)->textContent);
                        foreach (preg_split('/\s+/', $raw) as $c) {
                            $p = explode(',', trim($c));
                            if (count($p) >= 2 && is_numeric($p[0]) && is_numeric($p[1])) {
                                $puntos[] = [floatval($p[1]), floatval($p[0])];
                            }
                        }
                        break;
                    }
                }
            }
        }
        if (count($puntos) < 3) return null;
        $R = 6371000;
        $lat0 = $puntos[0][0] * M_PI / 180;
        $cosLat = cos($lat0);
        $area = 0.0;
        $n = count($puntos);
        for ($i = 0; $i < $n; $i++) {
            $j  = ($i + 1) % $n;
            $x1 = $puntos[$i][1] * M_PI / 180 * $R * $cosLat;
            $y1 = $puntos[$i][0] * M_PI / 180 * $R;
            $x2 = $puntos[$j][1] * M_PI / 180 * $R * $cosLat;
            $y2 = $puntos[$j][0] * M_PI / 180 * $R;
            $area += $x1 * $y2 - $x2 * $y1;
        }
        return round(abs($area) / 2.0 / 10000, 6);
    } catch (Exception $e) {
        return null;
    }
}
?>