<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión no válida'
    ]);
    exit;
}

require "config/conexion.php";

$id_persona = $_POST['id'] ?? $_POST['id_persona'] ?? null;

if (!$id_persona) {
    echo json_encode([
        'success' => false,
        'message' => 'ID no enviado'
    ]);
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();

    /* =========================
       OBTENER PREDIO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT id_predio 
        FROM rna_predio 
        WHERE id_persona = ?
        LIMIT 1
    ");
    $stmt->execute([$id_persona]);
    $predio = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($predio) {
        $id_predio = $predio['id_predio'];

        /* =========================
           GEOREFERENCIACIÓN
        ========================= */
        $stmt = $pdo->prepare("
            DELETE FROM rna_georreferenciacion 
            WHERE id_predio = ?
        ");
        $stmt->execute([$id_predio]);

        /* =========================
           ACTIVIDAD
        ========================= */
        $stmt = $pdo->prepare("
            DELETE FROM rna_actividad 
            WHERE id_predio = ?
        ");
        $stmt->execute([$id_predio]);

        /* =========================
           PREDIO
        ========================= */
        $stmt = $pdo->prepare("
            DELETE FROM rna_predio 
            WHERE id_predio = ?
        ");
        $stmt->execute([$id_predio]);
    }

    /* =========================
       DOMICILIO
    ========================= */
    $stmt = $pdo->prepare("
        DELETE FROM rna_domicilio 
        WHERE id_persona = ?
    ");
    $stmt->execute([$id_persona]);

    /* =========================
       USUARIO RNA
    ========================= */
    $stmt = $pdo->prepare("
        DELETE FROM rna_usuario 
        WHERE id_persona = ?
    ");
    $stmt->execute([$id_persona]);

    /* =========================
       PERSONA (FINAL)
    ========================= */
    $stmt = $pdo->prepare("
        DELETE FROM rna_persona 
        WHERE id_persona = ?
    ");
    $stmt->execute([$id_persona]);

    // Confirmar transacción
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Registro eliminado correctamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar',
        'error'   => $e->getMessage()
    ]);
}
