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
    if ($tipo_asistentes === 'solo_directivos' && $id_periodo > 0) {
        // Buscar solo miembros de la junta directiva
        $st = $pdo->prepare("
            SELECT
                dm.socio_id                     AS id,
                dm.cedula_manual                AS cedula,
                dm.nombre_manual                AS nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)     AS ya_registro
            FROM directiva_miembros dm
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = dm.socio_id
                  AND a.convocatoria_id = ?
            WHERE dm.periodo_id = ?
              AND dm.tipo_junta = 'directiva'
              AND (
                    dm.nombre_manual LIKE ?
                 OR dm.cedula_manual  LIKE ?
              )
            ORDER BY dm.orden_cargo ASC, dm.nombre_manual
            LIMIT 10
        ");
        $st->execute([$conv_id, $id_periodo, $q, $q]);
    } else {
        // Búsqueda normal en tabla socios
        $st = $pdo->prepare("
            SELECT
                s.id_socio                          AS id,
                s.identificacion                    AS cedula,
                s.nombre_completo,
                IF(a.id IS NOT NULL, 1, 0)          AS ya_registro
            FROM socios s
            LEFT JOIN conv_asistencia a
                   ON a.id_socio        = s.id_socio
                  AND a.convocatoria_id  = ?
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
            substr($partes[0], 0, 1) .
            (isset($partes[1]) ? substr($partes[1], 0, 1) : '')
        );
    }
    echo json_encode($socios);

} catch (Exception $e) {
    echo json_encode([['_error' => $e->getMessage()]]);
}