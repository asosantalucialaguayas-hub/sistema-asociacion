<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode([]); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

$conv_id         = intval($_GET['conv_id'] ?? 0);
$tipo_asistentes = trim($_GET['tipo_asistentes'] ?? 'general');
$q_raw           = trim($_GET['q'] ?? '');
$q               = '%' . $q_raw . '%';

try {
    if ($tipo_asistentes === 'solo_directivos') {

        $stP  = $pdo->query("SELECT id FROM directiva_periodos WHERE estado='activo' ORDER BY id DESC LIMIT 1");
        $pRow = $stP->fetch(PDO::FETCH_ASSOC);
        if (!$pRow) {
            echo json_encode([['_error' => 'No hay período de directiva activo.']]);
            exit;
        }
        $pid = (int)$pRow['id'];

        // Usar CONVERT para forzar collation uniforme en todo
        $st = $pdo->prepare("
            SELECT
                COALESCE(s.id_socio, dm.socio_id)                                          AS id,
                COALESCE(s.identificacion, dm.cedula_manual)                                AS cedula,
                COALESCE(s.nombre_completo, dm.nombre_manual)                               AS nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)                                                  AS ya_registro,
                IF(s.id_socio IS NOT NULL, 0, 1)                                            AS sin_socio,
                MIN(dm.orden_cargo)                                                          AS orden_cargo
            FROM directiva_miembros dm
            LEFT JOIN socios s
                   ON CONVERT(s.identificacion USING utf8mb4) COLLATE utf8mb4_general_ci
                    = CONVERT(dm.cedula_manual  USING utf8mb4) COLLATE utf8mb4_general_ci
                  AND s.estado = 'activo'
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = COALESCE(s.id_socio, dm.socio_id)
                  AND a.convocatoria_id = ?
            WHERE dm.periodo_id = ?
              AND (
                    CONVERT(COALESCE(s.nombre_completo, dm.nombre_manual) USING utf8mb4)
                        COLLATE utf8mb4_general_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
                 OR CONVERT(COALESCE(s.identificacion, dm.cedula_manual)  USING utf8mb4)
                        COLLATE utf8mb4_general_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
              )
            GROUP BY CONVERT(COALESCE(s.identificacion, dm.cedula_manual) USING utf8mb4) COLLATE utf8mb4_general_ci
            ORDER BY sin_socio ASC, orden_cargo ASC, nombre_completo ASC
            LIMIT 15
        ");
        $st->execute([$conv_id, $pid, $q, $q]);

    } else {
        // Búsqueda general — también con CONVERT para evitar collation mismatch
        $st = $pdo->prepare("
            SELECT
                s.id_socio                         AS id,
                s.identificacion                   AS cedula,
                s.nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)         AS ya_registro
            FROM socios s
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = s.id_socio
                  AND a.convocatoria_id = ?
            WHERE s.estado = 'activo'
              AND (
                    CONVERT(s.nombre_completo USING utf8mb4) COLLATE utf8mb4_general_ci
                        LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
                 OR CONVERT(s.identificacion  USING utf8mb4) COLLATE utf8mb4_general_ci
                        LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
              )
            ORDER BY s.nombre_completo
            LIMIT 10
        ");
        $st->execute([$conv_id, $q, $q]);
    }

    $socios = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($socios as &$s) {
        $p = explode(' ', trim($s['nombre_completo'] ?? ''));
        $s['iniciales'] = strtoupper(substr($p[0]??'',0,1) . substr($p[1]??'',0,1));
        unset($s['sin_socio'], $s['orden_cargo']);
    }
    unset($s);

    echo json_encode($socios);

} catch (Exception $e) {
    echo json_encode([['_error' => $e->getMessage()]]);
}