<?php
// ============================================================
// ajax_buscar_socio.php
// Tabla socios: id_socio, identificacion, nombre_completo
// Para convocatorias solo_directivos: directiva_miembros
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode([]); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

$conv_id         = intval($_GET['conv_id'] ?? 0);
$tipo_asistentes = trim($_GET['tipo_asistentes'] ?? 'general');
$id_periodo      = intval($_GET['id_periodo'] ?? 0);
$q               = '%' . trim($_GET['q'] ?? '') . '%';

try {
    if ($tipo_asistentes === 'solo_directivos') {

        // ── Buscar el período activo si no viene id_periodo ───────
        if (!$id_periodo) {
            $stP = $pdo->query("SELECT id FROM directiva_periodos WHERE estado='activo' LIMIT 1");
            $pRow = $stP->fetch(PDO::FETCH_ASSOC);
            $id_periodo = $pRow ? (int)$pRow['id'] : 0;
        }

        if (!$id_periodo) {
            echo json_encode([['_error' => 'No hay período de directiva activo']]);
            exit;
        }

        // ── Obtener miembros únicos de AMBAS juntas (directiva + vigilancia)
        // Deduplicar por cédula: si alguien tiene 2 cargos, aparece una sola vez
        // Preferir el registro vinculado a socios (socio_id no nulo)
        $st = $pdo->prepare("
            SELECT
                /* Usar socio_id real si existe, sino usar el id del miembro como negativo */
                COALESCE(s.id_socio, dm.socio_id)        AS id,
                COALESCE(s.identificacion, dm.cedula_manual) AS cedula,
                COALESCE(s.nombre_completo, dm.nombre_manual) AS nombre_completo,
                /* ¿Ya registró asistencia? Comparar por id_socio si hay vínculo */
                IF(
                    a.id IS NOT NULL,
                    1, 0
                )                                         AS ya_registro,
                /* Para ordenar: primero los que tienen socio vinculado */
                IF(s.id_socio IS NOT NULL, 0, 1)          AS sin_socio,
                MIN(dm.orden_cargo)                        AS orden_cargo
            FROM directiva_miembros dm
            LEFT JOIN socios s
                   ON s.identificacion = dm.cedula_manual
                  AND s.estado = 'activo'
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = COALESCE(s.id_socio, dm.socio_id)
                  AND a.convocatoria_id = ?
            WHERE dm.periodo_id = ?
              AND (
                    UPPER(dm.nombre_manual) LIKE UPPER(?)
                 OR dm.cedula_manual        LIKE ?
                 OR UPPER(COALESCE(s.nombre_completo,'')) LIKE UPPER(?)
              )
            GROUP BY
                COALESCE(s.identificacion, dm.cedula_manual)
            ORDER BY sin_socio ASC, orden_cargo ASC, nombre_completo ASC
            LIMIT 15
        ");
        $st->execute([$conv_id, $id_periodo, $q, $q, $q]);

    } else {
        // ── Búsqueda normal: todos los socios activos ─────────────
        $st = $pdo->prepare("
            SELECT
                s.id_socio                          AS id,
                s.identificacion                    AS cedula,
                s.nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)          AS ya_registro
            FROM socios s
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = s.id_socio
                  AND a.convocatoria_id = ?
            WHERE s.estado = 'activo'
              AND (
                    s.nombre_completo LIKE ?
                 OR s.identificacion  LIKE ?
              )
            ORDER BY s.nombre_completo
            LIMIT 10
        ");
        $st->execute([$conv_id, $q, $q]);
    }

    $socios = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($socios as &$s) {
        $partes = explode(' ', trim($s['nombre_completo'] ?? ''));
        $s['iniciales'] = strtoupper(
            substr($partes[0] ?? '', 0, 1) .
            (isset($partes[1]) ? substr($partes[1], 0, 1) : '')
        );
        // Limpiar campos extra que no necesita el frontend
        unset($s['sin_socio'], $s['orden_cargo']);
    }
    unset($s);

    echo json_encode($socios);

} catch (Exception $e) {
    echo json_encode([['_error' => $e->getMessage()]]);
}