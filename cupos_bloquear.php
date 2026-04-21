<?php
// ============================================================
// cupos_bloquear.php
// Valida PIN de seguridad y bloquea/desbloquea cupo de un LPA
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// ── Verificar sesión ─────────────────────────────────────────
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// ── PIN de seguridad (guárdalo aquí o en config/conexion.php) 
define('PIN_SEGURIDAD', '40242745');

require "config/conexion.php"; // ajusta la ruta si es necesario

// ── Leer JSON del body ────────────────────────────────────────
$body   = json_decode(file_get_contents('php://input'), true);
$id_lpa = isset($body['id_lpa'])  ? (int)$body['id_lpa']  : 0;
$pin    = isset($body['pin'])     ? trim($body['pin'])     : '';
$accion = isset($body['accion'])  ? trim($body['accion'])  : ''; // 'bloquear' | 'desbloquear'

// ── Validaciones básicas ──────────────────────────────────────
if (!$id_lpa || !$pin || !in_array($accion, ['bloquear', 'desbloquear'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// ── Validar PIN ───────────────────────────────────────────────
if ($pin !== PIN_SEGURIDAD) {
    // Pequeña pausa para evitar fuerza bruta
    sleep(1);
    echo json_encode(['success' => false, 'message' => 'PIN incorrecto']);
    exit;
}

// ── Ejecutar acción en BD ─────────────────────────────────────
$nuevo_estado = ($accion === 'bloquear') ? 1 : 0;

try {
    $stmt = $pdo->prepare("UPDATE tabla_lpa SET cupo_bloqueado = ? WHERE id_lpa = ?");
    $stmt->execute([$nuevo_estado, $id_lpa]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }

    $msg = ($accion === 'bloquear') ? 'Cupo bloqueado correctamente' : 'Cupo desbloqueado correctamente';
    echo json_encode(['success' => true, 'message' => $msg, 'bloqueado' => $nuevo_estado]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}