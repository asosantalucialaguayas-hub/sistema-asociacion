<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require "config/conexion.php";

// Leer acción
$accion = '';
if (!empty($_GET['accion']))       $accion = trim($_GET['accion']);
elseif (!empty($_POST['accion']))   $accion = trim($_POST['accion']);
if (empty($accion)) {
    $body = file_get_contents('php://input');
    if ($body) {
        $decoded = json_decode($body, true);
        if (!empty($decoded['accion'])) $accion = $decoded['accion'];
    }
}

// Las acciones de descarga no usan Content-Type JSON
$accionesDescarga = ['exportar_socio', 'exportar_todos', 'exportar_excel', 'exportar_resumen_excel', 'exportar_resumen_global_excel', 'descargar_kml_actualizado'];
if (!in_array($accion, $accionesDescarga)) {
    header('Content-Type: application/json; charset=utf-8');
}

$uploadDir = __DIR__ . '/uploads/kml/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── HELPER: inyectar atributos actualizados en KML (description + ExtendedData) ──
function _inyectarAtributos(string $kml, string $atrsJson, string $titulo, string $codigo): string {
    $atrs = $atrsJson ? (json_decode($atrsJson, true) ?: []) : [];
    if (empty($atrs)) return $kml;
    $titulo = $titulo ?: $codigo;

    // 1. Nuevo <description> HTML
    $rows = '';
    foreach ($atrs as $i => $a) {
        $bg    = $i % 2 !== 0 ? ' bgcolor="#D4E4F3"' : '';
        $rows .= "<tr{$bg}><td>" . htmlspecialchars($a['k'] ?? '', ENT_XML1, 'UTF-8')
               . "</td><td>" . htmlspecialchars($a['v'] ?? '', ENT_XML1, 'UTF-8') . "</td></tr>";
    }
    $html    = '<table style="font-family:Arial;font-size:12px;width:100%;border-collapse:collapse;">'
             . '<tr style="background:#9CBCE2;font-weight:bold;text-align:center;"><td colspan="2">'
             . htmlspecialchars($titulo, ENT_XML1, 'UTF-8') . '</td></tr>' . $rows . '</table>';
    $newDesc = "<description><![CDATA[\n{$html}\n]]></description>";
    $kml = preg_match('/<description/i', $kml)
         ? preg_replace('/<description[\s\S]*?<\/description>/i', $newDesc, $kml)
         : preg_replace('/(<Placemark[^>]*>)/i', '$1' . $newDesc, $kml, 1);

    // 2. Nuevo <ExtendedData>
    $extXml = "<ExtendedData>\n";
    foreach ($atrs as $a) {
        $extXml .= '  <Data name="' . htmlspecialchars($a['k'] ?? '', ENT_XML1, 'UTF-8') . '">'
                 . '<value>' . htmlspecialchars($a['v'] ?? '', ENT_XML1, 'UTF-8') . '</value></Data>' . "\n";
    }
    $extXml .= "</ExtendedData>";
    $kml = preg_match('/<ExtendedData/i', $kml)
         ? preg_replace('/<ExtendedData>[\s\S]*?<\/ExtendedData>/i', $extXml, $kml)
         : preg_replace('/<\/Placemark>/i', $extXml . "\n</Placemark>", $kml, 1);

    return $kml;
}

// ── HELPER: leer contenido KML descomprimiendo KMZ si aplica ──
function _leerKml(string $rutaFisica): string {
    if (!file_exists($rutaFisica)) return '';
    $contenido = file_get_contents($rutaFisica);
    if (strtolower(pathinfo($rutaFisica, PATHINFO_EXTENSION)) === 'kmz') {
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
    return $contenido;
}

// ── DEBUG ─────────────────────────────────────────────────────────────────
if (isset($_GET['debug'])) {
    $info = ['accion' => $accion, 'pdo_ok' => false, 'tabla_socios' => false,
             'tabla_ubicaciones' => false, 'error' => null];
    try {
        $pdo->query("SELECT 1"); $info['pdo_ok'] = true;
        $pdo->query("SELECT 1 FROM socios LIMIT 1"); $info['tabla_socios'] = true;
        $pdo->query("SELECT 1 FROM socio_ubicaciones LIMIT 1"); $info['tabla_ubicaciones'] = true;
        $info['total_socios'] = (int)$pdo->query("SELECT COUNT(*) FROM socios")->fetchColumn();
        $cols = $pdo->query("SHOW COLUMNS FROM socio_ubicaciones LIKE 'codigo_archivo'")->fetchAll();
        $info['col_codigo_archivo'] = count($cols) > 0 ? 'OK' : 'FALTA — ejecuta la migración SQL';
        $colsColor = $pdo->query("SHOW COLUMNS FROM socio_ubicaciones LIKE 'color_capa'")->fetchAll();
        $info['col_color_capa'] = count($colsColor) > 0 ? 'OK' : 'FALTA — ejecuta: ALTER TABLE socio_ubicaciones ADD COLUMN color_capa VARCHAR(20) DEFAULT \'#38bdf8\'';
        $colsAtrs = $pdo->query("SHOW COLUMNS FROM socio_ubicaciones LIKE 'atributos'")->fetchAll();
        $info['col_atributos'] = count($colsAtrs) > 0 ? 'OK' : 'FALTA — ejecuta: ALTER TABLE socio_ubicaciones ADD COLUMN atributos LONGTEXT';
        $colsTit = $pdo->query("SHOW COLUMNS FROM socio_ubicaciones LIKE 'titulo_aviso'")->fetchAll();
        $info['col_titulo_aviso'] = count($colsTit) > 0 ? 'OK' : 'FALTA — ejecuta: ALTER TABLE socio_ubicaciones ADD COLUMN titulo_aviso VARCHAR(255)';
    } catch(Exception $e) { $info['error'] = $e->getMessage(); }
    echo json_encode($info, JSON_PRETTY_PRINT);
    exit;
}

// ── Helper WHERE reutilizable ──────────────────────────────────────────────
function buildWhere(array &$params, string $q, string $con_kml, string $adendum_f): string {
    $conditions = ["s.estado = 'activo'"];

    if ($con_kml === 'con') {
        $conditions[] = "EXISTS (SELECT 1 FROM socio_ubicaciones uf WHERE uf.id_socio = s.id_socio)";
    } elseif ($con_kml === 'sin') {
        $conditions[] = "NOT EXISTS (SELECT 1 FROM socio_ubicaciones uf WHERE uf.id_socio = s.id_socio)";
    }
    if ($adendum_f === '1' || $adendum_f === '2') {
        $conditions[] = "s.id_socio IN (SELECT lf.id_socio FROM tabla_lpa lf
                         WHERE lf.adendum = :adendum_f AND lf.id_socio IS NOT NULL)";
        $params[':adendum_f'] = (int)$adendum_f;
    }
    if ($q !== '') {
        $like = "%$q%";
        $conditions[] = "(s.identificacion LIKE :q1 OR s.nombre_completo LIKE :q2
                          OR s.nombres LIKE :q3 OR s.apellidos LIKE :q4
                          OR EXISTS (SELECT 1 FROM socio_ubicaciones uq
                                     WHERE uq.id_socio = s.id_socio AND uq.codigo_archivo LIKE :q5))";
        $params[':q1']=$like; $params[':q2']=$like;
        $params[':q3']=$like; $params[':q4']=$like; $params[':q5']=$like;
    }
    return "WHERE " . implode(" AND ", $conditions);
}

