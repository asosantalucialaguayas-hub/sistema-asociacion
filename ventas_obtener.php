<?php
require "layout/bootstrap.php";
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}
require __DIR__ . "/config/conexion.php";
if (!isset($pdo) || $pdo === null) {
    echo json_encode(['error' => 'Error de conexion a la base de datos']);
    exit;
}
try {
    $buscar = $_GET['buscar'] ?? '';
    $mes    = $_GET['mes']    ?? '';
    $anio   = $_GET['anio']   ?? '';

    // ── PAGINACION ──────────────────────────────────────────
    $pagina    = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $porPagina = 15;
    $offset    = ($pagina - 1) * $porPagina;
    // ───────────────────────────────────────────────────────

    // Query base (sin LIMIT todavia)
    $sqlBase = "
        SELECT 
            l.id_lpa,
            l.id_socio,
            s.identificacion,
            s.nombre_completo,
            s.sexo,
            IFNULL(l.volumen_produccion_estimado, 0) AS cupo_total,
            IFNULL(
                (SELECT SUM(v.cantidad_vende) 
                 FROM tabla_ventas v 
                 WHERE v.id_lpa = l.id_lpa";

    if ($mes !== '')  { $sqlBase .= " AND MONTH(v.fecha_venta) = :mes"; }
    if ($anio !== '') { $sqlBase .= " AND YEAR(v.fecha_venta) = :anio"; }

    $sqlBase .= "
                ), 0
            ) AS cupo_consumido,
            (
                IFNULL(l.volumen_produccion_estimado, 0) - 
                IFNULL(
                    (SELECT SUM(v.cantidad_vende) 
                     FROM tabla_ventas v 
                     WHERE v.id_lpa = l.id_lpa";

    if ($mes !== '')  { $sqlBase .= " AND MONTH(v.fecha_venta) = :mes2"; }
    if ($anio !== '') { $sqlBase .= " AND YEAR(v.fecha_venta) = :anio2"; }

    $sqlBase .= "
                    ), 0
                )
            ) AS cupo_disponible
        FROM tabla_lpa l
        INNER JOIN socios s ON l.id_socio = s.id_socio
        WHERE l.estado_lpa = 'activo'";

    if ($buscar !== '') {
        $sqlBase .= " AND (s.identificacion LIKE :buscar OR s.nombre_completo LIKE :buscar2)";
    }

    $sqlBase .= " ORDER BY s.nombre_completo ASC";

    // Funcion para hacer bind de parametros comunes
    $bindParams = function($stmt) use ($mes, $anio, $buscar) {
        if ($mes !== '') {
            $stmt->bindValue(':mes',  $mes, PDO::PARAM_STR);
            $stmt->bindValue(':mes2', $mes, PDO::PARAM_STR);
        }
        if ($anio !== '') {
            $stmt->bindValue(':anio',  $anio, PDO::PARAM_STR);
            $stmt->bindValue(':anio2', $anio, PDO::PARAM_STR);
        }
        if ($buscar !== '') {
            $bp = '%' . $buscar . '%';
            $stmt->bindValue(':buscar',  $bp, PDO::PARAM_STR);
            $stmt->bindValue(':buscar2', $bp, PDO::PARAM_STR);
        }
    };

    // ── CONTAR TOTAL ────────────────────────────────────────
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ($sqlBase) AS sub");
    $bindParams($stmtCount);
    $stmtCount->execute();
    $total        = (int)$stmtCount->fetchColumn();
    $totalPaginas = (int)ceil($total / $porPagina);
    // ───────────────────────────────────────────────────────

    // Query con LIMIT y OFFSET
    $sqlPag = $sqlBase . " LIMIT :limit OFFSET :offset";
    $stmt   = $pdo->prepare($sqlPag);
    $bindParams($stmt);
    $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── RESPUESTA CON PAGINACION ────────────────────────────
    echo json_encode([
        'success'      => true,
        'datos'        => $resultados,
        'pagina'       => $pagina,
        'porPagina'    => $porPagina,
        'total'        => $total,
        'totalPaginas' => $totalPaginas
    ], JSON_UNESCAPED_UNICODE);
    // ───────────────────────────────────────────────────────

} catch(PDOException $e) {
    error_log("Error en ventas_obtener.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al obtener datos', 'details' => $e->getMessage()]);
}
?>