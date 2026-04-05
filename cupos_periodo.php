<?php
// cupos_periodo.php
// Devuelve JSON con la lista de productores LPA y sus cupos actuales
// También devuelve estadísticas y el periodo activo

require "layout/bootstrap.php";
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require __DIR__ . "/config/conexion.php";

$accion = $_GET['accion'] ?? 'lista';

// ── Helpers ──────────────────────────────────────────────────────────────────
function getPeriodoActivo($pdo) {
    $stmt = $pdo->query("
        SELECT id_periodo, nombre, estado, fecha_apertura, fecha_cierre
        FROM periodo_comercializacion
        WHERE estado = 'ABIERTO'
        ORDER BY id_periodo DESC
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {

    // ── Acción: periodo activo ────────────────────────────────────────────────
    if ($accion === 'periodo_activo') {
        $periodo = getPeriodoActivo($pdo);
        if ($periodo) {
            echo json_encode(['success' => true, 'periodo' => $periodo]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sin periodo activo']);
        }
        exit;
    }

    // ── Acción: solo estadísticas ─────────────────────────────────────────────
    if ($accion === 'stats') {
        $stmt = $pdo->query("
            SELECT
                COUNT(*)                                                         AS total,
                SUM(IF(volumen_produccion_estimado > 0, 1, 0))                   AS con_cupo,
                SUM(IF(volumen_produccion_estimado IS NULL OR volumen_produccion_estimado = 0, 1, 0)) AS sin_cupo,
                SUM(IFNULL(volumen_produccion_estimado, 0))                      AS kg_total
            FROM tabla_lpa
            WHERE estado_lpa = 'activo'
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    // ── Acción: lista principal ───────────────────────────────────────────────
    $pagina    = max(1, intval($_GET['pagina'] ?? 1));
    $porPagina = 15;
    $offset    = ($pagina - 1) * $porPagina;

    $q       = trim($_GET['q']       ?? '');
    $adendum = trim($_GET['adendum'] ?? '');
    $estado  = trim($_GET['estado']  ?? '');

    // Condiciones WHERE
    $wheres = [];
    $params = [];

    if ($q !== '') {
        $wheres[] = "(s.identificacion LIKE :q OR s.nombre_completo LIKE :q2)";
        $params[':q']  = '%' . $q . '%';
        $params[':q2'] = '%' . $q . '%';
    }
    if ($adendum !== '') {
        $wheres[] = "l.adendum = :adendum";
        $params[':adendum'] = $adendum;
    }
    if ($estado !== '') {
        $wheres[] = "l.estado_lpa = :estado";
        $params[':estado'] = $estado;
    }

    $whereSQL = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

    // Total de registros
    $sqlCount = "
        SELECT COUNT(*) AS total
        FROM tabla_lpa l
        LEFT JOIN socios s ON s.id_socio = l.id_socio
        $whereSQL
    ";
    $stCount = $pdo->prepare($sqlCount);
    foreach ($params as $k => $v) $stCount->bindValue($k, $v);
    $stCount->execute();
    $totalReg = $stCount->fetchColumn();

    // Datos principales
    $sql = "
        SELECT
            l.id_lpa,
            l.id_socio,
            l.adendum,
            l.zona,
            l.area_cacao_ha,
            l.volumen_produccion_estimado,
            l.estado_lpa,
            l.anio,
            s.identificacion,
            COALESCE(s.nombre_completo, CONCAT(s.nombres, ' ', s.apellidos)) AS nombre_completo,
            s.sexo,
            s.telefono,
            IFNULL(
                (SELECT SUM(v.cantidad_vende)
                 FROM tabla_ventas v
                 WHERE v.id_lpa = l.id_lpa),
                0
            ) AS cupo_consumido
        FROM tabla_lpa l
        LEFT JOIN socios s ON s.id_socio = l.id_socio
        $whereSQL
        ORDER BY s.nombre_completo ASC
        LIMIT :limit OFFSET :offset
    ";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset,    PDO::PARAM_INT);
    $st->execute();
    $datos = $st->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas globales
    $stStats = $pdo->query("
        SELECT
            COUNT(*)                                                         AS total,
            SUM(IF(volumen_produccion_estimado > 0, 1, 0))                   AS con_cupo,
            SUM(IF(volumen_produccion_estimado IS NULL OR volumen_produccion_estimado = 0, 1, 0)) AS sin_cupo,
            SUM(IFNULL(volumen_produccion_estimado, 0))                      AS kg_total
        FROM tabla_lpa
    ");
    $stats = $stStats->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'      => true,
        'datos'        => $datos,
        'pagina'       => $pagina,
        'totalPaginas' => (int) ceil($totalReg / $porPagina),
        'total'        => (int) $totalReg,
        'porPagina'    => $porPagina,
        'stats'        => $stats,
    ]);

} catch (PDOException $e) {
    error_log("cupos_periodo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>