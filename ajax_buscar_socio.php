<?php
// ============================================================
// ajax_buscar_socio.php  - Buscar socios para registro manual
// ============================================================
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) { echo json_encode([]); exit; }

$q       = '%' . trim($_GET['q'] ?? '') . '%';
$conv_id = intval($_GET['conv_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.id, s.cedula, s.nombres, s.apellidos,
           IF(a.id IS NOT NULL, 1, 0) AS ya_registro
    FROM socios s
    LEFT JOIN asistencia a ON a.socio_id = s.id AND a.convocatoria_id = ?
    WHERE s.estado = 'activo'
      AND (s.nombres LIKE ? OR s.apellidos LIKE ? OR s.cedula LIKE ?)
    LIMIT 10
");
$stmt->execute([$conv_id, $q, $q, $q]);
$socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($socios as &$s) {
    $s['iniciales'] = strtoupper(substr($s['nombres'],0,1) . substr($s['apellidos'],0,1));
}

echo json_encode($socios);
