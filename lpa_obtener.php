<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

require "config/conexion.php";

$pagina        = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$porPagina     = 15;
$offset        = ($pagina - 1) * $porPagina;
$buscar        = isset($_GET['q'])       ? trim($_GET['q'])       : '';
$adendumFiltro = isset($_GET['adendum']) ? trim($_GET['adendum']) : '';

// Detectar si la columna adendum existe
$tieneAdendum = false;
try {
    $pdo->query("SELECT adendum FROM tabla_lpa LIMIT 1");
    $tieneAdendum = true;
} catch (Exception $e) {
    $tieneAdendum = false;
}

$adendumSelect = $tieneAdendum ? "l.adendum," : "'1' AS adendum,";

try {
    $conditions = [];
    $params     = [];

    // BÚSQUEDA MEJORADA - usando parámetros únicos para cada campo
    if ($buscar !== '') {
        $searchValue = "%{$buscar}%";
        $conditions[] = "(
            s.identificacion LIKE :q1 OR 
            s.nombre_completo LIKE :q2 OR 
            s.nombres LIKE :q3 OR 
            s.apellidos LIKE :q4 OR
            CONCAT(COALESCE(s.nombres,''), ' ', COALESCE(s.apellidos,'')) LIKE :q5
        )";
        $params[':q1'] = $searchValue;
        $params[':q2'] = $searchValue;
        $params[':q3'] = $searchValue;
        $params[':q4'] = $searchValue;
        $params[':q5'] = $searchValue;
    }

    // Filtro por periodo (nuevo)
    $periodoBuscar = isset($_GET['id_periodo']) ? trim($_GET['id_periodo']) : '';
    if ($periodoBuscar !== '') {
        $conditions[]          = "l.id_periodo = :id_periodo";
        $params[':id_periodo'] = $periodoBuscar;
    }

    // Filtro por adendum
    $adendumBuscar = isset($_GET['adendum'])      ? trim($_GET['adendum']) : '';
    $soloAdendum   = isset($_GET['solo_adendum']) ? trim($_GET['solo_adendum']) : '';
    if ($soloAdendum === '1' && $adendumBuscar !== '') {
        $conditions[]       = "l.id_lpa < 432 AND l.adendum = :adendum";
        $params[':adendum'] = $adendumBuscar;
    } else if ($tieneAdendum && $adendumFiltro !== '') {
        $conditions[]      = "l.adendum = :adendum";
        $params[':adendum'] = $adendumFiltro;
    }

    $where = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

    // Contar total de registros
    $sqlCount = "SELECT COUNT(*) FROM tabla_lpa l LEFT JOIN socios s ON l.id_socio = s.id_socio $where";
    $stmtTotal = $pdo->prepare($sqlCount);
    
    foreach ($params as $key => $val) {
        $stmtTotal->bindValue($key, $val, PDO::PARAM_STR);
    }
    
    $stmtTotal->execute();
    $total        = (int)$stmtTotal->fetchColumn();
    $totalPaginas = max(1, (int)ceil($total / $porPagina));

    // Query principal
    $sql = "
        SELECT
            l.id_lpa,
            l.id_socio,
            l.anio,
            $adendumSelect
            l.zona,
            l.comunidad_grupo,
            l.en_acercamiento,
            l.otra_org_fairtrade,
            l.area_total_ha,
            l.area_cacao_ha,
            l.num_matas_ha,
            l.certificacion_organica,
            l.volumen_produccion_estimado,
            l.volumen_entregado_org,
            l.estado_lpa,
            l.enero, l.febrero, l.marzo, l.abril,
            l.mayo, l.junio, l.julio, l.agosto,
            l.septiembre, l.octubre, l.noviembre, l.diciembre,
            s.identificacion,
            COALESCE(s.nombre_completo, CONCAT(COALESCE(s.nombres,''), ' ', COALESCE(s.apellidos,''))) AS nombre_completo,
            s.nombres,
            s.apellidos,
            COALESCE(s.sexo,'') AS sexo,
            s.fecha_nacimiento,
            s.telefono,
            s.correo,
            s.fecha_ingreso
        FROM tabla_lpa l
        LEFT JOIN socios s ON l.id_socio = s.id_socio
        $where
        ORDER BY COALESCE(s.nombre_completo, CONCAT(COALESCE(s.nombres,''), ' ', COALESCE(s.apellidos,''))) ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    
    // Bindear parámetros de búsqueda
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    
    // Bindear parámetros de paginación
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'      => true,
        'datos'        => $datos,
        'total'        => $total,
        'pagina'       => $pagina,
        'totalPaginas' => $totalPaginas,
        'porPagina'    => $porPagina
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error'   => true, 
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}