<?php
// eliminar_abono.php
header('Content-Type: application/json');

// ── Misma conexión que el resto del proyecto ──────────────────────────────────
require_once __DIR__ . '/config/conexion.php';   // expone $pdo

// ── Solo POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// ── Validar id_abono ──────────────────────────────────────────────────────────
$id_abono = isset($_POST['id_abono']) ? (int)$_POST['id_abono'] : 0;
if ($id_abono <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de abono inválido']);
    exit;
}

try {
    // ── Verificar que existe y está REGISTRADO ────────────────────────────────
    // ✅ columna correcta: id_abono (no "id")
    $stmt = $pdo->prepare('SELECT estado FROM pago_inscripcion_abono WHERE id_abono = ?');
    $stmt->execute([$id_abono]);
    $abono = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abono) {
        echo json_encode(['success' => false, 'message' => 'Abono no encontrado']);
        exit;
    }

    if ($abono['estado'] !== 'REGISTRADO') {
        echo json_encode(['success' => false, 'message' => 'Solo se pueden eliminar abonos REGISTRADOS']);
        exit;
    }

    // ── Marcar como ANULADO (no borrar físicamente) ───────────────────────────
    // ✅ columna correcta: id_abono (no "id")
    $stmt = $pdo->prepare('UPDATE pago_inscripcion_abono SET estado = ? WHERE id_abono = ?');
    $stmt->execute(['ANULADO', $id_abono]);

    echo json_encode(['success' => true, 'message' => 'Abono anulado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}