<?php
// ============================================================
// ajax_buscar_socio.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode([]); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

$conv_id         = intval($_GET['conv_id'] ?? 0);
$tipo_asistentes = trim($_GET['tipo_asistentes'] ?? 'general');
// IMPORTANTE: id_periodo del GET es el período COMERCIAL, NO el de directiva.
// Por eso lo ignoramos y siempre buscamos el período activo de directiva.
$q = '%' . trim($_GET['q'] ?? '') . '%';

try {
    if ($tipo_asistentes === 'solo_directivos') {

        // Buscar el período ACTIVO de directiva directamente
        $stP  = $pdo->query("SELECT id FROM directiva_periodos WHERE estado='activo' ORDER BY id DESC LIMIT 1");
        $pRow = $stP->fetch(PDO::FETCH_ASSOC);

        if (!$pRow) {
            echo json_encode([['_error' => 'No hay período de directiva activo. Ve a Directiva y verifica.']]);
            exit;
        }
        $pid = (int)$pRow['id'];

        // Miembros únicos de ambas juntas, deduplicados por cédula
        $st = $pdo->prepare("
            SELECT
                COALESCE(s.id_socio, dm.socio_id)              AS id,
                COALESCE(s.identificacion, dm.cedula_manual)    AS cedula,
                COALESCE(s.nombre_completo, dm.nombre_manual)   AS nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)                      AS ya_registro,
                IF(s.id_socio IS NOT NULL, 0, 1)                AS sin_socio,
                MIN(dm.orden_cargo)                              AS orden_cargo
            FROM directiva_miembros dm
            LEFT JOIN socios s
                   ON s.identificacion = dm.cedula_manual
                  AND s.estado = 'activo'
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = COALESCE(s.id_socio, dm.socio_id)
                  AND a.convocatoria_id = ?
            WHERE dm.periodo_id = ?
              AND (
                    UPPER(COALESCE(s.nombre_completo, dm.nombre_manual)) LIKE UPPER(?)
                 OR COALESCE(s.identificacion, dm.cedula_manual)          LIKE ?
              )
            GROUP BY COALESCE(s.identificacion, dm.cedula_manual)
            ORDER BY sin_socio ASC, orden_cargo ASC, nombre_completo ASC
            LIMIT 15
        ");
        $st->execute([$conv_id, $pid, $q, $q]);

    } else {
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
              AND (s.nombre_completo LIKE ? OR s.identificacion LIKE ?)
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