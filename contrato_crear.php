<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /auth/login.php");
    exit;
}
require "config/conexion.php";

try {
    $periodo = $pdo->query("SELECT * FROM periodo_comercializacion WHERE estado='ABIERTO' ORDER BY id_periodo DESC LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);

    if (!$periodo) {
        header("Location: periodos.php?msg=" . urlencode("No hay período ABIERTO. Abra uno primero.") . "&type=error");
        exit;
    }

    // Buscar contrato del periodo
    $stmt = $pdo->prepare("SELECT id_contrato FROM contrato_periodo WHERE id_periodo=? LIMIT 1");
    $stmt->execute([$periodo['id_periodo']]);
    $idContrato = (int)$stmt->fetchColumn();

    if ($idContrato <= 0) {
        $titulo = $periodo['nombre']; // ej "Contrato 2026 Comercialización"
        $ins = $pdo->prepare("INSERT INTO contrato_periodo (id_periodo, titulo, estado) VALUES (?, ?, 'BORRADOR')");
        $ins->execute([$periodo['id_periodo'], $titulo]);
        $idContrato = (int)$pdo->lastInsertId();
    }

    header("Location: contrato_detalle.php?id=" . $idContrato);
    exit;

} catch (Exception $e) {
    header("Location: periodos.php?msg=" . urlencode("Error creando contrato: " . $e->getMessage()) . "&type=error");
    exit;
}
