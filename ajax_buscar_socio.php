<?php
// ============================================================
// ajax_buscar_socio.php – Búsqueda de socios para asistencia
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode([]); exit; }
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json');

$q       = '%'.trim($_GET['q']??'').'%';
$conv_id = intval($_GET['conv_id']??0);

$st = $pdo->prepare("
    SELECT s.id_socio AS id, s.cedula, s.nombre_completo,
           IF(a.id IS NOT NULL,1,0) AS ya_registro
    FROM socios s
    LEFT JOIN conv_asistencia a ON a.id_socio=s.id_socio AND a.convocatoria_id=?
    WHERE s.estado='activo'
      AND (s.nombre_completo LIKE ? OR s.cedula LIKE ?)
    ORDER BY s.nombre_completo
    LIMIT 10
");
$st->execute([$conv_id,$q,$q]);
$socios = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($socios as &$s) {
    $partes = explode(' ', $s['nombre_completo']);
    $s['iniciales'] = strtoupper(substr($partes[0],0,1).(isset($partes[1])?substr($partes[1],0,1):''));
}
echo json_encode($socios);