<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

require "config/conexion.php";
require __DIR__ . "/config/periodo_guard.php";
$periodo = require_periodo_abierto_json($pdo); // 🔒 candado de período

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Método no permitido']));
}

$id_solicitud = $_POST['id_solicitud'] ?? null;
$id_acuerdo = $_POST['id_acuerdo'] ?? null;

if (!$id_solicitud) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'ID de solicitud no proporcionado']));
}

try {
    $pdo->beginTransaction();

    /* =========================
       1. ELIMINAR DOCUMENTOS
       (SOLO DE ESTA SOLICITUD)
    ========================= */
    $stmt = $pdo->prepare("DELETE FROM documentos_socios WHERE id_solicitud = ?");
    $stmt->execute([$id_solicitud]);

    /* =========================
       2. ELIMINAR ACUERDO
       SOLO EL ESPECÍFICO POR ID
       (NO TODOS LOS DE LA CÉDULA)
    ========================= */
    if ($id_acuerdo) {
        $stmt = $pdo->prepare("DELETE FROM acuerdo_productor WHERE id_acuerdo = ?");
        $stmt->execute([$id_acuerdo]);
    }

    /* =========================
       3. ELIMINAR SOLICITUD
       SOLO ESTA
    ========================= */
    $stmt = $pdo->prepare("DELETE FROM solicitud_ingreso WHERE id_solicitud = ?");
    $stmt->execute([$id_solicitud]);

    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Aspirante eliminado correctamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;