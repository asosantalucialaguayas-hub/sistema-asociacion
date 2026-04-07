<?php
// ============================================================
// ajax_registrar_asistencia.php – Adaptado BD real
// ============================================================
if (session_status()===PHP_SESSION_NONE) session_start();
require __DIR__ . "/layout/bootstrap.php";
header('Content-Type: application/json; charset=utf-8');

define('BIO_TOKEN', getenv('BIO_TOKEN') ?: 'CAMBIAR_TOKEN_SECRETO_AQUI');

$data     = json_decode(file_get_contents('php://input'), true) ?: [];
$conv_id  = intval($data['convocatoria_id'] ?? 0);
$socio_id = intval($data['socio_id'] ?? 0);
$metodo   = in_array($data['metodo']??'',['manual','biometrico','qr']) ? $data['metodo'] : 'manual';
$token    = $data['token'] ?? '';

$autenticado = isset($_SESSION['usuario']) || ($metodo==='biometrico' && $token===BIO_TOKEN);
if (!$autenticado) { echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
if (!$conv_id || !$socio_id) { echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit; }

// Verificar que la convocatoria esté activa
$stC = $pdo->prepare("SELECT estado FROM convocatorias WHERE id=?");
$stC->execute([$conv_id]); $conv=$stC->fetch();
if (!$conv||$conv['estado']!=='activa') {
    echo json_encode(['ok'=>false,'msg'=>'La convocatoria no está activa']); exit;
}

// Verificar socio usando id_socio
$stS = $pdo->prepare("SELECT id_socio, nombre_completo, identificacion FROM socios WHERE id_socio=? AND estado='activo'");
$stS->execute([$socio_id]); $socio=$stS->fetch();
if (!$socio) { echo json_encode(['ok'=>false,'msg'=>'Socio no encontrado o inactivo']); exit; }

try {
    $ins = $pdo->prepare("
        INSERT INTO conv_asistencia (convocatoria_id, id_socio, hora_registro, metodo, registrado_por)
        VALUES (?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE hora_registro=hora_registro
    ");
    $reg_por = $metodo==='biometrico' ? null : intval($_SESSION['id_usuario']??0);
    $ins->execute([$conv_id, $socio_id, $metodo, $reg_por]);

    if ($ins->rowCount()>0) {
        $total    = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
        $stPr     = $pdo->prepare("SELECT COUNT(*) FROM conv_asistencia WHERE convocatoria_id=?");
        $stPr->execute([$conv_id]);
        $presentes = (int)$stPr->fetchColumn();
        $pct = $total>0 ? round(($presentes/$total)*100,1):0;
        echo json_encode(['ok'=>true,'msg'=>'Registrado','socio'=>$socio['nombre_completo'],'presentes'=>$presentes,'total'=>$total,'porcentaje'=>$pct]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>$socio['nombre_completo'].' ya fue registrado anteriormente']);
    }
} catch(PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Error DB: '.$e->getMessage()]);
}