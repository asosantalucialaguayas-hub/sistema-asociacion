<?php
// ============================================================
// ajax_registrar_asistencia.php
// Registra asistencia (manual, biométrico, QR)
// ============================================================
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$conv_id  = intval($data['convocatoria_id'] ?? 0);
$socio_id = intval($data['socio_id'] ?? 0);
$metodo   = in_array($data['metodo'] ?? '', ['manual','biometrico','qr']) ? $data['metodo'] : 'manual';

// Validar biométrico: si viene del dispositivo usamos token secreto
if ($metodo === 'biometrico') {
    $token = $data['token'] ?? '';
    $token_esperado = defined('BIOMETRICO_TOKEN') ? BIOMETRICO_TOKEN : getenv('BIOMETRICO_TOKEN');
    if ($token !== $token_esperado && !isset($_SESSION['usuario_id'])) {
        echo json_encode(['ok'=>false,'msg'=>'Token inválido']); exit;
    }
}

if (!$conv_id || !$socio_id) {
    echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
}

// Verificar que la convocatoria esté activa
$stmt = $pdo->prepare("SELECT estado FROM convocatorias WHERE id = ?");
$stmt->execute([$conv_id]);
$conv = $stmt->fetch();

if (!$conv || $conv['estado'] !== 'activa') {
    echo json_encode(['ok'=>false,'msg'=>'La convocatoria no está activa']); exit;
}

// Verificar socio activo
$stmt2 = $pdo->prepare("SELECT id, nombres, apellidos, cedula FROM socios WHERE id = ? AND estado = 'activo'");
$stmt2->execute([$socio_id]);
$socio = $stmt2->fetch();

if (!$socio) {
    echo json_encode(['ok'=>false,'msg'=>'Socio no encontrado o inactivo']); exit;
}

// Insertar asistencia (ON DUPLICATE KEY: no duplicar)
try {
    $ins = $pdo->prepare("
        INSERT INTO asistencia (convocatoria_id, socio_id, hora_registro, metodo, registrado_por)
        VALUES (?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE hora_registro = hora_registro
    ");
    $registrado_por = $metodo === 'biometrico' ? null : $_SESSION['usuario_id'];
    $ins->execute([$conv_id, $socio_id, $metodo, $registrado_por]);

    if ($ins->rowCount() > 0) {
        // Calcular nuevo porcentaje para devolver al cliente
        $total = $pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
        $presentes = $pdo->prepare("SELECT COUNT(*) FROM asistencia WHERE convocatoria_id = ?");
        $presentes->execute([$conv_id]);
        $presentes = $presentes->fetchColumn();
        $pct = $total > 0 ? round(($presentes/$total)*100, 1) : 0;

        echo json_encode([
            'ok'       => true,
            'msg'      => 'Asistencia registrada',
            'socio'    => $socio['nombres'].' '.$socio['apellidos'],
            'cedula'   => $socio['cedula'],
            'presentes'=> $presentes,
            'total'    => $total,
            'porcentaje'=> $pct
        ]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'El socio ya fue registrado anteriormente']);
    }
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Error DB: '.$e->getMessage()]);
}
