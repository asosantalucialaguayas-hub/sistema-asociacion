<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
require __DIR__ . "/config/conexion.php";

try {
    $mujeres = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE UPPER(TRIM(sexo))='F'")->fetchColumn();
    $hombres = (int)$pdo->query("SELECT COUNT(*) FROM socios WHERE UPPER(TRIM(sexo))='M'")->fetchColumn();
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM socios")->fetchColumn();
    $otros   = $total - $mujeres - $hombres;

    echo json_encode([
        'success' => true,
        'mujeres' => $mujeres,
        'hombres' => $hombres,
        'otros'   => max(0, $otros),
        'total'   => $total,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
