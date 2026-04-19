<?php
// mapa_global_datos.php
// Devuelve TODOS los socios con sus archivos KML en una sola llamada.
// El KML se envía en base64 igual que leer_kml para compatibilidad.

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";
header('Content-Type: application/json; charset=utf-8');

try {
    // Una sola query: todos los archivos de socios activos con sus datos
    $stmt = $pdo->query("
        SELECT
            u.id_ubicacion,
            u.id_socio,
            u.nombre_archivo,
            u.ruta_archivo,
            u.tipo_archivo,
            u.codigo_archivo,
            u.descripcion,
            u.color_capa,
            u.atributos,
            u.titulo_aviso,
            u.subido_por,
            u.fecha_subida,
            COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_completo,
            s.identificacion
        FROM socio_ubicaciones u
        INNER JOIN socios s ON s.id_socio = u.id_socio AND s.estado = 'activo'
        ORDER BY s.identificacion ASC, u.codigo_archivo ASC
    ");
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];
    $uploadDir = __DIR__ . '/';

    foreach ($archivos as $a) {
        $rutaFisica = $uploadDir . $a['ruta_archivo'];
        if (!file_exists($rutaFisica)) continue;

        // Leer contenido
        $contenido = file_get_contents($rutaFisica);

        // Descomprimir KMZ si aplica
        if (strtolower($a['tipo_archivo']) === 'kmz') {
            $zk = new ZipArchive();
            if ($zk->open($rutaFisica) === true) {
                for ($ki = 0; $ki < $zk->numFiles; $ki++) {
                    $nz = $zk->getNameIndex($ki);
                    if (strtolower(pathinfo($nz, PATHINFO_EXTENSION)) === 'kml') {
                        $contenido = $zk->getFromIndex($ki);
                        break;
                    }
                }
                $zk->close();
            }
        }

        // Decodificar atributos
        $atributosBD = null;
        if (!empty($a['atributos'])) {
            $decoded = json_decode($a['atributos'], true);
            if (is_array($decoded) && count($decoded)) $atributosBD = $decoded;
        }

        $resultado[] = [
            'id_ubicacion'   => (int)$a['id_ubicacion'],
            'id_socio'       => (int)$a['id_socio'],
            'nombre_archivo' => $a['nombre_archivo'],
            'tipo_archivo'   => $a['tipo_archivo'],
            'codigo_archivo' => $a['codigo_archivo'] ?? '',
            'descripcion'    => $a['descripcion'] ?? '',
            'color_capa'     => $a['color_capa'] ?? '#38bdf8',
            'titulo_aviso'   => $a['titulo_aviso'] ?? '',
            'subido_por'     => $a['subido_por'] ?? '',
            'fecha_subida'   => $a['fecha_subida'] ?? '',
            'nombre_socio'   => $a['nombre_completo'],
            'identificacion' => $a['identificacion'],
            'atributos'      => $atributosBD,
            'kml'            => base64_encode($contenido),
        ];
    }

    echo json_encode([
        'success' => true,
        'total'   => count($resultado),
        'datos'   => $resultado,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