$joinLpa = "LEFT JOIN (
    SELECT lj.id_socio, lj.zona, lj.comunidad_grupo, lj.adendum
    FROM tabla_lpa lj
    INNER JOIN (
        SELECT id_socio, MAX(id_lpa) AS max_id
        FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio
    ) mx ON lj.id_socio = mx.id_socio AND lj.id_lpa = mx.max_id
) l ON l.id_socio = s.id_socio";

try {
    switch ($accion) {

        // ═══════════════════════════════════════════════════════════════════
        // BUSCAR SOCIOS
        // ═══════════════════════════════════════════════════════════════════
        case 'buscar_socios':
            $pagina    = max(1, intval($_GET['pagina'] ?? 1));
            $porPagina = intval($_GET['porPagina'] ?? 50);
            $offset    = ($pagina - 1) * $porPagina;
            $q         = trim($_GET['q'] ?? '');
            $con_kml   = trim($_GET['con_kml'] ?? '');
            $adendum_f = trim($_GET['adendum_f'] ?? '');
            $params    = [];
            $where     = buildWhere($params, $q, $con_kml, $adendum_f);

            $stCount = $pdo->prepare("SELECT COUNT(*) FROM socios s $joinLpa $where");
            foreach ($params as $k => $v) $stCount->bindValue($k, $v);
            $stCount->execute();
            $total = (int)$stCount->fetchColumn();

            $sql = "
                SELECT
                    s.id_socio,
                    s.identificacion,
                    COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                        TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_completo,
                    COALESCE(l.zona,'')            AS zona,
                    COALESCE(l.comunidad_grupo,'') AS comunidad_grupo,
                    COALESCE(l.adendum,0)          AS adendum,
                    s.estado,
                    (SELECT COUNT(*) FROM socio_ubicaciones uc
                     WHERE uc.id_socio = s.id_socio) AS total_archivos,
                    (SELECT SUBSTRING_INDEX(MAX(uc2.codigo_archivo),'_',1)
                     FROM socio_ubicaciones uc2
                     WHERE uc2.id_socio = s.id_socio
                       AND uc2.codigo_archivo IS NOT NULL AND uc2.codigo_archivo != '')
                        AS codigo_slc,
                    (SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(uc3.codigo_archivo,'_',-1) AS UNSIGNED)),0)+1
                     FROM socio_ubicaciones uc3
                     WHERE uc3.id_socio = s.id_socio
                       AND uc3.codigo_archivo IS NOT NULL AND uc3.codigo_archivo != '')
                        AS proximo_lote
                FROM socios s $joinLpa $where
                ORDER BY COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                         TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) ASC
                LIMIT $porPagina OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'      => true,
                'datos'        => $datos,
                'total'        => $total,
                'pagina'       => $pagina,
                'totalPaginas' => max(1,(int)ceil($total/$porPagina)),
                'porPagina'    => $porPagina,
                '_debug'       => ['q'=>$q,'con_kml'=>$con_kml,'adendum_f'=>$adendum_f,'total'=>$total,'filas'=>count($datos)]
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // STATS — solo socios activos
        // ═══════════════════════════════════════════════════════════════════
        case 'stats':
            $stTotal = $pdo->query("SELECT COUNT(*) FROM socios WHERE estado = 'activo'")->fetchColumn();
            $stCon   = $pdo->query("SELECT COUNT(DISTINCT su.id_socio) FROM socio_ubicaciones su INNER JOIN socios s ON s.id_socio = su.id_socio WHERE s.estado = 'activo'")->fetchColumn();
            $stArch  = $pdo->query("SELECT COUNT(*) FROM socio_ubicaciones su INNER JOIN socios s ON s.id_socio = su.id_socio WHERE s.estado = 'activo'")->fetchColumn();
            echo json_encode(['success'=>true,'stats'=>[
                'total'    => (int)$stTotal,
                'con_ubic' => (int)$stCon,
                'sin_ubic' => (int)$stTotal - (int)$stCon,
                'archivos' => (int)$stArch,
            ]]);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // VALIDAR CÓDIGO
        // ═══════════════════════════════════════════════════════════════════
        case 'validar_codigo':
            $codigo = strtoupper(trim($_GET['codigo'] ?? ''));
            if (!$codigo) { echo json_encode(['existe'=>false]); exit; }

            $stmt = $pdo->prepare("
                SELECT u.codigo_archivo,
                       COALESCE(s.nombre_completo,
                           CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,''))) AS socio
                FROM socio_ubicaciones u
                LEFT JOIN socios s ON s.id_socio = u.id_socio
                WHERE u.codigo_archivo = :codigo
                LIMIT 1
            ");
            $stmt->execute([':codigo' => $codigo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($row
                ? ['existe'=>true,  'socio'=>trim($row['socio'])]
                : ['existe'=>false]
            );
            break;

        // ═══════════════════════════════════════════════════════════════════
        // LISTAR archivos de un socio
        // ═══════════════════════════════════════════════════════════════════
        case 'listar':
            $id_socio = intval($_GET['id_socio'] ?? 0);
            if (!$id_socio) { echo json_encode(['success'=>false,'message'=>'id_socio requerido']); exit; }
            $stmt = $pdo->prepare("
                SELECT u.id_ubicacion, u.id_socio, u.nombre_archivo, u.ruta_archivo,
                       u.tipo_archivo, u.codigo_archivo, u.descripcion,
                       u.color_capa, u.atributos,
                       u.titulo_aviso,
                       u.subido_por, u.fecha_subida,
                       u.editado_por, u.fecha_edicion,
                       COALESCE(s.nombre_completo,
                           CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,''))) AS nombre_socio,
                       s.identificacion
                FROM socio_ubicaciones u
                LEFT JOIN socios s ON s.id_socio = u.id_socio
                WHERE u.id_socio = :id_socio
                ORDER BY u.codigo_archivo ASC, u.fecha_subida ASC
            ");
            $stmt->bindValue(':id_socio', $id_socio, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success'=>true,'datos'=>$stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // SUBIR archivo
        // ═══════════════════════════════════════════════════════════════════
        case 'subir':
            $id_socio       = intval($_POST['id_socio'] ?? 0);
            $descripcion    = trim($_POST['descripcion'] ?? '');
            $codigo_archivo = strtoupper(trim($_POST['codigo_archivo'] ?? ''));

            if (!$id_socio) {
                echo json_encode(['success'=>false,'message'=>'id_socio requerido']); exit;
            }
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                $errNum = $_FILES['archivo']['error'] ?? 'sin archivo';
                echo json_encode(['success'=>false,'message'=>"No se recibió archivo (error: $errNum)"]); exit;
            }
            if ($codigo_archivo === '') {
                echo json_encode(['success'=>false,'message'=>'El código del archivo es obligatorio']); exit;
            }
            if (!preg_match('/^SLC-\d{3}_\d+$/', $codigo_archivo)) {
                echo json_encode(['success'=>false,
                    'message'=>"Formato inválido: \"$codigo_archivo\" — usa SLC-NNN_L (ej: SLC-001_1)"]); exit;
            }
            $chk = $pdo->prepare("SELECT id_ubicacion FROM socio_ubicaciones WHERE codigo_archivo = :cod");
            $chk->execute([':cod' => $codigo_archivo]);
            if ($chk->fetch()) {
                echo json_encode(['success'=>false,
                    'message'=>"El código $codigo_archivo ya existe en la base de datos"]); exit;
            }

            $archivo = $_FILES['archivo'];
            $ext     = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['kml','kmz'])) {
                echo json_encode(['success'=>false,'message'=>'Solo .kml o .kmz']); exit;
            }
            if ($archivo['size'] > 10 * 1024 * 1024) {
                echo json_encode(['success'=>false,'message'=>'El archivo supera los 10MB']); exit;
            }

            $nombreFisico = $codigo_archivo . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($archivo['tmp_name'], $uploadDir . $nombreFisico)) {
                echo json_encode(['success'=>false,'message'=>'Error al guardar en servidor']); exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO socio_ubicaciones
                    (id_socio, nombre_archivo, ruta_archivo, tipo_archivo, codigo_archivo, descripcion, subido_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_socio,
                $archivo['name'],
                'uploads/kml/' . $nombreFisico,
                $ext,
                $codigo_archivo,
                $descripcion,
                $_SESSION['usuario'],
            ]);

            echo json_encode([
                'success'        => true,
                'message'        => 'Archivo subido correctamente',
                'codigo_archivo' => $codigo_archivo,
            ]);
            break;

        // ── ACCIÓN: subir_desde_conversor ──────────────────────────────────
        case 'subir_desde_conversor':
            $id_socio       = intval($_POST['id_socio'] ?? 0);
            $codigo         = strtoupper(trim($_POST['codigo_archivo'] ?? ''));
            $descripcion    = trim($_POST['descripcion'] ?? '');
            $atributos_json = $_POST['atributos'] ?? '[]';
            $color          = trim($_POST['color'] ?? '#38bdf8');
            $titulo_aviso   = trim($_POST['titulo_aviso'] ?? '');
            $kml_content    = $_POST['kml_content'] ?? '';
            $formato_origen = trim($_POST['formato_origen'] ?? 'KML');

            if (!$id_socio || !$codigo || !$kml_content) {
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
                exit;
            }
            if (!preg_match('/^SLC-\d{3}_\d+$/i', $codigo)) {
                echo json_encode(['success' => false, 'message' => 'Código inválido. Use formato SLC-NNN_L']);
                exit;
            }
            $check = $pdo->prepare("SELECT id_ubicacion FROM socio_ubicaciones WHERE codigo_archivo = ?");
            $check->execute([$codigo]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => "El código $codigo ya existe"]);
                exit;
            }

            $uploadDir = __DIR__ . '/uploads/kml/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $filename = $codigo . '.kml';
            $filepath = $uploadDir . $filename;

            if (file_put_contents($filepath, $kml_content) === false) {
                echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo en disco']);
                exit;
            }

            $ruta_relativa = 'uploads/kml/' . $filename;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO socio_ubicaciones
                        (id_socio, nombre_archivo, ruta_archivo, tipo_archivo,
                         codigo_archivo, descripcion, color_capa, titulo_aviso,
                         atributos, subido_por, fecha_subida)
                    VALUES (?, ?, ?, 'kml', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $id_socio, $filename, $ruta_relativa, $codigo,
                    $descripcion ?: "Importado desde Mini-QGIS ($formato_origen)",
                    $color, $titulo_aviso, $atributos_json,
                    $_SESSION['usuario'] ?? 'sistema'
                ]);
                echo json_encode([
                    'success'      => true,
                    'message'      => 'Guardado correctamente',
                    'id_ubicacion' => $pdo->lastInsertId(),
                    'codigo'       => $codigo,
                    'archivo'      => $filename
                ]);
            } catch (PDOException $e) {
                @unlink($filepath);
                echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
            }
            break;

        // ═══════════════════════════════════════════════════════════════════
        // ELIMINAR archivo
        // ═══════════════════════════════════════════════════════════════════
        case 'eliminar':
            $id_ubicacion = intval($_POST['id_ubicacion'] ?? 0);
            if (!$id_ubicacion) { echo json_encode(['success'=>false,'message'=>'id requerido']); exit; }
            $st = $pdo->prepare("SELECT ruta_archivo FROM socio_ubicaciones WHERE id_ubicacion = :id");
            $st->bindValue(':id', $id_ubicacion, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'No encontrado']); exit; }
            $rutaFisica = __DIR__ . '/' . $row['ruta_archivo'];
            if (file_exists($rutaFisica)) unlink($rutaFisica);
            $pdo->prepare("DELETE FROM socio_ubicaciones WHERE id_ubicacion = :id")
                ->execute([':id' => $id_ubicacion]);
            echo json_encode(['success'=>true,'message'=>'Eliminado correctamente']);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // LEER KML
        // ═══════════════════════════════════════════════════════════════════
        case 'leer_kml':
            $id_ubicacion = intval($_GET['id_ubicacion'] ?? 0);
            if (!$id_ubicacion) { echo json_encode(['success'=>false,'message'=>'id requerido']); exit; }
            $st = $pdo->prepare("SELECT * FROM socio_ubicaciones WHERE id_ubicacion = :id");
            $st->bindValue(':id', $id_ubicacion, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'No encontrado']); exit; }
            $rutaFisica = __DIR__ . '/' . $row['ruta_archivo'];
            if (!file_exists($rutaFisica)) {
                echo json_encode(['success'=>false,'message'=>'Archivo no encontrado: '.$row['ruta_archivo']]); exit;
            }
            $contenido = file_get_contents($rutaFisica);
            if ($row['tipo_archivo'] === 'kmz') {
                $zip = new ZipArchive();
                if ($zip->open($rutaFisica) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $nz = $zip->getNameIndex($i);
                        if (strtolower(pathinfo($nz, PATHINFO_EXTENSION)) === 'kml') {
                            $contenido = $zip->getFromIndex($i); break;
                        }
                    }
                    $zip->close();
                }
            }

            $atributosBD = null;
            if (!empty($row['atributos'])) {
                $decoded = json_decode($row['atributos'], true);
                if (is_array($decoded) && count($decoded)) $atributosBD = $decoded;
            }

            echo json_encode([
                'success'        => true,
                'kml'            => base64_encode($contenido),
                'nombre'         => $row['nombre_archivo'],
                'codigo_archivo' => $row['codigo_archivo'] ?? '',
                'tipo'           => $row['tipo_archivo'],
                'descripcion'    => $row['descripcion'],
                'color_capa'     => $row['color_capa'] ?? '#38bdf8',
                'atributos'      => $atributosBD,
                'titulo_aviso'   => $row['titulo_aviso'] ?? '',
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // ACTUALIZAR ETIQUETA GLOBAL
        // ═══════════════════════════════════════════════════════════════════
        case 'actualizar_etiqueta_global':
            $id_ubicacion = intval($_POST['id_ubicacion'] ?? 0);
            if (!$id_ubicacion) {
                echo json_encode(['success'=>false,'message'=>'id_ubicacion requerido']); exit;
            }

            $nombre       = trim($_POST['nombre']       ?? '');
            $descripcion  = trim($_POST['descripcion']  ?? '');
            $color        = trim($_POST['color']        ?? '#38bdf8');
            $atributos    = trim($_POST['atributos']    ?? '[]');
            $tituloAviso  = trim($_POST['titulo_aviso'] ?? '');

            $atrsDecoded = json_decode($atributos, true);
            if (!is_array($atrsDecoded)) $atrsDecoded = [];
            $atrsLimpios = array_values(array_filter($atrsDecoded, function($a) {
                return !empty(trim($a['k'] ?? '')) || !empty(trim($a['v'] ?? ''));
            }));

            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#38bdf8';

            $updates = ['fecha_edicion = NOW()', 'editado_por = :usuario'];
            $params3 = [':id' => $id_ubicacion, ':usuario' => $_SESSION['usuario']];

            if ($nombre !== '') {
                $updates[]          = 'nombre_archivo = :nombre';
                $params3[':nombre'] = $nombre;
            }
            $updates[]         = 'descripcion = :desc';
            $params3[':desc']  = $descripcion;
            $updates[]          = 'color_capa = :color';
            $params3[':color']  = $color;
            $updates[]             = 'atributos = :atributos';
            $params3[':atributos'] = json_encode($atrsLimpios, JSON_UNESCAPED_UNICODE);
            $updates[]                = 'titulo_aviso = :titulo_aviso';
            $params3[':titulo_aviso'] = $tituloAviso;

            $pdo->prepare("UPDATE socio_ubicaciones SET ".implode(', ',$updates)." WHERE id_ubicacion = :id")
                ->execute($params3);

            echo json_encode([
                'success'      => true,
                'message'      => 'Etiqueta actualizada correctamente',
                'atributos'    => $atrsLimpios,
                'color'        => $color,
                'titulo_aviso' => $tituloAviso,
                'editado_por'  => $_SESSION['usuario'],
                'id'           => $id_ubicacion,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // ACTUALIZAR descripción / nombre (legacy)
        // ═══════════════════════════════════════════════════════════════════
        case 'actualizar_descripcion':
            $id_ubicacion = intval($_POST['id_ubicacion'] ?? 0);
            $descripcion  = trim($_POST['descripcion'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            if (!$id_ubicacion) { echo json_encode(['success'=>false,'message'=>'id requerido']); exit; }
            $updates = []; $params2 = [':id'=>$id_ubicacion];
            if ($descripcion !== '') { $updates[] = 'descripcion = :desc';   $params2[':desc'] = $descripcion; }
            if ($nombre !== '')      { $updates[] = 'nombre_archivo = :nom'; $params2[':nom']  = $nombre; }
            if (!$updates) { echo json_encode(['success'=>true,'message'=>'Sin cambios']); break; }
            $pdo->prepare("UPDATE socio_ubicaciones SET ".implode(', ',$updates)." WHERE id_ubicacion = :id")
                ->execute($params2);
            echo json_encode(['success'=>true,'message'=>'Actualizado']);
            break;

        // ═══════════════════════════════════════════════════════════════════
        // EXPORTAR ZIP — archivos de UN socio
        // FIX: inyecta atributos actualizados + descomprime KMZ
        // ═══════════════════════════════════════════════════════════════════
        case 'exportar_socio':
            $id_socio = intval($_GET['id_socio'] ?? 0);
            if (!$id_socio) { echo json_encode(['success'=>false,'message'=>'id_socio requerido']); exit; }
            $st = $pdo->prepare("
                SELECT u.*, s.identificacion,
                    COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                        TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_socio
                FROM socio_ubicaciones u
                LEFT JOIN socios s ON s.id_socio = u.id_socio
                WHERE u.id_socio = :id ORDER BY u.codigo_archivo ASC
            ");
            $st->bindValue(':id', $id_socio, PDO::PARAM_INT);
            $st->execute();
            $archivos = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$archivos) { echo json_encode(['success'=>false,'message'=>'No hay archivos']); exit; }

            $cedula    = $archivos[0]['identificacion'] ?? 'socio';
            $zipNombre = 'KML_' . preg_replace('/[^a-zA-Z0-9_]/','_',$cedula) . '_' . date('Ymd') . '.zip';
            $zipRuta   = sys_get_temp_dir() . '/' . $zipNombre;
            $zip = new ZipArchive();
            $zip->open($zipRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach ($archivos as $a) {
                $rutaFisica = __DIR__ . '/' . $a['ruta_archivo'];
                $contenido  = _leerKml($rutaFisica);
                if ($contenido === '') continue;

                // Inyectar atributos BD actualizados (description + ExtendedData)
                $contenido = _inyectarAtributos(
                    $contenido,
                    $a['atributos'] ?? '',
                    $a['titulo_aviso'] ?? '',
                    $a['codigo_archivo'] ?? ''
                );

                $nombreEnZip = ($a['codigo_archivo'] ?: pathinfo($a['nombre_archivo'], PATHINFO_FILENAME)) . '.kml';
                $zip->addFromString($nombreEnZip, $contenido);
            }

            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.$zipNombre.'"');
            header('Content-Length: '.filesize($zipRuta));
            header('Pragma: no-cache');
            readfile($zipRuta); unlink($zipRuta);
            exit;

        // ═══════════════════════════════════════════════════════════════════
        // EXPORTAR ZIP GLOBAL — todos los socios ACTIVOS con ubicación
        // FIX 1: INNER JOIN + estado='activo' → carpetas = socios con KML activos
        // FIX 2: una carpeta por socio, sin duplicados
        // FIX 3: KML con atributos BD + ExtendedData actualizados
        // FIX 4: KMZ se descomprime y exporta como .kml
        // ═══════════════════════════════════════════════════════════════════
        case 'exportar_todos':
            $st = $pdo->query("
                SELECT u.*,
                    COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                        TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_socio,
                    s.identificacion
                FROM socio_ubicaciones u
                INNER JOIN socios s ON s.id_socio = u.id_socio AND s.estado = 'activo'
                ORDER BY s.identificacion ASC, u.codigo_archivo ASC
            ");
            $archivos = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$archivos) { echo json_encode(['success'=>false,'message'=>'No hay archivos']); exit; }

            $zipNombre = 'KML_TODOS_' . date('Ymd_His') . '.zip';
            $zipRuta   = sys_get_temp_dir() . '/' . $zipNombre;
            $zip = new ZipArchive();
            $zip->open($zipRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $carpetasVistas = [];

            foreach ($archivos as $a) {
                $rutaFisica = __DIR__ . '/' . $a['ruta_archivo'];
                $contenido  = _leerKml($rutaFisica);
                if ($contenido === '') continue; // archivo físico no existe, saltar

                // Inyectar atributos BD actualizados (description + ExtendedData)
                $contenido = _inyectarAtributos(
                    $contenido,
                    $a['atributos'] ?? '',
                    $a['titulo_aviso'] ?? '',
                    $a['codigo_archivo'] ?? ''
                );

                // Una carpeta por socio (cédula + nombre, sin caracteres raros)
                $ced     = preg_replace('/[^a-zA-Z0-9_]/','_', $a['identificacion'] ?? 'sin_cedula');
                $nom     = preg_replace('/[^a-zA-Z0-9_áéíóúÁÉÍÓÚñÑ ]/u','_', trim($a['nombre_socio'] ?? ''));
                $nom     = preg_replace('/\s+/','_', mb_substr($nom, 0, 35));
                $carpeta = $ced . '_' . $nom;

                // Crear carpeta solo la primera vez para este socio
                if (!isset($carpetasVistas[$carpeta])) {
                    $zip->addEmptyDir($carpeta);
                    $carpetasVistas[$carpeta] = true;
                }

                $nombreEnZip = ($a['codigo_archivo'] ?: pathinfo($a['nombre_archivo'], PATHINFO_FILENAME)) . '.kml';
                $zip->addFromString($carpeta . '/' . $nombreEnZip, $contenido);
            }

            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.$zipNombre.'"');
            header('Content-Length: '.filesize($zipRuta));
            header('Pragma: no-cache');
            readfile($zipRuta); unlink($zipRuta);
            exit;

        // ═══════════════════════════════════════════════════════════════════
        // EXPORTAR EXCEL
        // ═══════════════════════════════════════════════════════════════════
        case 'exportar_excel':
            $q         = trim($_GET['q'] ?? '');
            $con_kml   = trim($_GET['con_kml'] ?? '');
            $adendum_f = trim($_GET['adendum_f'] ?? '');
            $params    = [];
            $where     = buildWhere($params, $q, $con_kml, $adendum_f);

            $sql = "
                SELECT
                    s.identificacion,
                    COALESCE(NULLIF(TRIM(s.nombre_completo),''),
                        TRIM(CONCAT(COALESCE(s.nombres,''),' ',COALESCE(s.apellidos,'')))) AS nombre_completo,
                    COALESCE(l.zona,'')            AS zona,
                    COALESCE(l.comunidad_grupo,'') AS comunidad_grupo,
                    COALESCE(l.adendum,0)          AS adendum,
                    u.codigo_archivo,
                    u.nombre_archivo,
                    u.tipo_archivo,
                    u.descripcion,
                    u.fecha_subida
                FROM socios s $joinLpa
                LEFT JOIN socio_ubicaciones u ON u.id_socio = s.id_socio
                $where
                ORDER BY s.identificacion ASC, u.codigo_archivo ASC, u.fecha_subida ASC
            ";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $nombreXlsx = 'Ubicaciones_KML_' . date('Ymd_His') . '.xlsx';
            $xlsxRuta   = sys_get_temp_dir() . '/' . $nombreXlsx;
            $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');

            $headerCols = [
                'A1'=>'CÉDULA','B1'=>'NOMBRE PRODUCTOR/A','C1'=>'ZONA','D1'=>'COMUNIDAD',
                'E1'=>'ADENDUM','F1'=>'CÓDIGO KML','G1'=>'ARCHIVO','H1'=>'TIPO',
                'I1'=>'DESCRIPCIÓN','J1'=>'FECHA SUBIDA',
            ];
            $rowsXml = '<row r="1">';
            foreach ($headerCols as $ref => $titulo)
                $rowsXml .= '<c r="'.$ref.'" t="inlineStr" s="1"><is><t>'.$x($titulo).'</t></is></c>';
            $rowsXml .= '</row>'."\n";

            $rowNum = 2;
            foreach ($filas as $f) {
                $adendumLabel = $f['adendum']==2?'Adendum 2':($f['adendum']==1?'Adendum 1':'-');
                $fechaVal     = $f['fecha_subida'] ? date('d/m/Y H:i', strtotime($f['fecha_subida'])) : '';
                $rowsXml .= '<row r="'.$rowNum.'">';
                $rowsXml .= '<c r="A'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['identificacion']).'</t></is></c>';
                $rowsXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['nombre_completo']).'</t></is></c>';
                $rowsXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['zona']).'</t></is></c>';
                $rowsXml .= '<c r="D'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['comunidad_grupo']).'</t></is></c>';
                $rowsXml .= '<c r="E'.$rowNum.'" t="inlineStr"><is><t>'.$x($adendumLabel).'</t></is></c>';
                $rowsXml .= '<c r="F'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['codigo_archivo']??'').'</t></is></c>';
                $rowsXml .= '<c r="G'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['nombre_archivo']??'').'</t></is></c>';
                $rowsXml .= '<c r="H'.$rowNum.'" t="inlineStr"><is><t>'.$x(strtoupper($f['tipo_archivo']??'')).'</t></is></c>';
                $rowsXml .= '<c r="I'.$rowNum.'" t="inlineStr"><is><t>'.$x($f['descripcion']??'').'</t></is></c>';
                $rowsXml .= '<c r="J'.$rowNum.'" t="inlineStr"><is><t>'.$x($fechaVal).'</t></is></c>';
                $rowsXml .= '</row>'."\n";
                $rowNum++;
            }

            $widths = [14,36,16,20,12,14,30,7,30,18];
            $colsXml = '';
            foreach ($widths as $i => $w)
                $colsXml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$w.'" customWidth="1"/>';

            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetViews><sheetView workbookViewId="0" tabSelected="1"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="15"/>
  <cols>'.$colsXml.'</cols>
  <sheetData>'.$rowsXml.'</sheetData>
  <autoFilter ref="A1:J1"/>
