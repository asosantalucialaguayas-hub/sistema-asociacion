<?php
// ============================================================
// biometrico_endpoint.php
// ============================================================
// Este archivo recibe la huella del biométrico.
// El biométrico manda un POST con la cédula y un token de seguridad.
//
// FLUJO:
//   1. Dispositivo biométrico escanea huella
//   2. Dispositivo POST a: https://tudominio.com/asosantalu/biometrico_endpoint.php
//   3. Este script busca el socio por cédula y registra asistencia
//   4. Responde JSON para que el biométrico muestre mensaje
//
// CONFIGURAR EN TU DISPOSITIVO HIKVISION / ZKTECO:
//   URL: https://tudominio.com/asosantalu/biometrico_endpoint.php
//   Método: POST
//   Parámetros: cedula (o employee_id), token
// ============================================================

// ── Seguridad: solo POST desde IP del biométrico (opcional) ──
define('BIOMETRICO_TOKEN', 'TU_TOKEN_SECRETO_AQUI_CAMBIAR');
// define('BIOMETRICO_IP',    '192.168.1.50'); // IP del dispositivo (descomenta si quieres)

require_once '../config/db.php';
header('Content-Type: application/json');

// Bloquear si no es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido']);
    exit;
}

// Verificar IP del biométrico (opcional)
// if (defined('BIOMETRICO_IP') && $_SERVER['REMOTE_ADDR'] !== BIOMETRICO_IP) {
//     http_response_code(403);
//     echo json_encode(['ok'=>false,'msg'=>'IP no autorizada']);
//     exit;
// }

// Leer datos: soporta JSON y form-data
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST; // fallback form-data

$token  = $data['token']  ?? $data['Token']  ?? '';
$cedula = $data['cedula'] ?? $data['Cedula'] ?? $data['employee_id'] ?? $data['card_number'] ?? '';

// Validar token
if ($token !== BIOMETRICO_TOKEN) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'Token inválido']);
    exit;
}

$cedula = preg_replace('/\D/', '', trim($cedula)); // solo números
if (strlen($cedula) < 6) {
    echo json_encode(['ok'=>false,'msg'=>'Cédula inválida']);
    exit;
}

// Buscar socio por cédula
$stmt = $pdo->prepare("SELECT id, nombres, apellidos FROM socios WHERE cedula = ? AND estado = 'activo'");
$stmt->execute([$cedula]);
$socio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$socio) {
    echo json_encode(['ok'=>false,'msg'=>"Socio con cédula $cedula no encontrado o inactivo"]);
    exit;
}

// Buscar convocatoria activa en este momento
$conv = $pdo->query("
    SELECT id FROM convocatorias
    WHERE estado = 'activa'
    ORDER BY fecha DESC, hora DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo json_encode(['ok'=>false,'msg'=>'No hay ninguna sesión activa en este momento']);
    exit;
}

$conv_id = $conv['id'];

// Registrar asistencia
try {
    $ins = $pdo->prepare("
        INSERT INTO asistencia (convocatoria_id, socio_id, hora_registro, metodo, registrado_por)
        VALUES (?, ?, NOW(), 'biometrico', NULL)
        ON DUPLICATE KEY UPDATE hora_registro = hora_registro
    ");
    $ins->execute([$conv_id, $socio['id']]);

    if ($ins->rowCount() > 0) {
        // Calcular porcentaje actualizado
        $total    = $pdo->query("SELECT COUNT(*) FROM socios WHERE estado='activo'")->fetchColumn();
        $presentes_stmt = $pdo->prepare("SELECT COUNT(*) FROM asistencia WHERE convocatoria_id = ?");
        $presentes_stmt->execute([$conv_id]);
        $presentes = $presentes_stmt->fetchColumn();
        $pct = $total > 0 ? round(($presentes/$total)*100, 1) : 0;

        echo json_encode([
            'ok'         => true,
            'msg'        => 'Bienvenido, '.$socio['nombres'],
            'socio'      => $socio['nombres'].' '.$socio['apellidos'],
            'presentes'  => (int)$presentes,
            'total'      => (int)$total,
            'porcentaje' => $pct
        ]);
    } else {
        echo json_encode([
            'ok'  => false,
            'msg' => $socio['nombres'].' ya fue registrado anteriormente'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]);
}
