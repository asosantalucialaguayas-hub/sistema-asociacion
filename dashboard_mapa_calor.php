<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false]); exit; }
require __DIR__ . "/config/conexion.php";

try {
    // Socios por zona (desde tabla_lpa, último LPA por socio)
    $stmt = $pdo->query("
        SELECT
            COALESCE(NULLIF(TRIM(l.zona),''), 'Sin zona') AS nombre,
            COUNT(DISTINCT l.id_socio) AS total
        FROM tabla_lpa l
        INNER JOIN (
            SELECT id_socio, MAX(id_lpa) AS max_id FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio
        ) mx ON l.id_socio = mx.id_socio AND l.id_lpa = mx.max_id
        GROUP BY nombre
        ORDER BY total DESC
        LIMIT 30
    ");
    $zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Socios por comunidad/grupo (desde tabla_lpa)
    $stmt2 = $pdo->query("
        SELECT
            COALESCE(NULLIF(TRIM(l.comunidad_grupo),''), 'Sin comunidad') AS nombre,
            COUNT(DISTINCT l.id_socio) AS total
        FROM tabla_lpa l
        INNER JOIN (
            SELECT id_socio, MAX(id_lpa) AS max_id FROM tabla_lpa WHERE id_socio IS NOT NULL GROUP BY id_socio
        ) mx ON l.id_socio = mx.id_socio AND l.id_lpa = mx.max_id
        GROUP BY nombre
        ORDER BY total DESC
        LIMIT 40
    ");
    $comunidades = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'     => true,
        'zonas'       => $zonas,
        'comunidades' => $comunidades,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