</worksheet>';

            $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><sz val="11"/><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill></fills>
  <borders count="1"><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>
</styleSheet>';

            $zip = new ZipArchive();
            $zip->open($xlsxRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Ubicaciones KML" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->addFromString('xl/styles.xml', $stylesXml);
            $zip->close();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$nombreXlsx.'"');
            header('Content-Length: '.filesize($xlsxRuta));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            readfile($xlsxRuta); unlink($xlsxRuta);
            exit;

        // ═══════════════════════════════════════════════════════════════════
        // EXPORTAR RESUMEN EXCEL — lotes de un socio con hectáreas
        // ═══════════════════════════════════════════════════════════════════
        case 'exportar_resumen_excel':
            $payload = json_decode(trim($_POST['payload'] ?? '{}'), true);
            if (!$payload) { echo json_encode(['success'=>false,'message'=>'Payload inválido']); exit; }

            $cedula    = $payload['cedula']    ?? '';
            $nombre    = $payload['nombre']    ?? '';
            $zona      = $payload['zona']      ?? '';
            $codigoSlc = $payload['codigoSlc'] ?? '';
            $lotes     = $payload['lotes']     ?? [];
            $totalHa   = (float)($payload['totalHa'] ?? 0);

            $nombreXlsx = 'Lotes_' . preg_replace('/[^a-zA-Z0-9_]/','_',$cedula) . '_' . date('Ymd_His') . '.xlsx';
            $xlsxRuta   = sys_get_temp_dir() . '/' . $nombreXlsx;
            $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');

            $rowsXml  = '<row r="1">';
            $rowsXml .= '<c r="A1" t="inlineStr" s="1"><is><t>SOCIO:</t></is></c>';
            $rowsXml .= '<c r="B1" t="inlineStr"><is><t>'.$x($nombre).'</t></is></c>';
            $rowsXml .= '<c r="C1" t="inlineStr"><is><t>CÉDULA: '.$x($cedula).'</t></is></c>';
            $rowsXml .= '<c r="D1" t="inlineStr"><is><t>ZONA: '.$x($zona).'</t></is></c>';
            $rowsXml .= '<c r="E1" t="inlineStr"><is><t>CÓDIGO: '.$x($codigoSlc).'</t></is></c>';
            $rowsXml .= '<c r="F1" t="inlineStr"><is><t></t></is></c>';
            $rowsXml .= '</row>'."\n";
            $rowsXml .= '<row r="2">';
            foreach (['A'=>'#','B'=>'CÓDIGO','C'=>'HECTÁREAS','D'=>'DESCRIPCIÓN','E'=>'ESTADO','F'=>'FECHA'] as $col=>$h)
                $rowsXml .= '<c r="'.$col.'2" t="inlineStr" s="1"><is><t>'.$x($h).'</t></is></c>';
            $rowsXml .= '</row>'."\n";

            $rowNum = 3;
            foreach ($lotes as $i => $l) {
                $ha    = isset($l['hectareas']) && $l['hectareas'] !== null ? number_format((float)$l['hectareas'],3,'.','') : '';
                $fecha = !empty($l['fecha']) ? date('d/m/Y', strtotime($l['fecha'])) : '';
                $rowsXml .= '<row r="'.$rowNum.'">';
                $rowsXml .= '<c r="A'.$rowNum.'" t="inlineStr"><is><t>'.($i+1).'</t></is></c>';
                $rowsXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['codigo']??'').'</t></is></c>';
                $rowsXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.$x($ha).'</t></is></c>';
                $rowsXml .= '<c r="D'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['descripcion']??'').'</t></is></c>';
                $rowsXml .= '<c r="E'.$rowNum.'" t="inlineStr"><is><t>Activo</t></is></c>';
                $rowsXml .= '<c r="F'.$rowNum.'" t="inlineStr"><is><t>'.$x($fecha).'</t></is></c>';
                $rowsXml .= '</row>'."\n";
                $rowNum++;
            }
            $rowsXml .= '<row r="'.$rowNum.'">';
            $rowsXml .= '<c r="A'.$rowNum.'" t="inlineStr" s="1"><is><t>TOTAL</t></is></c>';
            $rowsXml .= '<c r="B'.$rowNum.'" t="inlineStr" s="1"><is><t></t></is></c>';
            $rowsXml .= '<c r="C'.$rowNum.'" t="inlineStr" s="1"><is><t>'.($totalHa>0?number_format($totalHa,3,'.',''):'').'</t></is></c>';
            $rowsXml .= '<c r="D'.$rowNum.'" t="inlineStr" s="1"><is><t></t></is></c>';
            $rowsXml .= '<c r="E'.$rowNum.'" t="inlineStr" s="1"><is><t></t></is></c>';
            $rowsXml .= '<c r="F'.$rowNum.'" t="inlineStr" s="1"><is><t></t></is></c>';
            $rowsXml .= '</row>'."\n";

            $colsXml2 = '<col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/><col min="4" max="4" width="28" customWidth="1"/><col min="5" max="5" width="12" customWidth="1"/><col min="6" max="6" width="16" customWidth="1"/>';
            $sheetXml2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetFormatPr defaultRowHeight="15"/><cols>'.$colsXml2.'</cols><sheetData>'.$rowsXml.'</sheetData><autoFilter ref="A2:F2"/></worksheet>';
            $stylesXml2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><sz val="11"/><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>';

            $zip2 = new ZipArchive();
            $zip2->open($xlsxRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip2->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip2->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip2->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Lotes KML" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip2->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip2->addFromString('xl/worksheets/sheet1.xml', $sheetXml2);
            $zip2->addFromString('xl/styles.xml', $stylesXml2);
            $zip2->close();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$nombreXlsx.'"');
            header('Content-Length: '.filesize($xlsxRuta));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            readfile($xlsxRuta); unlink($xlsxRuta);
            exit;

        // ═══════════════════════════════════════════════════════════════════
        // EXPORTAR RESUMEN GLOBAL EXCEL
        // ═══════════════════════════════════════════════════════════════════
        case 'exportar_resumen_global_excel':
            $payload = json_decode(trim($_POST['payload'] ?? '{}'), true);
            if (!$payload) { echo json_encode(['success'=>false,'message'=>'Payload inválido']); exit; }

            $lotes         = $payload['lotes']        ?? [];
            $totalHaGlobal = (float)($payload['totalHaGlobal'] ?? 0);

            $nombreXlsx = 'Resumen_Global_KML_' . date('Ymd_His') . '.xlsx';
            $xlsxRuta   = sys_get_temp_dir() . '/' . $nombreXlsx;
            $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');

            $headers = ['A'=>'CÉDULA','B'=>'NOMBRE PRODUCTOR/A','C'=>'ZONA','D'=>'COMUNIDAD','E'=>'ADENDUM','F'=>'CÓDIGO KML','G'=>'HECTÁREAS'];
            $rowsXml = '<row r="1">';
            foreach ($headers as $col=>$h)
                $rowsXml .= '<c r="'.$col.'1" t="inlineStr" s="1"><is><t>'.$x($h).'</t></is></c>';
            $rowsXml .= '</row>'."\n";

            $rowNum = 2;
            foreach ($lotes as $l) {
                $ha = isset($l['hectareas']) && $l['hectareas'] !== null ? number_format((float)$l['hectareas'],3,'.','') : '';
                $rowsXml .= '<row r="'.$rowNum.'">';
                $rowsXml .= '<c r="A'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['cedula']??'').'</t></is></c>';
                $rowsXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['nombre']??'').'</t></is></c>';
                $rowsXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['zona']??'').'</t></is></c>';
                $rowsXml .= '<c r="D'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['comunidad']??'').'</t></is></c>';
                $rowsXml .= '<c r="E'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['adendum']??'').'</t></is></c>';
                $rowsXml .= '<c r="F'.$rowNum.'" t="inlineStr"><is><t>'.$x($l['codigo']??'').'</t></is></c>';
                $rowsXml .= '<c r="G'.$rowNum.'" t="inlineStr"><is><t>'.$x($ha).'</t></is></c>';
                $rowsXml .= '</row>'."\n";
                $rowNum++;
            }
            $rowsXml .= '<row r="'.$rowNum.'">';
            $rowsXml .= '<c r="A'.$rowNum.'" t="inlineStr" s="1"><is><t>TOTAL GLOBAL</t></is></c>';
            for ($cc = ord('B'); $cc <= ord('F'); $cc++)
                $rowsXml .= '<c r="'.chr($cc).$rowNum.'" t="inlineStr" s="1"><is><t></t></is></c>';
            $rowsXml .= '<c r="G'.$rowNum.'" t="inlineStr" s="1"><is><t>'.($totalHaGlobal>0?number_format($totalHaGlobal,3,'.',''):'').'</t></is></c>';
            $rowsXml .= '</row>'."\n";

            $colsXmlG = '<col min="1" max="1" width="14" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/><col min="3" max="3" width="16" customWidth="1"/><col min="4" max="4" width="20" customWidth="1"/><col min="5" max="5" width="12" customWidth="1"/><col min="6" max="6" width="16" customWidth="1"/><col min="7" max="7" width="14" customWidth="1"/>';
            $sheetXmlG = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetFormatPr defaultRowHeight="15"/><cols>'.$colsXmlG.'</cols><sheetData>'.$rowsXml.'</sheetData><autoFilter ref="A1:G1"/></worksheet>';
            $stylesXmlG = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><sz val="11"/><b/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>';

            $zip3 = new ZipArchive();
            $zip3->open($xlsxRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip3->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip3->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip3->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Resumen Global KML" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip3->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip3->addFromString('xl/worksheets/sheet1.xml', $sheetXmlG);
            $zip3->addFromString('xl/styles.xml', $stylesXmlG);
            $zip3->close();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$nombreXlsx.'"');
            header('Content-Length: '.filesize($xlsxRuta));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            readfile($xlsxRuta); unlink($xlsxRuta);
            exit;

        // ═══════════════════════════════════════════════════════════════════
        // ACTUALIZAR KML EDITADO
        // ═══════════════════════════════════════════════════════════════════
        case 'actualizar_kml_editado':
            $id_ubicacion = intval($_POST['id_ubicacion'] ?? 0);
            $kml_content  = trim($_POST['kml_content']   ?? '');
            $atributos    = trim($_POST['atributos']      ?? '[]');

            if (!$id_ubicacion) { echo json_encode(['success'=>false,'message'=>'id_ubicacion requerido']); exit; }
            if (empty($kml_content)) { echo json_encode(['success'=>false,'message'=>'Contenido KML vacío']); exit; }

            $st = $pdo->prepare("SELECT ruta_archivo, nombre_archivo, codigo_archivo FROM socio_ubicaciones WHERE id_ubicacion = :id");
            $st->bindValue(':id', $id_ubicacion, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Registro no encontrado']); exit; }

            $rutaFisica = __DIR__ . '/' . $row['ruta_archivo'];
            $rutaKml    = $rutaFisica;

            if (strtolower(pathinfo($rutaFisica, PATHINFO_EXTENSION)) === 'kmz') {
                $rutaKml           = preg_replace('/\.kmz$/i', '_editado.kml', $rutaFisica);
                $nuevaRutaRelativa = preg_replace('/\.kmz$/i', '_editado.kml', $row['ruta_archivo']);
                $pdo->prepare("UPDATE socio_ubicaciones SET ruta_archivo = :ruta, tipo_archivo = 'kml' WHERE id_ubicacion = :id")
                    ->execute([':ruta' => $nuevaRutaRelativa, ':id' => $id_ubicacion]);
            }

            $written = file_put_contents($rutaKml, $kml_content);
            if ($written === false) {
                echo json_encode(['success'=>false,'message'=>'No se pudo escribir el archivo. Verifica permisos en uploads/kml/']); exit;
            }

            $atrsDecoded = json_decode($atributos, true);
            if (!is_array($atrsDecoded)) $atrsDecoded = [];

            $nuevaHa = null;
            foreach ($atrsDecoded as $atr) {
                if (($atr['tipo'] ?? '') === 'area') { $nuevaHa = floatval($atr['v'] ?? 0); break; }
            }

            $atrsLimpios = array_values(array_filter($atrsDecoded, function($a) {
                return !empty(trim($a['k'] ?? '')) || !empty(trim($a['v'] ?? ''));
            }));

            $pdo->prepare("UPDATE socio_ubicaciones SET atributos=:atributos, editado_por=:usuario, fecha_edicion=NOW() WHERE id_ubicacion=:id")
                ->execute([':atributos'=>json_encode($atrsLimpios,JSON_UNESCAPED_UNICODE),':usuario'=>$_SESSION['usuario'],':id'=>$id_ubicacion]);

            echo json_encode([
                'success'=>true,'message'=>'KML actualizado correctamente',
                'hectareas'=>$nuevaHa,'ruta'=>$row['ruta_archivo'],'editado_por'=>$_SESSION['usuario'],
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ═══════════════════════════════════════════════════════════════════
        case 'descargar_kml_actualizado':
            $id = intval($_GET['id_ubicacion'] ?? 0);
            if (!$id) { exit; }
            $st = $pdo->prepare("SELECT * FROM socio_ubicaciones WHERE id_ubicacion = :id");
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { exit; }
            $contenido = _leerKml(__DIR__.'/'.$row['ruta_archivo']);
            $contenido = _inyectarAtributos($contenido, $row['atributos']??'', $row['titulo_aviso']??'', $row['codigo_archivo']??'');
            header('Content-Type: application/vnd.google-earth.kml+xml');
            header('Content-Disposition: attachment; filename="'.($row['codigo_archivo']??'archivo').'.kml"');
            echo $contenido;
            exit;

        // ═══════════════════════════════════════════════════════════════════
        default:
            echo json_encode(['success'=>false,'message'=>'Acción no reconocida: '.$accion]);
    }

} catch (Exception $e) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>