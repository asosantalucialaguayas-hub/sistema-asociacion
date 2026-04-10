<?php
// ============================================================
// polling_biometrico.php  — VERSIÓN CORREGIDA
// ob_start captura el redirect del bootstrap antes de que salga
// ============================================================
define('BIO_TOKEN', 'SantaLucia2026_Bio#Token');

ob_start();
require __DIR__ . "/layout/bootstrap.php";
ob_end_clean();

header_remove();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Bio-Token');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function jSalir(array $d): void { echo json_encode($d); exit; }

$raw   = file_get_contents('php://input');
$data  = json_decode($raw, true) ?? [];
$token = $_SERVER['HTTP_X_BIO_TOKEN'] ?? $data['token'] ?? '';

if ($token !== BIO_TOKEN) {
    jSalir(['ok'=>false,'msg'=>'Token inválido']);
}

if (!isset($pdo)) {
    jSalir(['ok'=>false,'msg'=>'Sin conexión BD']);
}

$log_dir  = __DIR__ . '/logs/';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . 'polling_' . date('Y-m-d') . '.log';

$conv_id     = intval($_GET['conv_id'] ?? $data['conv_id'] ?? 0);
$marcaciones = $data['marcaciones'] ?? [];

file_put_contents($log_file,
    date('Y-m-d H:i:s') . " | TOKEN OK | conv=$conv_id | marc=" . count($marcaciones) . "\n",
    FILE_APPEND);

if (!$conv_id) jSalir(['ok'=>false,'msg'=>'Falta conv_id']);

try {
    $stConv = $pdo->prepare("SELECT id,titulo FROM convocatorias WHERE id=?");
    $stConv->execute([$conv_id]);
    $conv = $stConv->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jSalir(['ok'=>false,'msg'=>'Error BD: '.$e->getMessage()]);
}
if (!$conv) jSalir(['ok'=>false,'msg'=>"Convocatoria #$conv_id no encontrada"]);

if (empty($marcaciones)) {
    $stC = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
    $stC->execute([$conv_id]);
    $presentes = (int)$stC->fetchColumn();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
    $pct   = $total>0 ? round(($presentes/$total)*100,1) : 0;
    jSalir(['ok'=>true,'msg'=>'Sin marcaciones','registrados'=>0,'ya_existian'=>0,
            'presentes'=>$presentes,'total'=>$total,'porcentaje'=>$pct]);
}

$registrados = 0; $ya_existian = 0; $errores = [];

foreach ($marcaciones as $m) {
    $emp    = trim($m['employeeNo'] ?? '');
    $nombre = trim($m['nombre']     ?? '');
    if (empty($emp)) continue;

    $socio = null;
    foreach ([ltrim($emp,'0') ?: '0', $emp] as $b) {
        $st = $pdo->prepare("SELECT id_socio,nombre_completo,identificacion FROM socios WHERE identificacion=? AND estado='activo' LIMIT 1");
        $st->execute([$b]);
        $socio = $st->fetch(PDO::FETCH_ASSOC);
        if ($socio) break;
    }

    if (!$socio && strlen($nombre) > 3) {
        $p = explode(' ', strtoupper(trim($nombre)));
        if (count($p) >= 2) {
            $st = $pdo->prepare("SELECT id_socio,nombre_completo,identificacion FROM socios WHERE UPPER(nombre_completo) LIKE ? AND UPPER(nombre_completo) LIKE ? AND estado='activo' LIMIT 1");
            $st->execute(["%{$p[0]}%","%{$p[1]}%"]);
            $socio = $st->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$socio) {
        $errores[] = "No encontrado: emp=$emp nombre='$nombre'";
        file_put_contents($log_file, date('Y-m-d H:i:s')." | NO ENCONTRADO: emp=$emp nombre=$nombre\n", FILE_APPEND);
        continue;
    }

    try {
        $ins = $pdo->prepare("INSERT INTO conv_asistencia (convocatoria_id,id_socio,hora_registro,metodo,registrado_por) VALUES (?,?,NOW(),'biometrico',NULL) ON DUPLICATE KEY UPDATE hora_registro=hora_registro");
        $ins->execute([$conv_id,$socio['id_socio']]);
        if ($ins->rowCount()>0) {
            $registrados++;
            file_put_contents($log_file, date('Y-m-d H:i:s')." | REGISTRADO: {$socio['nombre_completo']} ({$socio['identificacion']})\n", FILE_APPEND);
        } else {
            $ya_existian++;
        }
    } catch (PDOException $e) { $errores[] = $e->getMessage(); }
}

$stC = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
$stC->execute([$conv_id]);
$presentes = (int)$stC->fetchColumn();
$total     = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
$pct       = $total>0 ? round(($presentes/$total)*100,1) : 0;

jSalir(['ok'=>true,'registrados'=>$registrados,'ya_existian'=>$ya_existian,
        'presentes'=>$presentes,'total'=>$total,'porcentaje'=>$pct,
        'errores'=>$errores,'conv'=>$conv['titulo']]);