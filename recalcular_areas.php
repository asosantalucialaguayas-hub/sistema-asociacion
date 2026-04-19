<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { die('No autorizado'); }
require "config/conexion.php";

$stmt = $pdo->query("
    SELECT id_ubicacion, ruta_archivo, tipo_archivo, atributos, codigo_archivo
    FROM socio_ubicaciones
    WHERE atributos IS NOT NULL AND atributos != '' AND atributos != '[]'
");
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$actualizados = 0;
$errores = 0;
$log = [];

foreach ($archivos as $a) {
    $atrs = json_decode($a['atributos'], true);
    if (!is_array($atrs)) continue;

    $tieneArea = false;
    foreach ($atrs as $atr) {
        $t = $atr['tipo'] ?? '';
        $k = strtolower($atr['k'] ?? '');
        if ($t === 'area' || $k === 'area' || $k === 'área' || $k === 'area_ha') {
            $tieneArea = true; break;
        }
    }
    if (!$tieneArea) continue;

    $ruta = __DIR__ . '/' . $a['ruta_archivo'];
    if (!file_exists($ruta)) {
        $log[] = "❌ No existe: " . $a['codigo_archivo'];
        $errores++; continue;
    }

    $contenido = file_get_contents($ruta);

    if (strtolower($a['tipo_archivo']) === 'kmz') {
        $zk = new ZipArchive();
        if ($zk->open($ruta) === true) {
            for ($ki = 0; $ki < $zk->numFiles; $ki++) {
                $nz = $zk->getNameIndex($ki);
                if (strtolower(pathinfo($nz, PATHINFO_EXTENSION)) === 'kml') {
                    $contenido = $zk->getFromIndex($ki); break;
                }
            }
            $zk->close();
        }
    }

    $area = calcularAreaKML($contenido);
    if ($area === null) {
        $log[] = "⚠ Sin geometría: " . $a['codigo_archivo'];
        continue;
    }

    $atrsActualizados = array_map(function($atr) use ($area) {
        $t = $atr['tipo'] ?? '';
        $k = strtolower($atr['k'] ?? '');
        if ($t === 'area' || $k === 'area' || $k === 'área' || $k === 'area_ha') {
            return array_merge($atr, ['v' => (string)round($area, 6)]);
        }
        return $atr;
    }, $atrs);

    $pdo->prepare("UPDATE socio_ubicaciones SET atributos = ? WHERE id_ubicacion = ?")
        ->execute([json_encode($atrsActualizados, JSON_UNESCAPED_UNICODE), $a['id_ubicacion']]);

    $log[] = "✅ " . $a['codigo_archivo'] . " → " . round($area, 4) . " ha";
    $actualizados++;
}

function calcularAreaKML(string $kml): ?float {
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
                            if (count($p) >= 2) {
                                $lon = floatval($p[0]);
                                $lat = floatval($p[1]);
                                if ($lon != 0 || $lat != 0) $puntos[] = [$lat, $lon];
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
            $j = ($i + 1) % $n;
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

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;'>";
echo "=== RECÁLCULO DE ÁREAS ===\n\n";
echo "Archivos revisados: " . count($archivos) . "\n";
echo "Actualizados: $actualizados\n";
echo "Errores/sin geom: $errores\n\n";
echo implode("\n", $log);
echo "\n\n=== LISTO — ya puedes borrar este archivo ===";
echo "</pre>";
?>